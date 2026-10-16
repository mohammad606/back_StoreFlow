<?php

namespace App\Http\Controllers;

use App\Services\StoreService;
use App\Http\Requests\IndexStoreRequest;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Requests\Store\QuantityHistoryRequest;
use App\Helpers\ApiResponse;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {
    }

    public function index(IndexStoreRequest $request)
    {
        try {
            $filters = array_merge([
                'sort_by' => 'name',
                'sort_order' => 'asc',
                'per_page' => 20,
            ], $request->validated());

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

    public function getQuantityHistory(QuantityHistoryRequest $request, $id)
    {
        try {
            $data = $this->storeService->getQuantityHistory(
                $id,
                $request->validated('start_date'),
                $request->validated('end_date')
            );

            return ApiResponse::success($data, 'Quantity history retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Product not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
