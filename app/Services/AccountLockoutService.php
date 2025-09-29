<?php

namespace App\Services;

use App\Mail\AccountLockedMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountLockoutService
{
    public function unlockAccount(User $user, string $reason = 'Manually unlocked by admin'): bool
    {
        try {
            if (! $user->isAccountLocked()) {
                return false; // Account wasn't locked
            }
            $user->unlockAccount($reason, false); // manually unlocked with reason
            Log::info('User account unlocked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $reason,
                'unlocked_by' => Auth::check() ? Auth::id() : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unlock user account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function resetFailedAttempts(User $user): bool
    {
        try {
            $previousAttempts = $user->failed_login_attempts;
            $user->resetFailedLoginAttempts();
            Log::info('Failed login attempts reset', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'previous_attempts' => $previousAttempts,
                'reset_by' => Auth::check() ? Auth::id() : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset failed login attempts', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAccountLocked(string $email): bool
    {
        $user = User::where('email', $email)->first();

        return $user ? $user->isAccountLocked() : false;
    }

    public function getLockedAccounts()
    {
        return User::where('account_locked', true)
            ->whereNotNull('locked_at')
            ->with('loginLogs')
            ->orderBy('locked_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'mfa_enabled' => $user->mfa_enabled,
                    'mfa_enabled_at' => $user->mfa_enabled_at,
                    'locked_at' => $user->locked_at,
                    'lock_expires_at' => $user->lock_expires_at,
                    'locked_reason' => $user->locked_reason,
                    'failed_attempts' => $user->failed_login_attempts,
                    'time_remaining' => $user->getLockTimeRemaining(),
                    'time_remaining_minutes' => $user->remaining_lock_time,
                    'recent_failed_logins' => $user->loginLogs()
                        ->where('successful', false)
                        ->where('login_at', '>=', now()->subHours(24))
                        ->count(),
                ];
            });
    }

    public function unlockUserAccount(int $userId, string $adminReason = 'Manually unlocked by administrator'): bool
    {
        try {
            $user = User::findOrFail($userId);
            if (! $user->isAccountLocked()) {
                return false;
            }
            $user->unlockAccount($adminReason, false);
            Log::info('User account manually unlocked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $adminReason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unlock user account', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function lockAccount(User $user, string $reason, int $durationHours = 24): bool
    {
        try {
            $durationMinutes = $durationHours * 60;
            $user->lockAccount($reason, $durationMinutes);
            Log::warning('User account manually locked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $reason,
                'duration_hours' => $durationHours,
            ]);

            // Send account locked notification email if user has email notifications enabled
            if ($user->email_notifications_enabled) {
                try {
                    Mail::to($user->email)->send(new AccountLockedMail(
                        $user,
                        $reason,
                        "{$durationHours} hours"
                    ));
                    Log::info('Account locked notification email sent for manual lock', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'reason' => $reason,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send account locked notification email for manual lock', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to lock user account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
