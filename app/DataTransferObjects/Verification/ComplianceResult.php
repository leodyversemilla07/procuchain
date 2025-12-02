<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

use App\Enums\StageEnums;
use Carbon\Carbon;

/**
 * Compliance Result DTO
 *
 * Represents the result of RA 9184/RA 12009 compliance verification
 */
final class ComplianceResult
{
    public function __construct(
        public readonly bool $isCompliant,
        public readonly string $prNumber,
        public readonly StageEnums $stage,
        public readonly array $documentTypeChecks,
        public readonly array $timelineChecks,
        public readonly array $procurementModeChecks,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly Carbon $verifiedAt,
    ) {}

    /**
     * Get total violations count
     */
    public function getViolationsCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get total warnings count
     */
    public function getWarningsCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Check if there are document type violations
     */
    public function hasDocumentTypeViolations(): bool
    {
        foreach ($this->documentTypeChecks as $check) {
            if (! ($check['valid'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if there are timeline violations
     */
    public function hasTimelineViolations(): bool
    {
        foreach ($this->timelineChecks as $check) {
            if (! ($check['compliant'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        // Add display names to document_type_checks
        $documentTypeChecksWithDisplayNames = array_map(function ($check) {
            $documentType = \App\Enums\DocumentTypeEnums::tryFrom($check['document_type'] ?? '');
            $check['document_type_display'] = $documentType?->getDisplayName() ?? ucwords(str_replace('_', ' ', $check['document_type'] ?? 'Unknown'));

            return $check;
        }, $this->documentTypeChecks);

        return [
            'is_compliant' => $this->isCompliant,
            'pr_number' => $this->prNumber,
            'stage' => $this->stage->value,
            'stage_display_name' => $this->stage->getDisplayName(),
            'document_type_checks' => $documentTypeChecksWithDisplayNames,
            'timeline_checks' => $this->timelineChecks,
            'procurement_mode_checks' => $this->procurementModeChecks,
            'summary' => [
                'violations_count' => $this->getViolationsCount(),
                'warnings_count' => $this->getWarningsCount(),
                'has_document_violations' => $this->hasDocumentTypeViolations(),
                'has_timeline_violations' => $this->hasTimelineViolations(),
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'verified_at' => $this->verifiedAt->toIso8601String(),
        ];
    }

    /**
     * Create a compliant result
     */
    public static function compliant(
        string $prNumber,
        StageEnums $stage,
        array $documentTypeChecks = [],
        array $timelineChecks = [],
        array $procurementModeChecks = [],
        array $warnings = []
    ): self {
        return new self(
            isCompliant: true,
            prNumber: $prNumber,
            stage: $stage,
            documentTypeChecks: $documentTypeChecks,
            timelineChecks: $timelineChecks,
            procurementModeChecks: $procurementModeChecks,
            errors: [],
            warnings: $warnings,
            verifiedAt: now(),
        );
    }

    /**
     * Create a non-compliant result
     */
    public static function nonCompliant(
        string $prNumber,
        StageEnums $stage,
        array $errors,
        array $documentTypeChecks = [],
        array $timelineChecks = [],
        array $procurementModeChecks = [],
        array $warnings = []
    ): self {
        return new self(
            isCompliant: false,
            prNumber: $prNumber,
            stage: $stage,
            documentTypeChecks: $documentTypeChecks,
            timelineChecks: $timelineChecks,
            procurementModeChecks: $procurementModeChecks,
            errors: $errors,
            warnings: $warnings,
            verifiedAt: now(),
        );
    }
}
