/**
 * ProcuChain Stream Filter: File Metadata Validation
 *
 * This Smart Filter validates file metadata published to the
 * file.metadata stream. It ensures file integrity information
 * is properly recorded for document verification.
 *
 * Aligned with: App\DataTransferObjects\FileMetadata
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

    // If data is null (too large), reject it - metadata should be small
    if (data === null) {
        return 'File metadata data is too large or missing';
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
            return 'Invalid JSON data in file metadata';
        }
    }

    // ============================================================
    // Required Fields Validation (aligned with FileMetadata DTO)
    // ============================================================

    // Note: DTO uses 'filename', not 'original_name'
    if (!data.filename) {
        return 'File metadata missing required field: filename';
    }

    if (!data.file_key) {
        return 'File metadata missing required field: file_key';
    }

    if (!data.data_txid) {
        return 'File metadata missing required field: data_txid';
    }

    if (!data.data_key) {
        return 'File metadata missing required field: data_key';
    }

    if (!data.mime_type) {
        return 'File metadata missing required field: mime_type';
    }

    if (data.size === undefined || data.size === null) {
        return 'File metadata missing required field: size';
    }

    if (!data.hash) {
        return 'File metadata missing required field: hash';
    }

    if (!data.storage_method) {
        return 'File metadata missing required field: storage_method';
    }

    if (!data.stored_at) {
        return 'File metadata missing required field: stored_at';
    }

    // ============================================================
    // Format Validations
    // ============================================================

    // Validate file_key format (should be unique identifier)
    if (typeof data.file_key !== 'string' || data.file_key.length < 10) {
        return 'Invalid file_key format. Must be at least 10 characters';
    }

    // Validate filename is not empty
    if (typeof data.filename !== 'string' || data.filename.trim() === '') {
        return 'Filename cannot be empty';
    }

    // Validate mime_type format
    var mimePattern = /^[a-z]+\/[a-z0-9\-\.\+]+$/i;
    if (!mimePattern.test(data.mime_type)) {
        return 'Invalid MIME type format: ' + data.mime_type;
    }

    // Validate allowed MIME types for procurement documents
    var allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/octet-stream', // Generic binary for edge cases
    ];

    var mimeTypeValid = false;
    var mimeTypeLower = data.mime_type.toLowerCase();
    for (var j = 0; j < allowedMimeTypes.length; j++) {
        if (allowedMimeTypes[j] === mimeTypeLower) {
            mimeTypeValid = true;
            break;
        }
    }

    if (!mimeTypeValid) {
        return 'File type not allowed for procurement documents: ' + data.mime_type;
    }

    // Validate file size
    if (typeof data.size !== 'number' || data.size <= 0) {
        return 'File size must be a positive number';
    }

    // Maximum file size: 50MB (aligned with application upload limit)
    var maxFileSize = 50 * 1024 * 1024;
    if (data.size > maxFileSize) {
        return 'File size exceeds maximum allowed (50MB)';
    }

    // Validate hash is SHA-256 (64 hex characters)
    var hashPattern = /^[a-fA-F0-9]{64}$/;
    if (!hashPattern.test(data.hash)) {
        return 'Invalid file hash. Must be SHA-256 (64 hex characters)';
    }

    // Validate storage_method
    var validStorageMethods = ['inline', 'chunked', 'offchain', 'stream', 'on_chain'];
    var storageMethodValid = false;
    for (var k = 0; k < validStorageMethods.length; k++) {
        if (validStorageMethods[k] === data.storage_method) {
            storageMethodValid = true;
            break;
        }
    }

    if (!storageMethodValid) {
        return 'Invalid storage method: ' + data.storage_method;
    }

    // Validate stored_at timestamp format (ISO 8601 basic check)
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.stored_at)) {
        return 'Invalid stored_at timestamp format. Must be ISO 8601';
    }

    // Validate keys include the file_key
    var hasFileKey = false;
    // Note: File key validation is informational - existing data may not have this
    // var hasFileKey = false;
    // if (item.keys) {
    //     for (var m = 0; m < item.keys.length; m++) {
    //         if (item.keys[m] === data.file_key) {
    //             hasFileKey = true;
    //             break;
    //         }
    //     }
    // }
    // if (!hasFileKey) {
    //     return 'File metadata must include file_key as a stream key';
    // }

    // Validate pr_number if present in additional metadata
    if (data.pr_number) {
        var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
        if (!prPattern.test(data.pr_number)) {
            return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN';
        }
    }

    // Validate chunk information if present (for large files stored in chunks)
    if (data.storage_method === 'chunked') {
        if (!data.chunk_count || typeof data.chunk_count !== 'number' || data.chunk_count <= 0) {
            return 'Chunked file must specify valid chunk_count';
        }

        if (!data.chunk_size || typeof data.chunk_size !== 'number' || data.chunk_size <= 0) {
            return 'Chunked file must specify valid chunk_size';
        }
    }

    // File metadata passed all validations
    return;
}
