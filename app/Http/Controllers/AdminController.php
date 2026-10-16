<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Helpers\ApiResponse;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Http\Requests\Admin\UpdateUserRepresentativesLimitRequest;
use App\Http\Requests\IndexAdminRepresentativesRequest;
use App\Http\Requests\IndexAdminUsersRequest;
use App\Http\Requests\User\UpdateUserAccessRequest;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct(
        protected AdminUserService $adminUserService
    ) {}

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

    public function updatePassword(UpdateUserPasswordRequest $request, int $id)
    {
        $user = $this->adminUserService->updatePassword(
            $id,
            $request->validated('password')
        );

        return ApiResponse::success(
            $user,
            'User password updated successfully'
        );
    }

    public function updateRepresentativesLimit(UpdateUserRepresentativesLimitRequest $request, int $id)
    {
        $user = $this->adminUserService->updateRepresentativesLimit(
            $id,
            $request->validated('representatives_limit')
        );

        return ApiResponse::success($user, 'User representative limit updated successfully');
    }

    public function destroyRepresentative(int $id)
    {
        try {
            $this->adminUserService->deleteRepresentative($id);

            return ApiResponse::success([], 'Representative deleted successfully');
        } catch (ModelNotFoundException) {
            return ApiResponse::error('Representative not found', 404);
        }
    }

    public function indexRepresentatives(IndexAdminRepresentativesRequest $request)
    {
        $filters = array_merge([
            'sort_by' => 'id',
            'sort_order' => 'desc',
            'per_page' => 20,
        ], $request->validated());

        $representatives = $this->adminUserService->getAllRepresentatives($filters);

        return ApiResponse::success(
            $representatives,
            'Representatives retrieved successfully'
        );
    }

    // ---------------------------------------------------------------------------------------------------------------
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

    // ---------------------------------------------------------------------------------------------------------------
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
