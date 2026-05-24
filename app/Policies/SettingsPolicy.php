<?php

namespace App\Policies;

use App\Models\User;

/**
 * Settings policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-settings', [SettingsPolicy::class, 'view'])
 * Gate::define('manage-settings', [SettingsPolicy::class, 'manage'])
 * Gate::define('manage-workflow-config', [SettingsPolicy::class, 'manageWorkflowConfig'])
 * Gate::define('manage-stage-document-config', [SettingsPolicy::class, 'manageStageDocumentConfig'])
 * Gate::define('manage-user-invitations', [SettingsPolicy::class, 'manageUserInvitations'])
 * Gate::define('view-workflow', [SettingsPolicy::class, 'viewWorkflow'])
 * Gate::define('manage-recoverable-data', [SettingsPolicy::class, 'manageRecoverableData'])
 *
 * Usage in controllers:
 * $this->authorize('manage-settings');
 */
class SettingsPolicy
{
    /**
     * Determine whether the user can view settings pages.
     * All authenticated users can view their own profile settings.
     * Role-specific settings require the 'view settings' permission.
     */
    public function view(User $user): bool
    {
        return $user->can('view settings');
    }

    /**
     * Determine whether the user can manage application-wide settings.
     */
    public function manage(User $user): bool
    {
        return $user->can('manage settings');
    }

    /**
     * Determine whether the user can manage procurement workflow configuration.
     */
    public function manageWorkflowConfig(User $user): bool
    {
        return $user->can('manage settings');
    }

    /**
     * Determine whether the user can manage stage document configuration.
     */
    public function manageStageDocumentConfig(User $user): bool
    {
        return $user->can('manage settings');
    }

    /**
     * Determine whether the user can manage user invitations.
     */
    public function manageUserInvitations(User $user): bool
    {
        return $user->can('create users');
    }

    /**
     * Determine whether the user can view the workflow page.
     * All authenticated users can view workflow definitions.
     */
    public function viewWorkflow(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage recoverable data.
     */
    public function manageRecoverableData(User $user): bool
    {
        return $user->can('manage settings');
    }
}
