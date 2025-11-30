<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\Customer;

class OrderService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Order();
    }

    public function getAll(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->model->with('items');

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('noa', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sortBy'] ?? 'date';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $allowedSorts = ['date', 'noa', 'customer_name', 'id'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'date';
        }
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['perPage'] ?? 20;
        return $query->paginate($perPage);
    }

    public function getById(int $id): Order
    {
        $query = $this->model->with('items');

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        return $query->findOrFail($id);
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);

            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $uniqueProductIds = array_unique($productIds);
            if (count($productIds) !== count($uniqueProductIds)) {
                throw new \Exception('Cannot add duplicate products in the order');
            }

            $order = Order::create([
                'date' => $data['date'],
                'sender' => $data['sender'],
                'noa' => $data['noa'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => auth()->id(),
            ]);

            $products = Store::whereIn('id', $productIds)->get()->keyBy('id');

            $itemsData = collect($data['items'])->map(function ($item) use ($order, $products) {
                $product = $products[$item['product_id']];

                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $product->quantity -= $item['quantity'];
                $product->save();

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
                $customer = Customer::findOrFail($data['customer_id']);
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
                $product = Store::find($item->product_id);
                if ($product) {
                    $product->quantity += $item->quantity;
                    $product->save();
                }
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
            $product = Store::find($item->product_id);
            if ($product) {
                $product->quantity += $item->quantity;
                $product->save();
            }
            $item->delete();
        }
        $order->load('items');
    }

    private function addNewItems(Order $order, array $newItems): void
    {
        $existingProductIds = $order->items->pluck('product_id')->toArray();

        $newProductIds = collect($newItems)->pluck('product_id')->toArray();
        $duplicatesInNew = array_diff_assoc($newProductIds, array_unique($newProductIds));
        if (!empty($duplicatesInNew)) {
            throw new \Exception('Cannot add duplicate products in new_items');
        }

        $duplicatesWithExisting = array_intersect($newProductIds, $existingProductIds);
        if (!empty($duplicatesWithExisting)) {
            throw new \Exception('Some products already exist in the order');
        }

        $productIds = collect($newItems)->pluck('product_id');
        $products = Store::whereIn('id', $productIds)->get()->keyBy('id');

        $itemsData = collect($newItems)->map(function ($item) use ($order, $products) {
            $product = $products[$item['product_id']];

            if ($product->quantity < $item['quantity']) {
                throw new \Exception("Insufficient stock for product: {$product->name}");
            }

            $product->quantity -= $item['quantity'];
            $product->save();

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

                $oldProduct = Store::find($item->product_id);
                $newProduct = Store::findOrFail($updatedItem['product_id']);
                $newQuantity = $updatedItem['quantity'] ?? $item->quantity;

                if ($oldProduct) {
                    $oldProduct->quantity += $item->quantity;
                    $oldProduct->save();
                }

                if ($newProduct->quantity < $newQuantity) {
                    if ($oldProduct) {
                        $oldProduct->quantity -= $item->quantity;
                        $oldProduct->save();
                    }
                    throw new \Exception("Insufficient stock for product: {$newProduct->name}");
                }

                $newProduct->quantity -= $newQuantity;
                $newProduct->save();

                $item->product_id = $newProduct->id;
                $item->name = $newProduct->name;
                $item->quantity = $newQuantity;
                $item->save();

                $existingProductIds[] = $newProduct->id;
            } else if (isset($updatedItem['quantity'])) {
                $oldQuantity = $item->quantity;
                $newQuantity = $updatedItem['quantity'];
                $difference = $newQuantity - $oldQuantity;

                $product = Store::find($item->product_id);
                if ($product) {
                    if ($difference > 0 && $product->quantity < $difference) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    $product->quantity -= $difference;
                    $product->save();
                }

                $item->quantity = $newQuantity;
                $item->save();
            }
        }
    }
}
