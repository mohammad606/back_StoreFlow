<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows an admin to change a user password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/auth/admin/users/{$user->id}/password", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'User password updated successfully')
        ->assertJsonMissingPath('data.password');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
});

it('rejects password changes from non-admin users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user, 'api')
        ->patchJson("/api/auth/admin/users/{$target->id}/password", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertForbidden();

    expect(Hash::check('old-password', $target->fresh()->password))->toBeTrue();
});

it('validates password length and confirmation before changing it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/auth/admin/users/{$target->id}/password", [
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check('old-password', $target->fresh()->password))->toBeTrue();
});

it('requires authentication to change a user password', function () {
    $target = User::factory()->create();

    $this->patchJson("/api/auth/admin/users/{$target->id}/password", [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnauthorized();
});
