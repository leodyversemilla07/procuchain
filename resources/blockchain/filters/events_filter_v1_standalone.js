/* eslint-disable @typescript-eslint/no-unused-vars, no-undef */
/**
 * Smart Filter: Procurement Events Stream
 * Version: 2.0.0 (pr_number support with backward compatibility)
 *
 * Validates event log entries published to the procurement.events stream.
 *
 * Changes in v2:
 * - Accept pr_number (PR-YYYY-####-####) as primary identifier
 * - Maintain backward compatibility with pr_number (UUID)
 * - Added PR number format validation
 *
 * Data Structure (from LogBlockchainEventJob):
 * {
 *   pr_number: string,
 *   procurement_title: string,
 *   event_type: string,
 *   stage: string,
 *   timestamp: ISO8601 string,
 *   user_address: string,
 *   details: string,
 *   category: string,
 *   severity: string ('info', 'warning', 'error'),
 *   document_count: number
 * }
 */

function filterstreamitem() {
    var item = getfilterstreamitem();

    // Only validate JSON items
    if (item.format !== 'json') {
        return 'Only JSON format is supported';
    }

    var data = item.data.json;

    // ========================================
    // 1. REQUIRED FIELDS VALIDATION
    // ========================================
    // Accept EITHER pr_number (new) OR pr_number (legacy) for backward compatibility
    if ((!data.pr_number || data.pr_number === '') && (!data.pr_number || data.pr_number === '')) {
        return 'Missing required field: pr_number or pr_number (at least one must be provided)';
    }

    var requiredFields = [
        'procurement_title',
        'event_type',
        'stage',
        'timestamp',
        'user_address',
        'details',
        'category',
        'severity',
        'document_count',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        var field = requiredFields[i];
        if (!data[field] && data[field] !== 0) {
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
    // 2. SEVERITY ENUM VALIDATION
    // ========================================
    var validSeverities = ['info', 'warning', 'error'];
    if (validSeverities.indexOf(data.severity) === -1) {
        return "Invalid severity '" + data.severity + "'. Must be: " + validSeverities.join(', ');
    }

    // ========================================
    // 3. EVENT TYPES VALIDATION
    // ========================================
    // Common event types from the application
    var validEventTypes = [
        'document_published',
        'document_corrected',
        'status_updated',
        'stage_advanced',
        'procurement_created',
        'procurement_submitted',
        'bids_opened',
        'bids_evaluated',
        'contract_awarded',
        'user_action',
        'system_event',
    ];

    if (validEventTypes.indexOf(data.event_type) === -1) {
        return "Invalid event_type '" + data.event_type + "'. Must be one of: " + validEventTypes.join(', ');
    }

    // ========================================
    // 4. CATEGORY VALIDATION
    // ========================================
    var validCategories = ['procurement', 'document', 'status', 'correction', 'user', 'system', 'audit'];

    if (validCategories.indexOf(data.category) === -1) {
        return "Invalid category '" + data.category + "'. Must be one of: " + validCategories.join(', ');
    }

    // ========================================
    // 5. STAGE VALIDATION (from StageEnums)
    // ========================================
    var validStages = [
        'procurement_initiation',
        'pre_procurement_conference',
        'advertisement_posting',
        'pre_bid_conference',
        'submission_opening_bids',
        'bid_evaluation',
        'post_qualification',
        'approval_award',
        'contract_signing',
        'notice_to_proceed',
        'contract_implementation',
        'contract_completion',
        'payment_processing',
        'final_acceptance',
        'project_closeout',
        'correction', // Special stage for corrections
    ];

    if (validStages.indexOf(data.stage) === -1) {
        return "Invalid stage '" + data.stage + "'. Must be one of valid procurement stages";
    }

    // ========================================
    // 6. BLOCKCHAIN ADDRESS VALIDATION
    // ========================================
    // MultiChain addresses are typically 25-40 characters
    if (typeof data.user_address !== 'string' || data.user_address.length < 25 || data.user_address.length > 40) {
        return 'Invalid blockchain address format';
    }

    // ========================================
    // 7. TIMESTAMP VALIDATION (ISO 8601)
    // ========================================
    // Basic ISO 8601 format check: YYYY-MM-DDTHH:MM:SS±HH:MM
    var isoRegex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/;
    if (!isoRegex.test(data.timestamp)) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    // ========================================
    // 8. STRING LENGTH VALIDATION
    // ========================================
    // Validate pr_number length if present (backward compatibility)
    var prNumberValue = data.pr_number || data.pr_number || '';
    if (prNumberValue.length < 3 || prNumberValue.length > 100) {
        return 'pr_number must be 3-100 characters';
    }

    if (data.procurement_title.length < 5 || data.procurement_title.length > 255) {
        return 'procurement_title must be 5-255 characters';
    }

    if (data.details.length < 3 || data.details.length > 1000) {
        return 'details must be 3-1000 characters';
    }

    // ========================================
    // 9. DOCUMENT COUNT VALIDATION
    // ========================================
    if (typeof data.document_count !== 'number' || data.document_count < 0) {
        return 'document_count must be a non-negative number';
    }

    // If document count is specified, it should be reasonable
    if (data.document_count > 10000) {
        return 'document_count exceeds maximum allowed (10000)';
    }

    // All validations passed
    return;
}
