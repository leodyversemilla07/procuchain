<?php

namespace App\Services;

use App\Events\AccountLocked;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AccountLockoutService
{
    /**
     * Handle failed login attempt and check for account locking
     */
    public function handleFailedLoginAttempt(User $user): void
    {
        try {
            // Don't process if account is already locked
            if ($user->isAccountLocked()) {
                return;
            }

            // Increment failed attempts
            $user->incrementFailedLoginAttempts();

            // Check if we need to lock the account (3 attempts = lock)
            if ($user->failed_login_attempts >= 3) {
                $lockDurationMinutes = config('auth.account_lockout_duration', 30);
                $user->lockAccount('Account locked due to multiple failed login attempts', $lockDurationMinutes);

                Log::warning('User account locked due to failed login attempts', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'failed_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->lock_expires_at,
                ]);

                AccountLocked::dispatch(
                    $user,
                    'Account locked due to multiple failed login attempts',
                    "{$lockDurationMinutes} minutes",
                );
            } else {
                Log::info('Failed login attempt recorded', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'failed_attempts' => $user->failed_login_attempts,
                    'attempts_remaining' => 3 - $user->failed_login_attempts,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle failed login attempt', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get account lockout statistics
     */
    public function getAccountLockoutStatistics(): array
    {
        $now = now();
        $last24Hours = $now->copy()->subHours(24);
        $last7Days = $now->copy()->subDays(7);

        return [
            'currently_locked' => User::where('account_locked', true)->count(),
            'locked_last_24h' => User::where('locked_at', '>=', $last24Hours)->count(),
            'locked_last_7_days' => User::where('locked_at', '>=', $last7Days)->count(),
            'total_lockouts' => User::whereNotNull('locked_at')->count(),
            'failed_attempts_last_24h' => UserLoginLog::where('successful', false)
                ->where('login_at', '>=', $last24Hours)
                ->count(),
        ];
    }

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
            ->with(['loginLogs', 'roles'])
            ->orderBy('locked_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? null,
                    'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
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

            AccountLocked::dispatch($user, $reason, "{$durationHours} hours");

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
