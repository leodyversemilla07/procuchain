<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

use App\Enums\StageEnums;
use Carbon\Carbon;

/**
 * Completeness Result DTO
 *
 * Represents the result of a stage document completeness check
 */
final class CompletenessResult
{
    public function __construct(
        public readonly bool $isComplete,
        public readonly string $prNumber,
        public readonly StageEnums $stage,
        public readonly float $completionPercentage,
        public readonly array $requiredDocuments,
        public readonly array $uploadedDocuments,
        public readonly array $missingDocuments,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly Carbon $verifiedAt,
    ) {}

    /**
     * Check if stage is ready for completion
     */
    public function canCompleteStage(): bool
    {
        return $this->isComplete && empty($this->missingDocuments);
    }

    /**
     * Get count of missing documents
     */
    public function getMissingCount(): int
    {
        return count($this->missingDocuments);
    }

    /**
     * Get count of uploaded documents
     */
    public function getUploadedCount(): int
    {
        return count($this->uploadedDocuments);
    }

    /**
     * Get count of required documents
     */
    public function getRequiredCount(): int
    {
        return count($this->requiredDocuments);
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'is_complete' => $this->isComplete,
            'pr_number' => $this->prNumber,
            'stage' => $this->stage->value,
            'stage_display_name' => $this->stage->getDisplayName(),
            'completion_percentage' => $this->completionPercentage,
            'required_documents' => $this->requiredDocuments,
            'uploaded_documents' => $this->uploadedDocuments,
            'missing_documents' => $this->missingDocuments,
            'document_counts' => [
                'required' => $this->getRequiredCount(),
                'uploaded' => $this->getUploadedCount(),
                'missing' => $this->getMissingCount(),
            ],
            'can_complete_stage' => $this->canCompleteStage(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'verified_at' => $this->verifiedAt->toIso8601String(),
        ];
    }

    /**
     * Create from validation service output
     */
    public static function fromValidation(
        string $prNumber,
        StageEnums $stage,
        array $validationResult,
        array $errors = [],
        array $warnings = []
    ): self {
        return new self(
            isComplete: $validationResult['can_complete'] ?? false,
            prNumber: $prNumber,
            stage: $stage,
            completionPercentage: $validationResult['completion_percentage'] ?? 0.0,
            requiredDocuments: $validationResult['required_documents'] ?? [],
            uploadedDocuments: $validationResult['uploaded_documents'] ?? [],
            missingDocuments: $validationResult['missing_documents'] ?? [],
            errors: $errors,
            warnings: $warnings,
            verifiedAt: now(),
        );
    }
}
