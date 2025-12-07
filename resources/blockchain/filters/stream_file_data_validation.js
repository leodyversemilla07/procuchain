/**
 * ProcuChain Stream Filter: File Data Validation
 *
 * This Smart Filter validates raw file data published to the
 * file.data stream. It ensures file data is properly formatted
 * and within size limits.
 *
 * The file.data stream stores binary file content as hex-encoded data.
 *
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();

    // ============================================================
    // Key Validation
    // ============================================================

    // File data must have a key (file_key)
    if (!item.keys || item.keys.length === 0) {
        return 'File data must have at least one key (file_key)';
    }

    // Validate file key format (should be unique identifier, at least 10 chars)
    var fileKey = item.keys[0];
    if (!fileKey || fileKey.length < 10) {
        return 'Invalid file key format. Must be at least 10 characters';
    }

    // ============================================================
    // Data Format Validation
    // ============================================================

    // Get the data - can be hex, text, or json
    var data = item.data;

    // If data is null, it means the data is too large and stored externally
    // This is acceptable for large files
    if (data === null) {
        // Large file data - allow but ensure proper keys exist
        return;
    }

    // For inline data, check format
    if (item.format === 'hex') {
        // Hex data validation
        if (typeof data !== 'string') {
            return 'Hex data must be a string';
        }

        // Validate hex format (only hex characters)
        var hexPattern = /^[a-fA-F0-9]*$/;
        if (!hexPattern.test(data)) {
            return 'Invalid hex data format. Must contain only hexadecimal characters';
        }

        // Validate reasonable size (max 1MB inline, larger files use offchain)
        var maxInlineSize = 1 * 1024 * 1024; // 1MB in bytes
        var dataSize = data.length / 2; // Hex is 2 chars per byte
        if (dataSize > maxInlineSize) {
            return 'Inline file data exceeds maximum size (1MB). Use chunked storage for larger files';
        }
    } else if (item.format === 'json') {
        // JSON format is typically used for metadata, not raw file data
        // But we allow it for flexibility
        var jsonData = data;
        if (typeof data === 'object' && data.json) {
            jsonData = data.json;
        }

        // If it contains file content as base64, validate it
        if (jsonData && jsonData.content) {
            // Base64 validation (rough check)
            var base64Pattern = /^[A-Za-z0-9+/=]+$/;
            if (typeof jsonData.content === 'string' && !base64Pattern.test(jsonData.content)) {
                return 'Invalid base64 content in file data';
            }
        }
    }

    // ============================================================
    // Publisher Validation
    // ============================================================

    // Ensure there's at least one publisher
    if (!item.publishers || item.publishers.length === 0) {
        return 'File data must have at least one publisher';
    }

    // File data passed all validations
    return;
}
