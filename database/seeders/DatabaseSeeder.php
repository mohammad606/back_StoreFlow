<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AllInputSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\OrderSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            AllInputSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
            StoreSeeder::class,
        ]);
    }
}
