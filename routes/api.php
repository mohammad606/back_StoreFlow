<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AllInputController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RepresentativeController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    // ---------------------------------------------------------------------------------------------------------------
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
    Route::middleware(['auth:api', 'account.access'])->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/update-name', [AuthController::class, 'updateName'])->name('auth.updateName');

    });
    // ---------------------------------------------------------------------------------------------------------------
    Route::middleware(['auth:api', 'admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::post('/users', [AdminController::class, 'store'])->name('users.store');
            Route::get('/users', [AdminController::class, 'index'])->name('users.index');
            Route::get('/representatives', [AdminController::class, 'indexRepresentatives'])->name('representatives.index');
            Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('users.destroy');
            Route::patch('/users/{id}/access', [AdminController::class, 'updateAccess'])->name('users.access');
            Route::patch('/users/{id}/password', [AdminController::class, 'updatePassword'])->name('users.password');
            Route::patch('/users/{id}/representatives-limit', [AdminController::class, 'updateRepresentativesLimit'])->name('users.representatives-limit');
            Route::delete('/representatives/{id}', [AdminController::class, 'destroyRepresentative'])->name('representatives.destroy');

        });

});

// ---------------------------------------------------------------------------------------------------------------

Route::middleware(['auth:api', 'account.access'])->prefix('store')->group(function () {
    Route::get('/', [StoreController::class, 'index']);
    Route::post('/', [StoreController::class, 'store']);
    Route::get('/{id}/quantity-history', [StoreController::class, 'getQuantityHistory']);
    Route::put('/{id}', [StoreController::class, 'update']);
    Route::delete('/{id}', [StoreController::class, 'destroy']);

});

// ---------------------------------------------------------------------------------------------------------------

Route::middleware(['auth:api', 'account.access'])->prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::put('/{id}', [CustomerController::class, 'update']);
    Route::delete('/{id}', [CustomerController::class, 'destroy']);

});

Route::middleware(['auth:api', 'account.access'])->prefix('inputs')->group(function () {
    Route::get('/', [AllInputController::class, 'index']);
    Route::post('/', [AllInputController::class, 'store']);
    Route::get('/{id}', [AllInputController::class, 'show']);
    Route::put('/{id}', [AllInputController::class, 'update']);
    Route::delete('/{id}', [AllInputController::class, 'destroy']);
});

Route::middleware(['auth:api', 'account.access'])->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/top-selling-products', [OrderController::class, 'topSellingProducts']);
    Route::get('{id}', [OrderController::class, 'show']);
    Route::put('{id}', [OrderController::class, 'update']);
    Route::delete('{id}', [OrderController::class, 'destroy']);
});

Route::middleware(['auth:api', 'account.access'])->prefix('adjustments')->group(function () {
    Route::get('/', [InventoryAdjustmentController::class, 'index']);
    Route::post('/', [InventoryAdjustmentController::class, 'store']);
    Route::get('/{id}', [InventoryAdjustmentController::class, 'show']);
    Route::put('/{id}', [InventoryAdjustmentController::class, 'update']);
    Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy']);
});

Route::middleware(['auth:api', 'account.access'])->prefix('dashboard')->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary']);
});

Route::middleware(['auth:api', 'account.access'])->prefix('representatives')->group(function () {
    Route::get('/', [RepresentativeController::class, 'index']);
    Route::post('/', [RepresentativeController::class, 'store']);
    Route::put('/{id}', [RepresentativeController::class, 'update']);
    Route::delete('/{id}', [RepresentativeController::class, 'destroy']);
});
