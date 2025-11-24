<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:6'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $token = Auth::login($user);

            return ApiResponse::success([
                'user' => $user,
                'auth' => [
                    'token' => $token,
                    'type' => 'bearer'
                ]
            ], 'User created successfully');

        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
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

    public function logout()
    {
        Auth::logout();
        return ApiResponse::success([], 'Successfully logged out');
    }

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

    public function me()
    {
        return ApiResponse::success(Auth::user(), 'Current user');
    }

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
