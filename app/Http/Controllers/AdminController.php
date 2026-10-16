<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\User\UpdateUserAccessRequest;
use App\Http\Requests\IndexAdminUsersRequest;
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
            'role' => $data['role'] ?? UserRoleEnum::User,
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
    public function index(IndexAdminUsersRequest $request)
    {
        $filters = array_merge([
            'sort_by' => 'id',
            'sort_order' => 'desc',
            'per_page' => 10,
        ], $request->validated());
        $filters['sort_by'] ??= 'id';
        $filters['sort_order'] ??= 'desc';
        $filters['per_page'] ??= 10;

        $query = User::query();
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }
        $query->orderBy($filters['sort_by'], $filters['sort_order']);

        $users = $query->paginate($filters['per_page']);

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
