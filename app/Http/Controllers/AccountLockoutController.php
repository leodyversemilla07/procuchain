<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\AuditLogService;
use App\Traits\AuditContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AccountLockoutController extends Controller
{
    use AuditContext;

    public function __construct(
        private AccountLockoutService $accountLockout,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Display locked accounts page
     */
    public function index(Request $request): Response
    {
        $this->authorize('unlock-user-account');
        try {
            $lockedUsers = $this->accountLockout->getLockedAccounts();

            // Format the data for the frontend - the LoginTrackingService already returns formatted data
            $formattedUsers = $lockedUsers->map(function ($userData) {
                return [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'two_factor_enabled' => $userData['two_factor_enabled'],
                    'two_factor_confirmed_at' => $userData['two_factor_confirmed_at']?->format('Y-m-d H:i:s'),
                    'account_locked' => true, // All users from getLockedAccounts are locked
                    'locked_at' => $userData['locked_at']?->format('Y-m-d H:i:s'),
                    'lock_expires_at' => $userData['lock_expires_at']?->format('Y-m-d H:i:s'),
                    'failed_login_attempts' => $userData['failed_attempts'],
                    'last_failed_login_at' => null, // Not provided in the service response
                    'locked_reason' => $userData['locked_reason'],
                    'is_currently_locked' => true, // All users from getLockedAccounts are currently locked
                    'lock_time_remaining' => $userData['time_remaining'],
                    'recent_failed_logins' => $userData['recent_failed_logins'],
                ];
            });

            return Inertia::render('admin/locked-accounts', [
                'lockedAccounts' => $formattedUsers,
            ]);
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to fetch locked accounts', [
                ...$this->auditContext($request),
            ]);

            return Inertia::render('admin/locked-accounts', [
                'error' => 'Failed to load locked accounts. Please try again later.',
            ]);
        }
    }

    /**
     * Unlock a user account
     */
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $this->authorize('unlock-user-account');
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);

            $result = $this->accountLockout->unlockAccount($user, $validated['reason'] ?? 'Manually unlocked by admin', $request->user());

            if ($result) {
                Log::info('Admin unlocked user account', [
                    ...$this->auditContext($request),
                    'unlocked_user_id' => $user->id,
                    'unlocked_user_email' => $user->email,
                    'reason' => $validated['reason'] ?? 'Manually unlocked by admin',
                ]);

                $this->auditLogService->log(
                    action: 'account.unlocked',
                    subjectType: 'user',
                    subjectId: (string) $user->id,
                    newValues: ['email' => $user->email, 'reason' => $validated['reason'] ?? 'Manually unlocked by admin']
                );

                return back()->with('success', "Account unlocked successfully for {$user->name}");
            } else {
                return back()->with('error', 'Account was not locked or unlock failed');
            }
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to unlock user account', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
            ]);

            return back()->with('error', 'Failed to unlock account');
        }
    }

    /**
     * Manually lock a user account
     */
    public function lock(Request $request, User $user): RedirectResponse
    {
        $this->authorize('unlock-user-account');
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:255',
                'duration_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            ]);

            // Prevent admin from locking their own account
            if ($user->id === $request->user()->id) {
                return back()->with('error', 'You cannot lock your own account.');
            }

            $durationHours = $validated['duration_hours'] ?? 24; // Default 24 hours
            $result = $this->accountLockout->lockAccount($user, $validated['reason'], $durationHours);

            if ($result) {
                Log::info('Admin manually locked user account', [
                    ...$this->auditContext($request),
                    'locked_user_id' => $user->id,
                    'locked_user_email' => $user->email,
                    'reason' => $validated['reason'],
                    'duration_hours' => $durationHours,
                ]);

                $this->auditLogService->log(
                    action: 'account.locked',
                    subjectType: 'user',
                    subjectId: (string) $user->id,
                    newValues: ['email' => $user->email, 'reason' => $validated['reason'], 'duration_hours' => $durationHours]
                );

                return back()->with('success', 'Account locked successfully.');
            } else {
                return back()->with('error', 'Account is already locked or lock failed.');
            }
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to lock user account', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
            ]);

            return back()->with('error', 'Failed to lock account.');
        }
    }

    /**
     * Reset failed login attempts for a user
     */
    public function resetAttempts(Request $request, User $user): RedirectResponse
    {
        $this->authorize('unlock-user-account');
        try {
            $result = $this->accountLockout->resetFailedAttempts($user, $request->user());

            if ($result) {
                Log::info('Admin reset failed login attempts', [
                    ...$this->auditContext($request),
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);

                $this->auditLogService->log(
                    action: 'account.attempts_reset',
                    subjectType: 'user',
                    subjectId: (string) $user->id,
                    newValues: ['email' => $user->email]
                );

                return back()->with('success', "Failed login attempts reset successfully for {$user->name}");
            } else {
                return back()->with('error', 'No failed attempts to reset');
            }
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to reset failed login attempts', [
                ...$this->auditContext($request),
                'user_id' => $user->id,
            ]);

            return back()->with('error', 'Failed to reset failed attempts');
        }
    }

    /**
     * Bulk unlock multiple user accounts
     */
    public function bulkUnlock(Request $request): RedirectResponse
    {
        $this->authorize('unlock-user-account');
        $validated = $request->validate([
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'required|integer|exists:users,id',
        ]);

        try {
            $accountIds = $validated['account_ids'];
            $currentUserId = $request->user()->id;
            $auditCtx = $this->auditContext($request);
            $successCount = 0;
            $failedAccounts = [];

            foreach ($accountIds as $userId) {
                // Prevent admin from unlocking their own account (though they shouldn't be locked)
                if ($userId === $currentUserId) {
                    continue;
                }

                $user = User::find($userId);
                if ($user && $user->isAccountLocked()) {
                    $result = $this->accountLockout->unlockAccount($user, 'Bulk unlocked by administrator', $request->user());
                    if ($result) {
                        $successCount++;
                    } else {
                        $failedAccounts[] = $user->email;
                    }
                }
            }

            Log::info('Admin performed bulk account unlock', [
                ...$auditCtx,
                'total_requested' => count($accountIds),
                'success_count' => $successCount,
                'failed_accounts' => $failedAccounts,
            ]);

            if ($successCount > 0) {
                $this->auditLogService->log(
                    action: 'account.bulk_unlocked',
                    subjectType: 'user',
                    newValues: ['account_ids' => $accountIds, 'success_count' => $successCount]
                );
                $message = $successCount === 1
                    ? 'Successfully unlocked 1 account'
                    : "Successfully unlocked {$successCount} accounts";

                return back()->with('success', $message);
            } else {
                return back()->with('error', 'Failed to unlock any accounts');
            }
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to bulk unlock accounts', [
                ...$this->auditContext($request),
                'account_ids' => $validated['account_ids'] ?? [],
            ]);

            return back()->with('error', 'Failed to unlock accounts. Please try again.');
        }
    }

    /**
     * Bulk reset failed login attempts for multiple users
     */
    public function bulkResetAttempts(Request $request): RedirectResponse
    {
        $this->authorize('unlock-user-account');
        $validated = $request->validate([
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'required|integer|exists:users,id',
        ]);

        try {
            $accountIds = $validated['account_ids'];
            $currentUserId = $request->user()->id;
            $auditCtx = $this->auditContext($request);
            $successCount = 0;
            $failedAccounts = [];

            foreach ($accountIds as $userId) {
                $user = User::find($userId);
                if ($user && $user->failed_login_attempts > 0) {
                    $result = $this->accountLockout->resetFailedAttempts($user, $request->user());
                    if ($result) {
                        $successCount++;
                    } else {
                        $failedAccounts[] = $user->email;
                    }
                }
            }

            Log::info('Admin performed bulk reset of failed login attempts', [
                ...$auditCtx,
                'total_requested' => count($accountIds),
                'success_count' => $successCount,
                'failed_accounts' => $failedAccounts,
            ]);

            if ($successCount > 0) {
                $this->auditLogService->log(
                    action: 'account.bulk_attempts_reset',
                    subjectType: 'user',
                    newValues: ['account_ids' => $accountIds, 'success_count' => $successCount]
                );
                $message = $successCount === 1
                    ? 'Successfully reset attempts for 1 account'
                    : "Successfully reset attempts for {$successCount} accounts";

                return back()->with('success', $message);
            } else {
                return back()->with('warning', 'No accounts had failed attempts to reset');
            }
        } catch (\Exception $e) {
            report($e);

            Log::error('Failed to bulk reset login attempts', [
                ...$this->auditContext($request),
                'account_ids' => $validated['account_ids'] ?? [],
            ]);

            return back()->with('error', 'Failed to reset login attempts. Please try again.');
        }
    }
}
