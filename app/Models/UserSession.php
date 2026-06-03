<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserSession Model
 *
 * Login/logout tracking synced FROM blockchain.
 * Source: user.login_sessions stream
 */
class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'session_id',
        'action',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'location',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fields that are included in the hash for integrity verification.
     */
    public static function getHashableFields(): array
    {
        return [
            'user_id',
            'action',
            'ip_address',
            'occurred_at',
        ];
    }
}
