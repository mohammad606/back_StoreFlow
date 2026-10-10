<?php

use App\Enums\AllInputTypeEnum;
use App\Models\AllInput;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\AllInputService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects customers and products owned by another user in order and input requests', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::create(['user_id' => $otherUser->id, 'name' => 'Other customer']);
    $product = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Other product',
        'quantity' => 10,
        'box' => 1,
    ]);

    $this->actingAs($user, 'api');

    $this->postJson('/api/orders', [
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORD-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'items.0.product_id']);

    $this->postJson('/api/inputs', [
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'IN-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'items.0.product_id']);

    expect(Order::count())->toBe(0)
        ->and(AllInput::count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(10);
});

it('allows users to create orders and inputs with their own customers and products', function () {
    $user = User::factory()->create();
    $customer = Customer::create(['user_id' => $user->id, 'name' => 'Owned customer']);
    $product = Store::create([
        'user_id' => $user->id,
        'name' => 'Owned product',
        'quantity' => 10,
        'box' => 1,
    ]);

    $this->actingAs($user, 'api');

    $this->postJson('/api/orders', [
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORD-OWNED',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 2]],
    ])->assertOk();

    $this->postJson('/api/inputs', [
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'IN-OWNED',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 3]],
    ])->assertOk();

    expect(Order::where('user_id', $user->id)->count())->toBe(1)
        ->and(AllInput::where('user_id', $user->id)->count())->toBe(1)
        ->and(AllInput::where('user_id', $user->id)->first()->type)->toBe(AllInputTypeEnum::Production);
});

it('enforces customer and product ownership in order and input services', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::create(['user_id' => $otherUser->id, 'name' => 'Other customer']);
    $ownedCustomer = Customer::create(['user_id' => $user->id, 'name' => 'Owned customer']);
    $ownedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Owned product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $foreignProduct = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Other product',
        'quantity' => 10,
        'box' => 1,
    ]);

    $this->actingAs($user, 'api');

    expect(fn () => app(OrderService::class)->create([
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORD-2',
        'customer_id' => $customer->id,
        'items' => [['product_id' => $ownedProduct->id, 'quantity' => 1]],
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => app(AllInputService::class)->create([
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'IN-2',
        'customer_id' => $customer->id,
        'items' => [['product_id' => $ownedProduct->id, 'quantity' => 1]],
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => app(OrderService::class)->create([
        'date' => now()->toDateString(),
        'sender' => 'Test',
        'noa' => 'ORD-3',
        'customer_id' => $ownedCustomer->id,
        'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => app(AllInputService::class)->create([
        'date' => now()->toDateString(),
        'type' => 'production',
        'noa' => 'IN-3',
        'customer_id' => $ownedCustomer->id,
        'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
    ]))->toThrow(ModelNotFoundException::class);

    expect(Order::count())->toBe(0)
        ->and(AllInput::count())->toBe(0)
        ->and($ownedProduct->fresh()->quantity)->toBe(10)
        ->and($foreignProduct->fresh()->quantity)->toBe(10);
});

it('rejects login and protected requests for expired or blocked accounts', function () {
    $expiredUser = User::factory()->create([
        'email' => 'expired@example.com',
        'password' => 'password123',
        'status' => User::STATUS_ACTIVE,
        'subscription_starts_at' => now()->subMonth(),
        'subscription_ends_at' => now()->subDay(),
        'trial_ends_at' => null,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $expiredUser->email,
        'password' => 'password123',
    ])->assertForbidden()
        ->assertJsonPath('data.status', User::STATUS_BLOCKED);

    $blockedUser = User::factory()->create([
        'status' => User::STATUS_BLOCKED,
        'trial_ends_at' => null,
    ]);

    $this->actingAs($blockedUser, 'api')
        ->getJson('/api/store')
        ->assertForbidden()
        ->assertJsonPath('data.status', User::STATUS_BLOCKED);
});

it('allows admins to access protected routes without a subscription', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'status' => User::STATUS_BLOCKED,
        'trial_ends_at' => null,
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/store')
        ->assertOk();
});
