<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BlockchainAuditLog Model
 *
 * Immutable audit trail synced FROM blockchain.
 * Source: audit.trail stream
 */
class BlockchainAuditLog extends Model
{
    protected $table = 'blockchain_audit_trail';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'pr_number',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
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
            'subject_type',
            'subject_id',
            'occurred_at',
        ];
    }
}
