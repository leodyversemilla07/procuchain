<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class LoginLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MANAGE_USERS->value);
    }

    public function manageBlockedIps(User $user): bool
    {
        return $user->can(Permission::MANAGE_USERS->value);
    }
}
