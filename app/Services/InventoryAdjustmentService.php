<?php

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use App\Models\InventoryAdjustment;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new InventoryAdjustment;
        $this->relationships = ['items.product'];
    }

    public function create(array $data): InventoryAdjustment
    {
        return DB::transaction(function () use ($data): InventoryAdjustment {
            $userId = $this->currentUserId();
            $items = collect($data['items']);
            $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $changes = $items->pluck('quantity_change')->map(fn ($change) => (int) $change);

            $this->assertBalancedChanges($productIds, $changes->all());

            $products = Store::where('user_id', $userId)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw (new ModelNotFoundException)->setModel(Store::class);
            }

            $adjustment = InventoryAdjustment::create([
                'user_id' => $userId,
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = $products[(int) $item['product_id']];
                $change = (int) $item['quantity_change'];
                $product->quantity += $change;
                $product->save();

                $adjustment->items()->create([
                    'store_id' => $product->id,
                    'quantity_change' => $change,
                ]);

                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $product->quantity,
                    'change_amount' => $change,
                    'change_type' => StockMovementTypeEnum::InventoryAdjustment,
                    'reference_type' => 'InventoryAdjustment',
                    'reference_id' => $adjustment->id,
                    'user_id' => $userId,
                    'notes' => $data['note'] ?? 'Inventory adjustment',
                ]);
            }

            return $adjustment->load('items.product');
        });
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = InventoryAdjustment::query()
            ->with('items.product')
            ->where('user_id', $this->currentUserId());

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->where('note', 'like', "%{$search}%")
                    ->orWhereHas('items.product', function (Builder $productQuery) use ($search): void {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1
                    && date('Y-m-d', strtotime($search)) === $search) {
                    $query->orWhere('date', $search);
                }
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        $sortBy = $filters['sort_by'] ?? 'date';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getById(int $id): InventoryAdjustment
    {
        return InventoryAdjustment::query()
            ->with('items.product')
            ->where('user_id', $this->currentUserId())
            ->findOrFail($id);
    }

    public function update(int $id, array $data): InventoryAdjustment
    {
        return DB::transaction(function () use ($id, $data): InventoryAdjustment {
            $adjustment = InventoryAdjustment::where('user_id', $this->currentUserId())
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($id);

            if (array_key_exists('date', $data)) {
                $adjustment->date = $data['date'];
            }

            if (array_key_exists('note', $data)) {
                $adjustment->note = $data['note'];
            }

            if (array_key_exists('items', $data)) {
                $this->replaceItems($adjustment, $data['items']);
            }

            $adjustment->save();

            return $adjustment->load('items.product');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $adjustment = InventoryAdjustment::where('user_id', $this->currentUserId())
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($id);

            $productIds = $adjustment->items->pluck('store_id')->all();
            $products = $this->lockOwnedProducts($productIds);
            $reversals = $adjustment->items->mapWithKeys(
                fn ($item) => [$item->store_id => -$item->quantity_change]
            );

            foreach ($reversals as $productId => $change) {
                $product = $products[$productId];
                $product->quantity += $change;
                $product->save();

                $this->recordHistory(
                    $adjustment,
                    $product,
                    $change,
                    'Inventory adjustment deleted'
                );
            }

            $adjustment->delete();
        });
    }

    private function replaceItems(InventoryAdjustment $adjustment, array $items): void
    {
        $userId = $this->currentUserId();
        $newItems = collect($items);
        $newIds = $newItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $newChanges = $newItems->pluck('quantity_change')->map(fn ($change) => (int) $change);
        $this->assertBalancedChanges($newIds, $newChanges->all());

        $oldChanges = $adjustment->items->mapWithKeys(
            fn ($item) => [$item->store_id => $item->quantity_change]
        );
        $productIds = array_values(array_unique(array_merge(
            $oldChanges->keys()->all(),
            $newIds
        )));
        $products = $this->lockOwnedProducts($productIds);

        $desiredChanges = $newItems->mapWithKeys(
            fn ($item) => [(int) $item['product_id'] => (int) $item['quantity_change']]
        );
        $quantityDeltas = collect($productIds)->mapWithKeys(
            fn ($productId) => [
                $productId => ($desiredChanges[$productId] ?? 0) - ($oldChanges[$productId] ?? 0),
            ]
        );

        foreach ($quantityDeltas as $productId => $change) {
            if ($change === 0) {
                continue;
            }

            $product = $products[$productId];
            $product->quantity += $change;
            $product->save();

            $this->recordHistory($adjustment, $product, $change, $adjustment->note);
        }

        $adjustment->items()->delete();
        $adjustment->items()->createMany(
            $newItems->map(fn ($item) => [
                'store_id' => (int) $item['product_id'],
                'quantity_change' => (int) $item['quantity_change'],
            ])->all()
        );
    }

    private function assertBalancedChanges(array $productIds, array $changes): void
    {
        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'items' => 'A product may only appear once in an adjustment.',
            ]);
        }

        if (array_sum($changes) !== 0
            || ! collect($changes)->contains(fn ($change) => $change < 0)
            || ! collect($changes)->contains(fn ($change) => $change > 0)) {
            throw ValidationException::withMessages([
                'items' => 'The adjustment must include increases and decreases whose total equals zero.',
            ]);
        }
    }

    private function lockOwnedProducts(array $productIds)
    {
        $products = Store::where('user_id', $this->currentUserId())
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            throw (new ModelNotFoundException)->setModel(Store::class);
        }

        return $products;
    }

    private function recordHistory(
        InventoryAdjustment $adjustment,
        Store $product,
        int $change,
        ?string $note
    ): void {
        StoreQuantityHistory::create([
            'store_id' => $product->id,
            'quantity' => $product->quantity,
            'change_amount' => $change,
            'change_type' => StockMovementTypeEnum::InventoryAdjustment,
            'reference_type' => 'InventoryAdjustment',
            'reference_id' => $adjustment->id,
            'user_id' => $this->currentUserId(),
            'notes' => $note ?? 'Inventory adjustment',
        ]);
    }
}
