<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\AllInput;
use App\Models\AllInputItem;
use App\Models\Store;
use App\Models\Customer;
use Illuminate\Support\Collection;

class AllInputService
{
    public function getAllInvoices(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AllInput::with('items')
            ->where('user_id', auth()->id());

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

    public function createInvoice(array $data): AllInput
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);

            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $uniqueProductIds = array_unique($productIds);
            if (count($productIds) !== count($uniqueProductIds)) {
                throw new \Exception('Cannot add duplicate products in the invoice');
            }

            $invoice = AllInput::create([
                'date' => $data['date'],
                'type' => $data['type'],
                'noa' => $data['noa'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => auth()->id(),
            ]);

            $products = Store::whereIn('id', $productIds)->get()->keyBy('id');

            $itemsData = collect($data['items'])->map(function ($item) use ($invoice, $products) {
                $product = $products[$item['product_id']];
                $product->quantity += $item['quantity'];
                $product->save();

                return [
                    'all_input_id' => $invoice->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                ];
            })->toArray();

            $invoice->items()->createMany($itemsData);

            return $invoice->load('items');
        });
    }

    public function updateInvoice(int $id, array $data): AllInput
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = AllInput::with('items')->where('user_id', auth()->id())->findOrFail($id);

            if (isset($data['date'])) $invoice->date = $data['date'];
            if (isset($data['type'])) $invoice->type = $data['type'];
            if (isset($data['noa'])) $invoice->noa = $data['noa'];
            if (isset($data['customer_id'])) {
                $customer = Customer::findOrFail($data['customer_id']);
                $invoice->customer_id = $customer->id;
                $invoice->customer_name = $customer->name;
            }
            $invoice->save();

            if (isset($data['deleted_items'])) {
                $this->deleteItems($invoice, $data['deleted_items']);
            }

            if (isset($data['new_items'])) {
                $this->addNewItems($invoice, $data['new_items']);
            }

            if (isset($data['updated_items'])) {
                $this->updateItems($invoice, $data['updated_items']);
            }

            return $invoice->load('items');
        });
    }

    public function deleteInvoice(int $id): void
    {
        DB::transaction(function () use ($id) {
            $invoice = AllInput::with('items')->where('user_id', auth()->id())->findOrFail($id);
            
            $items = $invoice->items;
            
            foreach ($items as $item) {
                $product = Store::find($item->product_id);
                if ($product) {
                    $product->quantity -= $item->quantity;
                    $product->save();
                }
            }
            
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    private function deleteItems(AllInput $invoice, array $itemIds): void
    {
        $deletedItems = AllInputItem::whereIn('id', $itemIds)
            ->where('all_input_id', $invoice->id)
            ->get();

        foreach ($deletedItems as $item) {
            $product = Store::find($item->product_id);
            if ($product) {
                $product->quantity -= $item->quantity;
                $product->save();
            }
            $item->delete();
        }
        $invoice->load('items');
    }

    private function addNewItems(AllInput $invoice, array $newItems): void
    {
        $existingProductIds = $invoice->items->pluck('product_id')->toArray();
        
        $newProductIds = collect($newItems)->pluck('product_id')->toArray();
        $duplicatesInNew = array_diff_assoc($newProductIds, array_unique($newProductIds));
        if (!empty($duplicatesInNew)) {
            throw new \Exception('Cannot add duplicate products in new_items');
        }
        
        $duplicatesWithExisting = array_intersect($newProductIds, $existingProductIds);
        if (!empty($duplicatesWithExisting)) {
            throw new \Exception('Some products already exist in the invoice');
        }
        
        $productIds = collect($newItems)->pluck('product_id');
        $products = Store::whereIn('id', $productIds)->get()->keyBy('id');

        $itemsData = collect($newItems)->map(function ($item) use ($invoice, $products) {
            $product = $products[$item['product_id']];
            $product->quantity += $item['quantity'];
            $product->save();

            return [
                'all_input_id' => $invoice->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $item['quantity'],
            ];
        })->toArray();

        $invoice->items()->createMany($itemsData);
        $invoice->load('items');
    }

    private function updateItems(AllInput $invoice, array $updatedItems): void
    {
        $updatedItemIds = collect($updatedItems)->pluck('id')->toArray();
        $existingProductIds = $invoice->items()
            ->whereNotIn('id', $updatedItemIds)
            ->pluck('product_id')
            ->toArray();
        
        foreach ($updatedItems as $updatedItem) {
            $item = AllInputItem::where('id', $updatedItem['id'])
                ->where('all_input_id', $invoice->id)
                ->firstOrFail();

            if (isset($updatedItem['product_id'])) {
                if (in_array($updatedItem['product_id'], $existingProductIds)) {
                    throw new \Exception('Product already exists in the invoice');
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
                    $oldProduct->quantity -= $item->quantity;
                    $oldProduct->save();
                }

                $newProduct->quantity += $newQuantity;
                $newProduct->save();

                $item->product_id = $newProduct->id;
                $item->name = $newProduct->name;
                $item->quantity = $newQuantity;
                $item->save();
                
                $existingProductIds[] = $newProduct->id;
            } 
            else if (isset($updatedItem['quantity'])) {
                $oldQuantity = $item->quantity;
                $newQuantity = $updatedItem['quantity'];
                $difference = $newQuantity - $oldQuantity;

                $item->quantity = $newQuantity;
                $item->save();

                $product = Store::find($item->product_id);
                if ($product) {
                    $product->quantity += $difference;
                    $product->save();
                }
            }
        }
    }
}

