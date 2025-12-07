/**
 * ProcuChain Stream Filter: Document Validation
 *
 * This Smart Filter validates stream items published to the
 * procurement.documents stream. It ensures all documents have
 * proper metadata and conform to ProcuChain requirements.
 *
 * Aligned with: App\DataTransferObjects\DocumentData
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

    // If data is null (too large), we need to check size
    if (data === null) {
        // Allow large files but ensure they have proper keys
        if (!item.keys || item.keys.length === 0) {
            return 'Document must have at least one key (pr_number)';
        }
        return;
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
            return 'Invalid JSON data in document';
        }
    }

    // ============================================================
    // Required Fields Validation (aligned with DocumentData DTO)
    // ============================================================

    // Core identification fields
    if (!data.pr_number) {
        return 'Document missing required field: pr_number';
    }

    if (!data.procurement_title) {
        return 'Document missing required field: procurement_title';
    }

    if (!data.stage) {
        return 'Document missing required field: stage';
    }

    if (!data.document_type) {
        return 'Document missing required field: document_type';
    }

    // File-related fields
    if (!data.file_key) {
        return 'Document missing required field: file_key';
    }

    if (!data.file_name) {
        return 'Document missing required field: file_name';
    }

    if (data.file_size === undefined || data.file_size === null) {
        return 'Document missing required field: file_size';
    }

    if (!data.mime_type) {
        return 'Document missing required field: mime_type';
    }

    // Hash field (named 'hash' in DTO, not 'file_hash')
    if (!data.hash) {
        return 'Document missing required field: hash';
    }

    // Transaction references
    if (!data.data_txid) {
        return 'Document missing required field: data_txid';
    }

    if (!data.metadata_txid) {
        return 'Document missing required field: metadata_txid';
    }

    // Actor field
    if (!data.uploaded_by) {
        return 'Document missing required field: uploaded_by';
    }

    // Timestamp
    if (!data.timestamp) {
        return 'Document missing required field: timestamp';
    }

    // ============================================================
    // Format Validations
    // ============================================================

    // Validate PR number format (supports PR-YYYY-NNN or PR-YYYY-XXXX-NNNN)
    var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
    if (!prPattern.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN, got: ' + data.pr_number;
    }

    // Validate hash is SHA-256 (64 hex characters)
    var hashPattern = /^[a-fA-F0-9]{64}$/;
    if (!hashPattern.test(data.hash)) {
        return 'Invalid hash. Must be SHA-256 (64 hex characters)';
    }

    // Validate procurement stage (aligned with StageEnums)
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

    var stageValid = false;
    for (var i = 0; i < validStages.length; i++) {
        if (validStages[i] === data.stage) {
            stageValid = true;
            break;
        }
    }

    if (!stageValid) {
        return 'Invalid procurement stage: ' + data.stage;
    }

    // Validate document type is not empty
    if (typeof data.document_type !== 'string' || data.document_type.trim() === '') {
        return 'Document type cannot be empty';
    }

    // Validate file_size is a positive number
    if (typeof data.file_size !== 'number' || data.file_size < 0) {
        return 'File size must be a non-negative number';
    }

    // Validate MIME type format
    var mimePattern = /^[a-z]+\/[a-z0-9\-\.\+]+$/i;
    if (!mimePattern.test(data.mime_type)) {
        return 'Invalid MIME type format: ' + data.mime_type;
    }

    // Validate publisher has write permission (built-in check)
    if (!item.publishers || item.publishers.length === 0) {
        return 'Document must have at least one publisher';
    }

    // Validate keys include the PR number
    var hasPrNumberKey = false;
    if (item.keys) {
        for (var j = 0; j < item.keys.length; j++) {
            if (item.keys[j] === data.pr_number) {
                hasPrNumberKey = true;
                break;
            }
        }
    }

    if (!hasPrNumberKey) {
        return 'Document must include pr_number as a stream key';
    }

    // Validate timestamp format (ISO 8601 basic check)
    // MultiChain JS engine doesn't have Date.parse, use regex instead
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.timestamp)) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }

    // Note: Future timestamp validation removed - requires Date.parse which is unavailable
    // Blockchain timestamp validation is handled by block time constraints

    // Document passed all validations
    return;
}
