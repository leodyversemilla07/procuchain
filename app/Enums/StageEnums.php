<?php

namespace App\Enums;

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
     * Get the storage path segment for file organization
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

    /**
     * Get the workflow stages for a specific procurement mode
     * Per NGPA IRR - different modes have different workflow requirements
     *
     * @return array<self>
     */
    public static function getStagesForMode(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // ═══════════════════════════════════════════════════════════════
            // COMPETITIVE MODES - Full or near-full bidding process
            // ═══════════════════════════════════════════════════════════════

            // Competitive Bidding: Full 15-stage workflow per Section 27
            ProcurementModeEnums::COMPETITIVE_BIDDING => [
                self::PROCUREMENT_INITIATION,
                self::PRE_PROCUREMENT_CONFERENCE,
                self::BIDDING_DOCUMENTS,
                self::PRE_BID_CONFERENCE,
                self::SUPPLEMENTAL_BID_BULLETIN,
                self::BID_OPENING,
                self::BID_EVALUATION,
                self::POST_QUALIFICATION,
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Limited Source Bidding: Same as Competitive Bidding per Section 28.5
            // "observe the procedure for Competitive Bidding"
            ProcurementModeEnums::LIMITED_SOURCE_BIDDING => [
                self::PROCUREMENT_INITIATION,
                self::PRE_PROCUREMENT_CONFERENCE,
                self::BIDDING_DOCUMENTS,
                self::PRE_BID_CONFERENCE,
                self::SUPPLEMENTAL_BID_BULLETIN,
                self::BID_OPENING,
                self::BID_EVALUATION,
                self::POST_QUALIFICATION,
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Competitive Dialogue: Two-stage process per Section 29
            ProcurementModeEnums::COMPETITIVE_DIALOGUE => [
                self::PROCUREMENT_INITIATION,
                self::PRE_PROCUREMENT_CONFERENCE,
                self::BIDDING_DOCUMENTS,           // First stage: initial proposals
                self::PRE_BID_CONFERENCE,          // Dialogue phase
                self::SUPPLEMENTAL_BID_BULLETIN,   // Finalize specs
                self::BID_OPENING,                 // Second stage: final proposals
                self::BID_EVALUATION,
                self::POST_QUALIFICATION,
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Unsolicited Offer with Bid Matching per Section 30
            ProcurementModeEnums::UNSOLICITED_OFFER_WITH_BID_MATCHING => [
                self::PROCUREMENT_INITIATION,      // Evaluate unsolicited offer
                self::PRE_PROCUREMENT_CONFERENCE,  // Negotiation with original offeror
                self::BIDDING_DOCUMENTS,           // Publication for bid matching
                self::BID_OPENING,                 // Bid matching period
                self::BID_EVALUATION,
                self::POST_QUALIFICATION,
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // ═══════════════════════════════════════════════════════════════
            // ALTERNATIVE MODES - Simplified procedures per Section 26.4
            // May be delegated to End-User or Procurement Unit
            // ═══════════════════════════════════════════════════════════════

            // Direct Contracting per Section 31: RFQ-based, no elaborate bidding
            ProcurementModeEnums::DIRECT_CONTRACTING => [
                self::PROCUREMENT_INITIATION,
                self::REQUEST_FOR_QUOTATION,       // BAC prepares RFQ per Section 31.3
                self::BAC_RESOLUTION,              // BAC recommends award
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Direct Acquisition per Section 32: Very simple (≤₱200,000)
            ProcurementModeEnums::DIRECT_ACQUISITION => [
                self::PROCUREMENT_INITIATION,
                self::NOTICE_OF_AWARD,             // Direct purchase
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Repeat Order per Section 33: Purchase from previous winning bidder
            ProcurementModeEnums::REPEAT_ORDER => [
                self::PROCUREMENT_INITIATION,
                self::REQUEST_FOR_QUOTATION,       // RFQ to previous winning bidder
                self::BAC_RESOLUTION,              // Verify conditions met
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Small Value Procurement per Section 34: RFQ with 3 quotations
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                self::PROCUREMENT_INITIATION,
                self::REQUEST_FOR_QUOTATION,       // RFQ to at least 3 suppliers per Section 34.3(c)
                self::ABSTRACT_OF_QUOTATIONS,      // Abstract of Quotations per Section 34.3(e)
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Negotiated Procurement per Section 35
            ProcurementModeEnums::NEGOTIATED_PROCUREMENT => [
                self::PROCUREMENT_INITIATION,
                self::PRE_PROCUREMENT_CONFERENCE,  // Negotiation phase
                self::BAC_RESOLUTION,              // Recommend award
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Direct Sales per Section 36: From supplier with completed contract
            ProcurementModeEnums::DIRECT_SALES => [
                self::PROCUREMENT_INITIATION,
                self::REQUEST_FOR_QUOTATION,       // RFQ to qualified supplier
                self::BAC_RESOLUTION,              // Verify conditions met
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],

            // Direct Procurement for STI per Section 37
            ProcurementModeEnums::DIRECT_PROCUREMENT_FOR_STI => [
                self::PROCUREMENT_INITIATION,
                self::REQUEST_FOR_QUOTATION,       // RFQ to R&D suppliers
                self::BAC_RESOLUTION,
                self::NOTICE_OF_AWARD,
                self::PERFORMANCE_BOND_CONTRACT_AND_PO,
                self::NOTICE_TO_PROCEED,
                self::MONITORING,
                self::COMPLETION,
                self::COMPLETED,
            ],
        };
    }

    /**
     * Get optional stages for a specific procurement mode
     * These stages can be skipped per NGPA IRR
     *
     * @return array<self>
     */
    public static function getOptionalStagesForMode(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // Competitive modes: Pre-procurement conference and supplemental bulletin optional
            ProcurementModeEnums::COMPETITIVE_BIDDING,
            ProcurementModeEnums::LIMITED_SOURCE_BIDDING,
            ProcurementModeEnums::COMPETITIVE_DIALOGUE,
            ProcurementModeEnums::UNSOLICITED_OFFER_WITH_BID_MATCHING => [
                self::PRE_PROCUREMENT_CONFERENCE,
                self::SUPPLEMENTAL_BID_BULLETIN,
            ],

            // SVP: Pre-bid conference optional per Section 34.3(d)
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                self::PRE_BID_CONFERENCE,
            ],

            // Other modes: No optional stages in simplified workflow
            default => [],
        };
    }

    /**
     * Get the next valid stages for a mode from current stage
     * Respects mode-specific workflow and optional stages
     *
     * @return array<self>
     */
    public function getNextStagesForMode(ProcurementModeEnums $mode): array
    {
        $modeStages = self::getStagesForMode($mode);
        $optionalStages = self::getOptionalStagesForMode($mode);
        $currentIndex = array_search($this, $modeStages, true);

        if ($currentIndex === false || $currentIndex >= count($modeStages) - 1) {
            return [];
        }

        $nextStages = [];
        $nextIndex = $currentIndex + 1;

        // Always include the immediate next stage
        if (isset($modeStages[$nextIndex])) {
            $nextStages[] = $modeStages[$nextIndex];

            // If next stage is optional, also include the one after
            if (in_array($modeStages[$nextIndex], $optionalStages, true)) {
                if (isset($modeStages[$nextIndex + 1])) {
                    $nextStages[] = $modeStages[$nextIndex + 1];
                }
            }
        }

        return $nextStages;
    }

    /**
     * Check if a stage is required for a specific mode
     */
    public function isRequiredForMode(ProcurementModeEnums $mode): bool
    {
        $modeStages = self::getStagesForMode($mode);
        $optionalStages = self::getOptionalStagesForMode($mode);

        return in_array($this, $modeStages, true) && ! in_array($this, $optionalStages, true);
    }

    /**
     * Check if a stage exists in a mode's workflow
     */
    public function existsInModeWorkflow(ProcurementModeEnums $mode): bool
    {
        return in_array($this, self::getStagesForMode($mode), true);
    }

    /**
     * Get stage count for a specific mode
     */
    public static function getStageCountForMode(ProcurementModeEnums $mode): int
    {
        return count(self::getStagesForMode($mode));
    }

    /**
     * Get required stage count for a mode (excluding optional stages)
     */
    public static function getRequiredStageCountForMode(ProcurementModeEnums $mode): int
    {
        $total = count(self::getStagesForMode($mode));
        $optional = count(self::getOptionalStagesForMode($mode));

        return $total - $optional;
    }

    /**
     * Get category-specific requirements for a stage
     * Per NGPA IRR different categories may have different requirements
     *
     * @return array{video_recording_threshold: ?float, timeline_days: ?int, special_requirements: array<string>}
     */
    public function getCategoryRequirements(ProcurementCategoryEnums $category): array
    {
        // Video recording thresholds per Section 38.3
        $videoThreshold = match ($category) {
            ProcurementCategoryEnums::GOODS => 10000000.00,
            ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS => 20000000.00,
            ProcurementCategoryEnums::CONSULTING_SERVICES => 5000000.00,
            ProcurementCategoryEnums::SERVICES => null, // Not specified in NGPA
        };

        // Competitive Dialogue timelines per Section 29.4.1
        $timelineDays = match (true) {
            $this === self::BIDDING_DOCUMENTS && $category === ProcurementCategoryEnums::GOODS => 45,
            $this === self::BIDDING_DOCUMENTS && $category === ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS => 65,
            $this === self::BIDDING_DOCUMENTS && $category === ProcurementCategoryEnums::CONSULTING_SERVICES => 75,
            default => null,
        };

        // Category-specific requirements
        $specialRequirements = match (true) {
            $this === self::POST_QUALIFICATION && $category === ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS => [
                'Site inspection verification',
                'Equipment verification',
                'PCAB license verification',
            ],
            $this === self::POST_QUALIFICATION && $category === ProcurementCategoryEnums::CONSULTING_SERVICES => [
                'Personnel qualification verification',
                'Previous project experience verification',
            ],
            $this === self::MONITORING && $category === ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS => [
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

    /**
     * Get workflow summary for a procurement mode
     *
     * @return array{mode: string, total_stages: int, required_stages: int, optional_stages: int, stages: array<array{stage: string, display_name: string, required: bool, phase: string}>}
     */
    public static function getWorkflowSummaryForMode(ProcurementModeEnums $mode): array
    {
        $stages = self::getStagesForMode($mode);
        $optionalStages = self::getOptionalStagesForMode($mode);

        $stageDetails = [];
        foreach ($stages as $stage) {
            $stageDetails[] = [
                'stage' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'required' => ! in_array($stage, $optionalStages, true),
                'phase' => $stage->getPhase(),
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
        return match ($this) {
            self::PROCUREMENT_INITIATION => [
                'Preparation of Purchase Request (PR)',
                'Project Procurement Management Plan (PPMP)',
                'Annual Investment Plan (AIP) inclusion',
                'Budget allocation and certification',
                'Specification preparation and market study',
            ],
            self::PRE_PROCUREMENT_CONFERENCE => [
                'Review of procurement documents',
                'Validation of technical specifications',
                'Budget adequacy assessment',
                'Timeline and milestone setting',
                'Readiness confirmation by BAC',
            ],
            self::BIDDING_DOCUMENTS => [
                'Preparation of Invitation to Bid (ITB)',
                'Instructions to Bidders (IB)',
                'Bid Data Sheet (BDS)',
                'General and Special Conditions of Contract',
                'Technical specifications and drawings',
                'Bill of Quantities / Schedule of Requirements',
            ],
            self::REQUEST_FOR_QUOTATION => [
                'Preparation of RFQ documents',
                'Selection of suppliers to invite',
                'Distribution of RFQ to at least 3 suppliers',
                'Supplier inquiries and clarifications',
                'Setting of submission deadline',
            ],
            self::PRE_BID_CONFERENCE => [
                'Presentation of procurement requirements',
                'Response to bidder queries and clarifications',
                'Site visit arrangements (if applicable)',
                'Recording of all queries and responses',
                'Distribution of conference minutes',
            ],
            self::SUPPLEMENTAL_BID_BULLETIN => [
                'Clarification of ambiguous specifications',
                'Correction of errors in bidding documents',
                'Response to written bidder queries',
                'Extension of bid submission deadline',
                'Amendment to terms and conditions',
            ],
            self::BID_OPENING => [
                'Verification of sealed bid envelopes',
                'Checking of bid security',
                'Opening of technical and financial proposals',
                'Recording of bid amounts',
                'Preliminary examination of bids',
            ],
            self::ABSTRACT_OF_QUOTATIONS => [
                'Collection of all quotation submissions',
                'Comparison of prices and terms',
                'Verification of supplier eligibility',
                'Determination of lowest calculated quotation',
                'Documentation of evaluation process',
            ],
            self::BID_EVALUATION => [
                'Detailed evaluation against specifications',
                'Verification of bid computation',
                'Assessment of technical compliance',
                'Financial capability evaluation',
                'Determination of Lowest Calculated Bid (LCB)',
            ],
            self::POST_QUALIFICATION => [
                'Verification of legal requirements',
                'Technical capability assessment',
                'Financial capability verification',
                'Site inspection (if applicable)',
                'Reference checking',
            ],
            self::BAC_RESOLUTION => [
                'Declaration of Lowest Calculated Responsive Bid',
                'Recommendation for award',
                'Documentation of BAC decision',
                'Approval by Head of Procuring Entity',
                'Filing of motion for reconsideration period',
            ],
            self::NOTICE_OF_AWARD => [
                'Preparation and signing of NOA',
                'Notification to winning bidder',
                'Posting on PhilGEPS and agency website',
                'Notice to unsuccessful bidders',
                'Setting deadline for contract signing',
            ],
            self::PERFORMANCE_BOND_CONTRACT_AND_PO => [
                'Submission of performance security',
                'Verification of bond authenticity',
                'Contract preparation and notarization',
                'Purchase order issuance',
                'PhilGEPS award notice posting',
            ],
            self::NOTICE_TO_PROCEED => [
                'Issuance of NTP to winning bidder',
                'Setting of contract effectivity date',
                'Coordination with end-user unit',
                'Mobilization preparation',
                'Timeline confirmation',
            ],
            self::MONITORING => [
                'Progress tracking and reporting',
                'Quality assurance inspections',
                'Delivery verification',
                'Issue resolution and documentation',
                'Milestone and payment processing',
            ],
            self::COMPLETION => [
                'Final inspection and acceptance',
                'Preparation of completion report',
                'Final payment processing',
                'Performance evaluation',
                'Contract closeout documentation',
            ],
            self::COMPLETED => [
                'All deliverables received and accepted',
                'All payments processed',
                'Contract formally closed',
                'Documentation archived',
                'Performance bond released (if applicable)',
            ],
        };
    }
}
