<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;

/**
 * Document Validation Service
 *
 * Validates document uploads and stage completion readiness
 */
class DocumentValidationService
{
    public function __construct(
        private readonly StageDocumentRequirements $requirements
    ) {}

    /**
     * Validate a document upload for a specific stage
     */
    public function validateUpload(
        StageEnums $stage,
        DocumentTypeEnums $documentType,
        array $uploadedTypes
    ): array {
        $requiredDocs = $this->requirements->getRequiredDocuments($stage);
        $optionalDocs = $this->requirements->getOptionalDocuments($stage);

        $allValidDocs = array_merge($requiredDocs, $optionalDocs);

        // Check if document type is valid for this stage
        $isValid = in_array($documentType, $allValidDocs, true);

        $errors = [];
        $warnings = [];

        if (! $isValid) {
            // Check if it's valid for current phase at least
            $currentPhase = $stage->getPhase();
            $allStagesInPhase = StageEnums::getStagesByPhase($currentPhase);

            $validInPhase = false;
            foreach ($allStagesInPhase as $phaseStage) {
                $phaseRequiredDocs = $this->requirements->getRequiredDocuments($phaseStage);
                $phaseOptionalDocs = $this->requirements->getOptionalDocuments($phaseStage);
                $phaseAllDocs = array_merge($phaseRequiredDocs, $phaseOptionalDocs);

                if (in_array($documentType, $phaseAllDocs, true)) {
                    $validInPhase = true;
                    $warnings[] = sprintf(
                        'Document type "%s" is typically required for %s stage, not %s. Please verify this is correct.',
                        $documentType->getDisplayName(),
                        $phaseStage->getDisplayName(),
                        $stage->getDisplayName()
                    );
                    break;
                }
            }

            if (! $validInPhase) {
                $errors[] = sprintf(
                    'Document type "%s" is not valid for stage "%s" or its phase.',
                    $documentType->getDisplayName(),
                    $stage->getDisplayName()
                );
            }
        }

        // Check for duplicates
        foreach ($uploadedTypes as $uploadedDoc) {
            if ($uploadedDoc === $documentType) {
                $warnings[] = sprintf(
                    'A document of type "%s" has already been uploaded for this stage. Uploading another will create a duplicate.',
                    $documentType->getDisplayName()
                );
                break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate if a stage is ready for completion
     */
    public function validateStageCompletion(StageEnums $stage, array $uploadedDocumentEnums): array
    {
        $requiredDocs = $this->requirements->getRequiredDocuments($stage);
        $missing = $this->requirements->getMissingDocuments($stage, $uploadedDocumentEnums);

        $canComplete = empty($missing);
        $completionPercentage = $this->calculateCompletionPercentage($stage, $uploadedDocumentEnums);

        return [
            'can_complete' => $canComplete,
            'required_documents' => array_map(fn ($doc) => $doc->value, $requiredDocs),
            'uploaded_documents' => array_map(fn ($doc) => $doc->value, $uploadedDocumentEnums),
            'missing_documents' => array_map(fn ($doc) => $doc->value, $missing),
            'completion_percentage' => $completionPercentage,
        ];
    }

    /**
     * Calculate completion percentage for a stage
     */
    public function calculateCompletionPercentage(StageEnums $stage, array $uploadedDocumentEnums): float
    {
        $requiredDocs = $this->requirements->getRequiredDocuments($stage);

        if (empty($requiredDocs)) {
            return 100.0;
        }

        $uploadedCount = 0;
        foreach ($requiredDocs as $requiredDoc) {
            foreach ($uploadedDocumentEnums as $uploadedDoc) {
                if ($uploadedDoc === $requiredDoc) {
                    $uploadedCount++;
                    break;
                }
            }
        }

        return round(($uploadedCount / count($requiredDocs)) * 100, 2);
    }

    /**
     * Get complete document guide for a stage
     */
    public function getStageDocumentGuide(StageEnums $stage): array
    {
        $requiredDocs = $this->requirements->getRequiredDocuments($stage);
        $optionalDocs = $this->requirements->getOptionalDocuments($stage);
        $counts = $this->requirements->getDocumentCounts($stage);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'required_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $requiredDocs),
            'optional_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $optionalDocs),
            'counts' => $counts,
        ];
    }
}
