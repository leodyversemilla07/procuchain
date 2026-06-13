<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own proFile, or admins can view any user
        return $user->id === $model->id || $user->hasPermissionTo('manage users');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own proFile (name, email, password)
        // Only admins can update other users or change roles
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermissionTo('edit users');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Prevents deleting the last admin to avoid system lockout.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Must have permission
        if (! $user->hasPermissionTo('delete users')) {
            return false;
        }

        // Prevent deleting the last admin — would lock the system
        if ($model->hasRole('admin')) {
            $adminCount = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count();
            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can delete any model (used by bulk-delete).
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete users');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo('manage users');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * Prevents deleting the last admin to avoid system lockout.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Users cannot permanently delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        if (! $user->hasPermissionTo('delete users')) {
            return false;
        }

        // Prevent deleting the last admin
        if ($model->hasRole('admin')) {
            $adminCount = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count();
            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can reset another user's password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        // Users cannot reset their own password via admin panel (they use settings)
        // Only admins can reset other users' passwords
        return $user->id !== $model->id && $user->hasPermissionTo('edit users');
    }

    /**
     * Determine whether the user can assign roles to another user.
     */
    public function assignRoles(User $user, User $model): bool
    {
        // Users cannot change their own role
        // Only admins can assign roles
        return $user->id !== $model->id && $user->hasPermissionTo('assign roles');
    }

    /**
     * Determine whether the user can unlock another user's account.
     */
    public function unlockAccount(User $user, User $model): bool
    {
        // Only admins can unlock accounts
        return $user->hasPermissionTo('manage users');
    }
}
