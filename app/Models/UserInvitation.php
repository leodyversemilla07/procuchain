<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string $name
 * @property string $role
 * @property string $token
 * @property int|null $invited_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $user_id
 * @property bool $revoked
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $invitedBy
 * @property-read User $user
 * @property-read User|null $revokedBy
 */
class UserInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'user_id',
        'revoked',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    /**
     * Get the user who sent the invitation
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the user who accepted the invitation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who revoked the invitation
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Check if invitation is valid (not expired, not accepted, not revoked)
     */
    public function isValid(): bool
    {
        return ! $this->isExpired()
            && ! $this->isAccepted()
            && ! $this->isRevoked();
    }

    /**
     * Check if invitation has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invitation has been accepted
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invitation has been revoked
     */
    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    /**
     * Check if invitation is pending (valid and not acted upon)
     */
    public function isPending(): bool
    {
        return $this->isValid();
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(User $user): void
    {
        $this->update([
            'accepted_at' => now(),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Revoke the invitation
     */
    public function revoke(User $revokedBy): void
    {
        $this->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy->id,
        ]);
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->where('accepted_at', null)
            ->where('revoked', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->where('accepted_at', null)
            ->where('revoked', false)
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope for accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope for revoked invitations
     */
    public function scopeRevoked($query)
    {
        return $query->where('revoked', true);
    }
}
