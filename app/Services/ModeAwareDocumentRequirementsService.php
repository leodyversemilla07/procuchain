<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Support\ModeDocumentRequirements;

class ModeAwareDocumentRequirementsService
{
    public function __construct(
        private readonly StageDocumentRequirementsService $baseRequirements,
        private readonly ModeDocumentRequirements $requirements,
    ) {}

    public function getRequiredDocuments(StageEnums $stage, ProcurementMode $mode): array
    {
        if (! $stage->existsInModeWorkflow($mode)) {
            return [];
        }

        return match ($mode) {
            ProcurementMode::COMPETITIVE_BIDDING,
            ProcurementMode::LIMITED_SOURCE_BIDDING => $this->baseRequirements->getRequiredDocuments($stage),

            ProcurementMode::COMPETITIVE_DIALOGUE => $this->requirements->getCompetitiveDialogueRequirements(
                $stage,
                $this->baseRequirements->getRequiredDocuments($stage),
            ),

            ProcurementMode::UNSOLICITED_OFFER_WITH_BID_MATCHING => $this->requirements->getUnsolicitedOfferRequirements(
                $stage,
                $this->baseRequirements->getRequiredDocuments($stage),
            ),

            ProcurementMode::SMALL_VALUE_PROCUREMENT,
            ProcurementMode::DIRECT_CONTRACTING,
            ProcurementMode::DIRECT_ACQUISITION,
            ProcurementMode::REPEAT_ORDER,
            ProcurementMode::DIRECT_SALES,
            ProcurementMode::NEGOTIATED_PROCUREMENT => $this->requirements->getAlternativeModeRequirements(
                $stage,
                $mode,
                $this->baseRequirements->getRequiredDocuments($stage),
            ),

            ProcurementMode::DIRECT_PROCUREMENT_FOR_STI => $this->requirements->getDirectProcurementSTIRequirements(
                $stage,
                $this->baseRequirements->getRequiredDocuments($stage),
            ),
        };
    }

    public function getOptionalDocuments(StageEnums $stage, ProcurementMode $mode): array
    {
        if (! $stage->existsInModeWorkflow($mode)) {
            return [];
        }

        if ($mode->isAlternativeMode()) {
            return $this->requirements->getAlternativeModeOptionalDocuments($stage, $mode);
        }

        return $this->baseRequirements->getOptionalDocuments($stage);
    }

    public function getDocumentCounts(StageEnums $stage, ProcurementMode $mode): array
    {
        $required = $this->getRequiredDocuments($stage, $mode);
        $optional = $this->getOptionalDocuments($stage, $mode);

        return [
            'required_count' => count($required),
            'optional_count' => count($optional),
            'total_count' => count($required) + count($optional),
        ];
    }

    public function getMissingDocuments(StageEnums $stage, ProcurementMode $mode, array $uploadedTypes): array
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

    public function hasMinimumRequiredDocuments(StageEnums $stage, ProcurementMode $mode, array $uploadedTypes): bool
    {
        return empty($this->getMissingDocuments($stage, $mode, $uploadedTypes));
    }

    public function getStageDocumentGuide(StageEnums $stage, ProcurementMode $mode): array
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
            'ngpa_reference' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
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

    public function getAbcAwareRequiredDocuments(
        StageEnums $stage,
        ProcurementMode $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $baseRequired = $this->getRequiredDocuments($stage, $mode);

        if ($this->requiresVideoRecording($stage, $abcAmount, $category)) {
            if ($stage === StageEnums::PRE_BID_CONFERENCE) {
                if (! in_array(DocumentTypeEnums::PRE_BID_RECORDING, $baseRequired, true)) {
                    $baseRequired[] = DocumentTypeEnums::PRE_BID_RECORDING;
                }
            } elseif ($stage === StageEnums::BID_OPENING) {
                if (! in_array(DocumentTypeEnums::BID_OPENING_RECORDING, $baseRequired, true)) {
                    $baseRequired[] = DocumentTypeEnums::BID_OPENING_RECORDING;
                }
            }
        }

        return $baseRequired;
    }

    public function requiresVideoRecording(StageEnums $stage, float $abcAmount, string $category = 'goods'): bool
    {
        if (! in_array($stage, [StageEnums::PRE_BID_CONFERENCE, StageEnums::BID_OPENING], true)) {
            return false;
        }

        $thresholds = [
            'goods' => 10_000_000.00,
            'infrastructure' => 20_000_000.00,
            'consulting' => 5_000_000.00,
        ];

        $threshold = $thresholds[$category] ?? $thresholds['goods'];

        return $abcAmount > $threshold;
    }

    public function getAbcAwareOptionalDocuments(
        StageEnums $stage,
        ProcurementMode $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $baseOptional = $this->getOptionalDocuments($stage, $mode);

        if ($this->requiresVideoRecording($stage, $abcAmount, $category)) {
            $baseOptional = array_filter($baseOptional, function ($doc) use ($stage) {
                if ($stage === StageEnums::PRE_BID_CONFERENCE) {
                    return $doc !== DocumentTypeEnums::PRE_BID_RECORDING;
                } elseif ($stage === StageEnums::BID_OPENING) {
                    return $doc !== DocumentTypeEnums::BID_OPENING_RECORDING;
                }

                return true;
            });
        }

        return array_values($baseOptional);
    }

    public function getAbcAwareStageDocumentGuide(
        StageEnums $stage,
        ProcurementMode $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $requiredDocs = $this->getAbcAwareRequiredDocuments($stage, $mode, $abcAmount, $category);
        $optionalDocs = $this->getAbcAwareOptionalDocuments($stage, $mode, $abcAmount, $category);

        $requiresRecording = $this->requiresVideoRecording($stage, $abcAmount, $category);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'ngpa_reference' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
            'abc_amount' => $abcAmount,
            'category' => $category,
            'requires_video_recording' => $requiresRecording,
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
            'counts' => [
                'required_count' => count($requiredDocs),
                'optional_count' => count($optionalDocs),
                'total_count' => count($requiredDocs) + count($optionalDocs),
            ],
        ];
    }
}
