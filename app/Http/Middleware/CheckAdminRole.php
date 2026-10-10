<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || $user->role !== UserRoleEnum::Admin) {
            return response()->json([
                'status' => false,
                'message' => "don't have admin access"
            ], 403);
        }

        return $next($request);
    }
}
