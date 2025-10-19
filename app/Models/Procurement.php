<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Procurement Model
 *
 * Minimal model for tracking blockchain publication status.
 * Source of truth is MultiChain blockchain - this model only tracks sync status.
 */
class Procurement extends Model
{
    use HasFactory;
    /**
     * Primary key type (non-integer string ID like PR-2025-0001-0001)
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'title',
        'stage',
        'current_status',
        'user_address',
        'document_count',
        'last_updated',
        'blockchain_txid',
        'blockchain_status',
        'blockchain_status_updated_at',
        'blockchain_error',
        'blockchain_retry_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'last_updated' => 'datetime',
            'blockchain_status_updated_at' => 'datetime',
            'document_count' => 'integer',
            'blockchain_retry_count' => 'integer',
        ];
    }

    /**
     * Get the documents for the procurement.
     */
    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProcurementDocument::class, 'procurement_id', 'id');
    }
}
