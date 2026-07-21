<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;

class StageWorkflowConfig
{
    private const WORKFLOWS = [
        ProcurementMode::COMPETITIVE_BIDDING->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::LIMITED_SOURCE_BIDDING->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::COMPETITIVE_DIALOGUE->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::UNSOLICITED_OFFER_WITH_BID_MATCHING->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::DIRECT_CONTRACTING->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::DIRECT_ACQUISITION->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::REPEAT_ORDER->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::SMALL_VALUE_PROCUREMENT->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::ABSTRACT_OF_QUOTATIONS,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::NEGOTIATED_PROCUREMENT->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::DIRECT_SALES->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
        ProcurementMode::DIRECT_PROCUREMENT_FOR_STI->value => [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ],
    ];

    private const OPTIONAL_STAGES = [
        ProcurementMode::COMPETITIVE_BIDDING->value => [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
        ],
        ProcurementMode::LIMITED_SOURCE_BIDDING->value => [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
        ],
        ProcurementMode::COMPETITIVE_DIALOGUE->value => [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
        ],
        ProcurementMode::UNSOLICITED_OFFER_WITH_BID_MATCHING->value => [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
        ],
        ProcurementMode::SMALL_VALUE_PROCUREMENT->value => [
            StageEnums::PRE_BID_CONFERENCE,
        ],
    ];

    public static function getStagesForMode(ProcurementMode $mode): array
    {
        return self::WORKFLOWS[$mode->value] ?? [];
    }

    public static function getOptionalStagesForMode(ProcurementMode $mode): array
    {
        return self::OPTIONAL_STAGES[$mode->value] ?? [];
    }

    public static function getNextStagesForMode(StageEnums $stage, ProcurementMode $mode): array
    {
        $modeStages = self::getStagesForMode($mode);
        $optionalStages = self::getOptionalStagesForMode($mode);
        $currentIndex = array_search($stage, $modeStages, true);

        if ($currentIndex === false || $currentIndex >= count($modeStages) - 1) {
            return [];
        }

        $nextStages = [];
        $nextIndex = $currentIndex + 1;

        if (isset($modeStages[$nextIndex])) {
            $nextStages[] = $modeStages[$nextIndex];

            if (in_array($modeStages[$nextIndex], $optionalStages, true)) {
                if (isset($modeStages[$nextIndex + 1])) {
                    $nextStages[] = $modeStages[$nextIndex + 1];
                }
            }
        }

        return $nextStages;
    }

    public static function isRequiredForMode(StageEnums $stage, ProcurementMode $mode): bool
    {
        return in_array($stage, self::getStagesForMode($mode), true)
            && ! in_array($stage, self::getOptionalStagesForMode($mode), true);
    }

    public static function existsInModeWorkflow(StageEnums $stage, ProcurementMode $mode): bool
    {
        return in_array($stage, self::getStagesForMode($mode), true);
    }

    public static function getStageCountForMode(ProcurementMode $mode): int
    {
        return count(self::getStagesForMode($mode));
    }

    public static function getRequiredStageCountForMode(ProcurementMode $mode): int
    {
        return count(self::getStagesForMode($mode)) - count(self::getOptionalStagesForMode($mode));
    }

    public static function getWorkflowSummaryForMode(ProcurementMode $mode): array
    {
        $stages = self::getStagesForMode($mode);
        $optionalStages = self::getOptionalStagesForMode($mode);

        $stageDetails = [];
        foreach ($stages as $s) {
            $stageDetails[] = [
                'stage' => $s->value,
                'display_name' => $s->getDisplayName(),
                'required' => ! in_array($s, $optionalStages, true),
                'phase' => $s->getPhase(),
            ];
        }

        return [
            'mode' => $mode->getDisplayName(),
            'total_stages' => count($stages),
            'required_stages' => count($stages) - count($optionalStages),
            'optional_stages' => count($optionalStages),
            'stages' => $stageDetails,
        ];
    }

    public static function getCategoryRequirements(StageEnums $stage, ProcurementCategory $category): array
    {
        // Video recording thresholds per Section 38.3
        $videoThreshold = match ($category) {
            ProcurementCategory::GOODS => 10000000.00,
            ProcurementCategory::INFRASTRUCTURE_PROJECTS => 20000000.00,
            ProcurementCategory::CONSULTING_SERVICES => 5000000.00,
            ProcurementCategory::SERVICES => null,
        };

        // Bidding document timelines
        $timelineDays = match (true) {
            $stage === StageEnums::BIDDING_DOCUMENTS && $category === ProcurementCategory::GOODS => 45,
            $stage === StageEnums::BIDDING_DOCUMENTS && $category === ProcurementCategory::INFRASTRUCTURE_PROJECTS => 65,
            $stage === StageEnums::BIDDING_DOCUMENTS && $category === ProcurementCategory::CONSULTING_SERVICES => 75,
            default => null,
        };

        $specialRequirements = match (true) {
            $stage === StageEnums::POST_QUALIFICATION && $category === ProcurementCategory::INFRASTRUCTURE_PROJECTS => [
                'Site inspection verification',
                'Equipment verification',
                'PCAB license verification',
            ],
            $stage === StageEnums::POST_QUALIFICATION && $category === ProcurementCategory::CONSULTING_SERVICES => [
                'Personnel qualification verification',
                'Previous project experience verification',
            ],
            $stage === StageEnums::MONITORING && $category === ProcurementCategory::INFRASTRUCTURE_PROJECTS => [
                'Progress billing review',
                'Work accomplishment inspection',
                'Variation order monitoring',
            ],
            default => [],
        };

        return [
            'video_recording_threshold' => $videoThreshold,
            'timeline_days' => $timelineDays,
            'special_requirements' => $specialRequirements,
        ];
    }
}
