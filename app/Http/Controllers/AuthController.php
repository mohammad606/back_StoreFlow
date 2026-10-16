<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Helpers\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateNameRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'status' => UserStatusEnum::FreeTrial,
            'device_id' => $data['device_id'],
            'trial_ends_at' => now()->addDays(7),
            'role' => UserRoleEnum::User,
        ]);

        $guard = Auth::guard('api');
        $token = $guard->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
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

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $guard = Auth::guard('api');
        if (!$token = $guard->attempt($credentials)) {
            return ApiResponse::error('Unauthorized', 401);
        }

        $user = $guard->user();

        if ($user->role !== UserRoleEnum::Admin && !$user->hasValidAccessPeriod()) {
            $status = $user->evaluateStatus();
            $guard->logout();

            return ApiResponse::error('Account access is blocked or expired.', 403, [
                'status' => $status,
            ]);
        }

        return ApiResponse::success([
            'user' => $user,
            'auth' => [
                'token' => $token,
                'type' => 'bearer'
            ]
        ], 'Login successful');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function logout()
    {
        Auth::guard('api')->logout();
        return ApiResponse::success([], 'Successfully logged out');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function refresh()
    {
        return ApiResponse::success([
            'user' => Auth::guard('api')->user(),
            'auth' => [
                'token' => Auth::guard('api')->refresh(),
                'type' => 'bearer'
            ]
        ], 'Token refreshed');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function me()
    {
        $user = Auth::guard('api')->user()->load('representatives');

        return ApiResponse::success($user, 'Current user');
    }
    //---------------------------------------------------------------------------------------------------------------
    public function updateName(UpdateNameRequest $request)
    {
        try {
            $data = $request->validated();
            $user = Auth::guard('api')->user();
            $user->name = $data['name'];
            $user->save();

            return ApiResponse::success([
                'user' => $user
            ], 'User name updated successfully');

        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
