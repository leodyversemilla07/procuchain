<?php

namespace App\Enums;

use App\Support\StageActivities;
use App\Support\StageWorkflowConfig;

/**
 * Stage Enum
 *
 * Represents all stages in the procurement workflow.
 * Stages follow the government procurement process from initiation to completion.
 */
enum StageEnums: string
{
    case PROCUREMENT_INITIATION = 'procurement_initiation';
    case PRE_PROCUREMENT_CONFERENCE = 'pre_procurement_conference';
    case BIDDING_DOCUMENTS = 'bidding_documents';
    case REQUEST_FOR_QUOTATION = 'request_for_quotation';  // For alternative modes (SVP, Direct Contracting, etc.)
    case PRE_BID_CONFERENCE = 'pre_bid_conference';
    case SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin';
    case BID_OPENING = 'bid_opening';
    case ABSTRACT_OF_QUOTATIONS = 'abstract_of_quotations';  // For alternative modes (evaluates RFQ responses)
    case BID_EVALUATION = 'bid_evaluation';
    case POST_QUALIFICATION = 'post_qualification';
    case BAC_RESOLUTION = 'bac_resolution';
    case NOTICE_OF_AWARD = 'notice_of_award';
    case PERFORMANCE_BOND_CONTRACT_AND_PO = 'performance_bond_contract_and_po';
    case NOTICE_TO_PROCEED = 'notice_to_proceed';
    case MONITORING = 'monitoring';
    case COMPLETION = 'completion';
    case COMPLETED = 'completed';

