/**
 * ProcuChain Transaction Filter: Procurement Validation
 *
 * This Smart Filter validates procurement-related transactions before
 * they are accepted into the blockchain. It enforces business rules
 * for document publishing and status transitions.
 *
 * Aligned with all DTOs in App\DataTransferObjects\*
 *
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filtertransaction() {
    var tx = getfiltertransaction();

    // Skip coinbase transactions (mining rewards)
    if (tx.vin.length === 0) {
        return;
    }

    // Check each output for stream items
    for (var i = 0; i < tx.vout.length; i++) {
        var output = tx.vout[i];

        // Check if this output contains stream data
        if (output.data && output.data.length > 0) {
            for (var j = 0; j < output.data.length; j++) {
                var dataItem = output.data[j];

                // Validate stream items
                if (dataItem.for) {
                    var streamName = dataItem.for;
                    var result = validateStreamItem(streamName, dataItem);
                    if (result) {
                        return result;
                    }
                }
            }
        }
    }

    // Transaction is valid
    return;
}

/**
 * Validate stream item based on the target stream
 */
function validateStreamItem(streamName, dataItem) {
    // Procurement documents stream validation
    if (streamName === 'procurement.documents') {
        return validateDocumentItem(dataItem);
    }

    // Procurement status stream validation
    if (streamName === 'procurement.status') {
        return validateStatusItem(dataItem);
    }

    // Procurement metadata stream validation
    if (streamName === 'procurement.metadata') {
        return validateMetadataItem(dataItem);
    }

    // Procurement events stream validation
    if (streamName === 'procurement.events') {
        return validateEventItem(dataItem);
    }

    // File data stream validation
    if (streamName === 'file.data') {
        return validateFileDataItem(dataItem);
    }

    // File metadata stream validation
    if (streamName === 'file.metadata') {
        return validateFileMetadataItem(dataItem);
    }

    // Allow other streams
    return;
}

/**
 * Validate document stream items (aligned with DocumentData DTO)
 */
function validateDocumentItem(dataItem) {
    var data = dataItem.json;

    if (!data) {
        return 'Document item must contain JSON data';
    }

    // Required fields for document items (aligned with DocumentData DTO)
    var requiredFields = [
        'pr_number',
        'procurement_title',
        'stage',
        'document_type',
        'file_key',
        'file_name',
        'hash', // Note: DTO uses 'hash', not 'file_hash'
        'uploaded_by',
        'timestamp',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        if (!data[requiredFields[i]]) {
            return 'Document item missing required field: ' + requiredFields[i];
        }
    }

    // Validate PR number format (PR-YYYY-XXXX-NNNN)
    if (!/^PR-\d{4}-\d{4}-\d{4}$/.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-XXXX-NNNN';
    }

    // Validate hash is SHA-256 (64 hex characters)
    if (!/^[a-fA-F0-9]{64}$/.test(data.hash)) {
        return 'Invalid hash. Must be SHA-256 (64 hex characters)';
    }

    // Validate stage is a valid procurement stage (aligned with StageEnums)
    var validStages = [
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

    if (validStages.indexOf(data.stage) === -1) {
        return 'Invalid procurement stage: ' + data.stage;
    }

    return;
}

/**
 * Validate status stream items (aligned with StatusData DTO)
 */
function validateStatusItem(dataItem) {
    var data = dataItem.json;

    if (!data) {
        return 'Status item must contain JSON data';
    }

    // Required fields for status items (aligned with StatusData DTO)
    var requiredFields = [
        'pr_number',
        'procurement_title',
        'stage',
        'current_status', // Note: DTO uses 'current_status', not 'status'
        'timestamp',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        if (!data[requiredFields[i]]) {
            return 'Status item missing required field: ' + requiredFields[i];
        }
    }

    // Validate PR number format
    if (!/^PR-\d{4}-\d{4}-\d{4}$/.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-XXXX-NNNN';
    }

    // Validate timestamp is a valid ISO 8601 date
    if (isNaN(Date.parse(data.timestamp))) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    return;
}

/**
 * Validate event stream items (aligned with EventData DTO)
 */
function validateEventItem(dataItem) {
    var data = dataItem.json;

    if (!data) {
        return 'Event item must contain JSON data';
    }

    // Required fields for event items (aligned with EventData DTO)
    var requiredFields = [
        'pr_number',
        'procurement_title',
        'stage',
        'event_type',
        'category',
        'severity',
        'details',
        'user_address', // Note: DTO uses 'user_address', not 'actor_address'
        'timestamp',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        if (!data[requiredFields[i]]) {
            return 'Event item missing required field: ' + requiredFields[i];
        }
    }

    // Validate PR number format
    if (!/^PR-\d{4}-\d{4}-\d{4}$/.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-XXXX-NNNN';
    }

    // Validate timestamp is a valid ISO 8601 date
    if (isNaN(Date.parse(data.timestamp))) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    return;
}

/**
 * Validate metadata stream items (aligned with ProcurementData DTO)
 */
function validateMetadataItem(dataItem) {
    var data = dataItem.json;

    if (!data) {
        return 'Metadata item must contain JSON data';
    }

    // Required fields for procurement metadata
    var requiredFields = ['pr_number', 'title', 'mode', 'category'];
    for (var i = 0; i < requiredFields.length; i++) {
        if (!data[requiredFields[i]]) {
            return 'Metadata item missing required field: ' + requiredFields[i];
        }
    }

    // Validate PR number format
    if (!/^PR-\d{4}-\d{4}-\d{4}$/.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-XXXX-NNNN';
    }

    // Validate procurement mode (NGPA IRR RA 12009 compliant)
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

    if (validModes.indexOf(data.mode) === -1) {
        return 'Invalid procurement mode: ' + data.mode;
    }

    // Validate procurement category
    var validCategories = ['goods', 'services', 'infrastructure_projects', 'consulting_services'];

    if (validCategories.indexOf(data.category) === -1) {
        return 'Invalid procurement category: ' + data.category;
    }

    return;
}

/**
 * Validate file data stream items
 */
function validateFileDataItem(dataItem) {
    // File data must have a key (file_key)
    if (!dataItem.keys || dataItem.keys.length === 0) {
        return 'File data item must have at least one key (file_key)';
    }

    // Validate file key format
    var fileKey = dataItem.keys[0];
    if (!fileKey || fileKey.length < 10) {
        return 'Invalid file key format';
    }

    return;
}

/**
 * Validate file metadata stream items (aligned with FileMetadata DTO)
 */
function validateFileMetadataItem(dataItem) {
    var data = dataItem.json;

    if (!data) {
        return 'File metadata item must contain JSON data';
    }

    // Required fields for file metadata (aligned with FileMetadata DTO)
    var requiredFields = [
        'filename', // Note: DTO uses 'filename', not 'original_name'
        'file_key',
        'data_txid',
        'data_key',
        'mime_type',
        'size',
        'hash',
        'storage_method',
        'stored_at',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        if (data[requiredFields[i]] === undefined || data[requiredFields[i]] === null) {
            return 'File metadata item missing required field: ' + requiredFields[i];
        }
    }

    // Validate hash is SHA-256
    if (!/^[a-fA-F0-9]{64}$/.test(data.hash)) {
        return 'Invalid file hash. Must be SHA-256 (64 hex characters)';
    }

    // Validate file size is positive
    if (typeof data.size !== 'number' || data.size <= 0) {
        return 'File size must be a positive number';
    }

    return;
}
