<?php

namespace App\Policies;

use App\Models\User;

/**
 * Login log policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-login-logs', [LoginLogPolicy::class, 'viewAny'])
 * Gate::define('manage-blocked-ips', [LoginLogPolicy::class, 'manageBlockedIps'])
 *
 * Usage in controllers:
 * $this->authorize('view-login-logs');
 */
class LoginLogPolicy
{
    /**
     * Determine whether the user can view login logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    /**
     * Determine whether the user can manage blocked IPs.
     */
    public function manageBlockedIps(User $user): bool
    {
        return $user->can('manage users');
    }
}
