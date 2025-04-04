<?php

namespace App\Enums;

enum StageEnums: string
{
    // Procurement stages
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
    case COMPLETED = 'completed';
    case COMPLETION = 'completion';

    // Helper method to get display name
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
            self::COMPLETED => 'Completed',
            self::COMPLETION => 'Completion',
        };
    }

    // Storage path helper similar to your example
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
            self::COMPLETED => 'Completed',
            self::COMPLETION => 'Completion',
        };
    }
}
