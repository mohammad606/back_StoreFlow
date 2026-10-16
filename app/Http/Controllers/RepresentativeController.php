<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\Representative\StoreRepresentativeRequest;
use App\Http\Requests\Representative\UpdateRepresentativeRequest;
use App\Services\RepresentativeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RepresentativeController extends Controller
{
    public function __construct(
        protected RepresentativeService $representativeService
    ) {}

    public function index()
    {
        return ApiResponse::success(
            $this->representativeService->getRepresentatives(),
            'Representatives retrieved successfully'
        );
    }

    public function store(StoreRepresentativeRequest $request)
    {
        try {
            $representative = $this->representativeService->create($request->validated());

            return ApiResponse::success($representative, 'Representative created successfully');
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), 403);
        }
    }

    public function update(UpdateRepresentativeRequest $request, int $id)
    {
        try {
            $representative = $this->representativeService->update($id, $request->validated());

            return ApiResponse::success($representative, 'Representative updated successfully');
        } catch (ModelNotFoundException) {
            return ApiResponse::error('Representative not found', 404);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->representativeService->delete($id);

            return ApiResponse::success([], 'Representative deleted successfully');
        } catch (ModelNotFoundException) {
            return ApiResponse::error('Representative not found', 404);
        }
    }
}
