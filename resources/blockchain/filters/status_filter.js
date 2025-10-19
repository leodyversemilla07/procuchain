/* eslint-disable @typescript-eslint/no-unused-vars, no-undef */
/**
 * ProcuChain Status Validation Filter
 *
 * Stream Filter for: procurement.status
 * Version: 1.0.0
 *
 * Purpose: Enforce status progression rules for procurement workflows
 * This ensures that procurement status transitions follow valid progression paths
 *
 * @see https://www.multichain.com/developers/smart-filters/
 */

/**
 * Main filter function - called by MultiChain for each stream item
 * Returns empty/undefined to accept, or error string to reject
 */
function filterstreamitem() {
    var item = getfilterstreamitem();

    // Ensure item has JSON data
    if (!item || !item.data || !item.data.json) {
        return 'Status data is missing or invalid format';
    }

    var data = item.data.json;

    // 1. Validate required fields
    var requiredFields = ['procurement_id', 'procurement_title', 'current_status', 'stage', 'timestamp', 'user_address'];

    for (var i = 0; i < requiredFields.length; i++) {
        var field = requiredFields[i];
        if (!data[field] || data[field] === '') {
            return 'Missing required field: ' + field;
        }
    }

    // 2. Validate status is in allowed values
    var validStatuses = [
        'procurement_submitted',
        'pre_procurement_conference_held',
        'pre_procurement_conference_skipped',
        'pre_procurement_conference_completed',
        'bidding_documents_published',
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
    ];

    var statusValid = false;
    for (var j = 0; j < validStatuses.length; j++) {
        if (data.current_status === validStatuses[j]) {
            statusValid = true;
            break;
        }
    }

    if (!statusValid) {
        return 'Invalid status: ' + data.current_status;
    }

    // 3. Validate stage is in allowed values
    var validStages = [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'pre_bid_conference',
        'supplemental_bid_bulletin',
        'bid_opening',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond_contract_and_po',
        'notice_to_proceed',
        'monitoring',
        'completion',
        'completed',
    ];

    var stageValid = false;
    for (var k = 0; k < validStages.length; k++) {
        if (data.stage === validStages[k]) {
            stageValid = true;
            break;
        }
    }

    if (!stageValid) {
        return 'Invalid stage: ' + data.stage;
    }

    // 4. Validate stage-status alignment
    // This ensures the status makes sense for the given stage
    var stageStatusMap = {
        procurement_initiation: ['procurement_submitted'],
        pre_procurement_conference: ['pre_procurement_conference_held', 'pre_procurement_conference_skipped', 'pre_procurement_conference_completed'],
        bidding_documents: ['bidding_documents_published'],
        pre_bid_conference: ['pre_bid_conference_held', 'pre_bid_conference_skipped', 'pre_bid_conference_completed'],
        supplemental_bid_bulletin: ['supplemental_bulletins_ongoing', 'supplemental_bulletins_completed'],
        bid_opening: ['bids_opened'],
        bid_evaluation: ['bids_evaluated'],
        post_qualification: ['post_qualification_verified', 'post_qualification_failed'],
        bac_resolution: ['resolution_recorded'],
        notice_of_award: ['awarded'],
        performance_bond_contract_and_po: ['performance_bond_contract_and_po_recorded'],
        notice_to_proceed: ['ntp_recorded'],
        monitoring: ['monitoring_completed'],
        completion: ['completion_documents_uploaded'],
        completed: ['completed'],
    };

    var allowedStatuses = stageStatusMap[data.stage];
    if (allowedStatuses) {
        var statusMatchesStage = false;
        for (var m = 0; m < allowedStatuses.length; m++) {
            if (data.current_status === allowedStatuses[m]) {
                statusMatchesStage = true;
                break;
            }
        }

        if (!statusMatchesStage) {
            return "Status '" + data.current_status + "' is not valid for stage '" + data.stage + "'";
        }
    }

    // 5. Validate blockchain address format
    var addressPattern = /^[a-zA-Z0-9]{20,50}$/;
    if (!addressPattern.test(data.user_address)) {
        return 'Invalid blockchain address format: ' + data.user_address;
    }

    // 6. Validate timestamp format
    if (data.timestamp.length < 19) {
        return 'Invalid timestamp format. Expected ISO 8601 format.';
    }

    // 7. Validate procurement title length
    if (data.procurement_title.length < 5) {
        return 'Procurement title too short. Minimum 5 characters required.';
    }

    if (data.procurement_title.length > 255) {
        return 'Procurement title too long. Maximum 255 characters allowed.';
    }

    // Note: Advanced transition validation (checking previous status)
    // would require querying the stream with liststreamkeyitems
    // This is omitted for performance reasons but can be added if needed

    // All validations passed
    return; // Accept
}
