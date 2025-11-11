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
    case PRE_BID_CONFERENCE = 'pre_bid_conference';
    case SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin';
    case BID_OPENING = 'bid_opening';
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
            self::PRE_BID_CONFERENCE => 'Pre-Bid Conference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental Bid Bulletin',
            self::BID_OPENING => 'Bid Opening',
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
            self::PRE_BID_CONFERENCE => 'PreBidConference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'SupplementalBidBulletin',
            self::BID_OPENING => 'BidOpening',
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
            self::PRE_BID_CONFERENCE => 'Conference to clarify bidding requirements and answer bidder questions',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Issuance of supplemental bulletins to modify or clarify bidding documents',
            self::BID_OPENING => 'Public opening of submitted bids',
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
     * Get the next stage in the workflow
     */
    public function getNextStage(): ?self
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => self::PRE_PROCUREMENT_CONFERENCE,
            self::PRE_PROCUREMENT_CONFERENCE => self::BIDDING_DOCUMENTS,
            self::BIDDING_DOCUMENTS => self::PRE_BID_CONFERENCE,
            self::PRE_BID_CONFERENCE => self::SUPPLEMENTAL_BID_BULLETIN,
            self::SUPPLEMENTAL_BID_BULLETIN => self::BID_OPENING,
            self::BID_OPENING => self::BID_EVALUATION,
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
     * Get the previous stage in the workflow
     */
    public function getPreviousStage(): ?self
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION => null,
            self::PRE_PROCUREMENT_CONFERENCE => self::PROCUREMENT_INITIATION,
            self::BIDDING_DOCUMENTS => self::PRE_PROCUREMENT_CONFERENCE,
            self::PRE_BID_CONFERENCE => self::BIDDING_DOCUMENTS,
            self::SUPPLEMENTAL_BID_BULLETIN => self::PRE_BID_CONFERENCE,
            self::BID_OPENING => self::SUPPLEMENTAL_BID_BULLETIN,
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
}
