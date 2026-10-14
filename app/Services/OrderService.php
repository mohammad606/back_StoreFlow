<?php

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

class OrderService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Order();
        $this->relationships = ['items'];
    }

    protected function applySearch($query, $search)
    {
        $query->where(function ($q) use ($search) {
            $q->where('noa', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('date', 'like', "%{$search}%");
        });
    }

    public function getTopSellingProducts(): Collection
    {
        $userId = $this->currentUserId();

        return Store::query()
            ->join('order_items', 'order_items.product_id', '=', 'store.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $userId)
            ->where('store.user_id', $userId)
            ->select([
                'store.id',
                'store.name',
                'store.quantity',
                'store.box',
                DB::raw('SUM(order_items.quantity) as sold_quantity'),
            ])
            ->groupBy('store.id', 'store.name', 'store.quantity', 'store.box')
            ->orderByDesc('sold_quantity')
            ->orderBy('store.id')
            ->limit(6)
            ->get();
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::where('user_id', $this->currentUserId())
                ->findOrFail($data['customer_id']);

            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $this->ensureUniqueProductIds($productIds, 'order');

            $order = Order::create([
                'date' => $data['date'],
                'sender' => $data['sender'],
                'noa' => $data['noa'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $this->currentUserId(),
            ]);

            $products = $this->ownedProducts($productIds);

            $itemsData = collect($data['items'])->map(function ($item) use ($order, $products) {
                $product = $products[$item['product_id']];

                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $product->quantity -= $item['quantity'];
                $product->save();


                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $product->quantity,
                    'change_amount' => -$item['quantity'],
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Sold in order #' . $order->id,
                ]);

                return [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                ];
            })->toArray();

            $order->items()->createMany($itemsData);

            return $order->load('items');
        });
    }

    public function update(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = $this->getById($id);

            if (isset($data['date']))
                $order->date = $data['date'];
            if (isset($data['sender']))
                $order->sender = $data['sender'];
            if (isset($data['noa']))
                $order->noa = $data['noa'];
            if (isset($data['customer_id'])) {
                $customer = Customer::where('user_id', $this->currentUserId())
                    ->findOrFail($data['customer_id']);
                $order->customer_id = $customer->id;
                $order->customer_name = $customer->name;
            }
            $order->save();

            if (isset($data['deleted_items'])) {
                $this->deleteItems($order, $data['deleted_items']);
            }

            if (isset($data['new_items'])) {
                $this->addNewItems($order, $data['new_items']);
            }

            if (isset($data['updated_items'])) {
                $this->updateItems($order, $data['updated_items']);
            }

            return $order->load('items');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $order = $this->getById($id);

            $items = $order->items;

            foreach ($items as $item) {
                $product = $this->ownedProduct($item->product_id);
                $product->quantity += $item->quantity;
                $product->save();


                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $product->quantity,
                    'change_amount' => $item->quantity,
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Order #' . $order->id . ' deleted',
                ]);
            }

            $order->items()->delete();
            $order->delete();
        });
    }

    private function deleteItems(Order $order, array $itemIds): void
    {
        $deletedItems = OrderItem::whereIn('id', $itemIds)
            ->where('order_id', $order->id)
            ->get();

        foreach ($deletedItems as $item) {
            $product = $this->ownedProduct($item->product_id);
            $product->quantity += $item->quantity;
            $product->save();


            StoreQuantityHistory::create([
                'store_id' => $product->id,
                'quantity' => $product->quantity,
                'change_amount' => $item->quantity,
                'change_type' => StockMovementTypeEnum::Order,
                'reference_type' => 'Order',
                'reference_id' => $order->id,
                'user_id' => $this->currentUserId(),
                'notes' => 'Item removed from order #' . $order->id,
            ]);
            $item->delete();
        }
        $order->load('items');
    }

    private function addNewItems(Order $order, array $newItems): void
    {
        $existingProductIds = $order->items->pluck('product_id')->toArray();

        $newProductIds = collect($newItems)->pluck('product_id')->toArray();
        $this->ensureUniqueProductIds($newProductIds, 'new_items');

        $duplicatesWithExisting = array_intersect($newProductIds, $existingProductIds);
        if (!empty($duplicatesWithExisting)) {
            throw new \Exception('Some products already exist in the order');
        }

        $productIds = collect($newItems)->pluck('product_id');
        $products = $this->ownedProducts($productIds->all());

        $itemsData = collect($newItems)->map(function ($item) use ($order, $products) {
            $product = $products[$item['product_id']];

            if ($product->quantity < $item['quantity']) {
                throw new \Exception("Insufficient stock for product: {$product->name}");
            }

            $product->quantity -= $item['quantity'];
            $product->save();


            StoreQuantityHistory::create([
                'store_id' => $product->id,
                'quantity' => $product->quantity,
                'change_amount' => -$item['quantity'],
                'change_type' => StockMovementTypeEnum::Order,
                'reference_type' => 'Order',
                'reference_id' => $order->id,
                'user_id' => $this->currentUserId(),
                'notes' => 'New item added to order #' . $order->id,
            ]);

            return [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $item['quantity'],
            ];
        })->toArray();

        $order->items()->createMany($itemsData);
        $order->load('items');
    }

    private function updateItems(Order $order, array $updatedItems): void
    {
        $updatedItemIds = collect($updatedItems)->pluck('id')->toArray();
        $existingProductIds = $order->items()
            ->whereNotIn('id', $updatedItemIds)
            ->pluck('product_id')
            ->toArray();

        foreach ($updatedItems as $updatedItem) {
            $item = OrderItem::where('id', $updatedItem['id'])
                ->where('order_id', $order->id)
                ->firstOrFail();

            if (isset($updatedItem['product_id'])) {
                if (in_array($updatedItem['product_id'], $existingProductIds)) {
                    throw new \Exception('Product already exists in the order');
                }

                $otherUpdatedItems = collect($updatedItems)
                    ->where('id', '!=', $updatedItem['id'])
                    ->pluck('product_id')
                    ->toArray();
                if (in_array($updatedItem['product_id'], $otherUpdatedItems)) {
                    throw new \Exception('Cannot update multiple items to the same product');
                }

                $oldProduct = $this->ownedProduct($item->product_id);
                $newProduct = $this->ownedProduct($updatedItem['product_id']);
                $newQuantity = $updatedItem['quantity'] ?? $item->quantity;

                $oldProduct->quantity += $item->quantity;
                $oldProduct->save();


                StoreQuantityHistory::create([
                    'store_id' => $oldProduct->id,
                    'quantity' => $oldProduct->quantity,
                    'change_amount' => $item->quantity,
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Product changed in order #' . $order->id,
                ]);

                if ($newProduct->quantity < $newQuantity) {
                    $oldProduct->quantity -= $item->quantity;
                    $oldProduct->save();
                    throw new \Exception("Insufficient stock for product: {$newProduct->name}");
                }

                $newProduct->quantity -= $newQuantity;
                $newProduct->save();


                StoreQuantityHistory::create([
                    'store_id' => $newProduct->id,
                    'quantity' => $newProduct->quantity,
                    'change_amount' => -$newQuantity,
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Product changed in order #' . $order->id,
                ]);

                $item->product_id = $newProduct->id;
                $item->name = $newProduct->name;
                $item->quantity = $newQuantity;
                $item->save();

                $existingProductIds[] = $newProduct->id;
            } else if (isset($updatedItem['quantity'])) {
                $oldQuantity = $item->quantity;
                $newQuantity = $updatedItem['quantity'];
                $difference = $newQuantity - $oldQuantity;

                $product = $this->ownedProduct($item->product_id);
                if ($difference > 0 && $product->quantity < $difference) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $product->quantity -= $difference;
                $product->save();


                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $product->quantity,
                    'change_amount' => -$difference,
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Quantity updated in order #' . $order->id,
                ]);

                $item->quantity = $newQuantity;
                $item->save();
            }
        }
    }

}
