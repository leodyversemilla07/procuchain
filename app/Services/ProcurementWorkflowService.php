<?php

namespace App\Services;

use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;

/**
 * Procurement Workflow Service
 *
 * Provides workflow configuration from database with fallback to enum defaults.
 * All changes made by admins are reflected for all users through this service.
 */
class ProcurementWorkflowService
{
    public function __construct(
        private readonly WorkflowDefinitionService $workflowDefinitionService
    ) {}

    /**
     * Get stages for a specific procurement mode.
     *
     * @return array<StageEnums>
     */
    public function getStagesForMode(ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getStagesForMode($mode);
    }

    /**
     * Get optional stages for a specific procurement mode.
     *
     * @return array<StageEnums>
     */
    public function getOptionalStagesForMode(ProcurementMode $mode): array
    {
        return $this->workflowDefinitionService->getOptionalStagesForMode($mode);
    }

    /**
     * Check if a stage exists in the workflow for a mode.
     */
    public function isStageInWorkflow(StageEnums $stage, ProcurementMode $mode): bool
    {
        return $this->workflowDefinitionService->isStageInWorkflow($stage, $mode);
    }

    /**
     * Check if a stage is optional for a mode.
     */
    public function isStageOptional(StageEnums $stage, ProcurementMode $mode): bool
    {
        return $this->workflowDefinitionService->isStageOptional($stage, $mode);
    }

    /**
     * Get the next possible stages from current stage for a mode.
     *
     * @return array<StageEnums>
     */
    public function getNextStagesForMode(StageEnums $currentStage, ProcurementMode $mode): array
    {
        $stages = $this->getStagesForMode($mode);
        $optionalStages = $this->getOptionalStagesForMode($mode);
        $currentIndex = array_search($currentStage, $stages, true);

        if ($currentIndex === false || $currentIndex >= count($stages) - 1) {
            return [];
        }

        $nextStages = [];
        $nextIndex = $currentIndex + 1;

        // Always include the immediate next stage
        if (isset($stages[$nextIndex])) {
            $nextStages[] = $stages[$nextIndex];

            // If next stage is optional, also include the one after
            if (in_array($stages[$nextIndex], $optionalStages, true)) {
                if (isset($stages[$nextIndex + 1])) {
                    $nextStages[] = $stages[$nextIndex + 1];
                }
            }
        }

        return $nextStages;
    }

    /**
     * Get stage count for a specific mode.
     */
    public function getStageCountForMode(ProcurementMode $mode): int
    {
        return count($this->getStagesForMode($mode));
    }

    /**
     * Get required stage count (excluding optional) for a mode.
     */
    public function getRequiredStageCountForMode(ProcurementMode $mode): int
    {
        $total = count($this->getStagesForMode($mode));
        $optional = count($this->getOptionalStagesForMode($mode));

        return $total - $optional;
    }

    /**
     * Get complete workflow configuration for a mode.
     */
    public function getWorkflowConfig(ProcurementMode $mode): array
    {
        $stages = $this->getStagesForMode($mode);
        $optionalStages = $this->getOptionalStagesForMode($mode);

        return [
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'description' => $mode->getDescription(),
            'irr_section' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
            'stages' => array_map(fn (StageEnums $stage) => [
                'value' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'description' => $stage->getDescription(),
                'phase' => $stage->getPhase(),
                'is_optional' => in_array($stage, $optionalStages, true),
            ], $stages),
            'stage_count' => count($stages),
            'optional_stage_count' => count($optionalStages),
            'required_stage_count' => count($stages) - count($optionalStages),
        ];
    }

    /**
     * Get all workflow configurations.
     *
     * @return array<string, array>
     */
    public function getAllWorkflowConfigs(): array
    {
        $configs = [];

        foreach (ProcurementMode::cases() as $mode) {
            $configs[$mode->value] = $this->getWorkflowConfig($mode);
        }

        return $configs;
    }

    /**
     * Clear cache for a specific mode or all modes.
     */
    public function clearCache(?ProcurementMode $mode = null): void
    {
        $this->workflowDefinitionService->clearCache($mode);
    }

    /**
     * Save workflow configuration for a mode.
     *
     * @param  array<StageEnums|string>  $stages
     * @param  array<StageEnums|string>  $optionalStages
     */
    public function saveWorkflowConfig(
        ProcurementMode $mode,
        array $stages,
        array $optionalStages = [],
        ?int $updatedBy = null
    ): ProcurementWorkflowConfig {
        $config = ProcurementWorkflowConfig::updateOrCreate(
            ['procurement_mode' => $mode->value],
            [
                'display_name' => $mode->getDisplayName(),
                'description' => $mode->getDescription(),
                'stages' => array_map(
                    fn ($s) => $s instanceof StageEnums ? $s->value : $s,
                    $stages
                ),
                'optional_stages' => array_map(
                    fn ($s) => $s instanceof StageEnums ? $s->value : $s,
                    $optionalStages
                ),
                'is_active' => true,
                'updated_by' => $updatedBy,
            ]
        );

        $this->clearCache($mode);

        return $config;
    }

    /**
     * Reset workflow configuration to defaults for a mode.
     */
    public function resetToDefaults(ProcurementMode $mode, ?int $updatedBy = null): ProcurementWorkflowConfig
    {
        // Get defaults from StageEnums
        $defaultStages = StageEnums::getStagesForMode($mode);
        $defaultOptional = StageEnums::getOptionalStagesForMode($mode);

        return $this->saveWorkflowConfig($mode, $defaultStages, $defaultOptional, $updatedBy);
    }
}
