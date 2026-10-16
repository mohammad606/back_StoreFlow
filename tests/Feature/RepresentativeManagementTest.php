<?php

use App\Models\Representative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function activeSubscriber(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'status' => 'active',
        'trial_ends_at' => null,
        'subscription_starts_at' => now()->subDay(),
        'subscription_ends_at' => now()->addMonth(),
    ], $attributes));
}

it('allows active subscribers to create up to their default representative limit', function () {
    $user = activeSubscriber();
    $this->actingAs($user, 'api');

    foreach ([1, 2] as $number) {
        $this->postJson('/api/representatives', [
            'name' => "Representative {$number}",
            'email' => "rep{$number}@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()
            ->assertJsonMissingPath('data.password');
    }

    $this->postJson('/api/representatives', [
        'name' => 'Third representative',
        'email' => 'rep3@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('representatives');

    expect($user->representatives()->count())->toBe(2)
        ->and(Hash::check('password123', $user->representatives()->first()->password))->toBeTrue();
});

it('does not allow free-trial users to create representatives', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/representatives', [
            'name' => 'Trial representative',
            'email' => 'trial-rep@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

    expect($user->representatives()->count())->toBe(0);
});

it('allows an admin to change a users representative limit', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = activeSubscriber();

    $this->actingAs($admin, 'api')
        ->patchJson("/api/auth/admin/users/{$user->id}/representatives-limit", [
            'representatives_limit' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.representatives_limit', 4);

    expect($user->fresh()->representatives_limit)->toBe(4);
});

it('does not let non-admin users change a representative limit', function () {
    $user = activeSubscriber();
    $target = activeSubscriber();

    $this->actingAs($user, 'api')
        ->patchJson("/api/auth/admin/users/{$target->id}/representatives-limit", [
            'representatives_limit' => 4,
        ])
        ->assertForbidden();

    expect($target->fresh()->representatives_limit)->toBe(2);
});

it('allows an admin to delete any representative', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = activeSubscriber();
    $representative = Representative::create([
        'user_id' => $owner->id,
        'name' => 'Representative',
        'email' => 'rep@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/auth/admin/representatives/{$representative->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Representative deleted successfully');

    expect($representative->fresh())->toBeNull()
        ->and($owner->representatives()->count())->toBe(0);
});

it('allows an admin to list representatives from all users with pagination', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstOwner = activeSubscriber(['name' => 'First Warehouse']);
    $secondOwner = activeSubscriber(['name' => 'Second Warehouse']);

    Representative::create([
        'user_id' => $firstOwner->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);
    Representative::create([
        'user_id' => $secondOwner->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/auth/admin/representatives?per_page=1&sort_by=name&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('pagination.per_page', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alice')
        ->assertJsonPath('data.0.user.id', $firstOwner->id)
        ->assertJsonMissingPath('data.0.password');

    $this->getJson('/api/auth/admin/representatives?search=Second%20Warehouse')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.name', 'Bob')
        ->assertJsonPath('data.0.user.name', 'Second Warehouse');
});

it('does not allow a non-admin to delete representatives through the admin endpoint', function () {
    $user = activeSubscriber();
    $owner = activeSubscriber();
    $representative = Representative::create([
        'user_id' => $owner->id,
        'name' => 'Representative',
        'email' => 'rep@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson("/api/auth/admin/representatives/{$representative->id}")
        ->assertForbidden();

    expect($representative->fresh())->not->toBeNull();
});

it('does not allow a non-admin to list all representatives', function () {
    $user = activeSubscriber();

    $this->actingAs($user, 'api')
        ->getJson('/api/auth/admin/representatives')
        ->assertForbidden();
});

it('validates representative data and keeps email uniqueness scoped to each warehouse', function () {
    $user = activeSubscriber();
    $otherUser = activeSubscriber();

    $this->actingAs($user, 'api')
        ->postJson('/api/representatives', [
            'name' => 'First representative',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

    $this->postJson('/api/representatives', [
        'name' => 'Duplicate representative',
        'email' => 'shared@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->actingAs($otherUser, 'api')
        ->postJson('/api/representatives', [
            'name' => 'Other warehouse representative',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();
});

it('allows owners to edit and delete only their own representatives', function () {
    $user = activeSubscriber();
    $otherUser = activeSubscriber();
    $ownedRepresentative = Representative::create([
        'user_id' => $user->id,
        'name' => 'Owned representative',
        'email' => 'owned@example.com',
        'password' => 'password123',
    ]);
    $foreignRepresentative = Representative::create([
        'user_id' => $otherUser->id,
        'name' => 'Foreign representative',
        'email' => 'foreign@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user, 'api')
        ->putJson("/api/representatives/{$ownedRepresentative->id}", [
            'name' => 'Updated representative',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated representative');

    $this->putJson("/api/representatives/{$foreignRepresentative->id}", [
        'name' => 'Hijacked representative',
    ])->assertNotFound();

    $this->deleteJson("/api/representatives/{$foreignRepresentative->id}")
        ->assertNotFound();

    $this->deleteJson("/api/representatives/{$ownedRepresentative->id}")
        ->assertOk();

    expect($ownedRepresentative->fresh())->toBeNull()
        ->and($foreignRepresentative->fresh())->not->toBeNull();
});

it('requires authentication for representative management', function () {
    $this->getJson('/api/representatives')->assertUnauthorized();
});

it('includes only the authenticated users representatives in the me response', function () {
    $user = activeSubscriber();
    $otherUser = activeSubscriber();
    $ownedRepresentative = Representative::create([
        'user_id' => $user->id,
        'name' => 'My representative',
        'email' => 'mine@example.com',
        'password' => 'password123',
    ]);
    Representative::create([
        'user_id' => $otherUser->id,
        'name' => 'Other representative',
        'email' => 'other@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonCount(1, 'data.representatives')
        ->assertJsonPath('data.representatives.0.id', $ownedRepresentative->id)
        ->assertJsonPath('data.representatives.0.name', 'My representative')
        ->assertJsonMissingPath('data.representatives.0.password')
        ->assertJsonMissing(['name' => 'Other representative']);
});
