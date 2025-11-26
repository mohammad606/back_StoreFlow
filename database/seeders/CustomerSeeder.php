<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\User;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        foreach ($users as $user) {
            for ($i = 1; $i <= 5; $i++) {
                Customer::create([
                    'user_id' => $user->id,
                    'name' => "Customer {$i} of User {$user->id}",
                    'phone' => rand(100000, 120000),
                    'address' => "Address {$i} of User {$user->id}",
                ]);
            }
        }
    }
}
