<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementMetadataCorrection Model
 *
 * Metadata corrections synced FROM blockchain.
 * Source: procurement.metadata.corrections stream
 */
class ProcurementMetadataCorrection extends Model
{
    protected $table = 'procurement_metadata_corrections';

    protected $fillable = [
        'procurement_id',
        'correction_type',
        'reason',
        'corrected_by',
        'user_address',
        // Original values
        'original_title',
        'original_description',
        'original_abc_amount',
        'original_funding_source',
        'original_category',
        'original_procurement_mode',
        'original_office',
        'original_end_user',
        'original_delivery_date',
        'original_bac_resolution_number',
        'original_bac_resolution_date',
        'original_approved_by',
        'original_approval_date',
        // Corrected values
        'corrected_title',
        'corrected_description',
        'corrected_abc_amount',
        'corrected_funding_source',
        'corrected_category',
        'corrected_procurement_mode',
        'corrected_office',
        'corrected_end_user',
        'corrected_delivery_date',
        'corrected_bac_resolution_number',
        'corrected_bac_resolution_date',
        'corrected_approved_by',
        'corrected_approval_date',
        // Blockchain
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'corrected_at',
    ];

    protected $casts = [
        'original_abc_amount' => 'decimal:2',
        'original_delivery_date' => 'date',
        'original_bac_resolution_date' => 'date',
        'original_approval_date' => 'date',
        'corrected_abc_amount' => 'decimal:2',
        'corrected_delivery_date' => 'date',
        'corrected_bac_resolution_date' => 'date',
        'corrected_approval_date' => 'date',
        'corrected_at' => 'datetime',
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
            'correction_type',
            'reason',
            'corrected_by',
            'corrected_at',
        ];
    }
}
