<?php

namespace App\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
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

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with($this->relationships);

        if (\Schema::hasColumn($this->model->getTable(), 'user_id')) {
            $query->where('user_id', $this->currentUserId());
        }

        if (isset($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['perPage'] ?? 20;
        return $query->paginate($perPage);
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
