<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementArchive Model
 *
 * Archive/restore actions synced FROM blockchain.
 * Source: procurement.archive stream
 */
class ProcurementArchive extends Model
{
    protected $table = 'procurement_archives';

    protected $fillable = [
        'procurement_id',
        'action',
        'reason',
        'user_address',
        'user_id',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
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
            'action',
            'reason',
            'user_id',
            'archived_at',
        ];
    }
}
