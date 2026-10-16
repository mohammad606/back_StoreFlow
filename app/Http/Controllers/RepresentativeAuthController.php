<?php

namespace App\Http\Controllers;

use App\Enums\UserStatusEnum;
use App\Helpers\ApiResponse;
use App\Http\Requests\Representative\LoginRepresentativeRequest;
use Illuminate\Support\Facades\Auth;

class RepresentativeAuthController extends Controller
{
    public function login(LoginRepresentativeRequest $request)
    {
        $credentials = $request->validated();
        $guard = Auth::guard('representative-api');

        $token = $guard->attempt([
            'user_id' => $credentials['warehouse_user_id'],
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        if (! $token) {
            return ApiResponse::error('Invalid representative credentials.', 401);
        }

        $representative = $guard->user()->load('user:id,name,email,status,subscription_starts_at,subscription_ends_at');
        $owner = $representative->user;

        if ($owner->status !== UserStatusEnum::Active || ! $owner->hasValidAccessPeriod()) {
            $guard->logout();

            return ApiResponse::error('The warehouse account is not active.', 403);
        }

        return ApiResponse::success([
            'representative' => $representative,
            'auth' => [
                'token' => $token,
                'type' => 'bearer',
            ],
        ], 'Representative login successful');
    }

    public function me()
    {
        return ApiResponse::success(
            Auth::guard('representative-api')->user()->load('user:id,name,email'),
            'Current representative'
        );
    }

    public function logout()
    {
        Auth::guard('representative-api')->logout();

        return ApiResponse::success([], 'Successfully logged out');
    }
}
