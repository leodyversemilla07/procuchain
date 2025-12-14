<?php

namespace App\Models;

use App\Mail\AccountUnlockedMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $blockchain_address
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $account_locked
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property \Illuminate\Support\Carbon|null $lock_expires_at
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $last_failed_login_at
 * @property string|null $locked_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property bool $email_notifications_enabled
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $primary_role
 * @property-read int $remaining_lock_time
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserLoginLog> $loginLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User role($roles, $guard = null, $without = false)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'blockchain_address',
        'password',
        'account_locked',
        'locked_at',
        'lock_expires_at',
        'failed_login_attempts',
        'last_failed_login_at',
        'locked_reason',
        'email_notifications_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
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
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'primary_role',
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
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's primary role attribute.
     */
    protected function primaryRole(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn (): ?string => $this->getPrimaryRole(),
        );
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
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is a BAC Secretariat member
     */
    public function isBacSecretariat(): bool
    {
        return $this->hasRole('bac_secretariat');
    }

    /**
     * Check if user is the BAC Chairman
     */
    public function isBacChairman(): bool
    {
        return $this->hasRole('bac_chairman');
    }

    /**
     * Check if user is the HOPE (Head of Procuring Entity)
     */
    public function isHope(): bool
    {
        return $this->hasRole('hope');
    }

    /**
     * Check if user can manage procurement
     */
    public function canManageProcurement(): bool
    {
        return $this->hasAnyPermission([
            'create procurement',
            'edit procurement',
            'delete procurement',
        ]);
    }

    /**
     * Check if user can approve procurement
     */
    public function canApproveProcurement(): bool
    {
        return $this->hasPermissionTo('approve procurement');
    }

    /**
     * Check if user can manage documents
     */
    public function canManageDocuments(): bool
    {
        return $this->hasAnyPermission([
            'upload documents',
            'delete documents',
        ]);
    }

    /**
     * Check if user can view documents
     */
    public function canViewDocuments(): bool
    {
        return $this->hasPermissionTo('view documents');
    }

    /**
     * Check if user can manage stages
     */
    public function canManageStages(): bool
    {
        return $this->hasAnyPermission([
            'manage procurement initiation',
            'manage pre-procurement conference',
            'manage bidding documents',
            'manage pre-bid conference',
            'manage supplemental bid bulletin',
            'manage bid opening',
            'manage bid evaluation',
            'manage post-qualification',
            'manage bac resolution',
            'manage notice of award',
            'manage performance bond contract po',
            'manage notice to proceed',
            'manage monitoring',
            'manage completion',
        ]);
    }

    /**
     * Check if user can access blockchain features
     */
    public function canAccessBlockchain(): bool
    {
        return $this->hasAnyPermission([
            'view blockchain transactions',
            'publish to blockchain',
        ]);
    }

    /**
     * Check if user can manage users
     */
    public function canManageUsers(): bool
    {
        return $this->hasAnyPermission([
            'manage users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',
        ]);
    }

    /**
     * Get all assigned role names
     */
    public function getAssignedRoles(): array
    {
        return $this->getRoleNames()->toArray();
    }

    /**
     * Get all permission names for this user
     */
    public function getAllowedPermissions(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Get user's primary role (first assigned role)
     */
    public function getPrimaryRole(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Check if user has dashboard access
     */
    public function hasDashboardAccess(): bool
    {
        return $this->hasAnyPermission([
            'view admin dashboard',
            'view bac-secretariat dashboard',
            'view bac-chairman dashboard',
            'view hope dashboard',
        ]);
    }

    /**
     * Get the appropriate dashboard route for the user
     */
    public function getDashboardRoute(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isBacSecretariat()) {
            return 'bac-secretariat.dashboard';
        }

        if ($this->isBacChairman()) {
            return 'bac-chairman.dashboard';
        }

        if ($this->isHope()) {
            return 'hope.dashboard';
        }

        return 'dashboard';
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
