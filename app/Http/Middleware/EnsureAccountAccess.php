<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if ($user->role !== 'admin' && !$user->hasValidAccessPeriod()) {
            return ApiResponse::error('Account access is blocked or expired.', 403, [
                'status' => $user->evaluateStatus(),
            ]);
        }

        return $next($request);
    }
}
