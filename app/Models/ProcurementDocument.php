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
 *
 * @property int $id
 * @property string $procurement_id Foreign key to procurements table
 * @property string $file_key Unique file identifier/path in storage
 * @property string $file_name Original filename
 * @property string $document_type Type/category of document
 * @property string $stage Procurement stage when uploaded
 * @property array|null $metadata Additional document metadata (JSON)
 * @property string|null $blockchain_txid Blockchain transaction ID
 * @property string $blockchain_status Status: pending|published|failed
 * @property \Illuminate\Support\Carbon|null $blockchain_status_updated_at
 * @property string|null $blockchain_error Error message if publication failed
 * @property int $blockchain_retry_count Number of retry attempts
 * @property bool $is_corrected Whether document has been corrected
 * @property string|null $correction_reason Reason for correction
 * @property \Illuminate\Support\Carbon|null $corrected_at When correction was made
 * @property string|null $corrected_by Who made the correction
 * @property string|null $correction_txid Blockchain transaction ID for correction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Procurement $procurement
 *
 * @method static \Database\Factories\ProcurementDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument whereProcurementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument whereFileKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument whereBlockchainStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProcurementDocument whereStage($value)
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
