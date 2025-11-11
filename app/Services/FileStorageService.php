<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Unified File Storage Service with Blockchain
 *
 * Handles all file operations for blockchain storage:
 * - Low-level: Direct blockchain storage operations
 * - High-level: Document upload orchestration with metadata preparation
 *
 * Stores files directly on blockchain for:
 * - True decentralization and redundancy across all nodes
 * - Zero recurring storage costs (no S3/Spaces fees)
 * - Heroku-compatible persistent storage
 * - Automatic replication across blockchain nodes
 * - Immutable audit trail
 * - Integrity verification with SHA-256 hashing
 * - No ephemeral filesystem issues
 */
class FileStorageService
{
    /**
     * Stream for file content (actual file data in hex)
     */
    protected string $dataStream = 'file.data';

    /**
     * Stream for file metadata
     */
    protected string $metadataStream = 'file.metadata';

    /**
     * Maximum chunk size for on-chain storage (8MB in bytes)
     * MultiChain default maximum-chunk-size is 16777216 bytes (16MB)
     * We use 8MB to be safe
     */
    protected int $maxChunkSize = 8388608;

    public function __construct(
        protected MultichainService $multichain
    ) {}

    /**
     * Upload a file directly to blockchain (on-chain storage)
     *
     * @param  UploadedFile  $file  The file to upload
     * @param  string  $path  The path identifier for the file
     * @param  string  $suffix  Optional suffix for the file key
     * @param  array  $metadata  Additional metadata to store on blockchain
     * @return array File storage information including file_key, data_txid and metadata_txid
     *
     * @throws Exception If storage fails
     */
    public function uploadFile(UploadedFile $file, string $path, string $suffix = '', array $metadata = []): array
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();

        // Read file content and convert to hex
        $fileContent = file_get_contents($file->getRealPath());
        $fileHex = bin2hex($fileContent);
        $fileHash = hash('sha256', $fileContent);

        // Check file size against chunk limit
        if ($fileSize > $this->maxChunkSize) {
            throw new Exception("File size ({$fileSize} bytes) exceeds maximum on-chain storage limit ({$this->maxChunkSize} bytes or ".($this->maxChunkSize / 1048576).' MB)');
        }

        // Generate unique file key
        $fileKey = $path.'/'.$suffix.'.'.$extension;
        $dataKey = str_replace('/', '_', $fileKey);

