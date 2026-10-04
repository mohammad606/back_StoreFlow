<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'device_id' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'status' => 'free_trial',
            'device_id' => $request->device_id,
            'trial_ends_at' => now()->addDays(7),
            'role' => 'user',
        ]);

        $token = Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
        ]);

        if (!$token) {
            return ApiResponse::error('Unable to create session for this user', 500);
        }

        return ApiResponse::success([
            'user' => $user,
            'auth' => [
                'token' => $token,
                'type' => 'bearer',
            ],
            'status' => $user->status,
            'trial_ends_at' => $user->trial_ends_at,
        ], 'Trial account created successfully');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return ApiResponse::error('Unauthorized', 401);
        }

        return ApiResponse::success([
            'user' => Auth::user(),
            'auth' => [
                'token' => $token,
                'type' => 'bearer'
            ]
        ], 'Login successful');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function logout()
    {
        Auth::logout();
        return ApiResponse::success([], 'Successfully logged out');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function refresh()
    {
        return ApiResponse::success([
            'user' => Auth::user(),
            'auth' => [
                'token' => Auth::refresh(),
                'type' => 'bearer'
            ]
        ], 'Token refreshed');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function me()
    {
        return ApiResponse::success(Auth::user(), 'Current user');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $user = Auth::user();
            $user->name = $request->name;
            $user->save();

            return ApiResponse::success([
                'user' => $user
            ], 'User name updated successfully');

        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
