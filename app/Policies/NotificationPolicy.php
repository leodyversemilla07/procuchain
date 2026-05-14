<?php

namespace App\Policies;

use App\Models\User;

/**
 * Notification policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-notifications', [NotificationPolicy::class, 'view'])
 * Gate::define('manage-notifications', [NotificationPolicy::class, 'manage'])
 *
 * Usage in controllers:
 * $this->authorize('view-notifications');
 */
class NotificationPolicy
{
    /**
     * Determine whether the user can view their notifications.
     * All authenticated users can view their own notifications.
     */
    public function view(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage (send) notifications.
     */
    public function manage(User $user): bool
    {
        return $user->can('send notifications');
    }
}
