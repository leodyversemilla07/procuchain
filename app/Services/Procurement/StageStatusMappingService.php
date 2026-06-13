<?php

namespace App\Services\Procurement;

use App\Enums\ProcurementMode;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;

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
class StageStatusMappingService
{
    /**
     * Map of stages to their initial, ongoing, and completion statuses.
     *
     * @var array<string, array{initial: ProcurementStatus, ongoing: ProcurementStatus|null, completion: ProcurementStatus}>
     */
    private const STAGE_STATUS_MAP = [
        // Pre-Procurement Phase
        'procurement_initiation' => [
            'initial' => ProcurementStatus::PROCUREMENT_INITIATED,
            'ongoing' => null,
            'completion' => ProcurementStatus::PROCUREMENT_INITIATED,
        ],
        'pre_procurement_conference' => [
            'initial' => ProcurementStatus::PROCUREMENT_SUBMITTED,
            'ongoing' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD,
            'completion' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
        ],
        'bidding_documents' => [
            'initial' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            'ongoing' => null,
            'completion' => ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED,
        ],
        'request_for_quotation' => [
            'initial' => ProcurementStatus::PROCUREMENT_SUBMITTED,
            'ongoing' => ProcurementStatus::PROCUREMENT_SUBMITTED,
            'completion' => ProcurementStatus::QUOTATIONS_RECEIVED,
        ],

        // Procurement/Bidding Phase
        'pre_bid_conference' => [
            'initial' => ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED,
            'ongoing' => ProcurementStatus::PRE_BID_CONFERENCE_HELD,
            'completion' => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED,
        ],
        'supplemental_bid_bulletin' => [
            'initial' => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED,
            'ongoing' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING,
            'completion' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,
        ],
        'bid_opening' => [
            'initial' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'ongoing' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'completion' => ProcurementStatus::BIDS_OPENED,
        ],
        'abstract_of_quotations' => [
            'initial' => ProcurementStatus::QUOTATIONS_RECEIVED,
            'ongoing' => ProcurementStatus::QUOTATIONS_RECEIVED,
            'completion' => ProcurementStatus::ABSTRACT_PREPARED,
        ],
        'bid_evaluation' => [
            'initial' => ProcurementStatus::BIDS_OPENED,
            'ongoing' => ProcurementStatus::BIDS_OPENED,
            'completion' => ProcurementStatus::BIDS_EVALUATED,
        ],
        'post_qualification' => [
            'initial' => ProcurementStatus::BIDS_EVALUATED,
            'ongoing' => ProcurementStatus::BIDS_EVALUATED,
            'completion' => ProcurementStatus::POST_QUALIFICATION_VERIFIED,
        ],
        'bac_resolution' => [
            // Note: Initial status is mode-dependent, see getInitialStatus()
            'initial' => ProcurementStatus::POST_QUALIFICATION_VERIFIED,
            'ongoing' => null,
            'completion' => ProcurementStatus::RESOLUTION_RECORDED,
        ],

        // Post-Procurement Phase
        'notice_of_award' => [
            'initial' => ProcurementStatus::RESOLUTION_RECORDED,
            'ongoing' => null,
            'completion' => ProcurementStatus::AWARDED,
        ],
        'performance_bond_contract_and_po' => [
            'initial' => ProcurementStatus::AWARDED,
            'ongoing' => null,
            'completion' => ProcurementStatus::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        ],
        'notice_to_proceed' => [
            'initial' => ProcurementStatus::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            'ongoing' => null,
            'completion' => ProcurementStatus::NTP_RECORDED,
        ],
        'monitoring' => [
            'initial' => ProcurementStatus::NTP_RECORDED,
            'ongoing' => null,
            'completion' => ProcurementStatus::MONITORING_COMPLETED,
        ],
        'completion' => [
            'initial' => ProcurementStatus::MONITORING_COMPLETED,
            'ongoing' => null,
            'completion' => ProcurementStatus::COMPLETION_DOCUMENTS_UPLOADED,
        ],
        'completed' => [
            'initial' => ProcurementStatus::COMPLETED,
            'ongoing' => null,
            'completion' => ProcurementStatus::COMPLETED,
        ],
    ];

    /**
     * Procurement modes that use RFQ-based workflow (BAC Resolution comes after Abstract of Quotations).
     */
    private const RFQ_BASED_MODES = [
        ProcurementMode::SMALL_VALUE_PROCUREMENT,
        ProcurementMode::DIRECT_CONTRACTING,
        ProcurementMode::REPEAT_ORDER,
        ProcurementMode::DIRECT_SALES,
        ProcurementMode::DIRECT_PROCUREMENT_FOR_STI,
    ];

    /**
     * Get the initial/default status when entering a new stage.
     *
     * This is MODE-AWARE and considers the procurement mode to return the correct status.
     */
    public function getInitialStatus(StageEnums $stage, ?ProcurementMode $mode = null): ProcurementStatus
    {
        // Validate that stage exists in the procurement's mode workflow
        if ($mode && ! $stage->existsInModeWorkflow($mode)) {
            return ProcurementStatus::PROCUREMENT_SUBMITTED;
        }

        // Special handling for BAC Resolution (mode-dependent initial status)
        if ($stage === StageEnums::BAC_RESOLUTION && $mode) {
            if (in_array($mode, self::RFQ_BASED_MODES, true)) {
                return ProcurementStatus::ABSTRACT_PREPARED;
            }

            return ProcurementStatus::POST_QUALIFICATION_VERIFIED;
        }

        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        return $mapping['initial'] ?? ProcurementStatus::PROCUREMENT_SUBMITTED;
    }

    /**
     * Get the ongoing status for a stage (used during document uploads).
     *
     * For stages without dedicated "ongoing" statuses, returns the previous stage's
     * completion status or the initial status.
     */
    public function getOngoingStatus(StageEnums $stage): ProcurementStatus
    {
        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        if ($mapping && $mapping['ongoing']) {
            return $mapping['ongoing'];
        }

        // Fallback to initial status if no specific ongoing status
        return $mapping['initial'] ?? ProcurementStatus::PROCUREMENT_SUBMITTED;
    }

    /**
     * Get the completion status for a given stage.
     *
     * This status is set when all required documents for the stage are uploaded.
     */
    public function getCompletionStatus(StageEnums $stage): ProcurementStatus
    {
        $mapping = self::STAGE_STATUS_MAP[$stage->value] ?? null;

        return $mapping['completion'] ?? ProcurementStatus::PROCUREMENT_SUBMITTED;
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
     * @return array{initial: ProcurementStatus, ongoing: ProcurementStatus|null, completion: ProcurementStatus}
     */
    public function getStatusesForStage(StageEnums $stage, ?ProcurementMode $mode = null): array
    {
        return [
            'initial' => $this->getInitialStatus($stage, $mode),
            'ongoing' => $this->getOngoingStatus($stage),
            'completion' => $this->getCompletionStatus($stage),
        ];
    }
}
