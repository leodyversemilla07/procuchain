<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DocumentAccess Model
 *
 * Document access tracking synced FROM blockchain.
 * Source: document.access stream
 */
class DocumentAccess extends Model
{
    protected $table = 'document_access';

    protected $fillable = [
        'user_id',
        'file_key',
        'pr_number',
        'document_type',
        'stage',
        'action',
        'ip_address',
        'user_agent',
        'view_duration',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'metadata',
        'accessed_at',
    ];

    protected $casts = [
        'view_duration' => 'integer',
        'accessed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'metadata' => 'array',
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
            'file_key',
            'action',
            'accessed_at',
        ];
    }
}
