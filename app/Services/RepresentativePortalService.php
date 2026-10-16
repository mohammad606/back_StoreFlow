<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Representative;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RepresentativePortalService
{
    public function getInventory(array $filters): LengthAwarePaginator
    {
        $representative = $this->currentRepresentative();
        $query = Store::query()
            ->where('user_id', $representative->user_id);

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_order'] ?? 'asc')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function getCustomers(array $filters): LengthAwarePaginator
    {
        $representative = $this->currentRepresentative();
        $query = Customer::query()
            ->where('user_id', $representative->user_id);

        if (! empty($filters['search'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_order'] ?? 'asc')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    private function currentRepresentative(): Representative
    {
        $representative = Auth::guard('representative-api')->user();

        abort_unless($representative instanceof Representative, 401, 'Unauthenticated.');

        return $representative;
    }
}
