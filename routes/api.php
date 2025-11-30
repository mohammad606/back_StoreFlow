<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AllInputController;
use App\Http\Controllers\OrderController;

Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    //---------------------------------------------------------------------------------------------------------------
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/update-name', [AuthController::class, 'updateName'])->name('auth.updateName');

    });
    //---------------------------------------------------------------------------------------------------------------
    Route::middleware(['auth:api', 'admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::post('/users', [AdminController::class, 'store'])->name('users.store');
            Route::get('/users', [AdminController::class, 'index'])->name('users.index');
            Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('users.destroy');

        });

});

//---------------------------------------------------------------------------------------------------------------

Route::middleware('auth:api')->prefix('store')->group(function () {
    Route::get('/', [StoreController::class, 'index']);
    Route::post('/', [StoreController::class, 'store']);
    Route::post('/{id}', [StoreController::class, 'update']);
    Route::delete('/{id}', [StoreController::class, 'destroy']);

});

//---------------------------------------------------------------------------------------------------------------

Route::middleware('auth:api')->prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::post('/{id}', [CustomerController::class, 'update']);
    Route::delete('/{id}', [CustomerController::class, 'destroy']);

});



Route::middleware('auth:api')->prefix('inputs')->group(function () {
    Route::get('/', [AllInputController::class, 'index']);
    Route::post('/', [AllInputController::class, 'store']);
    Route::get('{id}', [AllInputController::class, 'getInvoiceById']);
    Route::post('{id}', [AllInputController::class, 'update']);
    Route::delete('{id}', [AllInputController::class, 'destroy']);
});

Route::middleware('auth:api')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('{id}', [OrderController::class, 'show']);
    Route::post('{id}', [OrderController::class, 'update']);
    Route::delete('{id}', [OrderController::class, 'destroy']);
});
