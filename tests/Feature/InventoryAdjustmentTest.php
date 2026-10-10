<?php

use App\Enums\StockMovementTypeEnum;
use App\Models\InventoryAdjustment;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a balanced inventory adjustment and records stock history', function () {
    $user = User::factory()->create();
    $decreasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to decrease',
        'quantity' => 12,
        'box' => 1,
    ]);
    $increasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to increase',
        'quantity' => 3,
        'box' => 1,
    ]);

    $response = $this->actingAs($user, 'api')->postJson('/api/adjustments', [
        'date' => '2026-10-10',
        'note' => 'Physical count correction',
        'items' => [
            ['product_id' => $decreasedProduct->id, 'quantity_change' => -4],
            ['product_id' => $increasedProduct->id, 'quantity_change' => 4],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.date', '2026-10-10')
        ->assertJsonPath('data.note', 'Physical count correction')
        ->assertJsonCount(2, 'data.items');

    $adjustment = InventoryAdjustment::with('items')->firstOrFail();

    expect($adjustment->user_id)->toBe($user->id)
        ->and($decreasedProduct->fresh()->quantity)->toBe(8)
        ->and($increasedProduct->fresh()->quantity)->toBe(7)
        ->and($adjustment->items->sum('quantity_change'))->toBe(0)
        ->and(StoreQuantityHistory::where('reference_type', 'InventoryAdjustment')
            ->where('reference_id', $adjustment->id)
            ->count())->toBe(2)
        ->and(StoreQuantityHistory::first()->change_type)->toBe(StockMovementTypeEnum::InventoryAdjustment);
});

it('rejects an adjustment whose quantity changes do not balance', function () {
    $user = User::factory()->create();
    $products = collect(['Product A', 'Product B'])->map(fn ($name) => Store::create([
        'user_id' => $user->id,
        'name' => $name,
        'quantity' => 10,
        'box' => 1,
    ]));

    $this->actingAs($user, 'api')->postJson('/api/adjustments', [
        'date' => '2026-10-10',
        'items' => [
            ['product_id' => $products[0]->id, 'quantity_change' => -3],
            ['product_id' => $products[1]->id, 'quantity_change' => 2],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(InventoryAdjustment::count())->toBe(0)
        ->and($products[0]->fresh()->quantity)->toBe(10)
        ->and($products[1]->fresh()->quantity)->toBe(10)
        ->and(StoreQuantityHistory::count())->toBe(0);
});

it('rejects foreign products but allows inventory adjustments to result in negative stock', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Owned product',
        'quantity' => 2,
        'box' => 1,
    ]);
    $foreignProduct = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Foreign product',
        'quantity' => 5,
        'box' => 1,
    ]);
    $otherOwnedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Other owned product',
        'quantity' => 5,
        'box' => 1,
    ]);

    $this->actingAs($user, 'api');

    $this->postJson('/api/adjustments', [
        'date' => '2026-10-10',
        'items' => [
            ['product_id' => $ownedProduct->id, 'quantity_change' => -1],
            ['product_id' => $foreignProduct->id, 'quantity_change' => 1],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('items.1.product_id');

    $this->postJson('/api/adjustments', [
        'date' => '2026-10-10',
        'items' => [
            ['product_id' => $ownedProduct->id, 'quantity_change' => -3],
            ['product_id' => $otherOwnedProduct->id, 'quantity_change' => 3],
        ],
    ])->assertOk();

    expect(InventoryAdjustment::count())->toBe(1)
        ->and($ownedProduct->fresh()->quantity)->toBe(-1)
        ->and($foreignProduct->fresh()->quantity)->toBe(5)
        ->and($otherOwnedProduct->fresh()->quantity)->toBe(8)
        ->and(StoreQuantityHistory::where('reference_type', 'InventoryAdjustment')->count())->toBe(2);
});

it('requires authentication to create an inventory adjustment', function () {
    $this->postJson('/api/adjustments', [])
        ->assertUnauthorized();
});

it('lists only the authenticated user adjustments with search and date filters', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'First product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $secondProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Second product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $foreignProduct = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Foreign product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $adjustment = InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-10-09',
        'note' => 'Shelf A correction',
    ]);
    $adjustment->items()->createMany([
        ['store_id' => $firstProduct->id, 'quantity_change' => -2],
        ['store_id' => $secondProduct->id, 'quantity_change' => 2],
    ]);
    $otherUserAdjustment = InventoryAdjustment::create([
        'user_id' => $otherUser->id,
        'date' => '2026-10-09',
        'note' => 'Shelf A correction',
    ]);
    $otherUserAdjustment->items()->createMany([
        ['store_id' => $foreignProduct->id, 'quantity_change' => -2],
        ['store_id' => Store::create([
            'user_id' => $otherUser->id,
            'name' => 'Foreign product two',
            'quantity' => 10,
            'box' => 1,
        ])->id, 'quantity_change' => 2],
    ]);
    InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-09-01',
        'note' => 'Unrelated count',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/adjustments?search=Shelf&start_date=2026-10-01&end_date=2026-10-31&per_page=1');

    $response->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('pagination.per_page', 1)
        ->assertJsonPath('data.0.id', $adjustment->id)
        ->assertJsonCount(2, 'data.0.items');
});

