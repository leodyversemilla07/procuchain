<?php

namespace App\Services;

use App\DataTransferObjects\FileMetadata;
use App\Enums\StreamEnums;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Blockchain Storage Service for MultiChain
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
 * - Phase-organized structure (pre-procurement, procurement, post-procurement)
 */
final class BlockchainStorageService
{
    /**
     * Maximum chunk size for on-chain storage (50MB in bytes)
     * MultiChain default maximum-chunk-size is 16777216 bytes (16MB)
     * We use 50MB to handle large procurement documents
     */
    private int $maxChunkSize = 52428800;

    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Upload a file directly to blockchain (on-chain storage)
     *
     * @param  UploadedFile  $file  The file to upload
     * @param  string  $prNumber  PR Number (e.g., PR-2025-001)
     * @param  int  $stageId  Stage ID (1-15)
     * @param  string  $documentType  Document type (e.g., "Purchase Request")
     * @param  array  $metadata  Additional metadata to store on blockchain
     * @return array File storage information including file_key, data_txid and metadata_txid
     *
     * @throws Exception If storage fails
     */
    public function uploadFile(UploadedFile $file, string $prNumber, int $stageId, string $documentType, array $metadata = []): array
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();

        // Read file content using Laravel's UploadedFile::get() method
        // This is more reliable than file_get_contents(getRealPath()) for uploaded files
        $fileContent = $file->get();
        $fileHex = bin2hex($fileContent);
        $fileHash = hash('sha256', $fileContent);

        // Validate that we actually read the file content
        // Empty files should not be uploaded to blockchain
        if (empty($fileContent)) {
            Log::error('Empty file content detected during upload - rejecting upload', [
                'filename' => $file->getClientOriginalName(),
                'size' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'pr_number' => $prNumber,
                'document_type' => $documentType
            ]);

            throw new Exception("Failed to read file content. File appears to be empty or inaccessible. Reported size: {$fileSize} bytes");
        }

        if (strlen($fileContent) !== $fileSize) {
            Log::warning('File content size mismatch', [
                'filename' => $filename,
                'reported_size' => $fileSize,
                'actual_content_size' => strlen($fileContent),
            ]);
        }

        // Check file size against chunk limit
        if ($fileSize > $this->maxChunkSize) {
            throw new Exception("File size ({$fileSize} bytes) exceeds maximum on-chain storage limit ({$this->maxChunkSize} bytes or ".($this->maxChunkSize / 1048576).' MB)');
        }

        // Generate standardized file key with phase
        $fileKey = $this->generateFileKey($prNumber, $stageId, $documentType, $extension, $fileHash);
        $dataKey = str_replace('/', '_', $fileKey);

