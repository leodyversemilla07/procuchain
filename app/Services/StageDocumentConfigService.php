<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\StageDocumentConfig;

/**
 * Stage Document Configuration Service
 *
 * Provides document requirements from database with fallback to service defaults.
 * All changes made by admins are reflected for all users through this service.
 */
class StageDocumentConfigService
{
    public function __construct(
        private readonly WorkflowDefinitionService $workflowDefinitionService
    ) {}

    /**
     * Get required documents for a stage and mode.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage, ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getRequiredDocuments($stage, $mode);
    }

    /**
     * Get optional documents for a stage and mode.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocuments(StageEnums $stage, ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getOptionalDocuments($stage, $mode);
    }

    /**
     * Get document counts for a stage and mode.
     */
    public function getDocumentCounts(StageEnums $stage, ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getDocumentCounts($stage, $mode);
    }

    /**
     * Get missing required documents.
     *
     * @param  array<DocumentTypeEnums>  $uploadedTypes
     * @return array<DocumentTypeEnums>
     */
    public function getMissingDocuments(StageEnums $stage, ProcurementMode $mode, array $uploadedTypes): array
    {
        return $this->workflowDefinitionService->getMissingDocuments($stage, $mode, $uploadedTypes);
    }

    /**
     * Check if minimum required documents are uploaded.
     *
     * @param  array<DocumentTypeEnums>  $uploadedTypes
     */
    public function hasMinimumRequiredDocuments(StageEnums $stage, ProcurementMode $mode, array $uploadedTypes): bool
    {
        return empty($this->getMissingDocuments($stage, $mode, $uploadedTypes));
    }

    /**
     * Get complete document guide for a stage and mode.
     */
    public function getStageDocumentGuide(StageEnums $stage, ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getStageDocumentGuide($stage, $mode);
    }

    /**
     * Get all document configurations for a mode.
     */
    public function getAllConfigsForMode(ProcurementMode $mode): array
    {
        $configs = [];

        foreach (StageEnums::cases() as $stage) {
            $configs[$stage->value] = $this->getStageDocumentGuide($stage, $mode);
        }

        return $configs;
    }

    /**
     * Clear cache for a specific stage/mode or all.
     */
    public function clearCache(?StageEnums $stage = null, ?ProcurementMode $mode = null): void
    {
        $this->workflowDefinitionService->clearCache($mode, $stage);
    }

    /**
     * Save document configuration for a stage and mode.
     *
     * @param  array<DocumentTypeEnums|string>  $requiredDocuments
     * @param  array<DocumentTypeEnums|string>  $optionalDocuments
     */
    public function saveDocumentConfig(
        StageEnums $stage,
        ProcurementMode $mode,
        array $requiredDocuments,
        array $optionalDocuments = [],
        ?int $updatedBy = null
    ): StageDocumentConfig {
        $config = StageDocumentConfig::updateOrCreate(
            [
                'stage' => $stage->value,
                'procurement_mode' => $mode->value,
            ],
            [
                'stage_display_name' => $stage->getDisplayName(),
                'required_documents' => array_map(
                    fn ($d) => $d instanceof DocumentTypeEnums ? $d->value : $d,
                    $requiredDocuments
                ),
                'optional_documents' => array_map(
                    fn ($d) => $d instanceof DocumentTypeEnums ? $d->value : $d,
                    $optionalDocuments
                ),
                'is_active' => true,
                'updated_by' => $updatedBy,
            ]
        );

        $this->workflowDefinitionService->clearCache($mode, $stage);

        return $config;
    }

    /**
     * Reset document configuration to defaults for a stage and mode.
     */
    public function resetToDefaults(StageEnums $stage, ProcurementMode $mode, ?int $updatedBy = null): StageDocumentConfig
    {
        $defaultRequired = $this->workflowDefinitionService->getDefaultRequiredDocuments($stage, $mode);
        $defaultOptional = $this->workflowDefinitionService->getDefaultOptionalDocuments($stage, $mode);

        return $this->saveDocumentConfig($stage, $mode, $defaultRequired, $defaultOptional, $updatedBy);
    }

    /**
     * Get all available document types for selection.
     */
    public function getAllDocumentTypes(): array
    {
        return array_map(fn (DocumentTypeEnums $doc) => [
            'value' => $doc->value,
            'display_name' => $doc->getDisplayName(),
            'description' => $doc->getDescription(),
        ], DocumentTypeEnums::cases());
    }
}
