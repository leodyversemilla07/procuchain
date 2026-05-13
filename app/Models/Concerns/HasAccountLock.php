<?php

namespace App\Models\Concerns;

use App\Events\AccountUnlocked;

/**
 * Provides account lock management for a User model.
 *
 * Handles locking/unlocking accounts, tracking failed login attempts,
 * and calculating remaining lock time.
 *
 * SECURITY: Lockout fields (account_locked, locked_at, etc.) are intentionally
 * excluded from User::$fillable to prevent mass assignment attacks. All writes
 * in this trait use explicit property assignment instead of mass assignment.
 */
trait HasAccountLock
{
    /**
     * Check if the account is currently locked
     */
    public function isAccountLocked(): bool
    {
        if (! $this->account_locked) {
            return false;
        }

        // Check if lock has expired
        if ($this->lock_expires_at && $this->lock_expires_at->isPast()) {
            $this->unlockAccount('system', true); // auto-unlock due to expiration

            return false;
        }

        return true;
    }

    /**
     * Lock the user account
     */
    public function lockAccount(string $reason = 'Multiple failed login attempts', int $durationMinutes = 30): void
    {
        $this->account_locked = true;
        $this->locked_at = now();
        $this->lock_expires_at = now()->addMinutes($durationMinutes);
        $this->locked_reason = $reason;
        $this->save();
    }

    /**
     * Unlock the user account
     */
    public function unlockAccount(string $unlockedBy = 'system', bool $isAutoUnlock = false): void
    {
        $wasLocked = $this->account_locked;

        // Don't process if account is already unlocked
        if (! $wasLocked) {
            return;
        }

        $this->account_locked = false;
        $this->locked_at = null;
        $this->lock_expires_at = null;
        $this->failed_login_attempts = 0;
        $this->last_failed_login_at = null;
        $this->locked_reason = null;
        $this->save();

        AccountUnlocked::dispatch(
            $this,
            $isAutoUnlock ? 'Account automatically unlocked after lock period expired' : $unlockedBy,
            $isAutoUnlock,
            $unlockedBy,
        );
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->failed_login_attempts = $this->failed_login_attempts + 1;
        $this->last_failed_login_at = now();
        $this->save();
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->last_failed_login_at = null;
        $this->save();
    }

    /**
     * Get the time remaining until account unlock
     */
    public function getLockTimeRemaining(): ?string
    {
        $remainingMinutes = $this->getRemainingLockTimeAttribute();

        if ($remainingMinutes === 0) {
            return null;
        }

        // Convert minutes to human-readable format
        if ($remainingMinutes < 60) {
            return $remainingMinutes.' minute'.($remainingMinutes !== 1 ? 's' : '');
        }

        $hours = floor($remainingMinutes / 60);
        $minutes = $remainingMinutes % 60;

        $result = $hours.' hour'.($hours !== 1 ? 's' : '');
        if ($minutes > 0) {
            $result .= ' '.$minutes.' minute'.($minutes !== 1 ? 's' : '');
        }

        return $result;
    }

    /**
     * Get remaining lock time in minutes (accessor attribute)
     */
    public function getRemainingLockTimeAttribute(): int
    {
        // Return 0 if account is not locked or no expiration time is set
        if (! $this->account_locked || ! $this->lock_expires_at) {
            return 0;
        }

        // Return 0 if lock has already expired
        if ($this->lock_expires_at->isPast()) {
            return 0;
        }

        // Calculate remaining minutes (from now to lock expiration)
        return (int) ceil(now()->diffInMinutes($this->lock_expires_at, false));
    }
}