        Log::info('Storing file on blockchain', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'hash' => $fileHash,
            'hex_length' => strlen($fileHex),
        ]);

        // Store file content on blockchain as hex
        $dataTxid = $this->multichain->publish($this->dataStream, $dataKey, $fileHex);

        Log::info('File content stored on blockchain', [
            'data_txid' => $dataTxid,
        ]);

        // Publish metadata to blockchain
        $metadataTxid = $this->multichain->publish($this->metadataStream, $dataKey, [
            'json' => array_merge([
                'filename' => $filename,
                'file_key' => $fileKey,
                'data_txid' => $dataTxid,
                'data_key' => $dataKey,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'hash' => $fileHash,
                'storage_method' => 'on_chain',
                'stored_at' => now()->toIso8601String(),
            ], $metadata),
        ]);

        Log::info('File stored successfully on blockchain', [
            'file_key' => $fileKey,
            'data_txid' => $dataTxid,
            'metadata_txid' => $metadataTxid,
        ]);

        return [
            'file_key' => $fileKey,
            'data_txid' => $dataTxid,
            'metadata_txid' => $metadataTxid,
            'filename' => $filename,
            'size' => $fileSize,
            'hash' => $fileHash,
        ];
    }

    /**
     * Retrieve file from blockchain
     *
     * @param  string  $fileKey  The file key
     * @param  string|null  $dataTxid  Optional data transaction ID for direct retrieval
     * @return array File content and metadata
     *
     * @throws Exception If file not found
     */
    public function retrieveFile(string $fileKey, ?string $dataTxid = null): array
    {
        $dataKey = str_replace('/', '_', $fileKey);

        // If dataTxid provided, retrieve directly
        if ($dataTxid) {
            $dataItem = $this->multichain->getStreamItem($this->dataStream, $dataTxid, true);
        } else {
            // Otherwise, find by key
            $items = $this->multichain->listStreamKeyItems($this->dataStream, $dataKey, false, 1);
            if (empty($items)) {
                throw new Exception('File not found on blockchain');
            }
            $dataItem = $items[0];
        }

        // Get hex data from blockchain
        $fileHex = $dataItem['data'] ?? null;
        if (! $fileHex) {
            throw new Exception('File data not found in blockchain item');
        }

        // Convert hex back to binary
        $fileContent = hex2bin($fileHex);
        if ($fileContent === false) {
            throw new Exception('Failed to decode file data from blockchain');
        }

        $fileHash = hash('sha256', $fileContent);

        return [
            'content' => $fileContent,
            'filename' => basename($fileKey),
            'size' => strlen($fileContent),
            'hash' => $fileHash,
            'data_txid' => $dataItem['txid'] ?? null,
        ];
    }

    /**
     * Verify file integrity against blockchain metadata
     *
     * @param  string  $fileKey  The file key
     * @param  string  $metadataTxid  The metadata transaction ID
     * @return bool True if file matches blockchain metadata record
     */
    public function verifyFileIntegrity(string $fileKey, string $metadataTxid): bool
    {
        try {
            // Get metadata from blockchain
            $metadataItem = $this->multichain->getStreamItem($this->metadataStream, $metadataTxid, true);
            $metadata = $metadataItem['data']['json'] ?? null;

            if (! $metadata) {
                Log::error('Metadata not found', ['metadata_txid' => $metadataTxid]);

                return false;
            }

            $blockchainHash = $metadata['hash'] ?? null;
            $dataTxid = $metadata['data_txid'] ?? null;

            if (! $blockchainHash || ! $dataTxid) {
                Log::error('Invalid metadata structure', ['metadata' => $metadata]);

                return false;
            }

            // Retrieve file from blockchain and verify hash
            $fileData = $this->retrieveFile($fileKey, $dataTxid);
            $fileHash = $fileData['hash'];

            return $fileHash === $blockchainHash;
        } catch (Exception $e) {
            Log::error('File integrity verification failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file metadata from blockchain
     *
     * @param  string  $metadataTxid  The metadata transaction ID
     * @return array File metadata
     */
    public function getFileMetadata(string $metadataTxid): array
    {
        $metadataItem = $this->multichain->getStreamItem($this->metadataStream, $metadataTxid, true);

        return $metadataItem['data']['json'] ?? [];
    }

    /**
     * Mark a file as deleted on blockchain
     * Note: File content remains on blockchain (immutable) but marked as deleted
     *
     * @param  string  $fileKey  The file key to mark as deleted
     * @param  string  $reason  Reason for deletion
     * @return bool Success status
     */
    public function deleteFile(string $fileKey, string $reason = ''): bool
    {
        try {
            // Publish deletion record to blockchain
            $dataKey = str_replace('/', '_', $fileKey);
            $deletionKey = $dataKey.'_deleted';

            $this->multichain->publish($this->metadataStream, $deletionKey, [
                'json' => [
                    'file_key' => $fileKey,
                    'data_key' => $dataKey,
                    'action' => 'deleted',
                    'reason' => $reason,
                    'deleted_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info('File marked as deleted on blockchain', [
                'file_key' => $fileKey,
                'reason' => $reason,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('File deletion marking failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get maximum file size supported for on-chain storage
     *
     * @return int Maximum file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return $this->maxChunkSize;
    }

    /**
     * Get maximum file size in human-readable format
     *
     * @return string Maximum file size (e.g., "8 MB")
     */
    public function getMaxFileSizeFormatted(): string
    {
        $mb = $this->maxChunkSize / 1048576;

        return number_format($mb, 0).' MB';
    }

    // =====================================================================
    // HIGH-LEVEL DOCUMENT UPLOAD ORCHESTRATION
    // Merged from DocumentUploadService
    // =====================================================================

    /**
     * Upload files and prepare metadata for procurement documents.
     *
     * Orchestrates the complete upload process:
     * 1. Prepares metadata for all files
     * 2. Uploads each file to blockchain
     * 3. Returns complete metadata array with blockchain transaction IDs
     *
     * Merged from DocumentUploadService to consolidate file operations.
     *
     * @param  UploadedFile[]  $files
     * @param  array  $metadata  Metadata for each file
     * @param  string  $procurementId  Procurement ID
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $stageFolder  Stage folder name
     * @return array Complete metadata array with blockchain transaction IDs
     */
    public function uploadAndPrepare(array $files, array $metadata, string $procurementId, string $procurementTitle, string $stageFolder): array
    {
        $metadataArray = $this->prepareMetadata(
            $files,
            $metadata,
            $procurementId,
            $procurementTitle,
            $stageFolder
        );

        foreach ($files as $index => $file) {
            // Use base_path and sanitized_document_type from metadataArray
            $meta = &$metadataArray[$index];

            // Upload file with on-chain blockchain storage
            $result = $this->uploadFile(
                $file,
                $meta['base_path'].'/',
                $meta['sanitized_document_type'],
                [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'document_type' => $meta['document_type'] ?? 'unknown',
                    'stage' => $stageFolder,
                ]
            );

            // Store file_key, data_txid, metadata_txid for on-chain storage
            $meta['file_key'] = $result['file_key'];
            $meta['data_txid'] = $result['data_txid'];
            $meta['metadata_txid'] = $result['metadata_txid'];
            $meta['file_hash'] = $result['hash'];

            // Pure blockchain - no database records needed
            // Document tracking happens via blockchain streams only
        }

        return $metadataArray;
    }

    /**
     * Prepare metadata for procurement documents.
     *
     * Standardizes metadata format for all files before upload.
     * Generates file paths, sanitizes names, and calculates hashes.
     *
     * Merged from DocumentUploadService (originally from DocumentMetadataService).
     *
     * @param  UploadedFile[]  $files  Files to prepare metadata for
     * @param  array  $metadata  Input metadata for each file
     * @param  string  $procurementId  Procurement ID
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $stageFolder  Stage folder name
     * @return array Prepared metadata array with paths, hashes, and sanitized names
     */
    public function prepareMetadata(array $files, array $metadata, string $procurementId, string $procurementTitle, string $stageFolder): array
    {
        $sanitizedTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $procurementTitle);
        $basePath = trim("$procurementId-$sanitizedTitle/$stageFolder", '/');
        $metadataArray = [];

        foreach ($files as $index => $file) {
            $documentType = $metadata[$index]['document_type'] ?? "doc-$index";
            $sanitizedDocumentType = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documentType);
            $hash = hash('sha256', file_get_contents($file->getRealPath()));

            $metadataArray[] = array_merge(
                [
                    'hash' => $hash,
                    'file_size' => $file->getSize(),
                    'document_type' => $documentType,
                    'sanitized_document_type' => $sanitizedDocumentType,
                    'base_path' => $basePath,
                ],
                $metadata[$index]
            );
        }

        return $metadataArray;
    }
}
