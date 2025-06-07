<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\AccountUnlockedMail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'blockchain_address',
        'password',
        'account_locked',
        'locked_at',
        'lock_expires_at',
        'failed_login_attempts',
        'last_failed_login_at',
        'locked_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_locked' => 'boolean',
            'locked_at' => 'datetime',
            'lock_expires_at' => 'datetime',
            'last_failed_login_at' => 'datetime',
        ];
    }

    /**
     * Get the login logs for the user.
     */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(UserLoginLog::class);
    }

    /**
     * Get recent login logs for the user.
     */
    public function recentLoginLogs(int $limit = 10): HasMany
    {
        return $this->loginLogs()->orderBy('login_at', 'desc')->limit($limit);
    }

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
        $this->update([
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes($durationMinutes),
            'locked_reason' => $reason,
        ]);
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

        $this->update([
            'account_locked' => false,
            'locked_at' => null,
            'lock_expires_at' => null,
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
            'locked_reason' => null,
        ]);

        // Send unlock notification email only if account was actually locked
        try {
            $reason = $isAutoUnlock
                ? 'Account automatically unlocked after lock period expired'
                : $unlockedBy;

            Mail::to($this->email)->send(new AccountUnlockedMail(
                $this,
                $reason,
                $isAutoUnlock,
                $unlockedBy
            ));

            Log::info('Account unlocked notification email sent', [
                'user_id' => $this->id,
                'user_email' => $this->email,
                'auto_unlock' => $isAutoUnlock,
                'unlocked_by' => $unlockedBy,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send account unlocked notification email', [
                'user_id' => $this->id,
                'user_email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => $this->failed_login_attempts + 1,
            'last_failed_login_at' => now(),
        ]);
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
        ]);
    }

    /**
     * Get the time remaining until account unlock
     */
    public function getLockTimeRemaining(): ?string
    {
        if (! $this->isAccountLocked() || ! $this->lock_expires_at) {
            return null;
        }

        $remaining = $this->lock_expires_at->diffForHumans();

        return $remaining;
    }

    /**
     * Get the remaining lock time in minutes (for API/testing purposes)
     */
    public function getRemainingLockTimeAttribute(): int
    {
        if (! $this->account_locked || ! $this->lock_expires_at) {
            return 0;
        }

        $now = now();

        // If lock has expired, return 0
        if ($this->lock_expires_at->isPast()) {
            return 0;
        }

        // Return remaining minutes (round up to ensure we don't get 0 for partial minutes)
        return (int) ceil($now->diffInMinutes($this->lock_expires_at, false));
    }
}
