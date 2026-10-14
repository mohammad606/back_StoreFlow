<?php

use App\Models\AllInput;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns dashboard counts scoped to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::create([
        'user_id' => $user->id,
        'name' => 'Owned customer',
    ]);
    $otherCustomer = Customer::create([
        'user_id' => $otherUser->id,
        'name' => 'Other customer',
    ]);

    Store::create([
        'user_id' => $user->id,
        'name' => 'Available product',
        'quantity' => 4,
        'box' => 1,
    ]);
    Store::create([
        'user_id' => $user->id,
        'name' => 'Depleted product',
        'quantity' => -2,
        'box' => 1,
    ]);
    Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Other user product',
        'quantity' => -7,
        'box' => 1,
    ]);

    Order::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'OWN-ORDER',
    ]);
    Order::create([
        'user_id' => $otherUser->id,
        'customer_id' => $otherCustomer->id,
        'customer_name' => $otherCustomer->name,
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'OTHER-ORDER',
    ]);
    AllInput::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'OWN-INPUT',
    ]);
    AllInput::create([
        'user_id' => $otherUser->id,
        'customer_id' => $otherCustomer->id,
        'customer_name' => $otherCustomer->name,
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'OTHER-INPUT',
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.inventory_items_count', 2)
        ->assertJsonPath('data.orders_count', 1)
        ->assertJsonPath('data.inputs_count', 1)
        ->assertJsonPath('data.out_of_stock_items_count', 1);
});

it('requires authentication to retrieve the dashboard summary', function () {
    $this->getJson('/api/dashboard/summary')
        ->assertUnauthorized();
});
