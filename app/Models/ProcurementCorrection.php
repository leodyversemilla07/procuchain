<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementCorrection Model
 *
 * Correction records synced FROM blockchain.
 * Source: procurement.corrections stream
 */
class ProcurementCorrection extends Model
{
    protected $table = 'procurement_corrections';

    protected $fillable = [
        'procurement_id',
        'correction_type',
        'action',
        'reason',
        'original_txid',
        'original_document_hash',
        'corrected_by',
        'user_address',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'corrected_metadata',
        'corrected_at',
    ];

    protected $casts = [
        'corrected_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'corrected_metadata' => 'array',
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
            'correction_type',
            'action',
            'reason',
            'original_txid',
            'corrected_by',
            'corrected_at',
        ];
    }
}
