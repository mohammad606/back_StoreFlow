<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Helpers\ApiResponse;

class StoreController extends Controller
{

    public function index(Request $request)
    {
        $query = Store::where('user_id', auth()->id());
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }
        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $allowedSorts = ['id','name', 'quantity'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 20);
        $products = $query->paginate($perPage);

        return ApiResponse::success($products->items(), 'Products retrieved successfully', $products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'quantity' => 'required|integer|min:0',
            'box' => 'required|integer|min:1',
        ]);

        $product = Store::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'quantity' => $request->quantity,
            'box' => $request->box,
        ]);

        return ApiResponse::success($product,'Success');
    }
    public function update(Request $request, $id)
    {
        $product = Store::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'quantity' => 'sometimes|integer|min:0',
            'box' => 'sometimes|integer|min:1',
        ]);

        $product->update($request->only('name', 'quantity', 'box'));

        return ApiResponse::success($product, 'Product updated successfully');
    }
    public function destroy($id)
    {
        $product = Store::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
