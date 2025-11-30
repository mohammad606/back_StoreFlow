<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Support\Str;
use App\Models\User;

class OrderSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();

        foreach ($users as $user) {
            for ($i = 1; $i <= 3; $i++) {
                $allInput = Order::create([
                    'user_id' => $user->id,
                    'date' => now()->subDays(rand(0, 30)),
                    'sender' => 'Seeder',
                    'noa' => 'NOA-' . Str::upper(Str::random(6)),
                    'customer_id' => rand(1, 5),
                    'customer_name' => "Customer " . rand(1, 5),
                ]);
                $stores = Store::where('user_id', $user->id)->inRandomOrder()->take(rand(1, 5))->get();
                foreach ($stores as $store) {
                    OrderItem::create([
                        'order_id' => $allInput->id,
                        'name' => $store->name,
                        'quantity' => rand(1, $store->quantity),
                        'product_id' => $store->id,
                    ]);
                }
            }
        }
    }
}
