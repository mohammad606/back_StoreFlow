<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Customer();
    }

    protected function applySearch($query, $search)
    {
        $query->where('name', 'like', "%{$search}%");
    }

    public function getAll(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', $this->currentUserId());
        }

        if (isset($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        $normalized = $this->normalizeListFilters($filters, ['id', 'name', 'phone'], 'name', 'asc');

        $query->orderBy($normalized['sort_by'], $normalized['sort_order']);

        return $query->paginate($normalized['per_page']);
    }
}
