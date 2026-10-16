<?php

use App\Enums\StockMovementTypeEnum;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Representative;
use App\Models\RepresentativeInvoice;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invoiceOwner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'status' => 'active',
        'trial_ends_at' => null,
        'subscription_starts_at' => now()->subDay(),
        'subscription_ends_at' => now()->addMonth(),
    ], $attributes));
}

function createRepresentativeFor(User $owner, string $email = 'rep@example.com'): Representative
{
    return Representative::create([
        'user_id' => $owner->id,
        'name' => 'Sales Representative',
        'email' => $email,
        'password' => 'password123',
    ]);
}

function representativeLogin(User $owner, Representative $representative): array
{
    $response = test()->postJson('/api/representative/auth/login', [
        'warehouse_user_id' => $owner->id,
        'email' => $representative->email,
        'password' => 'password123',
    ]);

    $response->assertOk();

    return ['Authorization' => 'Bearer '.$response->json('data.auth.token')];
}

it('lets an active representative view only their owners inventory and customers', function () {
    $owner = invoiceOwner();
    $otherOwner = invoiceOwner();
    $representative = createRepresentativeFor($owner);

    $ownedProduct = Store::create([
        'user_id' => $owner->id,
        'name' => 'Owned product',
        'quantity' => 5,
        'box' => 1,
    ]);
    Store::create([
        'user_id' => $otherOwner->id,
        'name' => 'Foreign product',
        'quantity' => 12,
        'box' => 1,
    ]);
    $ownedCustomer = Customer::create(['user_id' => $owner->id, 'name' => 'Owned customer']);
    Customer::create(['user_id' => $otherOwner->id, 'name' => 'Foreign customer']);

    $headers = representativeLogin($owner, $representative);

    $this->withHeaders($headers)
        ->getJson('/api/representative/inventory')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownedProduct->id)
        ->assertJsonMissing(['name' => 'Foreign product']);

    $this->withHeaders($headers)
        ->getJson('/api/representative/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownedCustomer->id)
        ->assertJsonMissing(['name' => 'Foreign customer']);
});

it('submits an invoice without changing stock until the owner approves it', function () {
    $owner = invoiceOwner();
    $representative = createRepresentativeFor($owner);
    $customer = Customer::create(['user_id' => $owner->id, 'name' => 'Customer']);
    $product = Store::create([
        'user_id' => $owner->id,
        'name' => 'Product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $headers = representativeLogin($owner, $representative);

    $response = $this->withHeaders($headers)
        ->postJson('/api/representative/invoices', [
            'date' => now()->toDateString(),
            'noa' => 'REP-INV-1',
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.items.0.name', 'Product');

    $invoice = RepresentativeInvoice::with('items')->firstOrFail();
    expect($invoice->user_id)->toBe($owner->id)
        ->and($invoice->representative_id)->toBe($representative->id)
        ->and($product->fresh()->quantity)->toBe(10)
        ->and(Order::count())->toBe(0)
        ->and(StoreQuantityHistory::count())->toBe(0);

    $this->actingAs($owner, 'api')
        ->getJson('/api/representative-invoices?status=pending')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $invoice->id)
        ->assertJsonPath('data.0.representative.id', $representative->id);

    $this->withHeaders($headers)
        ->getJson('/api/representative/invoices')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $invoice->id);

    $this->actingAs($owner, 'api')
        ->getJson('/api/representative-invoices?status=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->actingAs($owner, 'api')
        ->postJson("/api/representative-invoices/{$invoice->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.order.sender', $representative->name)
        ->assertJsonPath('data.order.items.0.quantity', 3);

    $this->postJson("/api/representative-invoices/{$invoice->id}/approve")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($product->fresh()->quantity)->toBe(7)
        ->and(Order::where('user_id', $owner->id)->count())->toBe(1)
        ->and(StoreQuantityHistory::where('change_type', StockMovementTypeEnum::Order)->count())->toBe(1)
        ->and($invoice->fresh()->order_id)->not->toBeNull();
});

it('rejects a representative invoice without affecting inventory', function () {
    $owner = invoiceOwner();
    $representative = createRepresentativeFor($owner);
    $customer = Customer::create(['user_id' => $owner->id, 'name' => 'Customer']);
    $product = Store::create([
        'user_id' => $owner->id,
        'name' => 'Product',
        'quantity' => 10,
        'box' => 1,
    ]);
    $headers = representativeLogin($owner, $representative);

    $invoiceId = $this->withHeaders($headers)
        ->postJson('/api/representative/invoices', [
            'date' => now()->toDateString(),
            'noa' => 'REP-INV-2',
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])
        ->assertOk()
        ->json('data.id');

    $this->actingAs($owner, 'api')
        ->postJson("/api/representative-invoices/{$invoiceId}/reject", [
            'decision_note' => 'Please correct the quantities.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_note', 'Please correct the quantities.');

    expect($product->fresh()->quantity)->toBe(10)
        ->and(Order::count())->toBe(0)
        ->and(StoreQuantityHistory::count())->toBe(0);
});

it('does not approve an invoice when stock is insufficient', function () {
    $owner = invoiceOwner();
    $representative = createRepresentativeFor($owner);
    $customer = Customer::create(['user_id' => $owner->id, 'name' => 'Customer']);
    $product = Store::create([
        'user_id' => $owner->id,
        'name' => 'Low stock product',
        'quantity' => 2,
        'box' => 1,
    ]);
    $headers = representativeLogin($owner, $representative);

    $invoiceId = $this->withHeaders($headers)
        ->postJson('/api/representative/invoices', [
            'date' => now()->toDateString(),
            'noa' => 'REP-INV-3',
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])
        ->assertOk()
        ->json('data.id');

    $this->actingAs($owner, 'api')
        ->postJson("/api/representative-invoices/{$invoiceId}/approve")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect($product->fresh()->quantity)->toBe(2)
        ->and(Order::count())->toBe(0)
        ->and(StoreQuantityHistory::count())->toBe(0)
        ->and(RepresentativeInvoice::findOrFail($invoiceId)->status->value)->toBe('pending');
});

it('scopes invoice review to the owner and prevents representatives from changing stock', function () {
    $owner = invoiceOwner();
    $otherOwner = invoiceOwner();
    $representative = createRepresentativeFor($owner);
    $otherCustomer = Customer::create(['user_id' => $otherOwner->id, 'name' => 'Other customer']);
    $foreignProduct = Store::create([
        'user_id' => $otherOwner->id,
        'name' => 'Foreign product',
        'quantity' => 9,
        'box' => 1,
    ]);
    $otherInvoice = RepresentativeInvoice::create([
        'user_id' => $otherOwner->id,
        'representative_id' => createRepresentativeFor($otherOwner, 'other@example.com')->id,
        'customer_id' => $otherCustomer->id,
        'date' => now()->toDateString(),
        'noa' => 'OTHER-INV',
        'status' => 'pending',
    ]);
    $otherInvoice->items()->create([
        'store_id' => $foreignProduct->id,
        'name' => $foreignProduct->name,
        'quantity' => 2,
    ]);

    $headers = representativeLogin($owner, $representative);
    $this->withHeaders($headers)
        ->putJson("/api/store/{$foreignProduct->id}", ['quantity' => 100])
        ->assertUnauthorized();

    $this->actingAs($owner, 'api')
        ->postJson("/api/representative-invoices/{$otherInvoice->id}/approve")
        ->assertNotFound();

    expect($foreignProduct->fresh()->quantity)->toBe(9)
        ->and(Order::count())->toBe(0);
});
