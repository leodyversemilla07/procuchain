<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MANAGE_USERS->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::MANAGE_USERS->value);
    }
}
