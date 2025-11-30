<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Customer;
use App\Services\OrderService;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run()
    {
        $service = app(OrderService::class);
        $users = User::all();

        foreach ($users as $user) {

            auth()->login($user);

            for ($i = 1; $i <= 3; $i++) {
                $stores = Store::where('user_id', $user->id)
                    ->where('quantity', '>', 0)
                    ->inRandomOrder()
                    ->take(rand(1, 3))
                    ->get();

                if ($stores->isEmpty()) {
                    continue;
                }

                $items = [];
                foreach ($stores as $store) {

                    $maxQuantity = min($store->quantity, 10);
                    if ($maxQuantity > 0) {
                        $items[] = [
                            'product_id' => $store->id,
                            'quantity' => rand(1, $maxQuantity),
                        ];
                    }
                }

                if (empty($items)) {
                    continue;
                }

                $customer = Customer::inRandomOrder()->first();


                try {
                    $service->create([
                        'date' => now()->subDays(rand(0, 30))->format('Y-m-d'),
                        'sender' => 'Seeder',
                        'noa' => 'NOA-' . Str::upper(Str::random(6)),
                        'customer_id' => $customer->id,
                        'items' => $items,
                    ]);
                } catch (\Exception $e) {

                    continue;
                }
            }

            auth()->logout();
        }
    }
}
