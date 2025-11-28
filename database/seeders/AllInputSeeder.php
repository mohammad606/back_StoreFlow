<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AllInput;
use App\Models\AllInputItem;
use App\Models\Store;
use Illuminate\Support\Str;

class AllInputSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();

        foreach ($users as $user) {
            for ($i = 1; $i <= 3; $i++) {
                $allInput = AllInput::create([
                    'user_id' => $user->id,
                    'date' => now()->subDays(rand(0, 30)),
                    'type' => rand(0, 1) ? 'production' : 'return',
                    'noa' => 'NOA-' . Str::upper(Str::random(6)),
                    'customer_id' => rand(1, 5),
                    'customer_name' => "Customer " . rand(1,5),
                ]);
                $stores = Store::where('user_id', $user->id)->inRandomOrder()->take(rand(1, 5))->get();
                foreach ($stores as $store) {
                    AllInputItem::create([
                        'all_input_id' => $allInput->id,
                        'name' => $store->name,
                        'quantity' => rand(1, $store->quantity),
                        'product_id' => $store->id,
                    ]);
                }
            }
        }
    }
}
