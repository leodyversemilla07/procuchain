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
        // Users can view their own profile, or admins can view any user
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
        // Users can update their own profile (name, email, password)
        // Only admins can update other users or change roles
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermissionTo('edit users');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        // Only admins can delete users
        return $user->id !== $model->id && $user->hasPermissionTo('delete users');
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
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only admins can permanently delete users, but not themselves
        return $user->id !== $model->id && $user->hasPermissionTo('delete users');
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
