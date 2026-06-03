<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementEvent Model
 *
 * Audit trail events synced FROM blockchain.
 * Source: procurement.events stream
 */
class ProcurementEvent extends Model
{
    protected $table = 'procurement_events';

    protected $fillable = [
        'procurement_id',
        'event_type',
        'category',
        'severity',
        'details',
        'stage',
        'document_count',
        'user_address',
        'user_name',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'document_count' => 'integer',
        'occurred_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'metadata' => 'array',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * Get the fields that are included in the hash for integrity verification.
     */
    public static function getHashableFields(): array
    {
        return [
            'procurement_id',
            'event_type',
            'category',
            'severity',
            'details',
            'stage',
            'occurred_at',
        ];
    }
}
