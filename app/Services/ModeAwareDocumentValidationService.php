<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;

/**
 * Mode-Aware Document Validation Service
 *
 * Validates document uploads and stage completion readiness
 * with awareness of procurement mode requirements.
 *
 * Now uses database-backed configuration services to ensure
 * admin changes are reflected for all users.
 *
 * Aligned with NGPA IRR (RA 12009) for Municipality of Gloria,
 * Oriental Mindoro (4th Class Municipality).
 */
class ModeAwareDocumentValidationService
{
    public function __construct(
        private readonly WorkflowDefinitionService $workflowDefinitionService,
        private readonly StageDocumentRequirements $baseRequirements
    ) {}

    /**
     * Validate a document upload for a specific stage and mode
     */
    public function validateUpload(
        StageEnums $stage,
        DocumentTypeEnums $documentType,
        array $uploadedTypes,
        ?ProcurementModeEnums $mode = null
    ): array {
        // If no mode provided, fall back to base validation
        if ($mode === null) {
            return $this->validateBaseUpload($stage, $documentType, $uploadedTypes);
        }

        // Check if stage exists in mode workflow (using database-backed service)
        if (! $this->workflowDefinitionService->isStageInWorkflow($stage, $mode)) {
            return [
                'valid' => false,
                'errors' => [
                    sprintf(
                        'Stage "%s" is not applicable for procurement mode "%s".',
                        $stage->getDisplayName(),
                        $mode->getDisplayName()
                    ),
                ],
                'warnings' => [],
            ];
        }

        $requiredDocs = $this->workflowDefinitionService->getRequiredDocuments($stage, $mode);
        $optionalDocs = $this->workflowDefinitionService->getOptionalDocuments($stage, $mode);

        $allValidDocs = array_merge($requiredDocs, $optionalDocs);

        // Check if document type is valid for this stage and mode
        $isValid = in_array($documentType, $allValidDocs, true);

        $errors = [];
        $warnings = [];

        if (! $isValid) {
            // Check if it's valid for any stage in the mode workflow (using database-backed service)
            $validInWorkflow = false;
            $modeStages = $this->workflowDefinitionService->getStagesForMode($mode);

            foreach ($modeStages as $workflowStage) {
                $stageRequiredDocs = $this->workflowDefinitionService->getRequiredDocuments($workflowStage, $mode);
                $stageOptionalDocs = $this->workflowDefinitionService->getOptionalDocuments($workflowStage, $mode);
                $stageAllDocs = array_merge($stageRequiredDocs, $stageOptionalDocs);

                if (in_array($documentType, $stageAllDocs, true)) {
                    $validInWorkflow = true;
                    $warnings[] = sprintf(
                        'Document type "%s" is typically required for %s stage, not %s. Please verify this is correct.',
                        $documentType->getDisplayName(),
                        $workflowStage->getDisplayName(),
                        $stage->getDisplayName()
                    );
                    break;
                }
            }

            if (! $validInWorkflow) {
                $errors[] = sprintf(
                    'Document type "%s" is not valid for stage "%s" in %s procurement mode.',
                    $documentType->getDisplayName(),
                    $stage->getDisplayName(),
                    $mode->getDisplayName()
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

        // Add mode-specific info for alternative modes
        if ($mode->isAlternativeMode() && empty($errors)) {
            $warnings[] = sprintf(
                'Note: %s uses simplified documentation requirements per NGPA IRR %s.',
                $mode->getDisplayName(),
                $mode->getIrrSection()
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate if a stage is ready for completion for a specific mode
     */
    public function validateStageCompletion(
        StageEnums $stage,
        array $uploadedDocumentEnums,
        ?ProcurementModeEnums $mode = null
    ): array {
        if ($mode === null) {
            return $this->validateBaseStageCompletion($stage, $uploadedDocumentEnums);
        }

        // Check if stage exists in mode workflow (using database-backed service)
        if (! $this->workflowDefinitionService->isStageInWorkflow($stage, $mode)) {
            return [
                'can_complete' => false,
                'required_documents' => [],
                'uploaded_documents' => array_map(fn ($doc) => $doc->value, $uploadedDocumentEnums),
                'missing_documents' => [],
                'completion_percentage' => 0,
                'error' => 'Stage is not applicable for this procurement mode.',
            ];
        }

        $requiredDocs = $this->workflowDefinitionService->getRequiredDocuments($stage, $mode);
        $missing = $this->workflowDefinitionService->getMissingDocuments($stage, $mode, $uploadedDocumentEnums);

        $canComplete = empty($missing);
        $completionPercentage = $this->calculateCompletionPercentage($stage, $uploadedDocumentEnums, $mode);

        return [
            'can_complete' => $canComplete,
            'required_documents' => array_map(fn ($doc) => $doc->value, $requiredDocs),
            'uploaded_documents' => array_map(fn ($doc) => $doc->value, $uploadedDocumentEnums),
            'missing_documents' => array_map(fn ($doc) => $doc->value, $missing),
            'completion_percentage' => $completionPercentage,
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
        ];
    }

    /**
     * Calculate completion percentage for a stage with mode awareness
     */
    public function calculateCompletionPercentage(
        StageEnums $stage,
        array $uploadedDocumentEnums,
        ?ProcurementModeEnums $mode = null
    ): float {
        if ($mode === null) {
            $requiredDocs = $this->baseRequirements->getRequiredDocuments($stage);
        } else {
            $requiredDocs = $this->workflowDefinitionService->getRequiredDocuments($stage, $mode);
        }

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
     * Get complete document guide for a stage with mode awareness
     */
    public function getStageDocumentGuide(StageEnums $stage, ?ProcurementModeEnums $mode = null): array
    {
        if ($mode === null) {
            return $this->getBaseStageDocumentGuide($stage);
        }

        return $this->workflowDefinitionService->getStageDocumentGuide($stage, $mode);
    }

    /**
     * Get document requirements comparison between base and mode-specific
     *
     * This is useful to show users how requirements differ based on mode.
     */
    public function getRequirementsComparison(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $baseRequired = $this->baseRequirements->getRequiredDocuments($stage);
        $modeRequired = $this->workflowDefinitionService->getRequiredDocuments($stage, $mode);

        // Calculate difference using enum values for comparison
        $baseRequiredValues = array_map(fn ($doc) => $doc->value, $baseRequired);
        $modeRequiredValues = array_map(fn ($doc) => $doc->value, $modeRequired);

        $removedRequirements = array_diff($baseRequiredValues, $modeRequiredValues);
        $addedRequirements = array_diff($modeRequiredValues, $baseRequiredValues);

        return [
            'stage' => $stage->value,
            'mode' => $mode->value,
            'base_required_count' => count($baseRequired),
            'mode_required_count' => count($modeRequired),
            'removed_requirements' => array_values($removedRequirements),
            'added_requirements' => array_values($addedRequirements),
            'is_simplified' => count($modeRequired) < count($baseRequired),
            'ngpa_reference' => $mode->getIrrSection(),
        ];
    }

    // ==================================================================================
    // PRIVATE METHODS: Base (non-mode-aware) Validation
    // ==================================================================================

    /**
     * Base upload validation (without mode awareness)
     */
    private function validateBaseUpload(
        StageEnums $stage,
        DocumentTypeEnums $documentType,
        array $uploadedTypes
    ): array {
        $requiredDocs = $this->baseRequirements->getRequiredDocuments($stage);
        $optionalDocs = $this->baseRequirements->getOptionalDocuments($stage);

        $allValidDocs = array_merge($requiredDocs, $optionalDocs);
        $isValid = in_array($documentType, $allValidDocs, true);

        $errors = [];
        $warnings = [];

        if (! $isValid) {
            $errors[] = sprintf(
                'Document type "%s" is not valid for stage "%s".',
                $documentType->getDisplayName(),
                $stage->getDisplayName()
            );
        }

        // Check for duplicates
        foreach ($uploadedTypes as $uploadedDoc) {
            if ($uploadedDoc === $documentType) {
                $warnings[] = sprintf(
                    'A document of type "%s" has already been uploaded for this stage.',
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
     * Base stage completion validation (without mode awareness)
     */
    private function validateBaseStageCompletion(StageEnums $stage, array $uploadedDocumentEnums): array
    {
        $requiredDocs = $this->baseRequirements->getRequiredDocuments($stage);
        $missing = $this->baseRequirements->getMissingDocuments($stage, $uploadedDocumentEnums);

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
     * Base document guide (without mode awareness)
     */
    private function getBaseStageDocumentGuide(StageEnums $stage): array
    {
        $requiredDocs = $this->baseRequirements->getRequiredDocuments($stage);
        $optionalDocs = $this->baseRequirements->getOptionalDocuments($stage);
        $counts = $this->baseRequirements->getDocumentCounts($stage);

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
