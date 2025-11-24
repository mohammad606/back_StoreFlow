<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\User;

class StoreSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $boxOptions = [12, 24, 48, 60, 96];
        foreach ($users as $user) {
            for ($i = 1; $i <= 5; $i++) {
                Store::create([
                    'user_id' => $user->id,
                    'name' => "Product {$i} of User {$user->id}",
                    'quantity' => rand(10, 100),
                    'box' => $boxOptions[array_rand($boxOptions)],
                ]);
            }
        }
    }
}
