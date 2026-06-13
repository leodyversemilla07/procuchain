<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use Carbon\Carbon;

/**
 * Formats procurement data for display including stages, statuses, dates, and currency.
 */
final class ProcurementFormatterService
{
    /**
     * Get all stages in display order
     *
     * @return array<string>
     */
    public function getStageOrder(): array
    {
        return array_map(fn (StageEnums $stage) => $stage->getDisplayName(), StageEnums::cases());
    }

    /**
     * Get total number of stages
     */
    public function getTotalStages(): int
    {
        return count(StageEnums::cases());
    }

    // =========================================================================
    // STAGE FORMATTING
    // =========================================================================

    /**
     * Format stage name to human-readable format
     */
    public function formatStageName(string $stage): string
    {
        if (empty($stage)) {
            return StageEnums::PROCUREMENT_INITIATION->getDisplayName();
        }

        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getDisplayName();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getDisplayName();
            }
        }

        return ucwords(str_replace('_', ' ', $stage));
    }

    /**
     * Get stage order index
     */
    public function getStageOrderIndex(string $stage): int
    {
        $formattedStage = $this->formatStageName($stage);
        $stageOrder = $this->getStageOrder();
        $index = array_search($formattedStage, $stageOrder);

        return $index !== false ? $index : 999;
    }

    /**
     * Get stage description
     */
    public function getStageDescription(string $stage): ?string
    {
        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getDescription();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getDescription();
            }
        }

        return null;
    }

    /**
     * Get stage phase
     */
    public function getStagePhase(string $stage): string
    {
        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getPhase();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getPhase();
            }
        }

        return 'pre_procurement';
    }

    /**
     * Get stage phase display name
     */
    public function getStagePhaseDisplayName(string $stage): string
    {
        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getPhaseDisplayName();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getPhaseDisplayName();
            }
        }

        return 'Pre-Procurement';
    }

    /**
     * Calculate progress percentage based on stage
     */
    public function calculateProgress(string $stage): float
    {
        $stageIndex = $this->getStageOrderIndex($stage) + 1;
        $totalStages = $this->getTotalStages();

        if ($stageIndex > 0 && $stageIndex <= $totalStages) {
            return ($stageIndex / $totalStages) * 100;
        }

        return 0.0;
    }

    // =========================================================================
    // STATUS FORMATTING
    // =========================================================================

    /**
     * Format status name for display
     */
    public function formatStatus(string $statusText): string
    {
        if (empty($statusText)) {
            return 'Unknown Status';
        }

        $statusEnum = ProcurementStatus::tryFrom(strtolower(str_replace([' ', '-'], '_', $statusText)));

        if ($statusEnum !== null) {
            return $statusEnum->getDisplayName();
        }

        foreach (ProcurementStatus::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $statusText) === 0) {
                return $case->getDisplayName();
            }
        }

        return ucwords(str_replace('_', ' ', strtolower($statusText)));
    }

    /**
     * Get status badge variant based on status
     */
    public function getStatusVariant(ProcurementStatus $status): string
    {
        return match ($status) {
            ProcurementStatus::PROCUREMENT_SUBMITTED => 'default',
            ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD => 'secondary',
            ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'outline',
            ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'secondary',
            ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED => 'secondary',
            ProcurementStatus::BIDDING_DOCUMENTS_SUBMITTED => 'secondary',
            ProcurementStatus::PRE_BID_CONFERENCE_HELD => 'secondary',
            ProcurementStatus::PRE_BID_CONFERENCE_SKIPPED => 'outline',
            ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED => 'secondary',
            ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING => 'default',
            ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED => 'secondary',
            ProcurementStatus::BIDS_OPENED => 'outline',
            ProcurementStatus::BIDS_EVALUATED => 'default',
            ProcurementStatus::POST_QUALIFICATION_VERIFIED => 'secondary',
            ProcurementStatus::POST_QUALIFICATION_FAILED => 'destructive',
            ProcurementStatus::RESOLUTION_RECORDED => 'default',
            ProcurementStatus::AWARDED => 'secondary',
            ProcurementStatus::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'outline',
            ProcurementStatus::NTP_RECORDED => 'default',
            ProcurementStatus::MONITORING_COMPLETED => 'secondary',
            ProcurementStatus::COMPLETION_DOCUMENTS_UPLOADED => 'outline',
            ProcurementStatus::COMPLETED => 'default',
        };
    }

    /**
     * Get status information including variant and formatted label
     *
     * @return array{variant: string, label: string, description: string}
     */
    public function getStatusInfo(string $statusText): array
    {
        if (empty($statusText)) {
            return [
                'variant' => 'outline',
                'label' => 'Unknown Status',
                'description' => 'Status information not available',
            ];
        }

        $statusEnum = ProcurementStatus::tryFrom(strtolower(str_replace([' ', '-'], '_', $statusText)));

        if ($statusEnum !== null) {
            return [
                'variant' => $this->getStatusVariant($statusEnum),
                'label' => $statusEnum->getDisplayName(),
                'description' => $statusEnum->getDescription(),
            ];
        }

        return [
            'variant' => 'outline',
            'label' => ucwords(str_replace('_', ' ', strtolower($statusText))),
            'description' => 'Status information not available',
        ];
    }

    // =========================================================================
    // DATE/TIME FORMATTING
    // =========================================================================

    /**
     * Format timestamp to full date and time
     */
    public function formatDateTime(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('M j, Y, g:i A');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to date only
     */
    public function formatDateOnly(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('M j, Y');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to time only
     */
    public function formatTimeOnly(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Time';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('g:i A');
        } catch (\Exception $e) {
            return 'Invalid Time';
        }
    }

    // =========================================================================
    // DOCUMENT FORMATTING
    // =========================================================================

    /**
     * Format document type to human-readable format
     */
    public function formatDocumentType(string $documentType): string
    {
        if (empty($documentType)) {
            return 'Unknown Document';
        }

        $docTypeEnum = DocumentTypeEnums::fromString($documentType);

        if ($docTypeEnum !== null) {
            return $docTypeEnum->getDisplayName();
        }

        return ucwords(str_replace('_', ' ', $documentType));
    }

    /**
     * Format File size to human-readable format
     */
    public function formatfileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return 'N/A';
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log(1024));
        $decimals = $i === 1 ? 0 : ($i > 1 ? 1 : 0);
        $size = round($bytes / pow(1024, $i), $decimals);

        return number_format($size, $decimals, '.', ',').' '.$units[$i];
    }

    /**
     * Shorten hash for display
     */
    public function shortenHash(?string $hash, int $startLength = 5, int $endLength = 5): string
    {
        if (empty($hash)) {
            return 'N/A';
        }

        if (strlen($hash) <= $startLength + $endLength) {
            return $hash;
        }

        return substr($hash, 0, $startLength).'...'.substr($hash, -$endLength);
    }

    // =========================================================================
    // EVENT FORMATTING
    // =========================================================================

    /**
     * Format event type to human-readable format
     */
    public function formatEventType(string $eventType): string
    {
        if (empty($eventType)) {
            return 'Unknown Event';
        }

        $eventTypeMap = [
            'document_upload' => 'Document Uploaded',
            'document_uploaded' => 'Document Uploaded',
            'stage_transition' => 'Stage Transition',
            'phase_transition' => 'Phase Transition',
            'stage_completed' => 'Stage Completed',
            'procurement_created' => 'Procurement Created',
            'procurement_completed' => 'Procurement Completed',
            'status_update' => 'Status Update',
            'document_verified' => 'Document Verified',
            'document_rejected' => 'Document Rejected',
            'approval_granted' => 'Approval Granted',
            'approval_rejected' => 'Approval Rejected',
        ];

        $lowerEventType = strtolower($eventType);
        if (isset($eventTypeMap[$lowerEventType])) {
            return $eventTypeMap[$lowerEventType];
        }

        return ucwords(str_replace('_', ' ', $eventType));
    }

    /**
     * Format event category to human-readable format
     */
    public function formatEventCategory(string $category): string
    {
        if (empty($category)) {
            return '';
        }

        $categoryMap = [
            'stage_transition' => 'Workflow',
            'document' => 'Document',
            'procurement' => 'Procurement',
            'workflow' => 'Workflow',
            'milestone' => 'Milestone',
            'approval' => 'Approval',
            'notification' => 'Notification',
        ];

        $lowerCategory = strtolower($category);
        if (isset($categoryMap[$lowerCategory])) {
            return $categoryMap[$lowerCategory];
        }

        return ucwords(str_replace('_', ' ', $category));
    }

    // =========================================================================
    // CURRENCY FORMATTING
    // =========================================================================

    /**
     * Format currency value with peso sign
     */
    public function formatCurrency(float|int|string|null $value): string
    {
        if ($value === null || $value === '' || $value === 0) {
            return '₱ 0.00';
        }

        $numericValue = is_string($value) ? (float) $value : $value;

        return '₱ '.number_format($numericValue, 2);
    }

    // =========================================================================
    // METADATA FORMATTING
    // =========================================================================

    /**
     * Format stage metadata with all formatted fields
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function formatStageMetadata(array $metadata): array
    {
        $dateFields = [
            'meeting_date',
            'submission_date',
            'issuance_date',
            'opening_date',
            'evaluation_date',
            'signing_date',
            'report_date',
            'issue_date',
            'completion_date',
        ];

        foreach ($dateFields as $field) {
            if (isset($metadata[$field]) && ! empty($metadata[$field])) {
                $metadata[$field.'_formatted'] = $this->formatDateOnly($metadata[$field]);
            }
        }

        if (isset($metadata['validity_period']) && is_array($metadata['validity_period'])) {
            if (isset($metadata['validity_period']['start_date'])) {
                $metadata['validity_period']['start_date_formatted'] = $this->formatDateOnly(
                    $metadata['validity_period']['start_date']
                );
            }
            if (isset($metadata['validity_period']['end_date'])) {
                $metadata['validity_period']['end_date_formatted'] = $this->formatDateOnly(
                    $metadata['validity_period']['end_date']
                );
            }
        }

        $currencyFields = ['appropriation', 'bid_value', 'bond_amount'];
        foreach ($currencyFields as $field) {
            if (isset($metadata[$field]) && ! empty($metadata[$field])) {
                $metadata[$field.'_formatted'] = $this->formatCurrency($metadata[$field]);
            }
        }

        return $metadata;
    }

    /**
     * Format correction type for display
     */
    public function formatCorrectionType(string $correctionType): string
    {
        return match ($correctionType) {
            'replace' => 'Document Replacement',
            'invalidate' => 'Document Invalidation',
            'metadata' => 'Metadata Correction',
            'document_correction' => 'Document Correction',
            default => ucwords(str_replace('_', ' ', $correctionType)),
        };
    }
}
