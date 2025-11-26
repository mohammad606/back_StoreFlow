<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Helpers\ApiResponse;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::where('user_id', auth()->id());
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }
        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $allowedSorts = ['id', 'name', 'phone'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortOrder);
        $perPage = $request->get('perPage', 20);
        $customers = $query->paginate($perPage);

        return ApiResponse::success($customers->items(), 'Customers retrieved successfully', $customers);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $customer = Customer::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return ApiResponse::success($customer, 'Customer created successfully');
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $customer->update($request->only('name', 'phone', 'address'));

        return ApiResponse::success($customer, 'Customer updated successfully');
    }

    public function destroy($id)
    {
        $customer = Customer::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