it('returns one adjustment with its products and hides other users adjustments', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = Store::create([
        'user_id' => $user->id,
        'name' => 'Adjustment product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $otherProduct = Store::create([
        'user_id' => $otherUser->id,
        'name' => 'Other adjustment product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $adjustment = InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-10-10',
        'note' => 'Single adjustment',
    ]);
    $adjustment->items()->create([
        'store_id' => $product->id,
        'quantity_change' => -2,
    ]);
    $foreignAdjustment = InventoryAdjustment::create([
        'user_id' => $otherUser->id,
        'date' => '2026-10-10',
    ]);
    $foreignAdjustment->items()->create([
        'store_id' => $otherProduct->id,
        'quantity_change' => 2,
    ]);

    $this->actingAs($user, 'api')
        ->getJson("/api/adjustments/{$adjustment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $adjustment->id)
        ->assertJsonPath('data.note', 'Single adjustment')
        ->assertJsonPath('data.items.0.product.id', $product->id)
        ->assertJsonPath('data.items.0.quantity_change', -2);

    $this->getJson("/api/adjustments/{$foreignAdjustment->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Inventory adjustment not found');
});

it('updates adjustment metadata and allows item changes to result in negative stock', function () {
    $user = User::factory()->create();
    $decreasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to decrease',
        'quantity' => 2,
        'box' => 1,
    ]);
    $increasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to increase',
        'quantity' => 7,
        'box' => 1,
    ]);
    $adjustment = InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-10-09',
        'note' => 'Original note',
    ]);
    $adjustment->items()->createMany([
        ['store_id' => $decreasedProduct->id, 'quantity_change' => -4],
        ['store_id' => $increasedProduct->id, 'quantity_change' => 4],
    ]);

    $this->actingAs($user, 'api')
        ->putJson("/api/adjustments/{$adjustment->id}", [
            'date' => '2026-10-10',
            'note' => 'Updated note',
            'items' => [
                ['product_id' => $decreasedProduct->id, 'quantity_change' => -8],
                ['product_id' => $increasedProduct->id, 'quantity_change' => 8],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.date', '2026-10-10')
        ->assertJsonPath('data.note', 'Updated note');

    expect($decreasedProduct->fresh()->quantity)->toBe(-2)
        ->and($increasedProduct->fresh()->quantity)->toBe(11)
        ->and($adjustment->fresh()->items->sum('quantity_change'))->toBe(0)
        ->and(StoreQuantityHistory::where('reference_type', 'InventoryAdjustment')
            ->where('reference_id', $adjustment->id)
            ->count())->toBe(2);
});

it('deletes an adjustment by reversing stock even when the reversal results in negative stock', function () {
    $user = User::factory()->create();
    $decreasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to decrease',
        'quantity' => 8,
        'box' => 1,
    ]);
    $increasedProduct = Store::create([
        'user_id' => $user->id,
        'name' => 'Product to increase',
        'quantity' => 7,
        'box' => 1,
    ]);
    $adjustment = InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-10-10',
    ]);
    $adjustment->items()->createMany([
        ['store_id' => $decreasedProduct->id, 'quantity_change' => -4],
        ['store_id' => $increasedProduct->id, 'quantity_change' => 4],
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson("/api/adjustments/{$adjustment->id}")
        ->assertOk();

    expect(InventoryAdjustment::whereKey($adjustment->id)->exists())->toBeFalse()
        ->and($decreasedProduct->fresh()->quantity)->toBe(12)
        ->and($increasedProduct->fresh()->quantity)->toBe(3)
        ->and(StoreQuantityHistory::where('reference_type', 'InventoryAdjustment')
            ->where('reference_id', $adjustment->id)
            ->count())->toBe(2);

    $unsafeAdjustment = InventoryAdjustment::create([
        'user_id' => $user->id,
        'date' => '2026-10-10',
    ]);
    $unsafeAdjustment->items()->createMany([
        ['store_id' => $decreasedProduct->id, 'quantity_change' => -2],
        ['store_id' => $increasedProduct->id, 'quantity_change' => 2],
    ]);
    $increasedProduct->update(['quantity' => 1]);

    $this->deleteJson("/api/adjustments/{$unsafeAdjustment->id}")
        ->assertOk();

    expect(InventoryAdjustment::whereKey($unsafeAdjustment->id)->exists())->toBeFalse()
        ->and($decreasedProduct->fresh()->quantity)->toBe(14)
        ->and($increasedProduct->fresh()->quantity)->toBe(-1)
        ->and(StoreQuantityHistory::where('reference_type', 'InventoryAdjustment')
            ->where('reference_id', $unsafeAdjustment->id)
            ->count())->toBe(2);
});
