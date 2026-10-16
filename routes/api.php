<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AllInputController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RepresentativeAuthController;
use App\Http\Controllers\RepresentativeController;
use App\Http\Controllers\RepresentativeInvoiceController;
use App\Http\Controllers\RepresentativePortalController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User authentication and administration
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->middleware('throttle:5,1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });

    Route::middleware(['auth:api', 'account.access'])->group(function (): void {
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/update-name', [AuthController::class, 'updateName'])->name('auth.updateName');
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth:api', 'admin'])
        ->group(function (): void {
            Route::post('/users', [AdminController::class, 'store'])->name('users.store');
            Route::get('/users', [AdminController::class, 'index'])->name('users.index');
            Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('users.destroy');
            Route::patch('/users/{id}/access', [AdminController::class, 'updateAccess'])->name('users.access');
            Route::patch('/users/{id}/password', [AdminController::class, 'updatePassword'])->name('users.password');
            Route::patch('/users/{id}/representatives-limit', [AdminController::class, 'updateRepresentativesLimit'])
                ->name('users.representatives-limit');

            Route::get('/representatives', [AdminController::class, 'indexRepresentatives'])
                ->name('representatives.index');
            Route::delete('/representatives/{id}', [AdminController::class, 'destroyRepresentative'])
                ->name('representatives.destroy');
        });
});

/*
|--------------------------------------------------------------------------
| Warehouse owner API
|--------------------------------------------------------------------------
|
| Every endpoint in this group uses the warehouse owner's JWT and account
| access middleware. Ownership of individual records is enforced in services.
*/

Route::middleware(['auth:api', 'account.access'])->group(function (): void {
    Route::prefix('store')->group(function (): void {
        Route::get('/', [StoreController::class, 'index']);
        Route::post('/', [StoreController::class, 'store']);
        Route::get('/{id}/quantity-history', [StoreController::class, 'getQuantityHistory']);
        Route::put('/{id}', [StoreController::class, 'update']);
        Route::delete('/{id}', [StoreController::class, 'destroy']);
    });

    Route::prefix('customers')->group(function (): void {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
    });

    Route::prefix('inputs')->group(function (): void {
        Route::get('/', [AllInputController::class, 'index']);
        Route::post('/', [AllInputController::class, 'store']);
        Route::get('/{id}', [AllInputController::class, 'show']);
        Route::put('/{id}', [AllInputController::class, 'update']);
        Route::delete('/{id}', [AllInputController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function (): void {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/top-selling-products', [OrderController::class, 'topSellingProducts']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}', [OrderController::class, 'update']);
        Route::delete('/{id}', [OrderController::class, 'destroy']);
    });

    Route::prefix('adjustments')->group(function (): void {
        Route::get('/', [InventoryAdjustmentController::class, 'index']);
        Route::post('/', [InventoryAdjustmentController::class, 'store']);
        Route::get('/{id}', [InventoryAdjustmentController::class, 'show']);
        Route::put('/{id}', [InventoryAdjustmentController::class, 'update']);
        Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy']);
    });

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::prefix('representatives')->group(function (): void {
        Route::get('/', [RepresentativeController::class, 'index']);
        Route::post('/', [RepresentativeController::class, 'store']);
        Route::put('/{id}', [RepresentativeController::class, 'update']);
        Route::delete('/{id}', [RepresentativeController::class, 'destroy']);
    });

    Route::prefix('representative-invoices')->group(function (): void {
        Route::get('/', [RepresentativeInvoiceController::class, 'index']);
        Route::post('/{id}/approve', [RepresentativeInvoiceController::class, 'approve']);
        Route::post('/{id}/reject', [RepresentativeInvoiceController::class, 'reject']);
    });
});

/*
|--------------------------------------------------------------------------
| Representative API
|--------------------------------------------------------------------------
|
| These endpoints authenticate against the representatives provider only.
| Representatives can read their owner's inventory/customers and submit
| invoices, but cannot access warehouse-owner mutation endpoints.
*/

Route::prefix('representative')->group(function (): void {
    Route::post('/auth/login', [RepresentativeAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware(['auth:representative-api', 'representative.access'])->group(function (): void {
        Route::post('/auth/logout', [RepresentativeAuthController::class, 'logout']);
        Route::get('/auth/me', [RepresentativeAuthController::class, 'me']);

        Route::get('/inventory', [RepresentativePortalController::class, 'inventory']);
        Route::get('/customers', [RepresentativePortalController::class, 'customers']);
        Route::get('/invoices', [RepresentativePortalController::class, 'invoices']);
        Route::post('/invoices', [RepresentativePortalController::class, 'storeInvoice']);
    });
});
