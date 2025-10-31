<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Procurement Model
 *
 * Minimal model for tracking blockchain publication status.
 * Source of truth is MultiChain blockchain - this model only tracks sync status.
 * Uses custom string-based primary keys (e.g., PR-2025-0001-0001).
 *
 * @property string $id Custom procurement ID (format: PR-YYYY-####-####)
 * @property string $title Procurement title/name
 * @property string $stage Current procurement stage
 * @property string $current_status Current status
 * @property string $user_address Blockchain address of user managing procurement
 * @property int $document_count Number of associated documents
 * @property \Illuminate\Support\Carbon $last_updated Last update timestamp
 * @property string|null $blockchain_txid Blockchain transaction ID
 * @property string $blockchain_status Status: pending|published|failed
 * @property \Illuminate\Support\Carbon|null $blockchain_status_updated_at
 * @property string|null $blockchain_error Error message if publication failed
 * @property int $blockchain_retry_count Number of retry attempts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProcurementDocument> $documents
 *
 * @method static \Database\Factories\ProcurementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement query()
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement whereStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Procurement whereBlockchainStatus($value)
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
