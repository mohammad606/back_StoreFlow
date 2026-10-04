<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;


class AdminController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:admin,user'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
        ]);

        return ApiResponse::success($user, 'User created successfully');
    }

    public function updateAccess(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'string', 'in:free_trial,active,blocked'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date', 'after_or_equal:subscription_starts_at'],
        ]);

        $user = User::findOrFail($id);

        if ($request->status === 'active') {
            $user->status = 'active';
            $user->subscription_starts_at = $request->filled('subscription_starts_at')
                ? $request->subscription_starts_at
                : now();
            $user->subscription_ends_at = $request->filled('subscription_ends_at')
                ? $request->subscription_ends_at
                : now()->addDays(30);
            $user->trial_ends_at = null;
        } elseif ($request->status === 'free_trial') {
            $user->status = 'free_trial';
            $user->trial_ends_at = now()->addDays(7);
            $user->subscription_starts_at = null;
            $user->subscription_ends_at = null;
        } else {
            $user->status = 'blocked';
            $user->subscription_starts_at = null;
            $user->subscription_ends_at = null;
            $user->trial_ends_at = null;
        }

        $user->save();

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

        return ApiResponse::success($users->items(), 'Users retrieved successfully', $users);
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