    /**
     * Get the user-friendly display name for the stage
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => 'Procurement Initiation',
            self::PRE_PROCUREMENT_CONFERENCE => 'Pre-Procurement Conference',
            self::BIDDING_DOCUMENTS => 'Bidding Documents',
            self::REQUEST_FOR_QUOTATION => 'Request for Quotation',
            self::PRE_BID_CONFERENCE => 'Pre-Bid Conference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental Bid Bulletin',
            self::BID_OPENING => 'Bid Opening',
            self::ABSTRACT_OF_QUOTATIONS => 'Abstract of Quotations',
            self::BID_EVALUATION => 'Bid Evaluation',
            self::POST_QUALIFICATION => 'Post-Qualification',
            self::BAC_RESOLUTION => 'BAC Resolution',
            self::NOTICE_OF_AWARD => 'Notice of Award',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => 'Performance Bond, Contract and PO',
            self::NOTICE_TO_PROCEED => 'Notice to Proceed',
            self::MONITORING => 'Monitoring',
            self::COMPLETION => 'Completion',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get the storage path segment for File organization
     */
    public function getStoragePathSegment(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => 'ProcurementInitiation',
            self::PRE_PROCUREMENT_CONFERENCE => 'PreProcurementConference',
            self::BIDDING_DOCUMENTS => 'BiddingDocuments',
            self::REQUEST_FOR_QUOTATION => 'RequestForQuotation',
            self::PRE_BID_CONFERENCE => 'PreBidConference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'SupplementalBidBulletin',
            self::BID_OPENING => 'BidOpening',
            self::ABSTRACT_OF_QUOTATIONS => 'AbstractOfQuotations',
            self::BID_EVALUATION => 'BidEvaluation',
            self::POST_QUALIFICATION => 'PostQualification',
            self::BAC_RESOLUTION => 'BACResolution',
            self::NOTICE_OF_AWARD => 'NoticeOfAward',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => 'PerformanceBondContractAndPO',
            self::NOTICE_TO_PROCEED => 'NTP',
            self::MONITORING => 'Monitoring',
            self::COMPLETION => 'Completion',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get a description of the stage
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => 'Initial stage where procurement requirements are defined and approved',
            self::PRE_PROCUREMENT_CONFERENCE => 'Optional conference to discuss procurement requirements with potential bidders',
            self::BIDDING_DOCUMENTS => 'Preparation and publication of official bidding documents',
            self::REQUEST_FOR_QUOTATION => 'Preparation and sending of RFQ to suppliers for alternative procurement modes',
            self::PRE_BID_CONFERENCE => 'Conference to clarify bidding requirements and answer bidder questions',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Issuance of supplemental bulletins to modify or clarify bidding documents',
            self::BID_OPENING => 'Public opening of submitted bids',
            self::ABSTRACT_OF_QUOTATIONS => 'Compilation and evaluation of received quotations from suppliers',
            self::BID_EVALUATION => 'Technical and financial evaluation of submitted bids',
            self::POST_QUALIFICATION => 'Verification of winning bidder\'s qualifications and capacity',
            self::BAC_RESOLUTION => 'Formal resolution by the Bids and Awards Committee',
            self::NOTICE_OF_AWARD => 'Official notification of contract award to winning bidder',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => 'Submission of performance bond, contract signing, and purchase order issuance',
            self::NOTICE_TO_PROCEED => 'Authorization for contractor to begin work',
            self::MONITORING => 'Active monitoring of contract implementation',
            self::COMPLETION => 'Final stage of contract completion and acceptance',
            self::COMPLETED => 'Procurement process fully completed',
        };
    }

    /**
     * Get the next stage in the workflow (simple linear path for Competitive Bidding)
     * For flexible workflow or alternative modes, use getNextStagesForMode() instead
     */
    public function getNextStage(): ?self
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => self::PRE_PROCUREMENT_CONFERENCE,
            self::PRE_PROCUREMENT_CONFERENCE => self::BIDDING_DOCUMENTS,
            self::BIDDING_DOCUMENTS => self::PRE_BID_CONFERENCE,
            self::REQUEST_FOR_QUOTATION => self::ABSTRACT_OF_QUOTATIONS,
            self::PRE_BID_CONFERENCE => self::SUPPLEMENTAL_BID_BULLETIN,
            self::SUPPLEMENTAL_BID_BULLETIN => self::BID_OPENING,
            self::BID_OPENING => self::BID_EVALUATION,
            self::ABSTRACT_OF_QUOTATIONS => self::BAC_RESOLUTION,
            self::BID_EVALUATION => self::POST_QUALIFICATION,
            self::POST_QUALIFICATION => self::BAC_RESOLUTION,
            self::BAC_RESOLUTION => self::NOTICE_OF_AWARD,
            self::NOTICE_OF_AWARD => self::PERFORMANCE_BOND_CONTRACT_AND_PO,
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => self::NOTICE_TO_PROCEED,
            self::NOTICE_TO_PROCEED => self::MONITORING,
            self::MONITORING => self::COMPLETION,
            self::COMPLETION => self::COMPLETED,
            self::COMPLETED => null,
        };
    }

    /**
     * Get all possible next stages (flexible workflow - Issue #8 fix)
     * Returns array of stages that can follow the current stage
     * Note: This is for Competitive Bidding. Use getNextStagesForMode() for other modes.
     *
     * @return array<self>
     */
    public function getNextStages(): array
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => [
                self::PRE_PROCUREMENT_CONFERENCE, // Optional per RA 12009 (NGPA)
                self::BIDDING_DOCUMENTS,           // Can skip to this (Competitive Bidding)
                self::REQUEST_FOR_QUOTATION,       // For alternative modes
            ],
            self::PRE_PROCUREMENT_CONFERENCE => [
                self::BIDDING_DOCUMENTS,
                self::REQUEST_FOR_QUOTATION,       // For alternative modes
                self::BAC_RESOLUTION,              // For Negotiated Procurement
            ],
            self::BIDDING_DOCUMENTS => [
                self::PRE_BID_CONFERENCE,
            ],
            self::REQUEST_FOR_QUOTATION => [
                self::ABSTRACT_OF_QUOTATIONS,
            ],
            self::PRE_BID_CONFERENCE => [
                self::SUPPLEMENTAL_BID_BULLETIN,  // Optional
                self::BID_OPENING,                // Can skip to this
            ],
            self::SUPPLEMENTAL_BID_BULLETIN => [
                self::SUPPLEMENTAL_BID_BULLETIN,  // Can repeat
                self::BID_OPENING,                // Move forward
            ],
            self::BID_OPENING => [
                self::BID_EVALUATION,
            ],
            self::ABSTRACT_OF_QUOTATIONS => [
                self::BAC_RESOLUTION,
            ],
            self::BID_EVALUATION => [
                self::POST_QUALIFICATION,
            ],
            self::POST_QUALIFICATION => [
                self::BAC_RESOLUTION,
            ],
            self::BAC_RESOLUTION => [
                self::NOTICE_OF_AWARD,
            ],
            self::NOTICE_OF_AWARD => [
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
            ],
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => [
                self::NOTICE_TO_PROCEED,
            ],
            self::NOTICE_TO_PROCEED => [
                self::MONITORING,
            ],
            self::MONITORING => [
                self::COMPLETION,
            ],
            self::COMPLETION => [
                self::COMPLETED,
            ],
            self::COMPLETED => [],
        };
    }

    /**
     * Check if this stage can be skipped (Issue #8 fix)
     * Per RA 12009 (NGPA), some stages are optional
     */
    public function canSkip(): bool
    {
        return match ($this) {
            self::PRE_PROCUREMENT_CONFERENCE => true, // Optional per RA 12009 (NGPA)
            self::SUPPLEMENTAL_BID_BULLETIN => true,   // Optional
            default => false,
        };
    }

    /**
     * Check if this stage can be repeated (Issue #8 fix)
     * Some stages like supplemental bulletins can occur multiple times
     */
    public function canRepeat(): bool
    {
        return match ($this) {
            self::SUPPLEMENTAL_BID_BULLETIN => true,  // Can be issued multiple times
            default => false,
        };
    }

    /**
     * Check if the given stage is a valid next stage from current stage
     * Supports flexible workflow (Issue #8 fix)
     */
    public function isValidNextStage(self $nextStage): bool
    {
        $possibleNext = $this->getNextStages();

        return in_array($nextStage, $possibleNext, true);
    }

    /**
     * Get the previous stage in the workflow (for Competitive Bidding)
     * Note: For alternative modes, the previous stage may differ
     */
    public function getPreviousStage(): ?self
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => null,
            self::PRE_PROCUREMENT_CONFERENCE => self::PROCUREMENT_INITIATION,
            self::BIDDING_DOCUMENTS => self::PRE_PROCUREMENT_CONFERENCE,
            self::REQUEST_FOR_QUOTATION => self::PROCUREMENT_INITIATION,
            self::PRE_BID_CONFERENCE => self::BIDDING_DOCUMENTS,
            self::SUPPLEMENTAL_BID_BULLETIN => self::PRE_BID_CONFERENCE,
            self::BID_OPENING => self::SUPPLEMENTAL_BID_BULLETIN,
            self::ABSTRACT_OF_QUOTATIONS => self::REQUEST_FOR_QUOTATION,
            self::BID_EVALUATION => self::BID_OPENING,
            self::POST_QUALIFICATION => self::BID_EVALUATION,
            self::BAC_RESOLUTION => self::POST_QUALIFICATION,
            self::NOTICE_OF_AWARD => self::BAC_RESOLUTION,
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => self::NOTICE_OF_AWARD,
            self::NOTICE_TO_PROCEED => self::PERFORMANCE_BOND_CONTRACT_AND_PO,
            self::MONITORING => self::NOTICE_TO_PROCEED,
            self::COMPLETION => self::MONITORING,
            self::COMPLETED => self::COMPLETION,
        };
    }

    /**
     * Check if the stage is final
     */
    public function isFinal(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if the stage is initial
     */
    public function isInitial(): bool
    {
        return $this === self::PROCUREMENT_INITIATION;
    }

    /**
     * Get the procurement phase this stage belongs to per RA 12009 (NGPA)
     *
     * NGPA IRR structure:
     *   Pre-Procurement: Rule II (Sec 7-12) — Planning & Preparation
     *   Procurement:     Rule V-X (Sec 41-65) — Bidding, Evaluation, Post-Qual
     *   Post-Procurement: Rule XI (Sec 66-71) — Award & Implementation
     *
     * @return string 'pre_procurement', 'procurement', or 'post_procurement'
     */
    public function getPhase(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION,
            self::PRE_PROCUREMENT_CONFERENCE,
            self::BIDDING_DOCUMENTS,
            self::REQUEST_FOR_QUOTATION => 'pre_procurement',

            self::PRE_BID_CONFERENCE,
            self::SUPPLEMENTAL_BID_BULLETIN,
            self::BID_OPENING,
            self::ABSTRACT_OF_QUOTATIONS,
            self::BID_EVALUATION,
            self::POST_QUALIFICATION,
            self::BAC_RESOLUTION => 'procurement', // BAC Resolution (Sec 66) recommends award — still procurement phase

            self::NOTICE_OF_AWARD,
            self::PERFORMANCE_BOND_CONTRACT_AND_PO,
            self::NOTICE_TO_PROCEED,
            self::MONITORING,
            self::COMPLETION,
            self::COMPLETED => 'post_procurement',
        };
    }

    /**
     * Get the display name for the phase
     */
    public function getPhaseDisplayName(): string
    {
        return match ($this->getPhase()) {
            'pre_procurement' => 'Pre-Procurement',
            'procurement' => 'Procurement',
            'post_procurement' => 'Post-Procurement',
        };
    }

    /**
     * Get the full phase display with description
     */
    public function getPhaseDisplayNameWithDescription(): string
    {
        return match ($this->getPhase()) {
            'pre_procurement' => 'Pre-Procurement (Planning & Preparation)',
            'procurement' => 'Procurement (Bidding & Evaluation)',
            'post_procurement' => 'Post-Procurement (Award & Implementation)',
        };
    }

    /**
     * Check if the stage is in the pre-procurement phase
     */
    public function isPreProcurement(): bool
    {
        return $this->getPhase() === 'pre_procurement';
    }

    /**
     * Check if the stage is in the procurement phase
     */
    public function isProcurement(): bool
    {
        return $this->getPhase() === 'procurement';
    }

    /**
     * Check if the stage is in the post-procurement phase
     */
    public function isPostProcurement(): bool
    {
        return $this->getPhase() === 'post_procurement';
    }

    /**
     * Get all stages for a specific phase
     *
     * @param  string  $phase  'pre_procurement', 'procurement', or 'post_procurement'
     * @return array<self>
     */
    public static function getStagesByPhase(string $phase): array
    {
        return array_filter(
            self::cases(),
            fn (self $stage) => $stage->getPhase() === $phase
        );
    }

    /**
     * Get all phases with their stages grouped
     *
     * @return array<string, array<self>>
     */
    public static function getAllPhasesWithStages(): array
    {
        return [
            'pre_procurement' => self::getStagesByPhase('pre_procurement'),
            'procurement' => self::getStagesByPhase('procurement'),
            'post_procurement' => self::getStagesByPhase('post_procurement'),
        ];
    }

    /**
     * Get phase progress based on stage position
     *
     * @return array{phase: string, progress: int, current_stage_in_phase: int, total_stages_in_phase: int}
     */
    public function getPhaseProgress(): array
    {
        $phase = $this->getPhase();
        $stagesInPhase = self::getStagesByPhase($phase);
        $totalStagesInPhase = count($stagesInPhase);

        // Find position of current stage within its phase
        $currentPosition = 0;
        foreach ($stagesInPhase as $index => $stage) {
            if ($stage === $this) {
                $currentPosition = $index + 1;
                break;
            }
        }

        $progress = $totalStagesInPhase > 0
            ? (int) round(($currentPosition / $totalStagesInPhase) * 100)
            : 0;

        return [
            'phase' => $phase,
            'progress' => $progress,
            'current_stage_in_phase' => $currentPosition,
            'total_stages_in_phase' => $totalStagesInPhase,
        ];
    }

    /**
     * Get all cases as an array of values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as an array of display names
     *
     * @return array<string, string> [value => display_name]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getDisplayName();
        }

        return $options;
    }

    /**
     * Get the integer ID for this stage (1-based index for compatibility with legacy systems)
     */
    public function getId(): int
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $index + 1; // 1-based indexing
    }

    /**
     * Get the URL-friendly slug (kebab-case) for this stage
     */
    public function getSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /**
     * Get stage from slug (kebab-case)
     */
    public static function fromSlug(string $slug): ?self
    {
        $value = str_replace('-', '_', $slug);

        return self::tryFrom($value);
    }

    public static function getStagesForMode(ProcurementMode $mode): array
    {
        return StageWorkflowConfig::getStagesForMode($mode);
    }

    /**
     * Get optional stages for a specific procurement mode
     * These stages can be skipped per NGPA IRR
     *
     * @return array<self>
     */
    public static function getOptionalStagesForMode(ProcurementMode $mode): array
    {
        return StageWorkflowConfig::getOptionalStagesForMode($mode);
    }

    /**
     * Get the next valid stages for a mode from current stage
     * Respects mode-specific workflow and optional stages
     *
     * @return array<self>
     */
    public function getNextStagesForMode(ProcurementMode $mode): array
    {
        return StageWorkflowConfig::getNextStagesForMode($this, $mode);
    }

    /**
     * Check if a stage is required for a specific mode
     */
    public function isRequiredForMode(ProcurementMode $mode): bool
    {
        return StageWorkflowConfig::isRequiredForMode($this, $mode);
    }

    /**
     * Check if a stage exists in a mode's workflow
     */
    public function existsInModeWorkflow(ProcurementMode $mode): bool
    {
        return StageWorkflowConfig::existsInModeWorkflow($this, $mode);
    }

    public static function getStageCountForMode(ProcurementMode $mode): int
    {
        return StageWorkflowConfig::getStageCountForMode($mode);
    }

    public static function getRequiredStageCountForMode(ProcurementMode $mode): int
    {
        return StageWorkflowConfig::getRequiredStageCountForMode($mode);
    }

    /**
     * Get category-specific requirements for a stage
     * Per NGPA IRR different categories may have different requirements
     *
     * @return array{video_recording_threshold: ?float, timeline_days: ?int, special_requirements: array<string>}
     */
    public function getCategoryRequirements(ProcurementCategory $category): array
    {
        return StageWorkflowConfig::getCategoryRequirements($this, $category);
    }

    /**
     * Get workflow summary for a procurement mode
     *
     * @return array{mode: string, total_stages: int, required_stages: int, optional_stages: int, stages: array<array{stage: string, display_name: string, required: bool, phase: string}>}
     */
    public static function getWorkflowSummaryForMode(ProcurementMode $mode): array
    {
        return StageWorkflowConfig::getWorkflowSummaryForMode($mode);
    }

    /**
     * Check if this stage can be repeated in the workflow
     */
    public function isRepeatable(): bool
    {
        return $this === self::SUPPLEMENTAL_BID_BULLETIN;
    }

    /**
     * Get key activities performed during this stage
     *
     * @return array<int, string>
     */
    public function getKeyActivities(): array
    {
        return StageActivities::getKeyActivities($this);
    }
}
