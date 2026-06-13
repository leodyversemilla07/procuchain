<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_USERS->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermissionTo(Permission::MANAGE_USERS->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CREATE_USERS->value);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermissionTo(Permission::EDIT_USERS->value);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if (! $user->hasPermissionTo(Permission::DELETE_USERS->value)) {
            return false;
        }

        if ($model->hasRole(UserRole::ADMIN->value)) {
            $adminCount = User::whereHas('roles', fn ($q) => $q->where('name', UserRole::ADMIN->value))->count();
            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::DELETE_USERS->value);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_USERS->value);
    }

    public function forceDelete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if (! $user->hasPermissionTo(Permission::DELETE_USERS->value)) {
            return false;
        }

        if ($model->hasRole(UserRole::ADMIN->value)) {
            $adminCount = User::whereHas('roles', fn ($q) => $q->where('name', UserRole::ADMIN->value))->count();
            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->id !== $model->id && $user->hasPermissionTo(Permission::EDIT_USERS->value);
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->id !== $model->id && $user->hasPermissionTo(Permission::ASSIGN_ROLES->value);
    }

    public function unlockAccount(User $user, User $model): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_USERS->value);
    }
}
