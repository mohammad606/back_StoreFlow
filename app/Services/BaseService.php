<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

abstract class BaseService
{
    protected Model $model;
    protected array $relationships = [];

    public function __construct()
    {
        $this->setModel();
    }

    abstract public function setModel(): void;

    protected function currentUserId(): int
    {
        $userId = Auth::guard('api')->id();

        if ($userId === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return (int) $userId;
    }

    protected function normalizeListFilters(
        array $filters,
        array $allowedSorts = [],
        string $defaultSort = 'created_at',
        string $defaultOrder = 'desc',
        int $defaultPerPage = 20,
    ): array {
        $sortBy = $filters['sort_by'] ?? $filters['sortBy'] ?? $defaultSort;
        $sortOrder = $filters['sort_order'] ?? $filters['sortOrder'] ?? $defaultOrder;
        $perPage = (int) ($filters['per_page'] ?? $filters['perPage'] ?? $defaultPerPage);

        if ($allowedSorts !== [] && ! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = $defaultSort;
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = $defaultOrder;
        }

        return [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'per_page' => min(100, max(1, $perPage)),
        ];
    }

    protected function ensureUniqueProductIds(array $productIds, string $context = 'items'): void
    {
        $uniqueProductIds = array_unique($productIds);

        if (count($productIds) !== count($uniqueProductIds)) {
            throw new \Exception("Cannot add duplicate products in the {$context}");
        }
    }

    protected function ownedProduct(int $id): Store
    {
        return Store::where('user_id', $this->currentUserId())
            ->findOrFail($id);
    }

    protected function ownedProducts(array $ids): EloquentCollection
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $ids)));
        $products = Store::where('user_id', $this->currentUserId())
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($uniqueIds)) {
            throw (new ModelNotFoundException())->setModel(Store::class);
        }

        return $products;
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with($this->relationships);

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', $this->currentUserId());
        }

        if (isset($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        $normalized = $this->normalizeListFilters(
            $filters,
            ['id', 'name', 'date', 'created_at', 'quantity', 'noa', 'type', 'sender', 'customer_name', 'phone'],
            'created_at',
            'desc'
        );

        $query->orderBy($normalized['sort_by'], $normalized['sort_order']);

        return $query->paginate($normalized['per_page']);
    }

    public function getById(int $id): Model
    {
        $query = $this->model->newQuery()->with($this->relationships);

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', $this->currentUserId());
        }

        return $query->findOrFail($id);
    }

    public function create(array $data): Model
    {
        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $data['user_id'] = $this->currentUserId();
        }

        return $this->model->create($data);
    }

    public function update(int $id, array $data): Model
    {
        $record = $this->getById($id);
        $record->update($data);
        return $record;
    }

    public function delete(int $id): void
    {
        $record = $this->getById($id);
        $record->delete();
    }

    protected function applySearch($query, $search)
    {
        if (\Schema::hasColumn($this->model->getTable(), 'name')) {
            $query->where('name', 'like', "%{$search}%");
        }
    }
}
