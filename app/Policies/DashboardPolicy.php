<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class DashboardPolicy
{
    public function viewAdmin(User $user): bool
    {
        return $user->can(Permission::VIEW_ADMIN_DASHBOARD->value);
    }

    public function viewBacSecretariat(User $user): bool
    {
        return $user->can(Permission::VIEW_BAC_SECRETARIAT_DASHBOARD->value);
    }

    public function viewBacChairman(User $user): bool
    {
        return $user->can(Permission::VIEW_BAC_CHAIRMAN_DASHBOARD->value);
    }

    public function viewHope(User $user): bool
    {
        return $user->can(Permission::VIEW_HOPE_DASHBOARD->value);
    }
}
