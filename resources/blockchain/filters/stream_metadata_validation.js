/**
 * ProcuChain Stream Filter: Procurement Metadata Validation
 *
 * This Smart Filter validates procurement metadata published to the
 * procurement.metadata stream. It ensures all procurements have
 * proper metadata conforming to RA 12009 (NGPA) requirements.
 *
 * Aligned with: App\DataTransferObjects\ProcurementData
 *
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();

    // Get the JSON data from the stream item
    var data = item.data;

    // If data is null (too large), reject it - metadata should be small
    if (data === null) {
        return 'Procurement metadata is too large or missing';
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
            return 'Invalid JSON data in procurement metadata';
        }
    }

    // ============================================================
    // Required Fields Validation (aligned with ProcurementData DTO)
    // ============================================================

    if (!data.pr_number) {
        return 'Procurement metadata missing required field: pr_number';
    }

    if (!data.title) {
        return 'Procurement metadata missing required field: title';
    }

    if (!data.description) {
        return 'Procurement metadata missing required field: description';
    }

    if (data.abc_amount === undefined || data.abc_amount === null) {
        return 'Procurement metadata missing required field: abc_amount';
    }

    if (!data.funding_source) {
        return 'Procurement metadata missing required field: funding_source';
    }

    if (!data.category) {
        return 'Procurement metadata missing required field: category';
    }

    if (!data.procurement_mode) {
        return 'Procurement metadata missing required field: procurement_mode';
    }

    if (!data.office) {
        return 'Procurement metadata missing required field: office';
    }

    if (!data.status) {
        return 'Procurement metadata missing required field: status';
    }

    if (!data.user_id) {
        return 'Procurement metadata missing required field: user_id';
    }

    if (!data.created_at) {
        return 'Procurement metadata missing required field: created_at';
    }

    // ============================================================
    // Format Validations
    // ============================================================

    // Validate PR number format (supports PR-YYYY-NNN or PR-YYYY-XXXX-NNNN)
    var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
    if (!prPattern.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN';
    }

    // Validate procurement category (aligned with ProcurementCategoryEnums)
    var validCategories = ['goods', 'services', 'infrastructure_projects', 'consulting_services'];
    var categoryValid = false;
    for (var i = 0; i < validCategories.length; i++) {
        if (validCategories[i] === data.category) {
            categoryValid = true;
            break;
        }
    }
    if (!categoryValid) {
        return 'Invalid procurement category: ' + data.category;
    }

    // Validate procurement mode (aligned with ProcurementModeEnums - NGPA IRR RA 12009)
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
    for (var j = 0; j < validModes.length; j++) {
        if (validModes[j] === data.procurement_mode) {
            modeValid = true;
            break;
        }
    }
    if (!modeValid) {
        return 'Invalid procurement mode: ' + data.procurement_mode;
    }

    // Validate ABC amount is a positive number
    var abcAmount = parseFloat(data.abc_amount);
    if (isNaN(abcAmount) || abcAmount <= 0) {
        return 'ABC amount must be a positive number';
    }

    // Validate title length
    if (typeof data.title !== 'string' || data.title.length < 5) {
        return 'Title must be at least 5 characters';
    }
    if (data.title.length > 500) {
        return 'Title must not exceed 500 characters';
    }

    // Validate description length
    if (typeof data.description !== 'string' || data.description.length < 10) {
        return 'Description must be at least 10 characters';
    }

    // Validate timestamp format (ISO 8601 basic check)
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.created_at)) {
        return 'Invalid created_at timestamp format. Must be ISO 8601';
    }

    // Validate optional date fields if present
    if (data.delivery_date && !isoPattern.test(data.delivery_date)) {
        return 'Invalid delivery_date format. Must be ISO 8601';
    }
    if (data.bac_resolution_date && !isoPattern.test(data.bac_resolution_date)) {
        return 'Invalid bac_resolution_date format. Must be ISO 8601';
    }
    if (data.philgeps_posting_date && !isoPattern.test(data.philgeps_posting_date)) {
        return 'Invalid philgeps_posting_date format. Must be ISO 8601';
    }
    if (data.approval_date && !isoPattern.test(data.approval_date)) {
        return 'Invalid approval_date format. Must be ISO 8601';
    }

    // Validate keys include the PR number
    var hasPrNumberKey = false;
    if (item.keys) {
        for (var k = 0; k < item.keys.length; k++) {
            if (item.keys[k] === data.pr_number) {
                hasPrNumberKey = true;
                break;
            }
        }
    }
    if (!hasPrNumberKey) {
        return 'Procurement metadata must include pr_number as a stream key';
    }

    // Procurement metadata passed all validations
    return;
}
