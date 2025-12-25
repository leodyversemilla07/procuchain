<?php

namespace App\Services;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Procurement Workflow Service
 *
 * Provides workflow configuration from database with fallback to enum defaults.
 * All changes made by admins are reflected for all users through this service.
 */
class ProcurementWorkflowService
{
    private const CACHE_TTL = 300; // 5 minutes

    private const CACHE_PREFIX = 'workflow.';

    /**
     * Get stages for a specific procurement mode.
     *
     * @return array<StageEnums>
     */
    public function getStagesForMode(ProcurementModeEnums $mode): array
    {
        $cacheKey = self::CACHE_PREFIX."stages.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($mode) {
            $config = ProcurementWorkflowConfig::forMode($mode)->active()->first();

            if ($config) {
                return $config->getStagesAsEnums();
            }

            // Fallback to hardcoded defaults from StageEnums
            return StageEnums::getStagesForMode($mode);
        });
    }

    /**
     * Get optional stages for a specific procurement mode.
     *
     * @return array<StageEnums>
     */
    public function getOptionalStagesForMode(ProcurementModeEnums $mode): array
    {
        $cacheKey = self::CACHE_PREFIX."optional.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($mode) {
            $config = ProcurementWorkflowConfig::forMode($mode)->active()->first();

            if ($config) {
                return $config->getOptionalStagesAsEnums();
            }

            // Fallback to hardcoded defaults from StageEnums
            return StageEnums::getOptionalStagesForMode($mode);
        });
    }

    /**
     * Check if a stage exists in the workflow for a mode.
     */
    public function isStageInWorkflow(StageEnums $stage, ProcurementModeEnums $mode): bool
    {
        $stages = $this->getStagesForMode($mode);

        return in_array($stage, $stages, true);
    }

    /**
     * Check if a stage is optional for a mode.
     */
    public function isStageOptional(StageEnums $stage, ProcurementModeEnums $mode): bool
    {
        $optionalStages = $this->getOptionalStagesForMode($mode);

        return in_array($stage, $optionalStages, true);
    }

    /**
     * Get the next possible stages from current stage for a mode.
     *
     * @return array<StageEnums>
     */
    public function getNextStagesForMode(StageEnums $currentStage, ProcurementModeEnums $mode): array
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
    public function getStageCountForMode(ProcurementModeEnums $mode): int
    {
        return count($this->getStagesForMode($mode));
    }

    /**
     * Get required stage count (excluding optional) for a mode.
     */
    public function getRequiredStageCountForMode(ProcurementModeEnums $mode): int
    {
        $total = count($this->getStagesForMode($mode));
        $optional = count($this->getOptionalStagesForMode($mode));

        return $total - $optional;
    }

    /**
     * Get complete workflow configuration for a mode.
     */
    public function getWorkflowConfig(ProcurementModeEnums $mode): array
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

        foreach (ProcurementModeEnums::cases() as $mode) {
            $configs[$mode->value] = $this->getWorkflowConfig($mode);
        }

        return $configs;
    }

    /**
     * Clear cache for a specific mode or all modes.
     */
    public function clearCache(?ProcurementModeEnums $mode = null): void
    {
        if ($mode) {
            Cache::forget(self::CACHE_PREFIX."stages.{$mode->value}");
            Cache::forget(self::CACHE_PREFIX."optional.{$mode->value}");
        } else {
            // Clear all mode caches
            foreach (ProcurementModeEnums::cases() as $m) {
                Cache::forget(self::CACHE_PREFIX."stages.{$m->value}");
                Cache::forget(self::CACHE_PREFIX."optional.{$m->value}");
            }
        }
    }

    /**
     * Save workflow configuration for a mode.
     *
     * @param  array<StageEnums|string>  $stages
     * @param  array<StageEnums|string>  $optionalStages
     */
    public function saveWorkflowConfig(
        ProcurementModeEnums $mode,
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
    public function resetToDefaults(ProcurementModeEnums $mode, ?int $updatedBy = null): ProcurementWorkflowConfig
    {
        // Get defaults from StageEnums
        $defaultStages = StageEnums::getStagesForMode($mode);
        $defaultOptional = StageEnums::getOptionalStagesForMode($mode);

        return $this->saveWorkflowConfig($mode, $defaultStages, $defaultOptional, $updatedBy);
    }
}
