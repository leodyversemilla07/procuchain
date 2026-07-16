<?php

namespace App\Models;

use App\Enums\Permission as Perm;
use App\Enums\UserRole;
use App\Models\Concerns\HasAccountLock;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $blockchain_address
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $account_locked
 * @property Carbon|null $locked_at
 * @property Carbon|null $lock_expires_at
 * @property int $failed_login_attempts
 * @property Carbon|null $last_failed_login_at
 * @property string|null $locked_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $email_notifications_enabled
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $primary_role
 * @property-read int $remaining_lock_time
 * @property-read Collection<int, UserLoginLog> $loginLogs
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read Collection<int, BlockedIp> $blockedIps
 * @property-read Collection<int, UserInvitation> $invitations
 * @property-read Collection<int, UserInvitation> $acceptedInvitations
 * @property-read Collection<int, UserInvitation> $revokedInvitations
 * @property-read Collection<int, DocumentViewLog> $documentViewLogs
 * @property-read Collection<int, ProcurementWorkflowConfig> $updatedWorkflowConfigs
 * @property-read Collection<int, StageDocumentConfig> $updatedStageDocumentConfigs
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
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
    /** @use HasFactory<UserFactory> */
    use HasAccountLock, HasFactory, HasPushSubscriptions, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * SECURITY: Sensitive fields (password, 2FA secrets, account lockout fields)
     * are intentionally excluded from $fillable. These must be set explicitly
     * via `$model->field = value` to prevent mass assignment attacks.
     *
     * @see https://laravel.com/docs/13.x/eloquent#mass-assignment
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'blockchain_address',
        'email_notifications_enabled',
        'notification_preferences',
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
            'notification_preferences' => 'json',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Return the default notification preference structure.
     *
     * @return array<string, array<string, bool>>
     */
    public static function getDefaultNotificationPreferences(): array
    {
        return [
            'procurement_stage_updates' => ['email' => true, 'push' => true],
            'procurement_corrections' => ['email' => true, 'push' => true],
            'document_uploads' => ['email' => false, 'push' => true],
            'account_security' => ['email' => true, 'push' => true],
            'user_invitations' => ['email' => true, 'push' => false],
            'system_announcements' => ['email' => true, 'push' => true],
            'integrity_breach' => ['email' => true, 'push' => true],
        ];
    }

    /**
     * Get merged notification preferences (user overrides merged into defaults).
     *
     * @return array<string, array<string, bool>>
     */
    public function getMergedNotificationPreferences(): array
    {
        $defaults = self::getDefaultNotificationPreferences();
        $saved = $this->notification_preferences ?? [];

        foreach ($defaults as $type => $channels) {
            if (isset($saved[$type])) {
                $defaults[$type] = array_merge($channels, $saved[$type]);
            }
        }

        return $defaults;
    }

    /**
     * Check whether a notification of the given type and channel is enabled for this user.
     */
    public function isNotificationEnabled(string $eventType, string $channel): bool
    {
        if ($channel === 'email' && ! $this->email_notifications_enabled) {
            return false;
        }

        $prefs = $this->getMergedNotificationPreferences();

        return (bool) ($prefs[$eventType][$channel] ?? false);
    }

    /**
     * Get the user's primary role attribute.
     */
    protected function primaryRole(): Attribute
    {
        return Attribute::make(
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
     * Get the audit logs for the user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Get the blocked IPs created by this user.
     */
    public function blockedIps(): HasMany
    {
        return $this->hasMany(BlockedIp::class, 'blocked_by');
    }

    /**
     * Get the invitations sent by this user.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    /**
     * Get the invitations accepted by this user.
     */
    public function acceptedInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'user_id');
    }

    /**
     * Get the invitations revoked by this user.
     */
    public function revokedInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'revoked_by');
    }

    /**
     * Get the document views for the user.
     */
    public function DocumentViewLogs(): HasMany
    {
        return $this->hasMany(DocumentViewLog::class);
    }

    /**
     * Get the procurement workflow configs updated by this user.
     */
    public function updatedWorkflowConfigs(): HasMany
    {
        return $this->hasMany(ProcurementWorkflowConfig::class, 'updated_by');
    }

    /**
     * Get the stage document configs updated by this user.
     */
    public function updatedStageDocumentConfigs(): HasMany
    {
        return $this->hasMany(StageDocumentConfig::class, 'updated_by');
    }

    /**
     * Get recent login logs for the user.
     */
    public function recentLoginLogs(int $limit = 10): HasMany
    {
        return $this->loginLogs()->orderBy('login_at', 'desc')->limit($limit);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    public function isBacSecretariat(): bool
    {
        return $this->hasRole(UserRole::BAC_SECRETARIAT->value);
    }

    public function isBacChairman(): bool
    {
        return $this->hasRole(UserRole::BAC_CHAIRMAN->value);
    }

    public function isHope(): bool
    {
        return $this->hasRole(UserRole::HOPE->value);
    }

    public function canManageProcurement(): bool
    {
        return $this->hasAnyPermission([
            Perm::CREATE_PROCUREMENT->value,
            Perm::EDIT_PROCUREMENT->value,
            Perm::DELETE_PROCUREMENT->value,
        ]);
    }

    public function canApproveProcurement(): bool
    {
        return $this->hasPermissionTo(Perm::APPROVE_PROCUREMENT->value);
    }

    public function canManageDocuments(): bool
    {
        return $this->hasAnyPermission([
            Perm::UPLOAD_DOCUMENTS->value,
            Perm::DELETE_DOCUMENTS->value,
        ]);
    }

    public function canViewDocuments(): bool
    {
        return $this->hasPermissionTo(Perm::VIEW_DOCUMENTS->value);
    }

    public function canManageStages(): bool
    {
        return $this->hasAnyPermission([
            Perm::MANAGE_PROCUREMENT_INITIATION->value,
            Perm::MANAGE_PRE_PROCUREMENT_CONFERENCE->value,
            Perm::MANAGE_BIDDING_DOCUMENTS->value,
            Perm::MANAGE_PRE_BID_CONFERENCE->value,
            Perm::MANAGE_SUPPLEMENTAL_BID_BULLETIN->value,
            Perm::MANAGE_BID_OPENING->value,
            Perm::MANAGE_BID_EVALUATION->value,
            Perm::MANAGE_POST_QUALIFICATION->value,
            Perm::MANAGE_BAC_RESOLUTION->value,
            Perm::MANAGE_NOTICE_OF_AWARD->value,
            Perm::MANAGE_PERFORMANCE_BOND_CONTRACT_PO->value,
            Perm::MANAGE_NOTICE_TO_PROCEED->value,
            Perm::MANAGE_MONITORING->value,
            Perm::MANAGE_COMPLETION->value,
        ]);
    }

    public function canAccessBlockchain(): bool
    {
        return $this->hasAnyPermission([
            Perm::VIEW_BLOCKCHAIN_TRANSACTIONS->value,
            Perm::PUBLISH_TO_BLOCKCHAIN->value,
        ]);
    }

    public function canManageUsers(): bool
    {
        return $this->hasAnyPermission([
            Perm::MANAGE_USERS->value,
            Perm::CREATE_USERS->value,
            Perm::EDIT_USERS->value,
            Perm::DELETE_USERS->value,
            Perm::ASSIGN_ROLES->value,
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

    public function hasDashboardAccess(): bool
    {
        return $this->hasAnyPermission([
            Perm::VIEW_ADMIN_DASHBOARD->value,
            Perm::VIEW_BAC_SECRETARIAT_DASHBOARD->value,
            Perm::VIEW_BAC_CHAIRMAN_DASHBOARD->value,
            Perm::VIEW_HOPE_DASHBOARD->value,
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
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
