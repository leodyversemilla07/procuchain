<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class NotificationPolicy
{
    public function view(User $user): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::SEND_NOTIFICATIONS->value);
    }
}
