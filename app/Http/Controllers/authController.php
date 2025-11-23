<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user' => $user,
                    'token' => [
                        'access_token' => $token,
                        'type' => 'bearer'
                    ]
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => Auth::user(),
                'token' => [
                    'access_token' => $token,
                    'type' => 'bearer'
                ]
            ]
        ]);
    }

    public function logout()
    {
        Auth::logout();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => true,
            'message' => 'Token refreshed',
            'data' => [
                'user' => Auth::user(),
                'token' => [
                    'access_token' => Auth::refresh(),
                    'type' => 'bearer'
                ]
            ]
        ]);
    }

    public function me()
    {
        return response()->json([
            'status' => true,
            'data' => Auth::user()
        ]);
    }
}
