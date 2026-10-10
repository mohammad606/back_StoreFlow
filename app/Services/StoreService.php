<?php

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Models\Store;
use App\Models\StoreQuantityHistory;

class StoreService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Store();
        $this->relationships = [];
    }

    protected function applySearch($query, $search)
    {
        $query->where('name', 'like', "%{$search}%");
    }

    public function create(array $data): Store
    {
        return DB::transaction(function () use ($data) {
            $data['user_id'] = $this->currentUserId();

            $product = Store::create($data);

            StoreQuantityHistory::create([
                'store_id' => $product->id,
                'quantity' => $data['quantity'],
                'change_amount' => $data['quantity'],
                'change_type' => StockMovementTypeEnum::Initial,
                'user_id' => $this->currentUserId(),
                'notes' => 'Initial quantity',
            ]);

            return $product;
        });
    }

    public function update(int $id, array $data): Store
    {
        return DB::transaction(function () use ($id, $data) {
            $product = $this->getById($id);
            $oldQuantity = $product->quantity;

            $product->update($data);

            if (isset($data['quantity']) && $data['quantity'] != $oldQuantity) {
                $changeAmount = $data['quantity'] - $oldQuantity;
                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $data['quantity'],
                    'change_amount' => $changeAmount,
                    'change_type' => StockMovementTypeEnum::ManualUpdate,
                    'user_id' => $this->currentUserId(),
                    'notes' => 'Manual quantity update',
                ]);
            }

            return $product->fresh();
        });
    }

    public function getQuantityHistory(int $id, ?string $startDate = null, ?string $endDate = null): array
    {
        $product = $this->getById($id);

        $endDate = $endDate ?? now()->format('Y-m-d');
        $startDate = $startDate ?? now()->subDays(10)->format('Y-m-d');

        $history = StoreQuantityHistory::where('store_id', $id)
            ->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->orderBy('created_at', 'desc')
            ->with('user:id,name')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->created_at->format('Y-m-d'),
                    'time' => $record->created_at->format('H:i:s'),
                    'quantity' => $record->quantity,
                    'change_amount' => $record->change_amount,
                    'change_type' => $record->change_type,
                    'reference_type' => $record->reference_type,
                    'reference_id' => $record->reference_id,
                    'user' => $record->user ? $record->user->name : null,
                    'notes' => $record->notes,
                ];
            });

        return [
            'item' => [
                'id' => $product->id,
                'name' => $product->name,
                'current_quantity' => $product->quantity,
                'box' => $product->box,
            ],
            'history' => $history,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }
}
