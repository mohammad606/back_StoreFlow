<?php

namespace App\Http\Middleware;

use App\Enums\UserStatusEnum;
use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRepresentativeOwnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $representative = Auth::guard('representative-api')->user();
        $owner = $representative?->user;

        if ($owner === null
            || $owner->status !== UserStatusEnum::Active
            || ! $owner->hasValidAccessPeriod()) {
            return ApiResponse::error('The warehouse account is not active.', 403);
        }

        return $next($request);
    }
}
