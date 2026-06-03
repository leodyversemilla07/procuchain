<?php

namespace App\Policies;

use App\Models\User;

/**
 * Audit log policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-audit-log', [AuditLogPolicy::class, 'viewAny'])
 * Gate::define('update-audit-log', [AuditLogPolicy::class, 'update'])
 *
 * Usage in controllers:
 * $this->authorize('view-audit-log');
 * $this->authorize('update-audit-log');
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

 /**
 * Determine whether the user can update/repair audit log entries.
 * Only users who can manage users (admins) may trigger repairs.
 */
 public function update(User $user): bool
 {
 return $user->can('manage users');
 }
}
