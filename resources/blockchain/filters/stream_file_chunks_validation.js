/**
 * ProcuChain Stream Filter: File Chunks Validation
 *
 * This Smart Filter validates chunked file data published to the
 * file.chunks stream. It ensures large files are properly chunked
 * and each chunk has valid metadata.
 *
 * The file.chunks stream stores large files split into smaller chunks
 * for efficient blockchain storage.
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

    // Chunk data must have keys (file_key and chunk index)
    if (!item.keys || item.keys.length === 0) {
        return 'File chunk must have at least one key (file_key)';
    }

    // First key should be the file_key
    var fileKey = item.keys[0];
    if (!fileKey || fileKey.length < 10) {
        return 'Invalid file key format. Must be at least 10 characters';
    }

    // ============================================================
    // Data Format Validation
    // ============================================================

    var data = item.data;

    // If data is null, reject - chunks should have inline data
    if (data === null) {
        return 'Chunk data is missing';
    }

    // Handle object with json property
    if (typeof data === 'object' && data.json) {
        data = data.json;
    }

    // Parse JSON if string
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            // Might be hex data, which is also valid
            var hexPattern = /^[a-fA-F0-9]*$/;
            if (hexPattern.test(data)) {
                // Valid hex chunk - minimal validation
                if (data.length === 0) {
                    return 'Chunk data cannot be empty';
                }
                return; // Accept hex chunk
            }
            return 'Invalid chunk data format';
        }
    }

    // ============================================================
    // Chunk Metadata Validation (if JSON format)
    // ============================================================

    if (typeof data === 'object') {
        // Validate chunk index
        if (data.chunk_index === undefined || data.chunk_index === null) {
            return 'Chunk missing required field: chunk_index';
        }

        var chunkIndex = parseInt(data.chunk_index, 10);
        if (isNaN(chunkIndex) || chunkIndex < 0) {
            return 'chunk_index must be a non-negative integer';
        }

        // Validate total chunks if provided
        if (data.total_chunks !== undefined) {
            var totalChunks = parseInt(data.total_chunks, 10);
            if (isNaN(totalChunks) || totalChunks <= 0) {
                return 'total_chunks must be a positive integer';
            }

            if (chunkIndex >= totalChunks) {
                return 'chunk_index must be less than total_chunks';
            }
        }

        // Validate chunk data/content
        if (!data.content && !data.data && !data.hex) {
            return 'Chunk must contain content, data, or hex field';
        }

        // Validate chunk hash if provided
        if (data.chunk_hash) {
            var hashPattern = /^[a-fA-F0-9]{64}$/;
            if (!hashPattern.test(data.chunk_hash)) {
                return 'Invalid chunk_hash. Must be SHA-256 (64 hex characters)';
            }
        }

        // Validate file_key matches the stream key
        if (data.file_key && data.file_key !== fileKey) {
            return 'file_key in data does not match stream key';
        }

        // Validate chunk size if provided
        if (data.chunk_size !== undefined) {
            var chunkSize = parseInt(data.chunk_size, 10);
            if (isNaN(chunkSize) || chunkSize <= 0) {
                return 'chunk_size must be a positive integer';
            }

            // Max chunk size: 1MB
            var maxChunkSize = 1 * 1024 * 1024;
            if (chunkSize > maxChunkSize) {
                return 'chunk_size exceeds maximum allowed (1MB)';
            }
        }
    }

    // ============================================================
    // Publisher Validation
    // ============================================================

    if (!item.publishers || item.publishers.length === 0) {
        return 'File chunk must have at least one publisher';
    }

    // File chunk passed all validations
    return;
}
