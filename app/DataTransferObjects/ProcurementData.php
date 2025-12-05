<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use Carbon\Carbon;

/**
 * Procurement Data Transfer Object
 *
 * Represents immutable procurement metadata stored on blockchain.
 * This DTO contains data that spans the entire procurement lifecycle
 * across all three phases (Pre-Procurement, Procurement, Post-Procurement).
 * Fields are populated progressively as the procurement advances through stages.
 *
 * @see \App\Enums\StageEnums For the complete procurement workflow stages
 */
final class ProcurementData
{
    public function __construct(
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: PRE-PROCUREMENT - Procurement Initiation Stage
        // ═══════════════════════════════════════════════════════════════════
        // Created at: StageEnums::PROCUREMENT_INITIATION
        // Required fields per RA 9184 IRR-A Section 7
        public readonly string $prNumber,
        public readonly ?string $appReference,
        public readonly string $title,
        public readonly string $description,
        public readonly float $abcAmount,
        public readonly string $fundingSource,
        public readonly ProcurementCategoryEnums $category,
        public readonly ProcurementModeEnums $procurementMode,
        public readonly string $office,
        public readonly ?string $endUser,
        // Note: Delivery details are populated at Contract Implementation stage per NGPA IRR Section 71
        // They are nullable at Procurement Initiation but required before contract signing
        public readonly ?string $deliveryLocation,
        public readonly ?Carbon $deliveryDate,
        public readonly ?int $deliveryTermDays,
        public readonly ?string $preparedBy,

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: PRE-PROCUREMENT - BAC Resolution Stage
        // ═══════════════════════════════════════════════════════════════════
        // Populated at: StageEnums::BAC_RESOLUTION
        // Required for certain procurement modes per RA 9184
        public readonly ?string $bacResolutionNumber,
        public readonly ?Carbon $bacResolutionDate,

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: PRE-PROCUREMENT - Bidding Documents Stage
        // ═══════════════════════════════════════════════════════════════════
        // Populated at: StageEnums::BIDDING_DOCUMENTS
        // PhilGEPS posting required for Public Bidding per RA 9184
        public readonly ?string $philgepsReference,
        public readonly ?Carbon $philgepsPostingDate,

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: PROCUREMENT - Bidding & Evaluation Stages
        // ═══════════════════════════════════════════════════════════════════
        // No additional DTO fields for Phase 2 (document-based stages only)
        // Stages: Pre-Bid Conference, Bid Opening, Bid Evaluation, Post-Qualification

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: POST-PROCUREMENT - Notice of Award Stage
        // ═══════════════════════════════════════════════════════════════════
        // Populated at: StageEnums::NOTICE_OF_AWARD
        // Contains HoPE (Head of Procuring Entity) approval details
        public readonly ?string $approvedBy,
        public readonly ?Carbon $approvalDate,

        // ═══════════════════════════════════════════════════════════════════
        // METADATA - Throughout Procurement Lifecycle
        // ═══════════════════════════════════════════════════════════════════
        // Status changes as procurement progresses through stages
        public readonly string $status,
        public readonly string $userId,
        public readonly Carbon $createdAt,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'app_reference' => $this->appReference,
            'title' => $this->title,
            'description' => $this->description,
            'abc_amount' => (string) $this->abcAmount,
            'funding_source' => $this->fundingSource,
            'category' => $this->category->value,
            'procurement_mode' => $this->procurementMode->value,
            'office' => $this->office,
            'end_user' => $this->endUser,
            'delivery_location' => $this->deliveryLocation,
            'delivery_date' => $this->deliveryDate?->toIso8601String(),
            'delivery_term_days' => $this->deliveryTermDays,
            'prepared_by' => $this->preparedBy,
            'bac_resolution_number' => $this->bacResolutionNumber,
            'bac_resolution_date' => $this->bacResolutionDate?->toIso8601String(),
            'philgeps_reference' => $this->philgepsReference,
            'philgeps_posting_date' => $this->philgepsPostingDate?->toIso8601String(),
            'approved_by' => $this->approvedBy,
            'approval_date' => $this->approvalDate?->toIso8601String(),
            'status' => $this->status,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        // Extract PR number from data
        $prNumber = $data['pr_number'] ?? '';

        return new self(
            prNumber: $prNumber,
            appReference: $data['app_reference'] ?? null,
            title: $data['title'],
            description: $data['description'],
            abcAmount: (float) $data['abc_amount'],
            fundingSource: $data['funding_source'],
            category: ProcurementCategoryEnums::from($data['category']),
            procurementMode: ProcurementModeEnums::from($data['procurement_mode']),
            office: $data['office'] ?? '',
            endUser: $data['end_user'] ?? null,
            deliveryLocation: $data['delivery_location'] ?? null,
            deliveryDate: isset($data['delivery_date']) ? Carbon::parse($data['delivery_date']) : null,
            deliveryTermDays: $data['delivery_term_days'] ?? null,
            preparedBy: $data['prepared_by'] ?? null,
            bacResolutionNumber: $data['bac_resolution_number'] ?? null,
            bacResolutionDate: isset($data['bac_resolution_date']) ? Carbon::parse($data['bac_resolution_date']) : null,
            philgepsReference: $data['philgeps_reference'] ?? null,
            philgepsPostingDate: isset($data['philgeps_posting_date']) ? Carbon::parse($data['philgeps_posting_date']) : null,
            approvedBy: $data['approved_by'] ?? null,
            approvalDate: isset($data['approval_date']) ? Carbon::parse($data['approval_date']) : null,
            status: $data['status'],
            userId: $data['user_id'],
            createdAt: Carbon::parse($data['created_at']),
        );
    }

