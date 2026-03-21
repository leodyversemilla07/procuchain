<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use Illuminate\Support\Facades\Cache;

class WorkflowDefinitionService
{
    private const CACHE_TTL = 300;

    private const WORKFLOW_CACHE_PREFIX = 'workflow.definition.';

    private const DOCUMENT_CACHE_PREFIX = 'workflow.documents.';

    public function __construct(
        private readonly StageDocumentRequirements $defaultRequirements,
        private readonly ModeAwareDocumentRequirements $modeAwareRequirements,
    ) {}

    /**
     * @return array<StageEnums>
     */
    public function getStagesForMode(ProcurementModeEnums $mode): array
    {
        $cacheKey = self::WORKFLOW_CACHE_PREFIX."stages.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($mode) {
            $config = ProcurementWorkflowConfig::query()
                ->forMode($mode)
                ->active()
                ->first();

            if ($config !== null) {
                return $config->getStagesAsEnums();
            }

            return StageEnums::getStagesForMode($mode);
        });
    }

    /**
     * @return array<StageEnums>
     */
    public function getOptionalStagesForMode(ProcurementModeEnums $mode): array
    {
        $cacheKey = self::WORKFLOW_CACHE_PREFIX."optional.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($mode) {
            $config = ProcurementWorkflowConfig::query()
                ->forMode($mode)
                ->active()
                ->first();

            if ($config !== null) {
                return $config->getOptionalStagesAsEnums();
            }

            return StageEnums::getOptionalStagesForMode($mode);
        });
    }

    public function isStageInWorkflow(StageEnums $stage, ProcurementModeEnums $mode): bool
    {
        return in_array($stage, $this->getStagesForMode($mode), true);
    }

    public function isStageOptional(StageEnums $stage, ProcurementModeEnums $mode): bool
    {
        return in_array($stage, $this->getOptionalStagesForMode($mode), true);
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $cacheKey = self::DOCUMENT_CACHE_PREFIX."required.{$stage->value}.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stage, $mode) {
            $config = StageDocumentConfig::query()
                ->forStage($stage)
                ->forMode($mode)
                ->active()
                ->first();

            if ($config !== null) {
                return $config->getRequiredDocumentsAsEnums();
            }

            return $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
        });
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $cacheKey = self::DOCUMENT_CACHE_PREFIX."optional.{$stage->value}.{$mode->value}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stage, $mode) {
            $config = StageDocumentConfig::query()
                ->forStage($stage)
                ->forMode($mode)
                ->active()
                ->first();

            if ($config !== null) {
                return $config->getOptionalDocumentsAsEnums();
            }

            return $this->modeAwareRequirements->getOptionalDocuments($stage, $mode);
        });
    }

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
     * @param  array<DocumentTypeEnums>  $uploadedTypes
     * @return array<DocumentTypeEnums>
     */
    public function getMissingDocuments(StageEnums $stage, ProcurementModeEnums $mode, array $uploadedTypes): array
    {
        $requiredDocuments = $this->getRequiredDocuments($stage, $mode);
        $missingDocuments = [];

        foreach ($requiredDocuments as $requiredDocument) {
            if (! in_array($requiredDocument, $uploadedTypes, true)) {
                $missingDocuments[] = $requiredDocument;
            }
        }

        return $missingDocuments;
    }

    public function getStageDocumentGuide(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $requiredDocuments = $this->getRequiredDocuments($stage, $mode);
        $optionalDocuments = $this->getOptionalDocuments($stage, $mode);
        $counts = $this->getDocumentCounts($stage, $mode);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'ngpa_reference' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
            'required_documents' => array_map(fn (DocumentTypeEnums $document): array => [
                'value' => $document->value,
                'display_name' => $document->getDisplayName(),
                'description' => $document->getDescription(),
            ], $requiredDocuments),
            'optional_documents' => array_map(fn (DocumentTypeEnums $document): array => [
                'value' => $document->value,
                'display_name' => $document->getDisplayName(),
                'description' => $document->getDescription(),
            ], $optionalDocuments),
            'counts' => $counts,
        ];
    }

    public function clearCache(?ProcurementModeEnums $mode = null, ?StageEnums $stage = null): void
    {
        if ($mode === null) {
            foreach (ProcurementModeEnums::cases() as $modeCase) {
                $this->clearCache($modeCase);
            }

            return;
        }

        Cache::forget(self::WORKFLOW_CACHE_PREFIX."stages.{$mode->value}");
        Cache::forget(self::WORKFLOW_CACHE_PREFIX."optional.{$mode->value}");

        if ($stage !== null) {
            Cache::forget(self::DOCUMENT_CACHE_PREFIX."required.{$stage->value}.{$mode->value}");
            Cache::forget(self::DOCUMENT_CACHE_PREFIX."optional.{$stage->value}.{$mode->value}");

            return;
        }

        foreach (StageEnums::cases() as $stageCase) {
            Cache::forget(self::DOCUMENT_CACHE_PREFIX."required.{$stageCase->value}.{$mode->value}");
            Cache::forget(self::DOCUMENT_CACHE_PREFIX."optional.{$stageCase->value}.{$mode->value}");
        }
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getDefaultRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        return $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getDefaultOptionalDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        return $this->modeAwareRequirements->getOptionalDocuments($stage, $mode);
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getBaseRequiredDocuments(StageEnums $stage): array
    {
        return $this->defaultRequirements->getRequiredDocuments($stage);
    }

    /**
     * @return array<DocumentTypeEnums>
     */
    public function getBaseOptionalDocuments(StageEnums $stage): array
    {
        return $this->defaultRequirements->getOptionalDocuments($stage);
    }
}
