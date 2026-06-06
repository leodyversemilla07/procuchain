<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Procurement Model
 *
 * Normalized procurement data synced FROM blockchain.
 * Blockchain is source of truth. This is a query cache.
 *
 * @property int $id
 * @property string $pr_number
 * @property string $title
 * @property string $category
 * @property string $procurement_mode
 * @property string $current_stage
 * @property string $current_status
 * @property float $abc_amount
 * @property string|null $txid - Blockchain transaction ID
 * @property string|null $user_id
 * @property string|null $data_hash - Hash computed at sync time from blockchain data
 * @property string|null $blockchain_hash - Original hash from blockchain (immutable)
 * @property bool $is_blockchain_verified
 * @property bool $has_breach
 */
class Procurement extends Model
{
    use SoftDeletes;

    protected $table = 'procurements';

    protected $fillable = [
        'pr_number',
        'app_reference',
        'title',
        'description',
        'category',
        'procurement_mode',
        'office',
        'end_user',
        'fund_source',
        'prepared_by',
        'abc_amount',
        'approved_budget',
        'contract_price',
        'delivery_location',
        'delivery_date',
        'delivery_term_days',
        'philgeps_reference',
        'philgeps_posting_date',
        'bac_resolution_number',
        'bac_resolution_date',
        'approved_by',
        'approval_date',
        'current_stage',
        'current_status',
        'previous_status',
        'stage_progress',
        'documents_count',
        'initiated_at',
        'awarded_at',
        'completed_at',
        'last_updated_at',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'user_address',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'abc_amount' => 'decimal:2',
        'approved_budget' => 'decimal:2',
        'contract_price' => 'decimal:2',
        'delivery_date' => 'date',
        'philgeps_posting_date' => 'date',
        'bac_resolution_date' => 'date',
        'approval_date' => 'date',
        'initiated_at' => 'datetime',
        'awarded_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'is_active' => 'boolean',
        'stage_progress' => 'integer',
        'documents_count' => 'integer',
        'delivery_term_days' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────────

    public function stages(): HasMany
    {
        return $this->hasMany(ProcurementStage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProcurementEvent::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ProcurementCorrection::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithBreaches($query)
    {
        return $query->where('has_breach', true);
    }

    public function scopeForPrNumber($query, string $prNumber)
    {
        return $query->where('pr_number', $prNumber);
    }

    /**
     * Get the fields that are included in the hash for integrity verification.
     * These fields are extracted from blockchain data_json.
     */
    public static function getHashableFields(): array
    {
        return [
            'pr_number',
            'app_reference',
            'title',
            'description',
            'category',
            'procurement_mode',
            'office',
            'end_user',
            'fund_source',
            'prepared_by',
            'abc_amount',
            'approved_budget',
            'contract_price',
            'delivery_location',
            'delivery_date',
            'delivery_term_days',
            'philgeps_reference',
            'philgeps_posting_date',
            'bac_resolution_number',
            'bac_resolution_date',
            'approved_by',
            'approval_date',
            'user_address',
            'user_id',
            'initiated_at',
        ];
    }
}
