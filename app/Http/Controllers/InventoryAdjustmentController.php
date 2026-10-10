<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\IndexInventoryAdjustmentsRequest;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\UpdateInventoryAdjustmentRequest;
use App\Services\InventoryAdjustmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryAdjustmentService $inventoryAdjustmentService
    ) {}

    public function index(IndexInventoryAdjustmentsRequest $request)
    {
        $adjustments = $this->inventoryAdjustmentService->getAll($request->validated());

        return ApiResponse::success(
            $adjustments->items(),
            'Inventory adjustments retrieved successfully',
            $adjustments
        );
    }

    public function store(StoreInventoryAdjustmentRequest $request)
    {
        $adjustment = $this->inventoryAdjustmentService->create($request->validated());

        return ApiResponse::success($adjustment, 'Inventory adjustment created successfully');
    }

    public function show(int $id)
    {
        try {
            $adjustment = $this->inventoryAdjustmentService->getById($id);

            return ApiResponse::success($adjustment, 'Inventory adjustment retrieved successfully');
        } catch (ModelNotFoundException $exception) {
            return ApiResponse::error('Inventory adjustment not found', 404);
        }
    }

    public function update(UpdateInventoryAdjustmentRequest $request, int $id)
    {
        try {
            $adjustment = $this->inventoryAdjustmentService->update($id, $request->validated());

            return ApiResponse::success($adjustment, 'Inventory adjustment updated successfully');
        } catch (ModelNotFoundException $exception) {
            return ApiResponse::error('Inventory adjustment not found', 404);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->inventoryAdjustmentService->delete($id);

            return ApiResponse::success([], 'Inventory adjustment deleted successfully');
        } catch (ModelNotFoundException $exception) {
            return ApiResponse::error('Inventory adjustment not found', 404);
        }
    }
}