        Log::info('Storing file on blockchain', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'content_size' => strlen($fileContent),
            'hash' => $fileHash,
            'hex_length' => strlen($fileHex),
        ]);

        // Store file content on blockchain as hex
        $dataTxid = $this->multichain->publish(StreamEnums::FILE_DATA->value, $dataKey, $fileHex);

        Log::info('File content stored on blockchain', [
            'data_txid' => $dataTxid,
        ]);

        // Create FileMetadata DTO
        $fileMetadata = new FileMetadata(
            filename: $filename,
            fileKey: $fileKey,
            dataTxid: $dataTxid,
            dataKey: $dataKey,
            mimeType: $mimeType,
            size: $fileSize,
            hash: $fileHash,
            storageMethod: 'on_chain',
            storedAt: now(),
            additionalMetadata: array_merge($metadata, [
                'pr_number' => $prNumber,
                'stage_id' => $stageId,
                'phase' => $this->getPhaseFromStage($stageId),
                'document_type' => $documentType,
            ]),
        );

        // Publish metadata to blockchain using DTO
        $metadataTxid = $this->multichain->publish(
            StreamEnums::FILE_METADATA->value,
            $dataKey,
            ['json' => $fileMetadata->toBlockchainArray()]
        );

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
            'mime_type' => $mimeType,
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

        // Retrieve file metadata first to get FileMetadata DTO
        $metadataItems = $this->multichain->liststreamkeyitems(StreamEnums::FILE_METADATA->value, $dataKey, false, 1);
        $fileMetadata = null;

        if (! empty($metadataItems)) {
            $metadataJson = $metadataItems[0]['data']['json'] ?? null;
            if ($metadataJson) {
                $fileMetadata = FileMetadata::fromBlockchainArray($metadataJson);
            }
        }

        // If dataTxid provided, retrieve directly
        if ($dataTxid) {
            $dataItem = $this->multichain->getstreamitem(StreamEnums::FILE_DATA->value, $dataTxid, true);
        } elseif ($fileMetadata) {
            // Use data_txid from metadata
            $dataItem = $this->multichain->getstreamitem(StreamEnums::FILE_DATA->value, $fileMetadata->dataTxid, true);
        } else {
            // Otherwise, find by key
            $items = $this->multichain->liststreamkeyitems(StreamEnums::FILE_DATA->value, $dataKey, false, 1);
            if (empty($items)) {
                throw new Exception('File not found on blockchain');
            }
            $dataItem = $items[0];
        }

        // Get hex data from blockchain - handle both verbose and non-verbose responses
        $fileHex = null;
        if (is_string($dataItem['data'] ?? null)) {
            // Non-verbose mode - data is directly a hex string
            $fileHex = $dataItem['data'];
        } elseif (is_array($dataItem['data'] ?? null)) {
            // Verbose mode - need to use gettxoutdata to get raw hex
            $txid = $dataItem['txid'] ?? $dataItem['data']['txid'] ?? null;
            $vout = $dataItem['vout'] ?? $dataItem['data']['vout'] ?? 0;

            if ($txid) {
                $fileHex = $this->multichain->gettxoutdata($txid, $vout);
            }
        }

        if (! $fileHex) {
            throw new Exception('File data not found in blockchain item');
        }

        // Convert hex back to binary
        $fileContent = hex2bin($fileHex);
        if ($fileContent === false) {
            throw new Exception('Failed to decode file data from blockchain');
        }

        $fileHash = hash('sha256', $fileContent);

        // Verify hash if we have metadata
        if ($fileMetadata && $fileHash !== $fileMetadata->hash) {
            throw new Exception('File integrity check failed: hash mismatch');
        }

        return [
            'content' => $fileContent,
            'filename' => $fileMetadata ? $fileMetadata->filename : basename($fileKey),
            'size' => $fileMetadata ? $fileMetadata->size : strlen($fileContent),
            'hash' => $fileHash,
            'data_txid' => $dataItem['txid'] ?? null,
            'mime_type' => $fileMetadata?->mimeType,
            'file_key' => $fileMetadata?->fileKey ?? $fileKey,
            'stored_at' => $fileMetadata?->storedAt,
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
            $metadataItem = $this->multichain->getstreamitem(StreamEnums::FILE_METADATA->value, $metadataTxid, true);
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
        $metadataItem = $this->multichain->getstreamitem(StreamEnums::FILE_METADATA->value, $metadataTxid, true);

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

            // Publish deletion marker to blockchain
            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $deletionKey, [
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
    // FILE KEY GENERATION WITH PHASE SUPPORT
    // =====================================================================

    /**
     * Generate a standardized blockchain file key with comprehensive metadata
     *
     * Pattern: {pr_number}/{phase}/{stage_id}/{document_type}_{timestamp}_{hash_short}.{ext}
     * Example: PR-2025-001/pre-procurement/stage-01/purchase_request_20251115143022_a3f5b8c.pdf
     *
     * Benefits:
     * - Phase-organized (pre-procurement, procurement, post-procurement)
     * - Unique per upload (timestamp + hash prevents collisions)
     * - Human readable (includes document type and PR number)
     * - Sortable (timestamp in ISO format)
     * - Verifiable (hash short for quick integrity checks)
     * - Organized (hierarchical by PR, phase, and stage)
     */
    private function generateFileKey(
        string $prNumber,
        int $stageId,
        string $documentType,
        string $extension,
        string $fileHash
    ): string {
        // Sanitize document type (e.g., "Purchase Request" -> "purchase_request")
        $sanitizedType = Str::slug($documentType, '_');

        // Get short hash (first 7 chars) for uniqueness verification
        $hashShort = substr($fileHash, 0, 7);

        // Generate timestamp (YmdHis format for sortability)
        $timestamp = now()->format('YmdHis');

        // Format stage with leading zeros (stage-01, stage-02, etc.)
        $stageFormatted = sprintf('stage-%02d', $stageId);

        // Determine phase based on stage ID
        $phase = $this->getPhaseFromStage($stageId);

        // Build hierarchical file key with phase
        return sprintf(
            '%s/%s/%s/%s_%s_%s.%s',
            $prNumber,           // PR-2025-001
            $phase,              // pre-procurement / procurement / post-procurement
            $stageFormatted,     // stage-01
            $sanitizedType,      // purchase_request
            $timestamp,          // 20251115143022
            $hashShort,          // a3f5b8c
            $extension           // pdf
        );
    }

    /**
     * Get phase name based on stage ID
     *
     * Stage 1-3: Pre-Procurement (Planning & Preparation)
     * Stage 4-9: Procurement (Bidding & Evaluation)
     * Stage 10-15: Post-Procurement (Award & Implementation)
     */
    private function getPhaseFromStage(int $stageId): string
    {
        return match (true) {
            $stageId >= 1 && $stageId <= 3 => 'pre-procurement',
            $stageId >= 4 && $stageId <= 9 => 'procurement',
            $stageId >= 10 && $stageId <= 15 => 'post-procurement',
            default => 'unknown-phase',
        };
    }

    // =====================================================================
    // HIGH-LEVEL DOCUMENT UPLOAD ORCHESTRATION
    // =====================================================================

    /**
     * Upload files and prepare metadata for procurement documents.
     *
     * Orchestrates the complete upload process:
     * 1. Uploads each file to blockchain with phase-based naming
     * 2. Returns complete results with blockchain transaction IDs
     *
     * @param  UploadedFile[]  $files
     * @param  array  $metadata  Metadata for each file
     * @param  string  $prNumber  PR Number
     * @param  int  $stageId  Stage ID (1-15)
     * @param  string  $procurementTitle  Procurement title
     * @return array Complete metadata array with blockchain transaction IDs
     */
    public function uploadAndPrepare(array $files, array $metadata, string $prNumber, int $stageId, string $procurementTitle): array
    {
        $results = [];

        foreach ($files as $index => $file) {
            $meta = $metadata[$index] ?? [];
            $documentType = $meta['document_type'] ?? 'General Document';

            // Upload with standardized phase-based naming
            $result = $this->uploadFile(
                $file,
                $prNumber,
                $stageId,
                $documentType,
                [
                    'procurement_title' => $procurementTitle,
                    'description' => $meta['description'] ?? null,
                    'uploaded_by' => auth()->id(),
                ]
            );

            $results[] = array_merge($result, [
                'document_type' => $documentType,
                'description' => $meta['description'] ?? null,
                'phase' => $this->getPhaseFromStage($stageId),
                'stage_id' => $stageId,
            ]);
        }

        return $results;
    }
}
