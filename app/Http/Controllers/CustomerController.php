<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Http\Requests\IndexCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Helpers\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {
    }

    public function index(IndexCustomerRequest $request)
    {
        try {
            $filters = array_merge([
                'sort_by' => 'name',
                'sort_order' => 'asc',
                'per_page' => 20,
            ], $request->validated());

            $customers = $this->customerService->getAll($filters);

            return ApiResponse::success($customers->items(), 'Customers retrieved successfully', $customers);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function store(StoreCustomerRequest $request)
    {
        try {
            $customer = $this->customerService->create($request->validated());

            return ApiResponse::success($customer, 'Customer created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function update(UpdateCustomerRequest $request, $id)
    {
        try {
            $customer = $this->customerService->update($id, $request->validated());

            return ApiResponse::success($customer, 'Customer updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->customerService->delete($id);

            return ApiResponse::success([], 'Customer deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
