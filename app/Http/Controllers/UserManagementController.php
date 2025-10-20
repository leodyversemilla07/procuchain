<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display user management page
     */
    public function index(): Response
    {
        $users = User::select('id', 'name', 'email', 'blockchain_address', 'email_verified_at', 'remember_token', 'created_at', 'updated_at', 'account_locked', 'locked_at', 'lock_expires_at', 'failed_login_attempts', 'last_failed_login_at', 'locked_reason', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at')
            ->with('roles:id,name')
            ->where('id', '!=', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? null,
                    'roles' => $user->roles->map(function ($role) {
                        return ['id' => $role->id, 'name' => $role->name];
                    })->toArray(),
                    'blockchain_address' => $user->blockchain_address,
                    'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                    'remember_token' => $user->remember_token,
                    'two_factor_enabled' => ! empty($user->two_factor_secret),
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->format('Y-m-d H:i:s'),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
                    'account_locked' => $user->account_locked,
                    'locked_at' => $user->locked_at?->format('Y-m-d H:i:s'),
                    'lock_expires_at' => $user->lock_expires_at?->format('Y-m-d H:i:s'),
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'last_failed_login_at' => $user->last_failed_login_at?->format('Y-m-d H:i:s'),
                    'locked_reason' => $user->locked_reason,
                    'is_currently_locked' => $user->isAccountLocked(),
                ];
            });

        return Inertia::render('admin/user-management', [
            'users' => $users,
            'roles' => ['bac_secretariat', 'bac_chairman', 'hope', 'admin'],
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(['bac_secretariat', 'bac_chairman', 'hope', 'admin'])],
            'password' => 'required|string|min:8|confirmed',
            'blockchain_address' => 'nullable|string|max:255',
        ]);
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ]);

            // Assign role using Spatie Permission
            $user->assignRole($validated['role']);

            Log::info('Admin created new user', [
                'admin_id' => Auth::id(),
                'created_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
            ]);

            return redirect()->back()->with('success', 'User created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to create user. Please try again.']);
        }
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['bac_secretariat', 'bac_chairman', 'hope', 'admin'])],
            'blockchain_address' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        try {
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            // Sync role using Spatie Permission - remove old roles and assign new one
            $user->syncRoles([$validated['role']]);

            Log::info('Admin updated user', [
                'admin_id' => Auth::id(),
                'updated_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
            ]);

            return redirect()->back()->with('success', 'User updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update user', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to update user. Please try again.']);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(User $user)
    {
        try {
            // Prevent admin from deleting their own account
            if ($user->id === Auth::id()) {
                return redirect()->back()->withErrors(['error' => 'You cannot delete your own account.']);
            }

            $userEmail = $user->email;
            $user->delete();
            Log::info('Admin deleted user', [
                'admin_id' => Auth::id(),
                'deleted_user_email' => $userEmail,
            ]);

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to delete user. Please try again.']);
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:users,id',
        ]);

        try {
            $userIds = $validated['user_ids'];
            $currentUserId = Auth::id();

            // Remove current user's ID from the list to prevent self-deletion
            $userIds = array_filter($userIds, function ($id) use ($currentUserId) {
                return $id !== $currentUserId;
            });

            if (empty($userIds)) {
                return redirect()->back()->withErrors(['error' => 'Cannot delete your own account or no valid users selected.']);
            }

            // Get user details for logging before deletion
            $usersToDelete = User::whereIn('id', $userIds)->with('roles')->get(['id', 'email', 'name']);

            if ($usersToDelete->isEmpty()) {
                return redirect()->back()->withErrors(['error' => 'No valid users found for deletion.']);
            }

            // Perform bulk deletion within a transaction
            DB::transaction(function () use ($userIds, $usersToDelete, $currentUserId) {
                User::whereIn('id', $userIds)->delete();

                // Log the bulk deletion
                Log::info('Admin performed bulk user deletion', [
                    'admin_id' => $currentUserId,
                    'deleted_users' => $usersToDelete->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'email' => $user->email,
                            'name' => $user->name,
                            'role' => $user->roles->first()?->name ?? null,
                        ];
                    })->toArray(),
                    'total_deleted' => count($userIds),
                ]);
            });

            $deletedCount = count($userIds);
            $message = $deletedCount === 1 ? 'User deleted successfully.' : "{$deletedCount} users deleted successfully.";

            return redirect()->back()->with('success', $message);
        } catch (Exception $e) {
            Log::error('Failed to bulk delete users', [
                'admin_id' => Auth::id(),
                'user_ids' => $validated['user_ids'] ?? [],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to delete users. Please try again.']);
        }
    }

    /**
     * Send password reset link to a user
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            // Prevent admin from resetting their own password via admin panel
            if ($user->id === Auth::id()) {
                return redirect()->back()->withErrors(['error' => 'You cannot reset your own password from here. Please use the profile settings.']);
            }

            // Send password reset link
            $status = Password::sendResetLink(
                ['email' => $user->email]
            );

            if ($status === Password::RESET_LINK_SENT) {
                // Log the password reset action
                Log::info('Admin initiated password reset for user', [
                    'admin_id' => Auth::id(),
                    'admin_email' => Auth::user()->email,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'reason' => $validated['reason'],
                    'timestamp' => now()->toDateTimeString(),
                ]);

                return redirect()->back()->with('success', "Password reset link sent to {$user->email}");
            } else {
                Log::warning('Failed to send password reset link', [
                    'admin_id' => Auth::id(),
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'status' => $status,
                ]);

                return redirect()->back()->withErrors(['error' => 'Failed to send password reset link. Please try again.']);
            }
        } catch (\Exception $e) {
            Log::error('Error sending password reset link', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors(['error' => 'An error occurred while sending the reset link.']);
        }
    }
}