    /**
     * Create ProcurementData from array (alias for fromBlockchainArray)
     */
    public static function fromArray(array $data): self
    {
        return self::fromBlockchainArray($data);
    }

    public function requiresPhilGEPS(): bool
    {
        return $this->procurementMode->requiresPhilGEPS();
    }

    public function requiresBACResolution(): bool
    {
        return $this->procurementMode->requiresBACResolution();
    }

    /**
     * Check if procurement has been approved (Notice of Award stage)
     */
    public function isApproved(): bool
    {
        return $this->approvedBy !== null && $this->approvalDate !== null;
    }

    /**
     * Check if BAC resolution has been recorded
     */
    public function hasBACResolution(): bool
    {
        return $this->bacResolutionNumber !== null && $this->bacResolutionDate !== null;
    }

    /**
     * Check if posted to PhilGEPS
     */
    public function isPostedToPhilGEPS(): bool
    {
        return $this->philgepsReference !== null && $this->philgepsPostingDate !== null;
    }

    /**
     * Get the current phase based on populated fields
     * This provides a data-driven phase detection
     *
     * @return string 'pre_procurement', 'procurement', or 'post_procurement'
     */
    public function inferCurrentPhase(): string
    {
        // If approved, definitely in post-procurement
        if ($this->isApproved()) {
            return 'post_procurement';
        }

        // If posted to PhilGEPS or has BAC resolution, in procurement phase
        if ($this->isPostedToPhilGEPS() || $this->hasBACResolution()) {
            return 'procurement';
        }

        // Otherwise, still in pre-procurement
        return 'pre_procurement';
    }

    /**
     * Get missing required fields for PhilGEPS posting
     *
     * @return array<string>
     */
    public function getMissingPhilGEPSFields(): array
    {
        if (! $this->requiresPhilGEPS()) {
            return [];
        }

        $missing = [];

        if ($this->philgepsReference === null) {
            $missing[] = 'PhilGEPS Reference Number';
        }

        if ($this->philgepsPostingDate === null) {
            $missing[] = 'PhilGEPS Posting Date';
        }

        return $missing;
    }

    /**
     * Get missing required fields for BAC resolution
     *
     * @return array<string>
     */
    public function getMissingBACResolutionFields(): array
    {
        if (! $this->requiresBACResolution()) {
            return [];
        }

        $missing = [];

        if ($this->bacResolutionNumber === null) {
            $missing[] = 'BAC Resolution Number';
        }

        if ($this->bacResolutionDate === null) {
            $missing[] = 'BAC Resolution Date';
        }

        return $missing;
    }

    /**
     * Format ABC amount to currency
     */
    public function getFormattedAbcAmount(): string
    {
        return '₱ '.number_format($this->abcAmount, 2);
    }

    /**
     * Format delivery date to readable format
     */
    public function getFormattedDeliveryDate(): ?string
    {
        return $this->deliveryDate?->format('M j, Y');
    }

    /**
     * Format BAC resolution date to readable format
     */
    public function getFormattedBacResolutionDate(): ?string
    {
        return $this->bacResolutionDate?->format('M j, Y');
    }

    /**
     * Format PhilGEPS posting date to readable format
     */
    public function getFormattedPhilgepsPostingDate(): ?string
    {
        return $this->philgepsPostingDate?->format('M j, Y');
    }

    /**
     * Format approval date to readable format
     */
    public function getFormattedApprovalDate(): ?string
    {
        return $this->approvalDate?->format('M j, Y');
    }

    /**
     * Format created at timestamp
     */
    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt->format('M j, Y, g:i A');
    }
}
