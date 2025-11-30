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
            $query->where('user_id', auth()->id());
        }

        if (isset($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        $sortBy = $filters['sortBy'] ?? 'name';
        $sortOrder = $filters['sortOrder'] ?? 'asc';
        $allowedSorts = ['id', 'name', 'phone'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'name';
        }

        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['perPage'] ?? 20;
        return $query->paginate($perPage);
    }
}
