<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

use App\Enums\StageEnums;
use Carbon\Carbon;

/**
 * Verification Report DTO
 *
 * Represents a comprehensive verification report for a procurement
 */
final class VerificationReportDTO
{
    public function __construct(
        public readonly string $prNumber,
        public readonly StageEnums $stage,
        public readonly bool $overallValid,
        public readonly array $integrityResults,
        public readonly CompletenessResult $completenessResult,
        public readonly CrossReferenceResult $crossReferenceResult,
        public readonly ComplianceResult $complianceResult,
        public readonly array $summary,
        public readonly Carbon $generatedAt,
        public readonly ?int $verifiedBy = null,
    ) {}

    /**
     * Get overall verification status
     */
    public function getOverallStatus(): string
    {
        if ($this->overallValid) {
            return 'verified';
        }

        $criticalIssues = $this->getCriticalIssuesCount();
        if ($criticalIssues > 0) {
            return 'failed';
        }

        return 'warnings';
    }

    /**
     * Get total critical issues count
     */
    public function getCriticalIssuesCount(): int
    {
        $count = 0;

        // Count integrity failures
        foreach ($this->integrityResults as $result) {
            if (! ($result['is_valid'] ?? true)) {
                $count++;
            }
        }

        // Count other critical errors
        $count += count($this->completenessResult->errors);
        $count += count($this->crossReferenceResult->errors);
        $count += count($this->complianceResult->errors);

        return $count;
    }

    /**
     * Get total warnings count
     */
    public function getWarningsCount(): int
    {
        $count = 0;

        // Count integrity warnings
        foreach ($this->integrityResults as $result) {
            $count += count($result['warnings'] ?? []);
        }

        // Count other warnings
        $count += count($this->completenessResult->warnings);
        $count += count($this->crossReferenceResult->warnings);
        $count += count($this->complianceResult->warnings);

        return $count;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'stage' => $this->stage->value,
            'stage_display_name' => $this->stage->getDisplayName(),
            'overall_valid' => $this->overallValid,
            'overall_status' => $this->getOverallStatus(),
            'integrity_results' => $this->integrityResults,
            'completeness_result' => $this->completenessResult->toArray(),
            'cross_reference_result' => $this->crossReferenceResult->toArray(),
            'compliance_result' => $this->complianceResult->toArray(),
            'summary' => array_merge($this->summary, [
                'critical_issues' => $this->getCriticalIssuesCount(),
                'warnings' => $this->getWarningsCount(),
            ]),
            'generated_at' => $this->generatedAt->toIso8601String(),
            'verified_by' => $this->verifiedBy,
        ];
    }

    /**
     * Create from individual verification results
     */
    public static function fromResults(
        string $prNumber,
        StageEnums $stage,
        array $integrityResults,
        CompletenessResult $completenessResult,
        CrossReferenceResult $crossReferenceResult,
        ComplianceResult $complianceResult,
        ?int $verifiedBy = null
    ): self {
        // Determine overall validity
        $integrityValid = true;
        foreach ($integrityResults as $result) {
            if (! ($result['is_valid'] ?? true)) {
                $integrityValid = false;
                break;
            }
        }

        $overallValid = $integrityValid
            && $completenessResult->isComplete
            && $crossReferenceResult->isConsistent
            && $complianceResult->isCompliant;

        // Build summary
        $summary = [
            'integrity_valid' => $integrityValid,
            'documents_verified' => count($integrityResults),
            'completeness_percentage' => $completenessResult->completionPercentage,
            'cross_references_consistent' => $crossReferenceResult->isConsistent,
            'ra_12009_compliant' => $complianceResult->isCompliant,
        ];

        return new self(
            prNumber: $prNumber,
            stage: $stage,
            overallValid: $overallValid,
            integrityResults: $integrityResults,
            completenessResult: $completenessResult,
            crossReferenceResult: $crossReferenceResult,
            complianceResult: $complianceResult,
            summary: $summary,
            generatedAt: now(),
            verifiedBy: $verifiedBy,
        );
    }
}
