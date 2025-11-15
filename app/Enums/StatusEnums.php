<?php

namespace App\Enums;

/**
 * Status Enum
 *
 * Represents all possible statuses for procurement processes.
 * Each status corresponds to a specific state within a procurement stage.
 */
enum StatusEnums: string
{
    case PROCUREMENT_SUBMITTED = 'procurement_submitted';
    case PRE_PROCUREMENT_CONFERENCE_HELD = 'pre_procurement_conference_held';
    case PRE_PROCUREMENT_CONFERENCE_SKIPPED = 'pre_procurement_conference_skipped';
    case PRE_PROCUREMENT_CONFERENCE_COMPLETED = 'pre_procurement_conference_completed';
    case BIDDING_DOCUMENTS_PUBLISHED = 'bidding_documents_published';
    case BIDDING_DOCUMENTS_SUBMITTED = 'bidding_documents_submitted';
    case PRE_BID_CONFERENCE_HELD = 'pre_bid_conference_held';
    case PRE_BID_CONFERENCE_SKIPPED = 'pre_bid_conference_skipped';
    case PRE_BID_CONFERENCE_COMPLETED = 'pre_bid_conference_completed';
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
    case MONITORING_COMPLETED = 'monitoring_completed';
    case COMPLETION_DOCUMENTS_UPLOADED = 'completion_documents_uploaded';
    case COMPLETED = 'completed';

    // Issue #12 fix: Lifecycle state statuses for procurement management
    case STAGE_ON_HOLD = 'stage_on_hold';
    case STAGE_CANCELLED = 'stage_cancelled';
    case STAGE_REJECTED = 'stage_rejected';
    case STAGE_PENDING_CORRECTION = 'stage_pending_correction';

    /**
     * Get the user-friendly display name for the status
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PROCUREMENT_SUBMITTED => 'Procurement Submitted',
            self::PRE_PROCUREMENT_CONFERENCE_HELD => 'Pre-Procurement Conference Held',
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'Pre-Procurement Conference Skipped',
            self::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'Pre-Procurement Conference Completed',
            self::BIDDING_DOCUMENTS_PUBLISHED => 'Bidding Documents Published',
            self::BIDDING_DOCUMENTS_SUBMITTED => 'Bidding Documents Submitted',
            self::PRE_BID_CONFERENCE_HELD => 'Pre-Bid Conference Held',
            self::PRE_BID_CONFERENCE_SKIPPED => 'Pre-Bid Conference Skipped',
            self::PRE_BID_CONFERENCE_COMPLETED => 'Pre-Bid Conference Completed',
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
            self::MONITORING_COMPLETED => 'Monitoring Completed',
            self::COMPLETION_DOCUMENTS_UPLOADED => 'Completion Documents Uploaded',
            self::COMPLETED => 'Completed',
            // Issue #12 fix: Lifecycle states
            self::STAGE_ON_HOLD => 'On Hold',
            self::STAGE_CANCELLED => 'Cancelled',
            self::STAGE_REJECTED => 'Rejected',
            self::STAGE_PENDING_CORRECTION => 'Pending Correction',
        };
    }

    /**
     * Get the storage path segment for file organization
     */
    public function getStoragePathSegment(): string
    {
        return match ($this) {
            self::PROCUREMENT_SUBMITTED => 'ProcurementSubmitted',
            self::PRE_PROCUREMENT_CONFERENCE_HELD => 'PreProcurementConferenceHeld',
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'PreProcurementConferenceSkipped',
            self::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'PreProcurementConferenceCompleted',
            self::BIDDING_DOCUMENTS_PUBLISHED => 'BiddingDocumentsPublished',
            self::BIDDING_DOCUMENTS_SUBMITTED => 'BiddingDocumentsSubmitted',
            self::PRE_BID_CONFERENCE_HELD => 'PreBidConferenceHeld',
            self::PRE_BID_CONFERENCE_SKIPPED => 'PreBidConferenceSkipped',
            self::PRE_BID_CONFERENCE_COMPLETED => 'PreBidConferenceCompleted',
            self::SUPPLEMENTAL_BULLETINS_ONGOING => 'SupplementalBulletinsOngoing',
            self::SUPPLEMENTAL_BULLETINS_COMPLETED => 'SupplementalBulletinsCompleted',
            self::BIDS_OPENED => 'BidsOpened',
            self::BIDS_EVALUATED => 'BidsEvaluated',
            self::POST_QUALIFICATION_VERIFIED => 'PostQualificationVerified',
            self::POST_QUALIFICATION_FAILED => 'PostQualificationFailed',
            self::RESOLUTION_RECORDED => 'ResolutionRecorded',
            self::AWARDED => 'Awarded',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'PerformanceBondContractAndPORecorded',
            self::NTP_RECORDED => 'NTPRecorded',
            self::MONITORING_COMPLETED => 'MonitoringCompleted',
            self::COMPLETION_DOCUMENTS_UPLOADED => 'CompletionDocumentsUploaded',
            self::COMPLETED => 'Completed',
            // Issue #12 fix: Lifecycle states
            self::STAGE_ON_HOLD => 'OnHold',
            self::STAGE_CANCELLED => 'Cancelled',
            self::STAGE_REJECTED => 'Rejected',
            self::STAGE_PENDING_CORRECTION => 'PendingCorrection',
        };
    }

