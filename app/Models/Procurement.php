<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcurementMode;
use Carbon\Carbon;
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

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->pr_number,
            'app_reference' => $this->app_reference,
            'title' => $this->title,
            'description' => $this->description,
            'abc_amount' => (string) $this->abc_amount,
            'funding_source' => $this->fund_source,
            'category' => $this->category,
            'procurement_mode' => $this->procurement_mode,
            'office' => $this->office,
            'end_user' => $this->end_user,
            'delivery_location' => $this->delivery_location,
            'delivery_date' => $this->delivery_date?->toIso8601String(),
            'delivery_term_days' => $this->delivery_term_days,
            'prepared_by' => $this->prepared_by,
            'bac_resolution_number' => $this->bac_resolution_number,
            'bac_resolution_date' => $this->bac_resolution_date?->toIso8601String(),
            'philgeps_reference' => $this->philgeps_reference,
            'philgeps_posting_date' => $this->philgeps_posting_date?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approval_date' => $this->approval_date?->toIso8601String(),
            'status' => $this->current_status ?? 'draft',
            'user_id' => (string) $this->user_id,
            'user_address' => $this->user_address,
            'created_at' => ($this->initiated_at ?? $this->created_at)?->toIso8601String(),
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        $model = new static;

        $model->pr_number = $data['pr_number'] ?? '';
        $model->app_reference = $data['app_reference'] ?? null;
        $model->title = $data['title'] ?? '';
        $model->description = $data['description'] ?? '';
        $model->abc_amount = (float) ($data['abc_amount'] ?? 0);
        $model->fund_source = $data['funding_source'] ?? '';
        $model->category = $data['category'] ?? 'goods';
        $model->procurement_mode = $data['procurement_mode'] ?? 'competitive_bidding';
        $model->office = $data['office'] ?? '';
        $model->end_user = $data['end_user'] ?? null;
        $model->delivery_location = $data['delivery_location'] ?? null;
        $model->delivery_date = isset($data['delivery_date']) ? Carbon::parse($data['delivery_date']) : null;
        $model->delivery_term_days = $data['delivery_term_days'] ?? null;

        $preparedBy = $data['prepared_by'] ?? null;
        if (is_array($preparedBy)) {
            $preparedBy = $preparedBy['name'] ?? ($preparedBy['id'] ?? null);
        }
        $model->prepared_by = $preparedBy !== null ? (string) $preparedBy : null;

        $model->bac_resolution_number = $data['bac_resolution_number'] ?? null;
        $model->bac_resolution_date = isset($data['bac_resolution_date']) ? Carbon::parse($data['bac_resolution_date']) : null;
        $model->philgeps_reference = $data['philgeps_reference'] ?? null;
        $model->philgeps_posting_date = isset($data['philgeps_posting_date']) ? Carbon::parse($data['philgeps_posting_date']) : null;

        $approvedBy = $data['approved_by'] ?? null;
        if (is_array($approvedBy)) {
            $approvedBy = $approvedBy['name'] ?? ($approvedBy['id'] ?? null);
        }
        $model->approved_by = $approvedBy !== null ? (string) $approvedBy : null;
        $model->approval_date = isset($data['approval_date']) ? Carbon::parse($data['approval_date']) : null;

        $model->current_status = $data['status'] ?? 'draft';

        $userId = $data['user_id'] ?? '';
        if (is_array($userId)) {
            $userId = (string) ($userId['id'] ?? '');
        }
        $model->user_id = (string) $userId;
        $model->user_address = $data['user_address'] ?? null;
        $model->initiated_at = isset($data['created_at']) ? Carbon::parse($data['created_at'])->setTimezone('Asia/Manila') : now();

        return $model;
    }

    public function requiresPhilGEPS(): bool
    {
        return ProcurementMode::tryFrom($this->procurement_mode)?->requiresPhilGEPS() ?? false;
    }

    public function requiresBACResolution(): bool
    {
        return ProcurementMode::tryFrom($this->procurement_mode)?->requiresBACResolution() ?? false;
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null && $this->approval_date !== null;
    }

    public function hasBACResolution(): bool
    {
        return $this->bac_resolution_number !== null && $this->bac_resolution_date !== null;
    }

    public function isPostedToPhilGEPS(): bool
    {
        return $this->philgeps_reference !== null && $this->philgeps_posting_date !== null;
    }

    public function inferCurrentPhase(): string
    {
        if ($this->isApproved()) {
            return 'post_procurement';
        }

        if ($this->isPostedToPhilGEPS() || $this->hasBACResolution()) {
            return 'procurement';
        }

        return 'pre_procurement';
    }

    public function getMissingPhilGEPSFields(): array
    {
        if (! $this->requiresPhilGEPS()) {
            return [];
        }

        $missing = [];

        if ($this->philgeps_reference === null) {
            $missing[] = 'PhilGEPS Reference Number';
        }

        if ($this->philgeps_posting_date === null) {
            $missing[] = 'PhilGEPS Posting Date';
        }

        return $missing;
    }

    public function getMissingBACResolutionFields(): array
    {
        if (! $this->requiresBACResolution()) {
            return [];
        }

        $missing = [];

        if ($this->bac_resolution_number === null) {
            $missing[] = 'BAC Resolution Number';
        }

        if ($this->bac_resolution_date === null) {
            $missing[] = 'BAC Resolution Date';
        }

        return $missing;
    }

    public function getFormattedAbcAmount(): string
    {
        return '₱ '.number_format((float) $this->abc_amount, 2);
    }

    public function getFormattedDeliveryDate(): ?string
    {
        return $this->delivery_date?->format('M j, Y');
    }

    public function getFormattedBacResolutionDate(): ?string
    {
        return $this->bac_resolution_date?->format('M j, Y');
    }

    public function getFormattedPhilgepsPostingDate(): ?string
    {
        return $this->philgeps_posting_date?->format('M j, Y');
    }

    public function getFormattedApprovalDate(): ?string
    {
        return $this->approval_date?->format('M j, Y');
    }

    public function getFormattedCreatedAt(): string
    {
        $date = $this->initiated_at ?? $this->created_at;

        return $date?->format('M j, Y, g:i A') ?? now()->format('M j, Y, g:i A');
    }
}
