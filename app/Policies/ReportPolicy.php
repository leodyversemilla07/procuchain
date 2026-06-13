<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user): bool
    {
        return $user->can(Permission::VIEW_PROCUREMENT->value);
    }

    public function generate(User $user): bool
    {
        return $user->can(Permission::MANAGE_PROCUREMENTS->value);
    }

    public function export(User $user): bool
    {
        return $user->canAny([
            Permission::MANAGE_PROCUREMENTS->value,
            Permission::DOWNLOAD_DOCUMENTS->value,
        ]);
    }
}
