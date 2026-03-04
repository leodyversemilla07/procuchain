<?php

namespace App\Policies;

use App\Models\User;

/**
 * Procurement policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 *   Gate::define('view-procurement',    [ProcurementPolicy::class, 'view'])
 *   Gate::define('archive-procurement', [ProcurementPolicy::class, 'archive'])
 *   Gate::define('restore-procurement', [ProcurementPolicy::class, 'restore'])
 *   Gate::define('correct-procurement', [ProcurementPolicy::class, 'correct'])
 *   Gate::define('approve-procurement', [ProcurementPolicy::class, 'approve'])
 *   Gate::define('publish-procurement', [ProcurementPolicy::class, 'publish'])
 *
 * Usage in controllers:
 *   $this->authorize('archive-procurement');
 */
class ProcurementPolicy
{
    /**
     * Determine whether the user can view procurement records.
     */
    public function view(User $user): bool
    {
        return $user->hasPermissionTo('view procurement');
    }

    /**
     * Determine whether the user can initiate / create a procurement.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create procurement');
    }

    /**
     * Determine whether the user can archive a completed procurement.
     * Restricted to BAC Secretariat and Admin only.
     */
    public function archive(User $user): bool
    {
        return $user->hasPermissionTo('manage procurements');
    }

    /**
     * Determine whether the user can restore an archived procurement.
     * Restricted to BAC Secretariat and Admin only.
     */
    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('manage procurements');
    }

    /**
     * Determine whether the user can correct procurement metadata on the blockchain.
     */
    public function correct(User $user): bool
    {
        return $user->hasPermissionTo('edit procurement');
    }

    /**
     * Determine whether the user can approve a procurement or stage transition.
     */
    public function approve(User $user): bool
    {
        return $user->hasPermissionTo('approve procurement');
    }

    /**
     * Determine whether the user can publish procurement data to the blockchain.
     */
    public function publish(User $user): bool
    {
        return $user->hasPermissionTo('publish to blockchain');
    }
}
