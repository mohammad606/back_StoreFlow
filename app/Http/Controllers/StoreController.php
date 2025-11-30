<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use App\Models\User;

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
        $allowedSorts = ['id', 'name', 'quantity'];
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


        StoreQuantityHistory::create([
            'store_id' => $product->id,
            'quantity' => $request->quantity,
            'change_amount' => $request->quantity,
            'change_type' => 'initial',
            'user_id' => auth()->id(),
            'notes' => 'Initial quantity',
        ]);

        return ApiResponse::success($product, 'Success');
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

        $oldQuantity = $product->quantity;

        $product->update($request->only('name', 'quantity', 'box'));
        if ($request->has('quantity') && $request->quantity != $oldQuantity) {
            $changeAmount = $request->quantity - $oldQuantity;
            StoreQuantityHistory::create([
                'store_id' => $product->id,
                'quantity' => $request->quantity,
                'change_amount' => $changeAmount,
                'change_type' => 'manual_update',
                'user_id' => auth()->id(),
                'notes' => 'Manual quantity update',
            ]);
        }

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

    public function getQuantityHistory(Request $request, $id)
    {
        $product = Store::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $startDate = $request->get('start_date', now()->subDays(10)->format('Y-m-d'));


        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:' . $endDate,
            'end_date' => 'nullable|date|after_or_equal:' . $startDate,
        ]);

        $history = StoreQuantityHistory::where('store_id', $id)
            ->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->orderBy('created_at', 'desc')
            ->with('user:id,name')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->created_at->format('Y-m-d'),
                    'time' => $record->created_at->format('H:i:s'),
                    'quantity' => $record->quantity,
                    'change_amount' => $record->change_amount,
                    'change_type' => $record->change_type,
                    'reference_type' => $record->reference_type,
                    'reference_id' => $record->reference_id,
                    'user' => $record->user ? $record->user->name : null,
                    'notes' => $record->notes,
                ];
            });

        return ApiResponse::success([
            'item' => [
                'id' => $product->id,
                'name' => $product->name,
                'current_quantity' => $product->quantity,
                'box' => $product->box,
            ],
            'history' => $history,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], 'Quantity history retrieved successfully');
    }
}
