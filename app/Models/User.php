<?php

namespace App\Models;

use App\Mail\AccountUnlockedMail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, TwoFactorAuthenticatable;

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
        'email_notifications_enabled',
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
            'email_notifications_enabled' => 'boolean',
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

        // Send unlock notification email only if account was actually locked and user has email notifications enabled
        if ($this->email_notifications_enabled) {
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

    /**
     * Check if MFA is enabled for this user
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled && ! empty($this->google2fa_secret);
    }

    /**
     * Generate backup codes for MFA
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        $this->update([
            'backup_codes' => array_map('hash', array_fill(0, count($codes), 'sha256'), $codes),
            'backup_codes_generated_at' => now(),
        ]);

        return $codes;
    }
}
