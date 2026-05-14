<?php

namespace App\Policies;

use App\Models\User;

/**
 * Audit log policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-audit-log', [AuditLogPolicy::class, 'viewAny'])
 *
 * Usage in controllers:
 * $this->authorize('view-audit-log');
 */
class AuditLogPolicy
{
    /**
     * Determine whether the user can view audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }
}
