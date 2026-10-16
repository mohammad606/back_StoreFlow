<?php

namespace App\Services;

use App\Models\Representative;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    public function updatePassword(int $userId, string $password): User
    {
        $user = User::findOrFail($userId);
        $user->password = $password;
        $user->save();

        return $user;
    }

    public function updateRepresentativesLimit(int $userId, int $limit): User
    {
        return DB::transaction(function () use ($userId, $limit): User {
            $user = User::whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();
            $user->representatives_limit = $limit;
            $user->save();

            return $user;
        });
    }

    public function deleteRepresentative(int $representativeId): void
    {
        $representative = Representative::find($representativeId);

        if ($representative === null) {
            throw (new ModelNotFoundException)->setModel(Representative::class, [$representativeId]);
        }

        $representative->delete();
    }

    public function getAllRepresentatives(array $filters): LengthAwarePaginator
    {
        $query = Representative::query()
            ->with('user:id,name,email');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->where('representatives.name', 'like', "%{$search}%")
                    ->orWhere('representatives.email', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query
            ->orderBy('representatives.'.$sortBy, $sortOrder)
            ->paginate($filters['per_page'] ?? 20);
    }
}
