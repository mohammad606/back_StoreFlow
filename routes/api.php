<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

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


