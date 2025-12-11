<?php

namespace App\Policies;

use App\Models\User;

class ProcurementPolicy
{
    /**
     * Determine whether the user can view any procurements.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    /**
     * Determine whether the user can view the procurement.
     */
    public function view(User $user, $procurement): bool
    {
        return $user->can('view procurement');
    }

    /**
     * Determine whether the user can create procurements.
     */
    public function create(User $user): bool
    {
        return $user->can('create procurement');
    }

    /**
     * Determine whether the user can update the procurement.
     */
    public function update(User $user, $procurement): bool
    {
        return $user->can('edit procurement');
    }

    /**
     * Determine whether the user can delete the procurement.
     */
    public function delete(User $user, $procurement): bool
    {
        return $user->can('delete procurement');
    }

    /**
     * Determine whether the user can restore the procurement.
     */
    public function restore(User $user, $procurement): bool
    {
        return $user->can('edit procurement');
    }

    /**
     * Determine whether the user can permanently delete the procurement.
     */
    public function forceDelete(User $user, $procurement): bool
    {
        return $user->can('delete procurement');
    }

    /**
     * Determine whether the user can submit corrections for procurements.
     */
    public function submitCorrection(User $user, $procurement): bool
    {
        // Check if user has permission to manage procurements
        if (! $user->can('manage procurements')) {
            return false;
        }

        // Additional checks based on procurement stage or status
        // For now, allow corrections for all procurements
        // In the future, we might restrict based on procurement status
        return true;
    }

    /**
     * Determine whether the user can view correction history for procurements.
     */
    public function viewCorrectionHistory(User $user, $procurement): bool
    {
        return $user->can('view procurement');
    }

    /**
     * Determine whether the user can approve corrections for procurements.
     */
    public function approveCorrection(User $user, $procurement): bool
    {
        return $user->can('approve procurement corrections');
    }

    /**
     * Determine whether the user can reject corrections for procurements.
     */
    public function rejectCorrection(User $user, $procurement): bool
    {
        return $user->can('reject procurement corrections');
    }

    /**
     * Determine whether the user can manage procurement corrections.
     */
    public function manageCorrections(User $user): bool
    {
        return $user->can('manage procurement corrections');
    }
}
