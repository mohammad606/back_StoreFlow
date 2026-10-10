<?php

use App\Models\User;
use App\Enums\UserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a trial user with a device identifier and expiry date', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Trial User',
        'email' => 'trial@example.com',
        'password' => 'password123',
        'device_id' => 'device-123',
    ]);

    $response->assertStatus(200);

    $user = User::first();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe(UserStatusEnum::FreeTrial)
        ->and($user->device_id)->toBe('device-123')
        ->and($user->trial_ends_at)->not->toBeNull();
});

it('allows an admin to update the active subscription date range for a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'status' => 'active',
        'subscription_starts_at' => now()->subDays(2),
        'subscription_ends_at' => now()->addDays(10),
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->patchJson("/api/auth/admin/users/{$user->id}/access", [
        'status' => 'active',
        'subscription_starts_at' => now()->startOfDay()->toDateString(),
        'subscription_ends_at' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(200);

    $user->refresh();

    expect($user->status)->toBe(UserStatusEnum::Active)
        ->and($user->subscription_starts_at)->not->toBeNull()
        ->and($user->subscription_ends_at)->not->toBeNull();
});
