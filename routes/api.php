<?php

use Illuminate\Support\Facades\Route;



Route::post('/register',[\App\Http\Controllers\authController::class,'register']);
Route::post('/login',[\App\Http\Controllers\authController::class,'login']);
Route::post('/logout',[\App\Http\Controllers\authController::class,'logout'])->middleware(['auth:api']);

