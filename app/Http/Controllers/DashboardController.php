<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboardService->getSummary(),
            'Dashboard summary retrieved successfully'
        );
    }
}
