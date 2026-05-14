<?php

namespace App\Policies;

use App\Models\User;

/**
 * Report policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-reports', [ReportPolicy::class, 'view'])
 * Gate::define('generate-reports', [ReportPolicy::class, 'generate'])
 * Gate::define('export-reports', [ReportPolicy::class, 'export'])
 *
 * Usage in controllers:
 * $this->authorize('view-reports');
 */
class ReportPolicy
{
    /**
     * Determine whether the user can view reports.
     * Users with procurement view access can view reports.
     */
    public function view(User $user): bool
    {
        return $user->can('view procurement');
    }

    /**
     * Determine whether the user can generate reports.
     * Users who can manage procurements can generate reports.
     */
    public function generate(User $user): bool
    {
        return $user->can('manage procurements');
    }

    /**
     * Determine whether the user can export reports.
     * Users who can manage procurements or download documents can export reports.
     */
    public function export(User $user): bool
    {
        return $user->canAny(['manage procurements', 'download documents']);
    }
}
