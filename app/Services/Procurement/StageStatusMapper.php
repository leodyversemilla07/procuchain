<?php

namespace App\Services\Procurement;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;

/**
 * Single source of truth for Stage -> Status mappings.
 *
 * Consolidates the duplicate status mapping methods that were spread across
 * HasProcurementSupport trait and ProcurementStageController.
 *
 * Per NGPA IRR, each procurement stage has associated statuses:
 * - Initial status: When entering a new stage
 * - Ongoing status: While documents are being uploaded
 * - Completion status: When all required documents are uploaded
 */
class StageStatusMapper
{
    /**
     * Map of stages to their initial, ongoing, and completion statuses.
     *
     * @var array<string, array{initial: StatusEnums, ongoing: StatusEnums|null, completion: StatusEnums}>
     */
    private const STAGE_STATUS_MAP = [
        // Pre-Procurement Phase
        'procurement_initiation' => [
            'initial' => StatusEnums::PROCUREMENT_INITIATED,
            'ongoing' => null,
            'completion' => StatusEnums::PROCUREMENT_INITIATED,
        ],
        'pre_procurement_conference' => [
            'initial' => StatusEnums::PROCUREMENT_SUBMITTED,
            'ongoing' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
            'completion' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
        ],
        'bidding_documents' => [
            'initial' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            'ongoing' => null,
            'completion' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        ],
        'request_for_quotation' => [
            'initial' => StatusEnums::PROCUREMENT_SUBMITTED,
            'ongoing' => StatusEnums::PROCUREMENT_SUBMITTED,
            'completion' => StatusEnums::QUOTATIONS_RECEIVED,
        ],

        // Procurement/Bidding Phase
        'pre_bid_conference' => [
            'initial' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
            'ongoing' => StatusEnums::PRE_BID_CONFERENCE_HELD,
            'completion' => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
        ],
        'supplemental_bid_bulletin' => [
            'initial' => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            'ongoing' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
            'completion' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
        ],
        'bid_opening' => [
            'initial' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'ongoing' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'completion' => StatusEnums::BIDS_OPENED,
        ],
        'abstract_of_quotations' => [
            'initial' => StatusEnums::QUOTATIONS_RECEIVED,
            'ongoing' => StatusEnums::QUOTATIONS_RECEIVED,
            'completion' => StatusEnums::ABSTRACT_PREPARED,
        ],
        'bid_evaluation' => [
            'initial' => StatusEnums::BIDS_OPENED,
            'ongoing' => StatusEnums::BIDS_OPENED,
            'completion' => StatusEnums::BIDS_EVALUATED,
        ],
        'post_qualification' => [
            'initial' => StatusEnums::BIDS_EVALUATED,
            'ongoing' => StatusEnums::BIDS_EVALUATED,
            'completion' => StatusEnums::POST_QUALIFICATION_VERIFIED,
        ],
        'bac_resolution' => [
            // Note: Initial status is mode-dependent, see getInitialStatus()
            'initial' => StatusEnums::POST_QUALIFICATION_VERIFIED,
            'ongoing' => null,
            'completion' => StatusEnums::RESOLUTION_RECORDED,
        ],

        // Post-Procurement Phase
        'notice_of_award' => [
            'initial' => StatusEnums::RESOLUTION_RECORDED,
            'ongoing' => null,
            'completion' => StatusEnums::AWARDED,
        ],
        'performance_bond_contract_and_po' => [
            'initial' => StatusEnums::AWARDED,
            'ongoing' => null,
            'completion' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        ],
        'notice_to_proceed' => [
            'initial' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            'ongoing' => null,
            'completion' => StatusEnums::NTP_RECORDED,
        ],
        'monitoring' => [
            'initial' => StatusEnums::NTP_RECORDED,
            'ongoing' => null,
            'completion' => StatusEnums::MONITORING_COMPLETED,
        ],
        'completion' => [
            'initial' => StatusEnums::MONITORING_COMPLETED,
            'ongoing' => null,
            'completion' => StatusEnums::COMPLETION_DOCUMENTS_UPLOADED,
        ],
        'completed' => [
            'initial' => StatusEnums::COMPLETED,
            'ongoing' => null,
            'completion' => StatusEnums::COMPLETED,
        ],
    ];

    /**
     * Procurement modes that use RFQ-based workflow (BAC Resolution comes after Abstract of Quotations).
     */
    private const RFQ_BASED_MODES = [
        ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
        ProcurementModeEnums::DIRECT_CONTRACTING,
        ProcurementModeEnums::REPEAT_ORDER,
        ProcurementModeEnums::DIRECT_SALES,
        ProcurementModeEnums::DIRECT_PROCUREMENT_FOR_STI,
    ];

    /**
     * Get the initial/default status when entering a new stage.
     *
     * This is MODE-AWARE and considers the procurement mode to return the correct status.
     */
    public function getInitialStatus(StageEnums $stage, ?ProcurementModeEnums $mode = null): StatusEnums
    {
        // Validate that stage exists in the procurement's mode workflow
        if ($mode && ! $stage->existsInModeWorkflow($mode)) {
            return StatusEnums::PROCUREMENT_SUBMITTED;
        }

        // Special handling for BAC Resolution (mode-dependent initial status)
        if ($stage === StageEnums::BAC_RESOLUTION && $mode) {
            if (in_array($mode, self::RFQ_BASED_MODES, true)) {
                return StatusEnums::ABSTRACT_PREPARED;
            }

            return StatusEnums::POST_QUALIFICATION_VERIFIED;
        }

        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        return $mapping['initial'] ?? StatusEnums::PROCUREMENT_SUBMITTED;
    }

    /**
     * Get the ongoing status for a stage (used during document uploads).
     *
     * For stages without dedicated "ongoing" statuses, returns the previous stage's
     * completion status or the initial status.
     */
    public function getOngoingStatus(StageEnums $stage): StatusEnums
    {
        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        if ($mapping && $mapping['ongoing']) {
            return $mapping['ongoing'];
        }

        // Fallback to initial status if no specific ongoing status
        return $mapping['initial'] ?? StatusEnums::PROCUREMENT_SUBMITTED;
    }

    /**
     * Get the completion status for a given stage.
     *
     * This status is set when all required documents for the stage are uploaded.
     */
    public function getCompletionStatus(StageEnums $stage): StatusEnums
    {
        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        return $mapping['completion'] ?? StatusEnums::PROCUREMENT_SUBMITTED;
    }

    /**
     * Check if a stage has a dedicated ongoing status.
     */
    public function hasOngoingStatus(StageEnums $stage): bool
    {
        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        return $mapping && $mapping['ongoing'] !== null;
    }

    /**
     * Get all status mappings for a stage.
     *
     * @return array{initial: StatusEnums, ongoing: StatusEnums|null, completion: StatusEnums}
     */
    public function getStatusesForStage(StageEnums $stage, ?ProcurementModeEnums $mode = null): array
    {
        return [
            'initial' => $this->getInitialStatus($stage, $mode),
            'ongoing' => $this->getOngoingStatus($stage),
            'completion' => $this->getCompletionStatus($stage),
        ];
    }
}
