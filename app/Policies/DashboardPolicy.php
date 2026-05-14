<?php

namespace App\Policies;

use App\Models\User;

/**
 * Dashboard policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-admin-dashboard', [DashboardPolicy::class, 'viewAdmin'])
 * Gate::define('view-bac-secretariat-dashboard', [DashboardPolicy::class, 'viewBacSecretariat'])
 * Gate::define('view-bac-chairman-dashboard', [DashboardPolicy::class, 'viewBacChairman'])
 * Gate::define('view-hope-dashboard', [DashboardPolicy::class, 'viewHope'])
 *
 * Usage in controllers:
 * $this->authorize('view-admin-dashboard');
 */
class DashboardPolicy
{
    /**
     * Determine whether the user can view the admin dashboard.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->can('view admin dashboard');
    }

    /**
     * Determine whether the user can view the BAC Secretariat dashboard.
     */
    public function viewBacSecretariat(User $user): bool
    {
        return $user->can('view bac-secretariat dashboard');
    }

    /**
     * Determine whether the user can view the BAC Chairman dashboard.
     */
    public function viewBacChairman(User $user): bool
    {
        return $user->can('view bac-chairman dashboard');
    }

    /**
     * Determine whether the user can view the HOPE dashboard.
     */
    public function viewHope(User $user): bool
    {
        return $user->can('view hope dashboard');
    }
}
