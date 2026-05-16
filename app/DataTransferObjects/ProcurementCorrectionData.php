<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use Carbon\Carbon;

/**
 * Procurement Correction Data Transfer Object
 *
 * Represents corrections to procurement metadata that can be applied
 * at the procurement level (not document level). This allows correcting
 * procurement details like title, ABC amount, dates, etc.
 */
final class ProcurementCorrectionData
{
    public function __construct(
        public readonly string $txid,
        public readonly string $prNumber,
        public readonly string $procurementTitle,
        public readonly string $correctionType, // 'metadata', 'financial', 'dates', 'approval'
        public readonly string $reason,
        public readonly string $correctedBy,
        public readonly string $userAddress,
        public readonly Carbon $timestamp,

        // Original values (for audit trail)
        public readonly ?string $originalTitle,
        public readonly ?string $originalDescription,
        public readonly ?float $originalAbcAmount,
        public readonly ?string $originalFundingSource,
        public readonly ?ProcurementCategoryEnums $originalCategory,
        public readonly ?ProcurementModeEnums $originalProcurementMode,
        public readonly ?string $originalOffice,
        public readonly ?string $originalEndUser,
        public readonly ?string $originalPurpose,
        public readonly ?string $originalDeliveryLocation,
        public readonly ?Carbon $originalDeliveryDate,
        public readonly ?int $originalDeliveryTermDays,
        public readonly ?string $originalBacResolutionNumber,
        public readonly ?Carbon $originalBacResolutionDate,
        public readonly ?string $originalPhilgepsReference,
        public readonly ?Carbon $originalPhilgepsPostingDate,
        public readonly ?string $originalApprovedBy,
        public readonly ?Carbon $originalApprovalDate,

        // Corrected values
        public readonly ?string $correctedTitle,
        public readonly ?string $correctedDescription,
        public readonly ?float $correctedAbcAmount,
        public readonly ?string $correctedFundingSource,
        public readonly ?ProcurementCategoryEnums $correctedCategory,
        public readonly ?ProcurementModeEnums $correctedProcurementMode,
        public readonly ?string $correctedOffice,
        public readonly ?string $correctedEndUser,
        public readonly ?string $correctedPurpose,
        public readonly ?string $correctedDeliveryLocation,
        public readonly ?Carbon $correctedDeliveryDate,
        public readonly ?int $correctedDeliveryTermDays,
        public readonly ?string $correctedBacResolutionNumber,
        public readonly ?Carbon $correctedBacResolutionDate,
        public readonly ?string $correctedPhilgepsReference,
        public readonly ?Carbon $correctedPhilgepsPostingDate,
        public readonly ?string $correctedApprovedBy,
        public readonly ?Carbon $correctedApprovalDate,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'procurement_title' => $this->procurementTitle,
            'correction_type' => $this->correctionType,
            'reason' => $this->reason,
            'corrected_by' => $this->correctedBy,
            'user_address' => $this->userAddress,
            'timestamp' => $this->timestamp->toIso8601String(),

            // Original values
            'original_title' => $this->originalTitle,
            'original_description' => $this->originalDescription,
            'original_abc_amount' => $this->originalAbcAmount !== null ? (string) $this->originalAbcAmount : null,
            'original_funding_source' => $this->originalFundingSource,
            'original_category' => $this->originalCategory?->value,
            'original_procurement_mode' => $this->originalProcurementMode?->value,
            'original_office' => $this->originalOffice,
            'original_end_user' => $this->originalEndUser,
            'original_purpose' => $this->originalPurpose,
            'original_delivery_location' => $this->originalDeliveryLocation,
            'original_delivery_date' => $this->originalDeliveryDate?->toIso8601String(),
            'original_delivery_term_days' => $this->originalDeliveryTermDays,
            'original_bac_resolution_number' => $this->originalBacResolutionNumber,
            'original_bac_resolution_date' => $this->originalBacResolutionDate?->toIso8601String(),
            'original_philgeps_reference' => $this->originalPhilgepsReference,
            'original_philgeps_posting_date' => $this->originalPhilgepsPostingDate?->toIso8601String(),
            'original_approved_by' => $this->originalApprovedBy,
            'original_approval_date' => $this->originalApprovalDate?->toIso8601String(),

            // Corrected values
            'corrected_title' => $this->correctedTitle,
            'corrected_description' => $this->correctedDescription,
            'corrected_abc_amount' => $this->correctedAbcAmount !== null ? (string) $this->correctedAbcAmount : null,
            'corrected_funding_source' => $this->correctedFundingSource,
            'corrected_category' => $this->correctedCategory?->value,
            'corrected_procurement_mode' => $this->correctedProcurementMode?->value,
            'corrected_office' => $this->correctedOffice,
            'corrected_end_user' => $this->correctedEndUser,
            'corrected_purpose' => $this->correctedPurpose,
            'corrected_delivery_location' => $this->correctedDeliveryLocation,
            'corrected_delivery_date' => $this->correctedDeliveryDate?->toIso8601String(),
            'corrected_delivery_term_days' => $this->correctedDeliveryTermDays,
            'corrected_bac_resolution_number' => $this->correctedBacResolutionNumber,
            'corrected_bac_resolution_date' => $this->correctedBacResolutionDate?->toIso8601String(),
            'corrected_philgeps_reference' => $this->correctedPhilgepsReference,
            'corrected_philgeps_posting_date' => $this->correctedPhilgepsPostingDate?->toIso8601String(),
            'corrected_approved_by' => $this->correctedApprovedBy,
            'corrected_approval_date' => $this->correctedApprovalDate?->toIso8601String(),
        ];
    }

    public static function fromBlockchainArray(array $data, string $txid): self
    {
        // Handle corrected_by as either string or array
        $correctedBy = $data['corrected_by'] ?? '';
        if (is_array($correctedBy)) {
            $correctedBy = $correctedBy['name'] ?? ($correctedBy['id'] ?? '');
        }

        return new self(
            txid: $txid,
            prNumber: $data['pr_number'] ?? '',
            procurementTitle: $data['procurement_title'] ?? '',
            correctionType: $data['correction_type'] ?? '',
            reason: $data['reason'] ?? '',
            correctedBy: (string) $correctedBy,
            userAddress: $data['user_address'] ?? '',
            timestamp: Carbon::parse($data['timestamp'] ?? now())->setTimezone('Asia/Manila'),

            // Original values
            originalTitle: $data['original_title'] ?? null,
            originalDescription: $data['original_description'] ?? null,
            originalAbcAmount: isset($data['original_abc_amount']) ? (float) $data['original_abc_amount'] : null,
            originalFundingSource: $data['original_funding_source'] ?? null,
            originalCategory: isset($data['original_category']) ? ProcurementCategoryEnums::from($data['original_category']) : null,
            originalProcurementMode: isset($data['original_procurement_mode']) ? ProcurementModeEnums::from($data['original_procurement_mode']) : null,
            originalOffice: $data['original_office'] ?? null,
            originalEndUser: $data['original_end_user'] ?? null,
            originalPurpose: $data['original_purpose'] ?? null,
            originalDeliveryLocation: $data['original_delivery_location'] ?? null,
            originalDeliveryDate: isset($data['original_delivery_date']) ? Carbon::parse($data['original_delivery_date']) : null,
            originalDeliveryTermDays: $data['original_delivery_term_days'] ?? null,
            originalBacResolutionNumber: $data['original_bac_resolution_number'] ?? null,
            originalBacResolutionDate: isset($data['original_bac_resolution_date']) ? Carbon::parse($data['original_bac_resolution_date']) : null,
            originalPhilgepsReference: $data['original_philgeps_reference'] ?? null,
            originalPhilgepsPostingDate: isset($data['original_philgeps_posting_date']) ? Carbon::parse($data['original_philgeps_posting_date']) : null,
            originalApprovedBy: $data['original_approved_by'] ?? null,
            originalApprovalDate: isset($data['original_approval_date']) ? Carbon::parse($data['original_approval_date']) : null,

            // Corrected values
            correctedTitle: $data['corrected_title'] ?? null,
            correctedDescription: $data['corrected_description'] ?? null,
            correctedAbcAmount: isset($data['corrected_abc_amount']) ? (float) $data['corrected_abc_amount'] : null,
            correctedFundingSource: $data['corrected_funding_source'] ?? null,
            correctedCategory: isset($data['corrected_category']) ? ProcurementCategoryEnums::from($data['corrected_category']) : null,
            correctedProcurementMode: isset($data['corrected_procurement_mode']) ? ProcurementModeEnums::from($data['corrected_procurement_mode']) : null,
            correctedOffice: $data['corrected_office'] ?? null,
            correctedEndUser: $data['corrected_end_user'] ?? null,
            correctedPurpose: $data['corrected_purpose'] ?? null,
            correctedDeliveryLocation: $data['corrected_delivery_location'] ?? null,
            correctedDeliveryDate: isset($data['corrected_delivery_date']) ? Carbon::parse($data['corrected_delivery_date']) : null,
            correctedDeliveryTermDays: $data['corrected_delivery_term_days'] ?? null,
            correctedBacResolutionNumber: $data['corrected_bac_resolution_number'] ?? null,
            correctedBacResolutionDate: isset($data['corrected_bac_resolution_date']) ? Carbon::parse($data['corrected_bac_resolution_date']) : null,
            correctedPhilgepsReference: $data['corrected_philgeps_reference'] ?? null,
            correctedPhilgepsPostingDate: isset($data['corrected_philgeps_posting_date']) ? Carbon::parse($data['corrected_philgeps_posting_date']) : null,
            correctedApprovedBy: $data['corrected_approved_by'] ?? null,
            correctedApprovalDate: isset($data['corrected_approval_date']) ? Carbon::parse($data['corrected_approval_date']) : null,
        );
    }

    /**
     * Get the fields that were actually changed
     */
    public function getChangedFields(): array
    {
        $changes = [];

        $fields = [
            'title' => [$this->originalTitle, $this->correctedTitle],
            'description' => [$this->originalDescription, $this->correctedDescription],
            'abcAmount' => [$this->originalAbcAmount, $this->correctedAbcAmount],
            'fundingSource' => [$this->originalFundingSource, $this->correctedFundingSource],
            'category' => [$this->originalCategory?->value, $this->correctedCategory?->value],
            'procurementMode' => [$this->originalProcurementMode?->value, $this->correctedProcurementMode?->value],
            'office' => [$this->originalOffice, $this->correctedOffice],
            'endUser' => [$this->originalEndUser, $this->correctedEndUser],
            'purpose' => [$this->originalPurpose, $this->correctedPurpose],
            'deliveryLocation' => [$this->originalDeliveryLocation, $this->correctedDeliveryLocation],
            'deliveryDate' => [$this->originalDeliveryDate?->toIso8601String(), $this->correctedDeliveryDate?->toIso8601String()],
            'deliveryTermDays' => [$this->originalDeliveryTermDays, $this->correctedDeliveryTermDays],
            'bacResolutionNumber' => [$this->originalBacResolutionNumber, $this->correctedBacResolutionNumber],
            'bacResolutionDate' => [$this->originalBacResolutionDate?->toIso8601String(), $this->correctedBacResolutionDate?->toIso8601String()],
            'philgepsReference' => [$this->originalPhilgepsReference, $this->correctedPhilgepsReference],
            'philgepsPostingDate' => [$this->originalPhilgepsPostingDate?->toIso8601String(), $this->correctedPhilgepsPostingDate?->toIso8601String()],
            'approvedBy' => [$this->originalApprovedBy, $this->correctedApprovedBy],
            'approvalDate' => [$this->originalApprovalDate?->toIso8601String(), $this->correctedApprovalDate?->toIso8601String()],
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

    /**
     * Check if this correction actually changes any values
     */
    public function hasChanges(): bool
    {
        return ! empty($this->getChangedFields());
    }
}
