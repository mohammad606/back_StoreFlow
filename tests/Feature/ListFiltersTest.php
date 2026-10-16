<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects invalid list filters on user and admin list endpoints', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $userRoutes = [
        '/api/store',
        '/api/customers',
        '/api/inputs',
        '/api/orders',
        '/api/adjustments',
    ];

    $this->actingAs($user, 'api');

    foreach ($userRoutes as $route) {
        $this->getJson($route . '?sort_by=unknown_column')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort_by');
    }

    $this->getJson('/api/store?sort_order=random')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort_order');

    $this->getJson('/api/store?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    $this->actingAs($admin, 'api')
        ->getJson('/api/auth/admin/users?sort_by=id%20desc')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort_by');
});

it('continues to accept legacy camel case list filter names', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/store?sortBy=name&sortOrder=asc&perPage=1')
        ->assertOk()
        ->assertJsonPath('pagination.per_page', 1);
});
