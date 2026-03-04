<?php

namespace App\Policies;

use App\Models\User;

/**
 * Document policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 *   Gate::define('view-document',     [DocumentPolicy::class, 'view'])
 *   Gate::define('download-document', [DocumentPolicy::class, 'download'])
 *   Gate::define('upload-document',   [DocumentPolicy::class, 'upload'])
 *   Gate::define('correct-document',  [DocumentPolicy::class, 'correct'])
 *
 * Usage in controllers:
 *   $this->authorize('download-document');
 */
class DocumentPolicy
{
    /**
     * Determine whether the user can view documents (PDFs, file previews).
     */
    public function view(User $user): bool
    {
        return $user->hasPermissionTo('view documents');
    }

    /**
     * Determine whether the user can download procurement documents.
     */
    public function download(User $user): bool
    {
        return $user->hasPermissionTo('download documents');
    }

    /**
     * Determine whether the user can upload documents during a procurement stage.
     */
    public function upload(User $user): bool
    {
        return $user->hasPermissionTo('upload documents');
    }

    /**
     * Determine whether the user can submit a document correction.
     * Allowed for Admin, BAC Chairman, and BAC Secretariat.
     */
    public function correct(User $user): bool
    {
        return $user->hasAnyPermission([
            'edit procurement',
            'approve procurement',
        ]);
    }
}
