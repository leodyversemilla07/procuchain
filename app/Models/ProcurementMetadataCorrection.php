<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
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

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->procurement?->pr_number ?? '',
            'procurement_title' => $this->procurement?->title ?? '',
            'correction_type' => $this->correction_type,
            'reason' => $this->reason,
            'corrected_by' => $this->corrected_by,
            'user_address' => $this->user_address ?? '',
            'timestamp' => $this->corrected_at?->toIso8601String() ?? now()->toIso8601String(),
            'original_title' => $this->original_title,
            'original_description' => $this->original_description,
            'original_abc_amount' => $this->original_abc_amount !== null ? (string) $this->original_abc_amount : null,
            'original_funding_source' => $this->original_funding_source,
            'original_category' => $this->original_category,
            'original_procurement_mode' => $this->original_procurement_mode,
            'original_office' => $this->original_office,
            'original_end_user' => $this->original_end_user,
            'original_delivery_location' => $this->original_delivery_location,
            'original_delivery_date' => $this->original_delivery_date?->toIso8601String(),
            'original_delivery_term_days' => $this->original_delivery_term_days,
            'original_bac_resolution_number' => $this->original_bac_resolution_number,
            'original_bac_resolution_date' => $this->original_bac_resolution_date?->toIso8601String(),
            'original_philgeps_reference' => $this->original_philgeps_reference,
            'original_philgeps_posting_date' => $this->original_philgeps_posting_date?->toIso8601String(),
            'original_approved_by' => $this->original_approved_by,
            'original_approval_date' => $this->original_approval_date?->toIso8601String(),
            'corrected_title' => $this->corrected_title,
            'corrected_description' => $this->corrected_description,
            'corrected_abc_amount' => $this->corrected_abc_amount !== null ? (string) $this->corrected_abc_amount : null,
            'corrected_funding_source' => $this->corrected_funding_source,
            'corrected_category' => $this->corrected_category,
            'corrected_procurement_mode' => $this->corrected_procurement_mode,
            'corrected_office' => $this->corrected_office,
            'corrected_end_user' => $this->corrected_end_user,
            'corrected_delivery_location' => $this->corrected_delivery_location,
            'corrected_delivery_date' => $this->corrected_delivery_date?->toIso8601String(),
            'corrected_delivery_term_days' => $this->corrected_delivery_term_days,
            'corrected_bac_resolution_number' => $this->corrected_bac_resolution_number,
            'corrected_bac_resolution_date' => $this->corrected_bac_resolution_date?->toIso8601String(),
            'corrected_philgeps_reference' => $this->corrected_philgeps_reference,
            'corrected_philgeps_posting_date' => $this->corrected_philgeps_posting_date?->toIso8601String(),
            'corrected_approved_by' => $this->corrected_approved_by,
            'corrected_approval_date' => $this->corrected_approval_date?->toIso8601String(),
        ];
    }

    public static function fromBlockchainArray(array $data, string $txid): self
    {
        $model = new static;

        $correctedBy = $data['corrected_by'] ?? '';
        if (is_array($correctedBy)) {
            $correctedBy = $correctedBy['name'] ?? ($correctedBy['id'] ?? '');
        }

        $model->correction_type = $data['correction_type'] ?? '';
        $model->reason = $data['reason'] ?? '';
        $model->corrected_by = (string) $correctedBy;
        $model->user_address = $data['user_address'] ?? '';
        $model->corrected_at = isset($data['timestamp']) ? Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila') : now();
        $model->txid = $txid;

        $model->original_title = $data['original_title'] ?? null;
        $model->original_description = $data['original_description'] ?? null;
        $model->original_abc_amount = isset($data['original_abc_amount']) ? (float) $data['original_abc_amount'] : null;
        $model->original_funding_source = $data['original_funding_source'] ?? null;
        $model->original_category = $data['original_category'] ?? null;
        $model->original_procurement_mode = $data['original_procurement_mode'] ?? null;
        $model->original_office = $data['original_office'] ?? null;
        $model->original_end_user = $data['original_end_user'] ?? null;
        $model->original_delivery_location = $data['original_delivery_location'] ?? null;
        $model->original_delivery_date = isset($data['original_delivery_date']) ? Carbon::parse($data['original_delivery_date']) : null;
        $model->original_delivery_term_days = $data['original_delivery_term_days'] ?? null;
        $model->original_bac_resolution_number = $data['original_bac_resolution_number'] ?? null;
        $model->original_bac_resolution_date = isset($data['original_bac_resolution_date']) ? Carbon::parse($data['original_bac_resolution_date']) : null;
        $model->original_philgeps_reference = $data['original_philgeps_reference'] ?? null;
        $model->original_philgeps_posting_date = isset($data['original_philgeps_posting_date']) ? Carbon::parse($data['original_philgeps_posting_date']) : null;
        $model->original_approved_by = $data['original_approved_by'] ?? null;
        $model->original_approval_date = isset($data['original_approval_date']) ? Carbon::parse($data['original_approval_date']) : null;

        $model->corrected_title = $data['corrected_title'] ?? null;
        $model->corrected_description = $data['corrected_description'] ?? null;
        $model->corrected_abc_amount = isset($data['corrected_abc_amount']) ? (float) $data['corrected_abc_amount'] : null;
        $model->corrected_funding_source = $data['corrected_funding_source'] ?? null;
        $model->corrected_category = $data['corrected_category'] ?? null;
        $model->corrected_procurement_mode = $data['corrected_procurement_mode'] ?? null;
        $model->corrected_office = $data['corrected_office'] ?? null;
        $model->corrected_end_user = $data['corrected_end_user'] ?? null;
        $model->corrected_delivery_location = $data['corrected_delivery_location'] ?? null;
        $model->corrected_delivery_date = isset($data['corrected_delivery_date']) ? Carbon::parse($data['corrected_delivery_date']) : null;
        $model->corrected_delivery_term_days = $data['corrected_delivery_term_days'] ?? null;
        $model->corrected_bac_resolution_number = $data['corrected_bac_resolution_number'] ?? null;
        $model->corrected_bac_resolution_date = isset($data['corrected_bac_resolution_date']) ? Carbon::parse($data['corrected_bac_resolution_date']) : null;
        $model->corrected_philgeps_reference = $data['corrected_philgeps_reference'] ?? null;
        $model->corrected_philgeps_posting_date = isset($data['corrected_philgeps_posting_date']) ? Carbon::parse($data['corrected_philgeps_posting_date']) : null;
        $model->corrected_approved_by = $data['corrected_approved_by'] ?? null;
        $model->corrected_approval_date = isset($data['corrected_approval_date']) ? Carbon::parse($data['corrected_approval_date']) : null;

        return $model;
    }

    public function getChangedFields(): array
    {
        $changes = [];
        $fields = [
            'title' => [$this->original_title, $this->corrected_title],
            'description' => [$this->original_description, $this->corrected_description],
            'abcAmount' => [$this->original_abc_amount, $this->corrected_abc_amount],
            'fundingSource' => [$this->original_funding_source, $this->corrected_funding_source],
            'category' => [$this->original_category, $this->corrected_category],
            'procurementMode' => [$this->original_procurement_mode, $this->corrected_procurement_mode],
            'office' => [$this->original_office, $this->corrected_office],
            'endUser' => [$this->original_end_user, $this->corrected_end_user],
            'deliveryLocation' => [$this->original_delivery_location, $this->corrected_delivery_location],
            'deliveryDate' => [$this->original_delivery_date?->toIso8601String(), $this->corrected_delivery_date?->toIso8601String()],
            'deliveryTermDays' => [$this->original_delivery_term_days, $this->corrected_delivery_term_days],
            'bacResolutionNumber' => [$this->original_bac_resolution_number, $this->corrected_bac_resolution_number],
            'bacResolutionDate' => [$this->original_bac_resolution_date?->toIso8601String(), $this->corrected_bac_resolution_date?->toIso8601String()],
            'philgepsReference' => [$this->original_philgeps_reference, $this->corrected_philgeps_reference],
            'philgepsPostingDate' => [$this->original_philgeps_posting_date?->toIso8601String(), $this->corrected_philgeps_posting_date?->toIso8601String()],
            'approvedBy' => [$this->original_approved_by, $this->corrected_approved_by],
            'approvalDate' => [$this->original_approval_date?->toIso8601String(), $this->corrected_approval_date?->toIso8601String()],
        ];

        foreach ($fields as $field => $values) {
            [$original, $corrected] = $values;
            if ($original !== $corrected && $corrected !== null) {
                $changes[$field] = [
                    'original' => $original,
                    'corrected' => $corrected,
                ];
            }
        }

        return $changes;
    }

    public function hasChanges($changes = null, $attributes = null): bool
    {
        return ! empty($this->getChangedFields());
    }
}
