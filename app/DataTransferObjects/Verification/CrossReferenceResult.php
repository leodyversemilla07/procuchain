<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

use Carbon\Carbon;

/**
 * Cross Reference Result DTO
 *
 * Represents the result of cross-document validation (PR numbers, amounts, dates consistency)
 */
final class CrossReferenceResult
{
    public function __construct(
        public readonly bool $isConsistent,
        public readonly string $prNumber,
        public readonly array $prNumberChecks,
        public readonly array $amountChecks,
        public readonly array $dateChecks,
        public readonly array $signatoryChecks,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly Carbon $verifiedAt,
    ) {}

    /**
     * Get total issues count
     */
    public function getTotalIssues(): int
    {
        return count($this->errors);
    }

    /**
     * Get total warnings count
     */
    public function getTotalWarnings(): int
    {
        return count($this->warnings);
    }

    /**
     * Check if any PR number mismatches exist
     */
    public function hasPrNumberMismatch(): bool
    {
        foreach ($this->prNumberChecks as $check) {
            if (! ($check['matches'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any amount inconsistencies exist
     */
    public function hasAmountInconsistency(): bool
    {
        foreach ($this->amountChecks as $check) {
            if (! ($check['consistent'] ?? true)) {
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
        // Add display names to pr_number_checks
        $prNumberChecksWithDisplayNames = array_map(function ($check) {
            $documentType = \App\Enums\DocumentTypeEnums::tryFrom($check['document_type'] ?? '');
            $check['document_type_display'] = $documentType?->getDisplayName() ?? ucwords(str_replace('_', ' ', $check['document_type'] ?? 'Unknown'));

            return $check;
        }, $this->prNumberChecks);

        return [
            'is_consistent' => $this->isConsistent,
            'pr_number' => $this->prNumber,
            'pr_number_checks' => $prNumberChecksWithDisplayNames,
            'amount_checks' => $this->amountChecks,
            'date_checks' => $this->dateChecks,
            'signatory_checks' => $this->signatoryChecks,
            'summary' => [
                'total_issues' => $this->getTotalIssues(),
                'total_warnings' => $this->getTotalWarnings(),
                'has_pr_mismatch' => $this->hasPrNumberMismatch(),
                'has_amount_inconsistency' => $this->hasAmountInconsistency(),
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'verified_at' => $this->verifiedAt->toIso8601String(),
        ];
    }

    /**
     * Create a consistent result (no issues)
     */
    public static function consistent(
        string $prNumber,
        array $prNumberChecks = [],
        array $amountChecks = [],
        array $dateChecks = [],
        array $signatoryChecks = [],
        array $warnings = []
    ): self {
        return new self(
            isConsistent: true,
            prNumber: $prNumber,
            prNumberChecks: $prNumberChecks,
            amountChecks: $amountChecks,
            dateChecks: $dateChecks,
            signatoryChecks: $signatoryChecks,
            errors: [],
            warnings: $warnings,
            verifiedAt: now(),
        );
    }

    /**
     * Create an inconsistent result (with issues)
     */
    public static function inconsistent(
        string $prNumber,
        array $errors,
        array $prNumberChecks = [],
        array $amountChecks = [],
        array $dateChecks = [],
        array $signatoryChecks = [],
        array $warnings = []
    ): self {
        return new self(
            isConsistent: false,
            prNumber: $prNumber,
            prNumberChecks: $prNumberChecks,
            amountChecks: $amountChecks,
            dateChecks: $dateChecks,
            signatoryChecks: $signatoryChecks,
            errors: $errors,
            warnings: $warnings,
            verifiedAt: now(),
        );
    }
}
