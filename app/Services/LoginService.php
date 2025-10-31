<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;

class LoginService
{
    public function __construct(
        protected LoginLoggerService $loginLogger,
        protected AccountLockoutService $accountLockout,
        protected LoginAnalyticsService $loginAnalytics
    ) {}

    /**
     * Log a successful login
     */
    public function logLogin(User $user, Request $request): UserLoginLog
    {
        return $this->loginLogger->logLogin($user, $request);
    }

    /**
     * Log a failed login attempt
     */
    public function logFailedLogin(?string $email, Request $request): UserLoginLog
    {
        $loginLog = $this->loginLogger->logFailedLogin($email, $request);

        // Handle account locking for failed attempts
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->accountLockout->handleFailedLoginAttempt($user);
            }
        }

        return $loginLog;
    }

    /**
     * Log user logout
     */
    public function logLogout(User $user): void
    {
        $this->loginLogger->logLogout($user);
    }

    /**
     * Get formatted login statistics
     */
    public function getLoginStatistics(): array
    {
        return $this->loginAnalytics->getLoginStatistics();
    }

    /**
     * Get recent logins for admin dashboard
     */
    public function getRecentLogins(int $limit = 50): \Illuminate\Support\Collection
    {
        return $this->loginAnalytics->getRecentLogins($limit);
    }

    /**
     * Get suspicious login activities (multiple failed attempts, unusual IPs, etc.)
     */
    public function getSuspiciousActivities(): \Illuminate\Support\Collection
    {
        return $this->loginAnalytics->getSuspiciousActivities();
    }

    /**
     * Check if a user account is locked
     */
    public function isAccountLocked(string $email): bool
    {
        return $this->accountLockout->isAccountLocked($email);
    }

    /**
     * Get locked accounts
     */
    public function getLockedAccounts(): \Illuminate\Support\Collection
    {
        return $this->accountLockout->getLockedAccounts();
    }

    /**
     * Manually unlock a user account (for admin use)
     */
    public function unlockUserAccount(int $userId, string $adminReason = 'Manually unlocked by administrator'): bool
    {
        return $this->accountLockout->unlockUserAccount($userId, $adminReason);
    }

    /**
     * Unlock a user account (wrapper for admin controller)
     */
    public function unlockAccount(User $user, string $reason = 'Manually unlocked by admin'): bool
    {
        return $this->accountLockout->unlockAccount($user, $reason);
    }

    /**
     * Lock a user account (wrapper for admin controller)
     */
    public function lockAccount(User $user, string $reason, int $durationHours = 24): bool
    {
        return $this->accountLockout->lockAccount($user, $reason, $durationHours);
    }

    /**
     * Reset failed login attempts for a user (wrapper for admin controller)
     */
    public function resetFailedAttempts(User $user): bool
    {
        return $this->accountLockout->resetFailedAttempts($user);
    }

    /**
     * Get account lockout statistics
     */
    public function getAccountLockoutStatistics(): array
    {
        return $this->accountLockout->getAccountLockoutStatistics();
    }
}
