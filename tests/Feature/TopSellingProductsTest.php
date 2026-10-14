<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the six products with the highest sold quantities for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::create([
        'user_id' => $user->id,
        'name' => 'Owned customer',
    ]);

    $products = collect(range(1, 7))->map(fn (int $number) => Store::create([
        'user_id' => $user->id,
        'name' => "Product {$number}",
        'quantity' => 100,
        'box' => 1,
    ]));

    $firstOrder = Order::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORDER-1',
    ]);
    $firstOrder->items()->createMany([
        ['product_id' => $products[0]->id, 'name' => $products[0]->name, 'quantity' => 4],
        ['product_id' => $products[1]->id, 'name' => $products[1]->name, 'quantity' => 5],
        ['product_id' => $products[2]->id, 'name' => $products[2]->name, 'quantity' => 3],
        ['product_id' => $products[3]->id, 'name' => $products[3]->name, 'quantity' => 2],
        ['product_id' => $products[4]->id, 'name' => $products[4]->name, 'quantity' => 1],
        ['product_id' => $products[5]->id, 'name' => $products[5]->name, 'quantity' => 1],
        ['product_id' => $products[6]->id, 'name' => $products[6]->name, 'quantity' => 1],
    ]);

    $secondOrder = Order::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORDER-2',
    ]);
    $secondOrder->items()->create([
        'product_id' => $products[0]->id,
        'name' => $products[0]->name,
        'quantity' => 4,
    ]);

    $foreignProduct = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Foreign product',
        'quantity' => 100,
        'box' => 1,
    ]);
    $foreignCustomer = Customer::create([
        'user_id' => $otherUser->id,
        'name' => 'Foreign customer',
    ]);
    $foreignOrder = Order::create([
        'user_id' => $otherUser->id,
        'customer_id' => $foreignCustomer->id,
        'customer_name' => $foreignCustomer->name,
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'FOREIGN-ORDER',
    ]);
    $foreignOrder->items()->create([
        'product_id' => $foreignProduct->id,
        'name' => $foreignProduct->name,
        'quantity' => 1000,
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/orders/top-selling-products')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.0.id', $products[0]->id)
        ->assertJsonPath('data.0.sold_quantity', 8)
        ->assertJsonPath('data.1.id', $products[1]->id)
        ->assertJsonPath('data.1.sold_quantity', 5)
        ->assertJsonMissing(['id' => $products[6]->id])
        ->assertJsonMissing(['id' => $foreignProduct->id]);
});

it('requires authentication to retrieve top selling products', function () {
    $this->getJson('/api/orders/top-selling-products')
        ->assertUnauthorized();
});

it('returns an empty list when the user has no sold products', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/orders/top-selling-products')
        ->assertOk()
        ->assertExactJson([
            'status' => 'success',
            'message' => 'Top selling products retrieved successfully',
            'data' => [],
            'code' => 200,
        ]);
});
