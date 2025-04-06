<?php

namespace App\Enums;

enum StatusEnums: string
{
    case PROCUREMENT_SUBMITTED = 'procurement_submitted';
    case PRE_PROCUREMENT_CONFERENCE_HELD = 'pre_procurement_conference_held';
    case PRE_PROCUREMENT_CONFERENCE_SKIPPED = 'pre_procurement_conference_skipped';
    case PRE_PROCUREMENT_CONFERENCE_COMPLETED = 'pre_procurement_conference_completed';
    case BIDDING_DOCUMENTS_PUBLISHED = 'bidding_documents_published';
    case PRE_BID_CONFERENCE_HELD = 'pre_bid_conference_held';
    case PRE_BID_CONFERENCE_SKIPPED = 'pre_bid_conference_skipped';
    case SUPPLEMENTAL_BULLETINS_ONGOING = 'supplemental_bulletins_ongoing';
    case SUPPLEMENTAL_BULLETINS_COMPLETED = 'supplemental_bulletins_completed';
    
    case BIDS_OPENED = 'bids_opened';
    case BIDS_EVALUATED = 'bids_evaluated';
    case POST_QUALIFICATION_VERIFIED = 'post_qualification_verified';
    case POST_QUALIFICATION_FAILED = 'post_qualification_failed';
    case RESOLUTION_RECORDED = 'resolution_recorded';
    case AWARDED = 'awarded';
    case PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED = 'performance_bond_contract_and_po_recorded';
    case NTP_RECORDED = 'ntp_recorded';
    case MONITORING = 'monitoring';
    case COMPLETION_DOCUMENTS_UPLOADED = 'completion_documents_uploaded';
    case COMPLETED = 'completed';

    // Helper method to get display name
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PROCUREMENT_SUBMITTED => 'Procurement Submitted',
            self::PRE_PROCUREMENT_CONFERENCE_HELD => 'Pre-Procurement Conference Held',
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'Pre-Procurement Conference Skipped',
            self::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'Pre-Procurement Conference Completed',
            self::BIDDING_DOCUMENTS_PUBLISHED => 'Bidding Documents Published',
            self::PRE_BID_CONFERENCE_HELD => 'Pre-Bid Conference Held',
            self::PRE_BID_CONFERENCE_SKIPPED => 'Pre-Bid Conference Skipped',
            self::SUPPLEMENTAL_BULLETINS_ONGOING => 'Supplemental Bulletins Ongoing',
            self::SUPPLEMENTAL_BULLETINS_COMPLETED => 'Supplemental Bulletins Completed',
            self::BIDS_OPENED => 'Bids Opened',
            self::BIDS_EVALUATED => 'Bids Evaluated',
            self::POST_QUALIFICATION_VERIFIED => 'Post-Qualification Verified',
            self::POST_QUALIFICATION_FAILED => 'Post-Qualification Failed',
            self::RESOLUTION_RECORDED => 'Resolution Recorded',
            self::AWARDED => 'Awarded',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'Performance Bond, Contract and PO Recorded',
            self::NTP_RECORDED => 'NTP Recorded',
            self::MONITORING => 'Monitoring',
            self::COMPLETION_DOCUMENTS_UPLOADED => 'Completion Documents Uploaded',
            self::COMPLETED => 'Completed',
        };
    }

    // Storage path helper
    public function getStoragePathSegment(): string
    {
        return match ($this) {
            self::PROCUREMENT_SUBMITTED => 'ProcurementSubmitted',
            self::PRE_PROCUREMENT_CONFERENCE_HELD => 'PreProcurementConferenceHeld',
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'PreProcurementConferenceSkipped',
            self::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'PreProcurementConferenceCompleted',
            self::BIDDING_DOCUMENTS_PUBLISHED => 'BiddingDocumentsPublished',
            self::SUPPLEMENTAL_BULLETINS_ONGOING => 'SupplementalBulletinsOngoing',
            self::SUPPLEMENTAL_BULLETINS_COMPLETED => 'SupplementalBulletinsCompleted',
            self::PRE_BID_CONFERENCE_HELD => 'PreBidConferenceHeld',
            self::PRE_BID_CONFERENCE_SKIPPED => 'PreBidConferenceSkipped',
            self::BIDS_OPENED => 'BidsOpened',
            self::BIDS_EVALUATED => 'BidsEvaluated',
            self::POST_QUALIFICATION_VERIFIED => 'PostQualificationVerified',
            self::POST_QUALIFICATION_FAILED => 'PostQualificationFailed',
            self::RESOLUTION_RECORDED => 'ResolutionRecorded',
            self::AWARDED => 'Awarded',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'PerformanceBondContractAndPORecorded',
            self::NTP_RECORDED => 'NTPRecorded',
            self::MONITORING => 'Monitoring',
            self::COMPLETION_DOCUMENTS_UPLOADED => 'CompletionDocumentsUploaded',
            self::COMPLETED => 'Completed',
        };
    }
}
