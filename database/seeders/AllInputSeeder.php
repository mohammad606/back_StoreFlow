<?php

namespace Database\Seeders;

use App\Enums\AllInputTypeEnum;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Customer;
use App\Services\AllInputService;
use Illuminate\Support\Str;

class AllInputSeeder extends Seeder
{
    public function run()
    {
        $service = app(AllInputService::class);
        $users = User::all();

        foreach ($users as $user) {

            auth()->login($user);

            for ($i = 1; $i <= 3; $i++) {
                $stores = Store::where('user_id', $user->id)
                    ->inRandomOrder()
                    ->take(rand(1, 3))
                    ->get();

                if ($stores->isEmpty()) {
                    continue;
                }

                $items = [];
                foreach ($stores as $store) {
                    $items[] = [
                        'product_id' => $store->id,
                        'quantity' => rand(5, 20),
                    ];
                }

                $customer = Customer::inRandomOrder()->first();


                try {
                    $service->create([
                        'date' => now()->subDays(rand(0, 30))->format('Y-m-d'),
                        'type' => rand(0, 1) ? AllInputTypeEnum::Production : AllInputTypeEnum::Return,
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
