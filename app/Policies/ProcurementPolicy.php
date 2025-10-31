<?php

namespace App\Policies;

use App\Models\Procurement;
use App\Models\User;

class ProcurementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view procurement list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Procurement $procurement): bool
    {
        // All authenticated users can view individual procurements
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only BAC Secretariat can initiate procurement
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('create procurement');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Procurement $procurement): bool
    {
        // Only BAC Secretariat can update procurement
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('edit procurement');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Procurement $procurement): bool
    {
        // Only admins can delete procurement records
        return $user->isAdmin() || $user->hasPermissionTo('delete procurement');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Procurement $procurement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Procurement $procurement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can publish procurement to blockchain.
     */
    public function publish(User $user, Procurement $procurement): bool
    {
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('publish to blockchain');
    }

    /**
     * Determine whether the user can manage procurement stages.
     */
    public function manageStages(User $user, Procurement $procurement): bool
    {
        return $user->hasRole('bac_secretariat') || $user->canManageStages();
    }

    /**
     * Determine whether the user can approve procurement.
     */
    public function approve(User $user, Procurement $procurement): bool
    {
        return $user->hasAnyRole(['bac_chairman', 'hope']) || $user->canApproveProcurement();
    }

    /**
     * Determine whether the user can view blockchain transactions.
     */
    public function viewBlockchain(User $user, Procurement $procurement): bool
    {
        // All authenticated users can view blockchain data
        return true;
    }

    /**
     * Determine whether the user can retry failed blockchain publications.
     */
    public function retryBlockchainPublication(User $user, Procurement $procurement): bool
    {
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('publish to blockchain');
    }
}
