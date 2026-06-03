<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementStage Model
 *
 * Stage progression history synced FROM blockchain.
 * Source: procurement.status stream
 */
class ProcurementStage extends Model
{
    protected $table = 'procurement_stages';

    protected $fillable = [
        'procurement_id',
        'stage',
        'status',
        'previous_status',
        'entered_at',
        'completed_at',
        'duration_hours',
        'user_address',
        'user_name',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'duration_hours' => 'integer',
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
            'stage',
            'status',
            'previous_status',
            'entered_at',
            'user_address',
        ];
    }
}
