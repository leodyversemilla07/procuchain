 
/**
 * ProcuChain Status Validation Filter (Standalone Version)
 *
 * Stream Filter for: procurement.status
 * Version: 3.0.0 (pr_number support with backward compatibility)
 *
 * Purpose: Enforce status progression rules for procurement workflows
 * This ensures that procurement status transitions follow valid progression paths
 *
 * Changes in v3:
 * - Accept pr_number (PR-YYYY-####-####) as primary identifier
 * - Maintain backward compatibility with pr_number (UUID)
 * - Added PR number format validation
 *
 * Note: This is a standalone version for MultiChain Community Edition
 * which does not support libraries. All validation is inline.
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

    // ========================================
    // 1. REQUIRED FIELDS VALIDATION
    // ========================================
    // Accept EITHER pr_number (new) OR pr_number (legacy) for backward compatibility
    if ((!data.pr_number || data.pr_number === '') && (!data.pr_number || data.pr_number === '')) {
        return 'Missing required field: pr_number or pr_number (at least one must be provided)';
    }

    var requiredFields = ['procurement_title', 'current_status', 'stage', 'timestamp', 'user_address'];

    for (var i = 0; i < requiredFields.length; i++) {
        var field = requiredFields[i];
        if (!data[field] || data[field] === '') {
            return 'Missing required field: ' + field;
        }
    }

    // PR Number format validation (if provided)
    if (data.pr_number && data.pr_number !== '') {
        // PR-YYYY-####-#### format (e.g., PR-2025-0001-0042)
        var prNumberPattern = /^PR-\d{4}-\d{4}-\d{4}$/;
        if (!prNumberPattern.test(data.pr_number)) {
            return 'Invalid PR number format. Expected: PR-YYYY-####-#### (e.g., PR-2025-0001-0042)';
        }
    }

    // ========================================
    // 2. STATUS ENUM VALIDATION
    // ========================================
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

    // ========================================
    // 3. STAGE ENUM VALIDATION
    // ========================================
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

    // ========================================
    // 4. STAGE-STATUS ALIGNMENT VALIDATION
    // ========================================
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

    // ========================================
    // 5. BLOCKCHAIN ADDRESS VALIDATION
    // ========================================
    // MultiChain addresses are typically 25-40 characters
    if (typeof data.user_address !== 'string' || data.user_address.length < 25 || data.user_address.length > 40) {
        return 'Invalid blockchain address format';
    }

    // ========================================
    // 6. TIMESTAMP VALIDATION (ISO 8601)
    // ========================================
    if (data.timestamp.length < 19) {
        return 'Invalid timestamp format. Expected ISO 8601 format.';
    }

    // ========================================
    // 7. STRING LENGTH VALIDATION
    // ========================================
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
