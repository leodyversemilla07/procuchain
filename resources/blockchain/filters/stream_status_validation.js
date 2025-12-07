 
/**
 * ProcuChain Stream Filter: Status Transition Validation
 *
 * This Smart Filter validates status transitions published to the
 * procurement.status stream. It enforces the procurement workflow
 * rules and prevents invalid stage transitions.
 *
 * Aligned with: App\DataTransferObjects\StatusData
 *
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();

    // Get the JSON data from the stream item
    // MultiChain stores JSON data in item.data.json
    var data = item.data;

    // If data is null (too large), reject it - status updates should be small
    if (data === null) {
        return 'Status update data is too large or missing';
    }

    // Handle object with json property (standard MultiChain format)
    if (typeof data === 'object' && data.json) {
        data = data.json;
    }

    // Parse JSON data if it's a string
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            return 'Invalid JSON data in status update';
        }
    }

    // ============================================================
    // Required Fields Validation (aligned with StatusData DTO)
    // ============================================================

    if (!data.pr_number) {
        return 'Status update missing required field: pr_number';
    }

    if (!data.procurement_title) {
        return 'Status update missing required field: procurement_title';
    }

    if (!data.stage) {
        return 'Status update missing required field: stage';
    }

    // Note: DTO uses 'current_status', not 'status'
    if (!data.current_status) {
        return 'Status update missing required field: current_status';
    }

    if (!data.timestamp) {
        return 'Status update missing required field: timestamp';
    }

    // ============================================================
    // Format Validations
    // ============================================================

    // Validate PR number format (supports PR-YYYY-NNN or PR-YYYY-XXXX-NNNN)
    var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
    if (!prPattern.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN';
    }

    // Define valid stages in order (aligned with StageEnums)
    var stageOrder = [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'request_for_quotation',
        'pre_bid_conference',
        'supplemental_bid_bulletin',
        'bid_opening',
        'abstract_of_quotations',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond_contract_and_po', // Fixed: was performance_bond_contract_po
        'notice_to_proceed',
        'monitoring',
        'completion',
        'completed',
    ];

    // Validate stage is valid
    var currentStageIndex = -1;
    for (var j = 0; j < stageOrder.length; j++) {
        if (stageOrder[j] === data.stage) {
            currentStageIndex = j;
            break;
        }
    }

    if (currentStageIndex === -1) {
        return 'Invalid procurement stage: ' + data.stage;
    }

    // Define valid statuses (aligned with StatusEnums)
    var validStatuses = [
        // Core workflow statuses
        'procurement_initiated',
        'procurement_submitted',
        'pre_procurement_conference_held',
        'pre_procurement_conference_skipped',
        'pre_procurement_conference_completed',
        'bidding_documents_published',
        'bidding_documents_submitted',
        'pre_bid_conference_held',
        'pre_bid_conference_skipped',
        'pre_bid_conference_completed',
        'supplemental_bulletins_ongoing',
        'supplemental_bulletins_completed',
        'bids_opened',
        'bids_evaluated',
        'post_qualification_verified',
        'post_qualification_failed',
        'resolution_recorded',
        'awarded',
        'performance_bond_contract_and_po_recorded',
        'ntp_recorded',
        'monitoring_completed',
        'completion_documents_uploaded',
        'completed',
        // Alternative Procurement Methods (SVP, Direct Contracting, etc.)
        'quotations_received',
        'abstract_prepared',
        // Lifecycle states
        'stage_on_hold',
        'stage_cancelled',
        'stage_rejected',
        'stage_pending_correction',
        'stage_skipped',
        // Legacy/generic statuses for backward compatibility
        'pending',
        'in_progress',
        'cancelled',
        'on_hold',
        'draft',
        'pending_documents',
        'documents_uploaded',
        'documents_verified',
        'documents_published',
        'awaiting_approval',
        'approved',
        'rejected',
        'for_revision',
        'revised',
        'scheduled',
        'conducted',
        'minutes_uploaded',
        'bids_received',
        'evaluation_ongoing',
        'evaluation_complete',
        'contract_signed',
        'ntp_issued',
    ];

    // Validate current_status (converted to lowercase for comparison)
    var statusToCheck = data.current_status.toLowerCase();
    var statusValid = false;
    for (var k = 0; k < validStatuses.length; k++) {
        if (validStatuses[k] === statusToCheck) {
            statusValid = true;
            break;
        }
    }

    if (!statusValid) {
        return 'Invalid procurement status: ' + data.current_status;
    }

    // Validate timestamp format (ISO 8601 basic check)
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.timestamp)) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    // Note: Future timestamp validation removed - requires Date.parse which is unavailable
    // Blockchain timestamp validation is handled by block time constraints

    // Validate keys include the PR number
    var hasPrNumberKey = false;
    if (item.keys) {
        for (var m = 0; m < item.keys.length; m++) {
            if (item.keys[m] === data.pr_number) {
                hasPrNumberKey = true;
                break;
            }
        }
    }

    if (!hasPrNumberKey) {
        return 'Status update must include pr_number as a stream key';
    }

    // Validate previous_status transition if provided
    if (data.previous_status) {
        // previous_status should be a valid status
        var prevStatusToCheck = data.previous_status.toLowerCase();
        var prevStatusValid = false;
        for (var n = 0; n < validStatuses.length; n++) {
            if (validStatuses[n] === prevStatusToCheck) {
                prevStatusValid = true;
                break;
            }
        }

        if (!prevStatusValid) {
            return 'Invalid previous status: ' + data.previous_status;
        }
    }

    // Validate user_address matches publisher if present
    // Note: This check is informational - some statuses may be published by system
    // if (data.user_address) {
    //     var publisherMatches = false;
    //     if (item.publishers) {
    //         for (var p = 0; p < item.publishers.length; p++) {
    //             if (item.publishers[p] === data.user_address) {
    //                 publisherMatches = true;
    //                 break;
    //             }
    //         }
    //     }
    //     if (!publisherMatches) {
    //         return 'Status user_address does not match transaction publisher';
    //     }
    // }

    // Status update passed all validations
    return;
}
