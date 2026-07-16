<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class SettingsPolicy
{
    public function view(User $user): bool
    {
        return $user->can(Permission::VIEW_SETTINGS->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::MANAGE_SETTINGS->value);
    }

    public function manageWorkflowConfig(User $user): bool
    {
        return $user->can(Permission::MANAGE_SETTINGS->value);
    }

    public function manageStageDocumentConfig(User $user): bool
    {
        return $user->can(Permission::MANAGE_SETTINGS->value);
    }

    public function manageUserInvitations(User $user): bool
    {
        return $user->can(Permission::CREATE_USERS->value);
    }

    public function viewWorkflow(?User $user = null): bool
    {
        return true;
    }
}
