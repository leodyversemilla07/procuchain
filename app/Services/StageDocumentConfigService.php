<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\StageDocumentConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Stage Document Configuration Service
 *
 * Provides document requirements from database with fallback to service defaults.
 * All changes made by admins are reflected for all users through this service.
 */
class StageDocumentConfigService
{
    private const CACHE_TTL = 300; // 5 minutes

    private const CACHE_PREFIX = 'docs.';

    public function __construct(
        private readonly StageDocumentRequirements $defaultRequirements,
        private readonly ModeAwareDocumentRequirements $modeAwareRequirements
    ) {}

    /**
     * Get required documents for a stage and mode.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $cacheKey = self::CACHE_PREFIX."required.{$stage->value}.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stage, $mode) {
            $config = StageDocumentConfig::forStage($stage)
                ->forMode($mode)
                ->active()
                ->first();

            if ($config) {
                return $config->getRequiredDocumentsAsEnums();
            }

            // Fallback to hardcoded mode-aware defaults
            return $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
        });
    }

    /**
     * Get optional documents for a stage and mode.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $cacheKey = self::CACHE_PREFIX."optional.{$stage->value}.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stage, $mode) {
            $config = StageDocumentConfig::forStage($stage)
                ->forMode($mode)
                ->active()
                ->first();

            if ($config) {
                return $config->getOptionalDocumentsAsEnums();
            }

            // Fallback to hardcoded mode-aware defaults
            return $this->modeAwareRequirements->getOptionalDocuments($stage, $mode);
        });
    }

    /**
     * Get document counts for a stage and mode.
     */
    public function getDocumentCounts(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $required = $this->getRequiredDocuments($stage, $mode);
        $optional = $this->getOptionalDocuments($stage, $mode);

        return [
            'required_count' => count($required),
            'optional_count' => count($optional),
            'total_count' => count($required) + count($optional),
        ];
    }

    /**
     * Get missing required documents.
     *
     * @param  array<DocumentTypeEnums>  $uploadedTypes
     * @return array<DocumentTypeEnums>
     */
    public function getMissingDocuments(StageEnums $stage, ProcurementModeEnums $mode, array $uploadedTypes): array
    {
        $required = $this->getRequiredDocuments($stage, $mode);
        $missing = [];

        foreach ($required as $requiredDoc) {
            $found = false;
            foreach ($uploadedTypes as $uploadedDoc) {
                if ($uploadedDoc === $requiredDoc) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $requiredDoc;
            }
        }

        return $missing;
    }

    /**
     * Check if minimum required documents are uploaded.
     *
     * @param  array<DocumentTypeEnums>  $uploadedTypes
     */
    public function hasMinimumRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode, array $uploadedTypes): bool
    {
        return empty($this->getMissingDocuments($stage, $mode, $uploadedTypes));
    }

    /**
     * Get complete document guide for a stage and mode.
     */
    public function getStageDocumentGuide(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $requiredDocs = $this->getRequiredDocuments($stage, $mode);
        $optionalDocs = $this->getOptionalDocuments($stage, $mode);
        $counts = $this->getDocumentCounts($stage, $mode);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'required_documents' => array_map(fn (DocumentTypeEnums $doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $requiredDocs),
            'optional_documents' => array_map(fn (DocumentTypeEnums $doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $optionalDocs),
            'counts' => $counts,
        ];
    }

    /**
     * Get all document configurations for a mode.
     */
    public function getAllConfigsForMode(ProcurementModeEnums $mode): array
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
    public function clearCache(?StageEnums $stage = null, ?ProcurementModeEnums $mode = null): void
    {
        if ($stage && $mode) {
            Cache::forget(self::CACHE_PREFIX."required.{$stage->value}.{$mode->value}");
            Cache::forget(self::CACHE_PREFIX."optional.{$stage->value}.{$mode->value}");
        } elseif ($mode) {
            // Clear all stages for a mode
            foreach (StageEnums::cases() as $s) {
                Cache::forget(self::CACHE_PREFIX."required.{$s->value}.{$mode->value}");
                Cache::forget(self::CACHE_PREFIX."optional.{$s->value}.{$mode->value}");
            }
        } else {
            // Clear all
            foreach (ProcurementModeEnums::cases() as $m) {
                foreach (StageEnums::cases() as $s) {
                    Cache::forget(self::CACHE_PREFIX."required.{$s->value}.{$m->value}");
                    Cache::forget(self::CACHE_PREFIX."optional.{$s->value}.{$m->value}");
                }
            }
        }
    }

    /**
     * Save document configuration for a stage and mode.
     *
     * @param  array<DocumentTypeEnums|string>  $requiredDocuments
     * @param  array<DocumentTypeEnums|string>  $optionalDocuments
     */
    public function saveDocumentConfig(
        StageEnums $stage,
        ProcurementModeEnums $mode,
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

        $this->clearCache($stage, $mode);

        return $config;
    }

    /**
     * Reset document configuration to defaults for a stage and mode.
     */
    public function resetToDefaults(StageEnums $stage, ProcurementModeEnums $mode, ?int $updatedBy = null): StageDocumentConfig
    {
        // Get defaults from ModeAwareDocumentRequirements
        $defaultRequired = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
        $defaultOptional = $this->modeAwareRequirements->getOptionalDocuments($stage, $mode);

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
