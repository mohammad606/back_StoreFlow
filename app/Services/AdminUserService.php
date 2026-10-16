<?php

namespace App\Services;

use App\Models\User;

class AdminUserService
{
    public function updatePassword(int $userId, string $password): User
    {
        $user = User::findOrFail($userId);
        $user->password = $password;
        $user->save();

        return $user;
    }
}
