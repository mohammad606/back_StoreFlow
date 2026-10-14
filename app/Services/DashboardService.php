<?php

namespace App\Services;

use App\Models\AllInput;
use App\Models\Order;
use App\Models\Store;

class DashboardService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Store();
    }

    public function getSummary(): array
    {
        $userId = $this->currentUserId();

        return [
            'inventory_items_count' => Store::where('user_id', $userId)->count(),
            'orders_count' => Order::where('user_id', $userId)->count(),
            'inputs_count' => AllInput::where('user_id', $userId)->count(),
            'out_of_stock_items_count' => Store::where('user_id', $userId)
                ->where('quantity', '<', 0)
                ->count(),
        ];
    }
}
