<?php

use Illuminate\Support\Facades\Route;



Route::post('/register',[\App\Http\Controllers\authController::class,'register']);
