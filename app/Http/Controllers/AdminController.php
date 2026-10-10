<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\User\UpdateUserAccessRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;


class AdminController extends Controller
{

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'user',
        ]);

        return ApiResponse::success($user, 'User created successfully');
    }

    public function updateAccess(UpdateUserAccessRequest $request, $id)
    {
        $user = User::findOrFail($id);

        $user->update($request->validated());

        return ApiResponse::success($user, 'User access updated successfully');
    }

    //---------------------------------------------------------------------------------------------------------------
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }
        $sortBy = $request->get('sortBy', 'id');
        $sortOrder = $request->get('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $users = $query->paginate($perPage);

        return ApiResponse::success($users, 'Users retrieved successfully');
    }

    //---------------------------------------------------------------------------------------------------------------
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if (auth('api')->id() === $user->id) {
            return ApiResponse::error('You cannot delete your own account', 403);
        }

        $user->delete();

        return ApiResponse::success([], 'User deleted successfully');
    }

}
