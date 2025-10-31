<?php

namespace App\Policies;

use App\Models\ProcurementDocument;
use App\Models\User;

class ProcurementDocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view documents
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProcurementDocument $procurementDocument): bool
    {
        // All authenticated users can view individual documents
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only BAC Secretariat can upload documents
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('upload documents');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcurementDocument $procurementDocument): bool
    {
        // Documents cannot be updated directly - only corrected via blockchain
        // This is to maintain immutability
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcurementDocument $procurementDocument): bool
    {
        // Only admins can delete documents (should be rare due to blockchain immutability)
        return $user->isAdmin() || $user->hasPermissionTo('delete documents');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProcurementDocument $procurementDocument): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProcurementDocument $procurementDocument): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, ProcurementDocument $procurementDocument): bool
    {
        // All authenticated users can download documents
        return true;
    }

    /**
     * Determine whether the user can correct/replace a document.
     */
    public function correct(User $user, ProcurementDocument $procurementDocument): bool
    {
        // Only admins, BAC Chairman, and BAC Secretariat can correct documents
        return $user->hasAnyRole(['admin', 'bac_chairman', 'bac_secretariat']);
    }

    /**
     * Determine whether the user can view correction history.
     */
    public function viewCorrectionHistory(User $user, ProcurementDocument $procurementDocument): bool
    {
        // All authenticated users can view correction history for transparency
        return true;
    }

    /**
     * Determine whether the user can publish document to blockchain.
     */
    public function publish(User $user, ProcurementDocument $procurementDocument): bool
    {
        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('publish to blockchain');
    }

    /**
     * Determine whether the user can retry failed blockchain publication.
     */
    public function retryPublication(User $user, ProcurementDocument $procurementDocument): bool
    {
        // Only retry if document publication failed
        if ($procurementDocument->blockchain_status !== 'failed') {
            return false;
        }

        return $user->hasRole('bac_secretariat') || $user->hasPermissionTo('publish to blockchain');
    }
}
