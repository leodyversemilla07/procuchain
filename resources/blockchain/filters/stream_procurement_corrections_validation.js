/**
 * ProcuChain Stream Filter: Procurement Corrections Validation
 *
 * This Smart Filter validates procurement metadata corrections published to the
 * procurement.metadata.corrections stream. It ensures corrections to procurement
 * details are properly documented with audit trail.
 *
 * Aligned with: App\DataTransferObjects\ProcurementCorrectionData
 *
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();

    // Get the JSON data from the stream item
    var data = item.data;

    // If data is null (too large), reject it - corrections should be small
    if (data === null) {
        return 'Procurement correction data is too large or missing';
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
            return 'Invalid JSON data in procurement correction';
        }
    }

    // ============================================================
    // Required Fields Validation (aligned with ProcurementCorrectionData DTO)
    // ============================================================

    if (!data.pr_number) {
        return 'Procurement correction missing required field: pr_number';
    }

    if (!data.procurement_title) {
        return 'Procurement correction missing required field: procurement_title';
    }

    if (!data.correction_type) {
        return 'Procurement correction missing required field: correction_type';
    }

    if (!data.reason) {
        return 'Procurement correction missing required field: reason';
    }

    if (!data.corrected_by) {
        return 'Procurement correction missing required field: corrected_by';
    }

    if (!data.user_address) {
        return 'Procurement correction missing required field: user_address';
    }

    if (!data.timestamp) {
        return 'Procurement correction missing required field: timestamp';
    }

    // ============================================================
    // Format Validations
    // ============================================================

    // Validate PR number format (supports PR-YYYY-NNN or PR-YYYY-XXXX-NNNN)
    var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
    if (!prPattern.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN';
    }

    // Validate correction type
    var validCorrectionTypes = ['metadata', 'financial', 'dates', 'approval'];
    var correctionTypeValid = false;
    for (var i = 0; i < validCorrectionTypes.length; i++) {
        if (validCorrectionTypes[i] === data.correction_type) {
            correctionTypeValid = true;
            break;
        }
    }
    if (!correctionTypeValid) {
        return 'Invalid correction_type: ' + data.correction_type + '. Must be one of: ' + validCorrectionTypes.join(', ');
    }

    // Validate reason length (must be meaningful)
    if (typeof data.reason !== 'string' || data.reason.length < 10) {
        return 'Reason must be at least 10 characters';
    }
    if (data.reason.length > 1000) {
        return 'Reason must not exceed 1000 characters';
    }

    // Validate timestamp format (ISO 8601 basic check)
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.timestamp)) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    // Validate that at least one correction is provided
    var hasCorrectedValue = false;
    var correctedFields = [
        'corrected_title',
        'corrected_description',
        'corrected_abc_amount',
        'corrected_funding_source',
        'corrected_category',
        'corrected_procurement_mode',
        'corrected_office',
        'corrected_end_user',
        'corrected_purpose',
        'corrected_delivery_location',
        'corrected_delivery_date',
        'corrected_delivery_term_days',
        'corrected_bac_resolution_number',
        'corrected_bac_resolution_date',
        'corrected_philgeps_reference',
        'corrected_philgeps_posting_date',
        'corrected_approved_by',
        'corrected_approval_date',
    ];

    for (var j = 0; j < correctedFields.length; j++) {
        if (data[correctedFields[j]] !== undefined && data[correctedFields[j]] !== null) {
            hasCorrectedValue = true;
            break;
        }
    }

    if (!hasCorrectedValue) {
        return 'Procurement correction must include at least one corrected field';
    }

    // Validate corrected_abc_amount if provided
    if (data.corrected_abc_amount !== undefined && data.corrected_abc_amount !== null) {
        var abcAmount = parseFloat(data.corrected_abc_amount);
        if (isNaN(abcAmount) || abcAmount <= 0) {
            return 'Corrected ABC amount must be a positive number';
        }
    }

    // Validate corrected category if provided
    if (data.corrected_category) {
        var validCategories = ['goods', 'services', 'infrastructure_projects', 'consulting_services'];
        var categoryValid = false;
        for (var k = 0; k < validCategories.length; k++) {
            if (validCategories[k] === data.corrected_category) {
                categoryValid = true;
                break;
            }
        }
        if (!categoryValid) {
            return 'Invalid corrected_category: ' + data.corrected_category;
        }
    }

    // Validate corrected procurement mode if provided
    if (data.corrected_procurement_mode) {
        var validModes = [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer_with_bid_matching',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_for_sti',
        ];
        var modeValid = false;
        for (var m = 0; m < validModes.length; m++) {
            if (validModes[m] === data.corrected_procurement_mode) {
                modeValid = true;
                break;
            }
        }
        if (!modeValid) {
            return 'Invalid corrected_procurement_mode: ' + data.corrected_procurement_mode;
        }
    }

    // Validate keys include the PR number
    var hasPrNumberKey = false;
    if (item.keys) {
        for (var n = 0; n < item.keys.length; n++) {
            if (item.keys[n] === data.pr_number) {
                hasPrNumberKey = true;
                break;
            }
        }
    }
    if (!hasPrNumberKey) {
        return 'Procurement correction must include pr_number as a stream key';
    }

    // Procurement correction passed all validations
    return;
}
