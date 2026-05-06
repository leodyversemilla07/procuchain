<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use Carbon\Carbon;

/**
 * Procurement Data Transfer Object
 *
 * Represents immutable procurement metadata stored on blockchain.
 * This DTO contains data that spans the entire procurement lifecycle
 * across all three phases (Pre-Procurement, Procurement, Post-Procurement).
 * Fields are populated progressively as the procurement advances through stages.
 *
 * @see StageEnums For the complete procurement workflow stages
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

    /**
     * Convert the procurement data to a blockchain-compatible array format.
     *
     * Converts all properties to snake_case keys and serializes dates to ISO 8601 format
     * for storage on the blockchain. Enum values are converted to their string representations.
     *
     * @return array<string, mixed> The procurement data formatted for blockchain storage
     */
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

    /**
     * Create a ProcurementData instance from blockchain array data.
     *
     * Reconstructs a ProcurementData DTO from blockchain-stored data, handling type conversions
     * and flexible field structures (some fields may be stored as strings, objects, or arrays).
     * Missing fields are filled with sensible defaults.
     *
     * @param  array<string, mixed>  $data  The raw blockchain data with snake_case keys
     * @return self A new ProcurementData instance
     */
    public static function fromBlockchainArray(array $data): self
    {
        // Extract PR number from data
        $prNumber = $data['pr_number'] ?? '';

        // Handle prepared_by as either string or array
        $preparedBy = $data['prepared_by'] ?? null;
        if (is_array($preparedBy)) {
            $preparedBy = $preparedBy['name'] ?? ($preparedBy['id'] ?? null);
        }

        // Handle approved_by as either string or array
        $approvedBy = $data['approved_by'] ?? null;
        if (is_array($approvedBy)) {
            $approvedBy = $approvedBy['name'] ?? ($approvedBy['id'] ?? null);
        }

        // Handle user_id as either string or array
        $userId = $data['user_id'] ?? '';
        if (is_array($userId)) {
            $userId = (string) ($userId['id'] ?? '');
        }

        return new self(
            prNumber: $prNumber,
            appReference: $data['app_reference'] ?? null,
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            abcAmount: (float) ($data['abc_amount'] ?? 0),
            fundingSource: $data['funding_source'] ?? '',
            category: ProcurementCategoryEnums::from($data['category'] ?? 'goods'),
            procurementMode: ProcurementModeEnums::from($data['procurement_mode'] ?? 'competitive_bidding'),
            office: $data['office'] ?? '',
            endUser: $data['end_user'] ?? null,
            deliveryLocation: $data['delivery_location'] ?? null,
            deliveryDate: isset($data['delivery_date']) ? Carbon::parse($data['delivery_date']) : null,
            deliveryTermDays: $data['delivery_term_days'] ?? null,
            preparedBy: $preparedBy !== null ? (string) $preparedBy : null,
            bacResolutionNumber: $data['bac_resolution_number'] ?? null,
            bacResolutionDate: isset($data['bac_resolution_date']) ? Carbon::parse($data['bac_resolution_date']) : null,
            philgepsReference: $data['philgeps_reference'] ?? null,
            philgepsPostingDate: isset($data['philgeps_posting_date']) ? Carbon::parse($data['philgeps_posting_date']) : null,
            approvedBy: $approvedBy !== null ? (string) $approvedBy : null,
            approvalDate: isset($data['approval_date']) ? Carbon::parse($data['approval_date']) : null,
            status: $data['status'] ?? 'draft',
            userId: (string) $userId,
            createdAt: Carbon::parse($data['created_at'] ?? now()),
        );
    }

    /**
     * Create ProcurementData from array (convenience alias for fromBlockchainArray).
     *
     * Provides a more semantic way to instantiate ProcurementData from raw array data.
     *
     * @param  array<string, mixed>  $data  The raw data with snake_case keys
     * @return self A new ProcurementData instance
     */
    public static function fromArray(array $data): self
    {
        return self::fromBlockchainArray($data);
    }

    /**
     * Check if this procurement requires PhilGEPS posting.
     *
     * Delegates to the procurement mode's requirements per RA 9184.
     *
     * @return bool True if PhilGEPS posting is required for this procurement mode
     */
    public function requiresPhilGEPS(): bool
    {
        return $this->procurementMode->requiresPhilGEPS();
    }

    /**
     * Check if this procurement requires BAC (Bids and Awards Committee) resolution.
     *
     * Delegates to the procurement mode's requirements per RA 9184.
     *
     * @return bool True if BAC resolution is required for this procurement mode
     */
    public function requiresBACResolution(): bool
    {
        return $this->procurementMode->requiresBACResolution();
    }

    /**
     * Check if procurement has been approved (Notice of Award stage).
     *
     * Validates that both the approving authority and approval date are recorded,
     * indicating the Notice of Award has been issued.
     *
     * @return bool True if both approvedBy and approvalDate are set
     */
    public function isApproved(): bool
    {
        return $this->approvedBy !== null && $this->approvalDate !== null;
    }

    /**
     * Check if BAC (Bids and Awards Committee) resolution has been recorded.
     *
     * Validates that both the resolution number and date are set, indicating official
     * BAC approval of the procurement process.
     *
     * @return bool True if both bacResolutionNumber and bacResolutionDate are set
     */
    public function hasBACResolution(): bool
    {
        return $this->bacResolutionNumber !== null && $this->bacResolutionDate !== null;
    }

    /**
     * Check if procurement has been posted to PhilGEPS.
     *
     * Validates that both the PhilGEPS reference and posting date are recorded,
     * indicating the procurement has been publicly advertised on the Philippine Government
     * Procurement Portal.
     *
     * @return bool True if both philgepsReference and philgepsPostingDate are set
     */
    public function isPostedToPhilGEPS(): bool
    {
        return $this->philgepsReference !== null && $this->philgepsPostingDate !== null;
    }

    /**
     * Infer the current procurement phase based on populated fields.
     *
     * Provides data-driven phase detection by examining which fields have been populated.
     * Returns the most advanced phase indicated by the data:
     * - If approved: post_procurement
     * - If posted to PhilGEPS or BAC resolution exists: procurement
     * - Otherwise: pre_procurement
     *
     * @return string One of: 'pre_procurement', 'procurement', or 'post_procurement'
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
     * Get missing required fields for PhilGEPS posting.
     *
     * Returns an array of field names that are required for PhilGEPS posting but are
     * currently not set. Only returns fields if this procurement mode requires PhilGEPS.
     *
     * @return array<string> Field names that are missing and required
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
     * Get missing required fields for BAC resolution.
     *
     * Returns an array of field names that are required for BAC resolution but are
     * currently not set. Only returns fields if this procurement mode requires BAC resolution.
     *
     * @return array<string> Field names that are missing and required
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
     * Format the ABC (Approved Budget for Contract) amount as Philippine currency.
     *
     * Converts the numeric ABC amount to a human-readable Philippine Peso format.
     *
     * @return string The formatted amount (e.g., '₱ 1,000,000.00')
     */
    public function getFormattedAbcAmount(): string
    {
        return '₱ '.number_format($this->abcAmount, 2);
    }

    /**
     * Format the delivery date to a human-readable format.
     *
     * @return string|null The formatted date (e.g., 'Dec 8, 2025'), or null if not set
     */
    public function getFormattedDeliveryDate(): ?string
    {
        return $this->deliveryDate?->format('M j, Y');
    }

    /**
     * Format the BAC resolution date to a human-readable format.
     *
     * @return string|null The formatted date (e.g., 'Dec 8, 2025'), or null if not set
     */
    public function getFormattedBacResolutionDate(): ?string
    {
        return $this->bacResolutionDate?->format('M j, Y');
    }

    /**
     * Format the PhilGEPS posting date to a human-readable format.
     *
     * @return string|null The formatted date (e.g., 'Dec 8, 2025'), or null if not set
     */
    public function getFormattedPhilgepsPostingDate(): ?string
    {
        return $this->philgepsPostingDate?->format('M j, Y');
    }

    /**
     * Format the approval date to a human-readable format.
     *
     * @return string|null The formatted date (e.g., 'Dec 8, 2025'), or null if not set
     */
    public function getFormattedApprovalDate(): ?string
    {
        return $this->approvalDate?->format('M j, Y');
    }

    /**
     * Format the creation timestamp to a human-readable format with time.
     *
     * @return string The formatted datetime (e.g., 'Dec 8, 2025, 2:30 PM')
     */
    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt->format('M j, Y, g:i A');
    }
}
