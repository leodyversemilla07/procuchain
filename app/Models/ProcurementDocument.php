<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ProcurementDocument Model
 *
 * Minimal model for tracking blockchain publication status of documents.
 * Actual document data lives on blockchain (MultiChain) and DigitalOcean Spaces.
 * This model only tracks which documents have been successfully published to blockchain.
 */
class ProcurementDocument extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'procurement_id',
        'file_key',
        'file_name',
        'document_type',
        'stage',
        'metadata',
        'blockchain_txid',
        'blockchain_status',
        'blockchain_status_updated_at',
        'blockchain_error',
        'blockchain_retry_count',
        'is_corrected',
        'correction_reason',
        'corrected_at',
        'corrected_by',
        'correction_txid',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_corrected' => 'boolean',
            'blockchain_status_updated_at' => 'datetime',
            'corrected_at' => 'datetime',
            'blockchain_retry_count' => 'integer',
        ];
    }

    /**
     * Get the procurement that owns the document.
     */
    public function procurement(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id', 'id');
    }
}
