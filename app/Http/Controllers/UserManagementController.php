<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\User\BulkDeleteUsersRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BlockchainRpcClient;
use App\Services\UserRegistrationService;
use App\Traits\AuditContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    use AuditContext;

    public function __construct(protected AuditLogService $AuditLogService) {}

    /**
     * Display user management page
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-any-user');

        // SECURITY: Only select non-sensitive columns - never expose tokens, secrets, or recovery codes
        $users = User::select('id', 'name', 'email', 'blockchain_address', 'email_verified_at', 'created_at', 'updated_at', 'account_locked', 'locked_at', 'lock_expires_at', 'failed_login_attempts', 'last_failed_login_at', 'locked_reason', 'two_factor_confirmed_at')
            ->with('roles:id,name')
            ->where('id', '!=', $request->user()->id)
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
                    // SECURITY: Only indicate if 2FA is enabled, never expose secrets or recovery codes
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
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
            'roles' => UserRole::values(),
        ]);
    }

    /**
     * Store a new user
     */
    public function store(StoreUserRequest $request, BlockchainRpcClient $multichain): RedirectResponse
    {
        $this->authorize('create-user');

        $validated = $request->validated();

        try {
            // Auto-generate blockchain address if not provided
            $blockchainAddress = null;
            if (! empty($validated['blockchain_address'])) {
                // Use provided address (validate it first)
                $validation = $multichain->validateAddress($validated['blockchain_address']);
                if (! $validation['isvalid']) {
                    return redirect()->back()->withErrors(['blockchain_address' => 'Invalid blockchain address provided.']);
                }
                $blockchainAddress = $validated['blockchain_address'];
            } else {
                // Auto-generate new blockchain address
                $blockchainAddress = $multichain->getNewAddress();
            }

            // Password is set explicitly (not via $fillable) to prevent mass assignment attacks
            // Use User::make() to avoid NOT NULL constraint violation on password column
            $user = User::make([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'blockchain_address' => $blockchainAddress,
            ]);
            $user->password = Hash::make($validated['password']);
            $user->save();

            // Assign role using Spatie Permission
            $user->assignRole($validated['role']);

            // Publish user registration to blockchain
            app(UserRegistrationService::class)->publishRegistration(
                $user,
                auth()->user()?->name ?? 'System',
            );

            Log::info('Admin created new user', [
                ...$this->auditContext($request),
                'created_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
                'blockchain_address' => $blockchainAddress,
            ]);

            $this->AuditLogService->log(
                action: 'user.created',
                subjectType: 'user',
                subjectId: (string) $user->id,
                newValues: ['name' => $user->name, 'email' => $user->email, 'role' => $validated['role']]
            );

            return redirect()->back()->with('success', 'User created successfully with blockchain address.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to create user', [
                ...$this->auditContext($request),
                'error' => 'An error occurred managing users. Please try again.',
            ]);

            return redirect()->back()->with('error', 'Failed to create user. Please try again.');
        }
    }

    /**
     * Update an existing user
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update-user', $user);

        $validated = $request->validated();

        try {
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            $oldBlockchainAddress = $user->blockchain_address;
            if (array_key_exists('blockchain_address', $validated)) {
                $updateData['blockchain_address'] = ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null;
            }

            if (! empty($validated['password'])) {
                // Password is set explicitly (not via $fillable) to prevent mass assignment attacks
                $user->password = Hash::make($validated['password']);
            }

            $user->update($updateData);

            if ($user->wasChanged('blockchain_address') && $oldBlockchainAddress !== null && $user->blockchain_address !== null) {
                app(UserRegistrationService::class)->publishAddressChange(
                    $user,
                    $oldBlockchainAddress,
                    auth()->user()?->name ?? 'System',
                );
            }

            if (! empty($validated['password'])) {
                $user->save();
            }

            // Sync role using Spatie Permission - remove old roles and assign new one
            $user->syncRoles([$validated['role']]);

            Log::info('Admin updated user', [
                ...$this->auditContext($request),
                'updated_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
            ]);

            $this->AuditLogService->log(
                action: 'user.updated',
                subjectType: 'user',
                subjectId: (string) $user->id,
                newValues: ['name' => $validated['name'], 'email' => $validated['email'], 'role' => $validated['role']]
            );

            return redirect()->back()->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to update user', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
                'error' => 'An error occurred managing users. Please try again.',
            ]);

            return redirect()->back()->with('error', 'Failed to update user. Please try again.');
        }
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete-user', $user);

        try {
            // Policy already prevents deleting own account, but keeping check for explicit error message
            if ($user->id === $request->user()->id) {
                return redirect()->back()->with('error', 'You cannot delete your own account.');
            }

            $userEmail = $user->email;
            $userId = $user->id;
            $user->delete();
            Log::info('Admin deleted user', [
                ...$this->auditContext($request),
                'deleted_user_email' => $userEmail,
            ]);

            $this->AuditLogService->log(
                action: 'user.deleted',
                subjectType: 'user',
                subjectId: (string) $userId,
                oldValues: ['email' => $userEmail]
            );

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to delete user', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
                'error' => 'An error occurred managing users. Please try again.',
            ]);

            return redirect()->back()->with('error', 'Failed to delete user. Please try again.');
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(BulkDeleteUsersRequest $request): RedirectResponse
    {
        $this->authorize('delete-any-user');

        $validated = $request->validated();

        try {
            $userIds = $validated['user_ids'];

            if (empty($userIds)) {
                return redirect()->back()->with('error', 'No valid users selected for deletion.');
            }

            // Get user details for logging before deletion
            $usersToDelete = User::whereIn('id', $userIds)->with('roles')->get(['id', 'email', 'name']);

            if ($usersToDelete->isEmpty()) {
                return redirect()->back()->with('error', 'No valid users found for deletion.');
            }

            // Prevent bulk deletion that would remove the last admin
            $adminIdsToDelete = $usersToDelete->filter(fn ($u) => $u->hasRole(UserRole::ADMIN->value))->pluck('id');
            if ($adminIdsToDelete->isNotEmpty()) {
                $remainingAdminCount = User::whereHas('roles', fn ($q) => $q->where('name', UserRole::ADMIN->value))->count()
                    - $adminIdsToDelete->count();
                if ($remainingAdminCount < 1) {
                    return redirect()->back()->with('error', 'Cannot delete the last admin user. At least one admin must remain.');
                }
            }

            // Prevent deleting yourself via bulk action
            if ($usersToDelete->pluck('id')->contains($request->user()->id)) {
                return redirect()->back()->with('error', 'You cannot delete your own account.');
            }

            // Perform bulk deletion within a transaction
            $auditCtx = $this->auditContext($request);
            DB::transaction(function () use ($userIds, $usersToDelete, $auditCtx) {
                User::whereIn('id', $userIds)->delete();

                // Log the bulk deletion
                Log::info('Admin performed bulk user deletion', [
                    ...$auditCtx,
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

            $this->AuditLogService->log(
                action: 'user.bulk_deleted',
                subjectType: 'user',
                oldValues: [
                    'user_ids' => $userIds,
                    'emails' => $usersToDelete->pluck('email')->toArray(),
                ]
            );

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to bulk delete users', [
                ...$this->auditContext($request),
                'user_ids' => $validated['user_ids'] ?? [],
                'error' => 'An error occurred managing users. Please try again.',
            ]);

            return redirect()->back()->with('error', 'Failed to delete users. Please try again.');
        }
    }

    /**
     * Send password reset link to a user
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->authorize('reset-user-password', $user);

        $validated = $request->validated();

        try {
            // Policy already prevents resetting own password, but keeping check for explicit error message
            if ($user->id === $request->user()->id) {
                return redirect()->back()->with('error', 'You cannot reset your own password from here. Please use the proFile settings.');
            }

            // Send password reset link
            $status = Password::sendResetLink(
                ['email' => $user->email]
            );

            if ($status === Password::RESET_LINK_SENT) {
                // Log the password reset action
                Log::info('Admin initiated password reset for user', [
                    ...$this->auditContext($request),
                    'admin_email' => $request->user()->email,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'reason' => $validated['reason'],
                    'timestamp' => now()->toDateTimeString(),
                ]);

                $this->AuditLogService->log(
                    action: 'user.password_reset_sent',
                    subjectType: 'user',
                    subjectId: (string) $user->id,
                    newValues: ['email' => $user->email, 'reason' => $validated['reason']]
                );

                return redirect()->back()->with('success', "Password reset link sent to {$user->email}");
            } else {
                Log::warning('Failed to send password reset link', [
                    ...$this->auditContext($request),
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'status' => $status,
                ]);

                return redirect()->back()->with('error', 'Failed to send password reset link. Please try again.');
            }
        } catch (\Exception $e) {
            report($e);
            Log::error('Error sending password reset link', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
                'error' => 'An error occurred managing users. Please try again.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return redirect()->back()->with('error', 'An error occurred while sending the reset link.');
        }
    }
}
