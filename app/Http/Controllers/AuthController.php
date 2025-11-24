<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{

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
