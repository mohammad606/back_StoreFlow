<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Helpers\ApiResponse;

class StoreController extends Controller
{


    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Store::where('user_id', $user->id);
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

}
