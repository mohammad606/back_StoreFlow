<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Services\OrderService;
use App\Helpers\ApiResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'sortBy' => $request->get('sortBy', 'date'),
                'sortOrder' => $request->get('sortOrder', 'desc'),
                'perPage' => $request->get('perPage', 20),
            ];

            $orders = $this->orderService->getAllOrders($filters);

            return ApiResponse::success($orders->items(), 'Orders retrieved successfully', $orders);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $order = $this->orderService->getOrderById($id);

            return ApiResponse::success($order, 'Order retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Order not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            return ApiResponse::success($order, 'Order created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function update(UpdateOrderRequest $request, $id)
    {
        try {
            $order = $this->orderService->updateOrder($id, $request->validated());

            return ApiResponse::success($order, 'Order updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Order not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->orderService->deleteOrder($id);

            return ApiResponse::success([], 'Order deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Order not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