    /**
     * Get a description of the status
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PROCUREMENT_SUBMITTED => 'Initial procurement documents have been submitted',
            self::PRE_PROCUREMENT_CONFERENCE_HELD => 'Pre-procurement conference has been conducted',
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'Pre-procurement conference was not required or was skipped',
            self::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'Pre-procurement conference has concluded',
            self::BIDDING_DOCUMENTS_PUBLISHED => 'Bidding documents are publicly available',
            self::BIDDING_DOCUMENTS_SUBMITTED => 'Bidding documents have been uploaded to the system',
            self::PRE_BID_CONFERENCE_HELD => 'Pre-bid conference has been conducted',
            self::PRE_BID_CONFERENCE_SKIPPED => 'Pre-bid conference was not required or was skipped',
            self::PRE_BID_CONFERENCE_COMPLETED => 'Pre-bid conference has concluded',
            self::SUPPLEMENTAL_BULLETINS_ONGOING => 'Supplemental bulletins are being issued',
            self::SUPPLEMENTAL_BULLETINS_COMPLETED => 'All supplemental bulletins have been issued',
            self::BIDS_OPENED => 'Bids have been publicly opened',
            self::BIDS_EVALUATED => 'All bids have been evaluated',
            self::POST_QUALIFICATION_VERIFIED => 'Winning bidder has passed post-qualification',
            self::POST_QUALIFICATION_FAILED => 'Winning bidder has failed post-qualification',
            self::RESOLUTION_RECORDED => 'BAC resolution has been formally recorded',
            self::AWARDED => 'Contract has been officially awarded',
            self::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'Performance bond, contract, and purchase order have been recorded',
            self::NTP_RECORDED => 'Notice to Proceed has been issued and recorded',
            self::MONITORING_COMPLETED => 'Project monitoring phase has been completed',
            self::COMPLETION_DOCUMENTS_UPLOADED => 'Completion documents have been uploaded',
            self::COMPLETED => 'Procurement process is fully completed',
            // Issue #12 fix: Lifecycle states
            self::STAGE_ON_HOLD => 'Procurement has been temporarily paused',
            self::STAGE_CANCELLED => 'Procurement has been terminated',
            self::STAGE_REJECTED => 'Stage has been rejected and requires rework',
            self::STAGE_PENDING_CORRECTION => 'Awaiting corrections before proceeding',
        };
    }

    /**
     * Check if the status indicates a successful outcome
     */
    public function isSuccessful(): bool
    {
        return ! in_array($this, [
            self::POST_QUALIFICATION_FAILED,
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
            self::PRE_BID_CONFERENCE_SKIPPED,
            self::STAGE_ON_HOLD,
            self::STAGE_CANCELLED,
            self::STAGE_REJECTED,
            self::STAGE_PENDING_CORRECTION,
        ]);
    }

    /**
     * Check if the status indicates completion
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if the status indicates an in-progress state
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::SUPPLEMENTAL_BULLETINS_ONGOING,
            self::STAGE_PENDING_CORRECTION,
        ]);
    }

    /**
     * Check if the status indicates a blocked or problem state (Issue #12 fix)
     */
    public function isBlocked(): bool
    {
        return in_array($this, [
            self::POST_QUALIFICATION_FAILED,
            self::STAGE_ON_HOLD,
            self::STAGE_REJECTED,
            self::STAGE_PENDING_CORRECTION,
        ]);
    }

    /**
     * Check if the status indicates procurement is terminated (Issue #12 fix)
     */
    public function isTerminated(): bool
    {
        return $this === self::STAGE_CANCELLED;
    }

    /**
     * Check if the status indicates a stage was skipped (Issue #12 fix)
     */
    public function isSkipped(): bool
    {
        return in_array($this, [
            self::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
            self::PRE_BID_CONFERENCE_SKIPPED,
        ]);
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
