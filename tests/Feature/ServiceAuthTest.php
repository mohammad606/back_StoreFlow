<?php

use App\Models\User;
use App\Services\StoreService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication before creating a store record', function () {
    $service = new StoreService();

    expect(fn () => $service->create([
        'name' => 'Test Product',
        'quantity' => 10,
        'box' => 1,
    ]))->toThrow(AuthenticationException::class);
});

it('assigns the authenticated user to a new store record', function () {
    $user = User::factory()->create();

    $service = new StoreService();

    $this->actingAs($user, 'api');

    $product = $service->create([
        'name' => 'Auth Product',
        'quantity' => 7,
        'box' => 2,
    ]);

    expect($product->user_id)->toBe($user->id)
        ->and($product->name)->toBe('Auth Product');
});
