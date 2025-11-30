<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StoreService;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Helpers\ApiResponse;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'sortBy' => $request->get('sortBy', 'name'),
                'sortOrder' => $request->get('sortOrder', 'asc'),
                'perPage' => $request->get('perPage', 20),
            ];

            $allowedSorts = ['id', 'name', 'quantity'];
            if (!in_array($filters['sortBy'], $allowedSorts)) {
                $filters['sortBy'] = 'name';
            }

            $products = $this->storeService->getAll($filters);

            return ApiResponse::success($products->items(), 'Products retrieved successfully', $products);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function store(StoreStoreRequest $request)
    {
        try {
            $product = $this->storeService->create($request->validated());

            return ApiResponse::success($product, 'Product created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function update(UpdateStoreRequest $request, $id)
    {
        try {
            $product = $this->storeService->update($id, $request->validated());

            return ApiResponse::success($product, 'Product updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Product not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->storeService->delete($id);

            return ApiResponse::success([], 'Product deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Product not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function getQuantityHistory(Request $request, $id)
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $data = $this->storeService->getQuantityHistory(
                $id,
                $request->get('start_date'),
                $request->get('end_date')
            );

            return ApiResponse::success($data, 'Quantity history retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Product not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
