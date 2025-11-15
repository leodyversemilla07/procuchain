/* eslint-disable @typescript-eslint/no-unused-vars, no-undef */
/**
 * Smart Filter: Procurement Corrections Stream
 * Version: 2.0.0 (pr_number support with backward compatibility)
 *
 * Validates document correction records published to the procurement.corrections stream.
 *
 * Changes in v2:
 * - Accept pr_number (PR-YYYY-####-####) as primary identifier
 * - Maintain backward compatibility with pr_number (UUID)
 * - Added PR number format validation
 *
 * Data Structure (from PublishDocumentCorrectionJob):
 * {
 *   pr_number: string,
 *   procurement_title: string,
 *   correction_type: string,
 *   original_txid: string,
 *   original_document_hash: string,
 *   reason: string,
 *   corrected_by: string,
 *   user_address: string,
 *   timestamp: ISO8601 string,
 *   action: string ('replace' or 'invalidate'),
 *   corrected_metadata?: object (optional, only if action='replace')
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
        'correction_type',
        'original_txid',
        'original_document_hash',
        'reason',
        'corrected_by',
        'user_address',
        'timestamp',
        'action',
    ];

    for (var i = 0; i < requiredFields.length; i++) {
        var field = requiredFields[i];
        if (!data[field]) {
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
    // 2. CORRECTION TYPE VALIDATION
    // ========================================
    var validCorrectionTypes = ['document_correction', 'metadata_correction', 'status_correction', 'hash_correction'];

    if (validCorrectionTypes.indexOf(data.correction_type) === -1) {
        return "Invalid correction_type '" + data.correction_type + "'. Must be: " + validCorrectionTypes.join(', ');
    }

    // ========================================
    // 3. ACTION VALIDATION
    // ========================================
    var validActions = ['replace', 'invalidate'];
    if (validActions.indexOf(data.action) === -1) {
        return "Invalid action '" + data.action + "'. Must be: " + validActions.join(', ');
    }

    // If action is 'replace', corrected_metadata must be present
    if (data.action === 'replace' && !data.corrected_metadata) {
        return "corrected_metadata is required when action is 'replace'";
    }

    // If action is 'invalidate', corrected_metadata should not be present
    if (data.action === 'invalidate' && data.corrected_metadata) {
        return "corrected_metadata should not be present when action is 'invalidate'";
    }

    // ========================================
    // 4. ORIGINAL TXID VALIDATION
    // ========================================
    // MultiChain transaction IDs are 64 character hex strings
    if (typeof data.original_txid !== 'string' || data.original_txid.length !== 64) {
        return 'Invalid original_txid format. Must be 64 character hex string';
    }

    // Check if it's valid hex
    var hexRegex = /^[0-9a-f]{64}$/i;
    if (!hexRegex.test(data.original_txid)) {
        return 'Invalid original_txid. Must contain only hexadecimal characters';
    }

    // ========================================
    // 5. ORIGINAL DOCUMENT HASH VALIDATION (SHA-256)
    // ========================================
    if (typeof data.original_document_hash !== 'string' || data.original_document_hash.length !== 64) {
        return 'Invalid document hash format. Must be 64 character SHA-256 hash';
    }

    if (!hexRegex.test(data.original_document_hash)) {
        return 'Invalid document hash. Must be valid SHA-256 hex string';
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

    if (data.reason.length < 10 || data.reason.length > 1000) {
        return 'reason must be 10-1000 characters (provide detailed explanation)';
    }

    if (data.corrected_by.length < 3 || data.corrected_by.length > 255) {
        return 'corrected_by must be 3-255 characters';
    }

    // ========================================
    // 9. CORRECTED METADATA VALIDATION (if present)
    // ========================================
    if (data.corrected_metadata) {
        // Must be an object
        if (typeof data.corrected_metadata !== 'object' || Array.isArray(data.corrected_metadata)) {
            return 'corrected_metadata must be an object';
        }

        // If hash is provided in corrected_metadata, validate it
        if (data.corrected_metadata.hash) {
            if (typeof data.corrected_metadata.hash !== 'string' || data.corrected_metadata.hash.length !== 64) {
                return 'Invalid hash in corrected_metadata. Must be 64 character SHA-256 hash';
            }

            if (!hexRegex.test(data.corrected_metadata.hash)) {
                return 'Invalid hash in corrected_metadata. Must be valid SHA-256 hex string';
            }
        }

        // If file_size is provided, validate it
        if (data.corrected_metadata.file_size !== undefined) {
            if (typeof data.corrected_metadata.file_size !== 'number' || data.corrected_metadata.file_size < 0) {
                return 'file_size in corrected_metadata must be a non-negative number';
            }

            // Maximum 10MB
            if (data.corrected_metadata.file_size > 10485760) {
                return 'file_size in corrected_metadata exceeds maximum allowed (10MB)';
            }
        }
    }

    // All validations passed
    return;
}
