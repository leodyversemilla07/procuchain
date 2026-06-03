<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProcurementDocument Model
 *
 * Document metadata synced FROM blockchain.
 * Source: procurement.documents stream
 */
class ProcurementDocument extends Model
{
    use SoftDeletes;

    protected $table = 'procurement_documents';

    protected $fillable = [
        'procurement_id',
        'document_type',
        'stage',
        'filename',
        'file_key',
        'mime_type',
        'file_size',
        'hash',
        'description',
        'uploaded_by',
        'user_address',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'is_active',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'is_active' => 'boolean',
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
            'document_type',
            'filename',
            'file_key',
            'hash',
            'uploaded_by',
            'uploaded_at',
        ];
    }
}
