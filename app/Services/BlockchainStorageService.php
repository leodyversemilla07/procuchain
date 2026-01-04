<?php

namespace App\Services;

use App\Contracts\BlockchainStorageInterface;
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
 *
 * @see config/blockchain.php for upload limits and chunking configuration
 */
final class BlockchainStorageService implements BlockchainStorageInterface
{
    /**
     * Maximum chunk size for on-chain storage
     * Loaded from config('blockchain.upload.absolute_max_file_size')
     * Default: 50MB (52428800 bytes)
     */
    private int $maxChunkSize;

    /**
     * Recommended maximum file size for optimal blockchain performance
     * Loaded from config('blockchain.upload.max_file_size')
     * Default: 2MB (2097152 bytes)
     */
    private int $recommendedMaxSize;

    /**
     * Chunking threshold - files larger than this are split into chunks
     */
    private int $chunkThreshold;

    /**
     * Size of each chunk for large files
     */
    private int $chunkSize;

    /**
     * Whether chunking is enabled
     */
    private bool $chunkingEnabled;

    public function __construct(
        private Manager $multichain
    ) {
        $this->maxChunkSize = config('blockchain.upload.absolute_max_file_size', 52428800);
        $this->recommendedMaxSize = config('blockchain.upload.max_file_size', 2097152);
        $this->chunkThreshold = config('blockchain.upload.chunking.chunk_threshold', 1572864);
        $this->chunkSize = config('blockchain.upload.chunking.chunk_size', 1048576);
        $this->chunkingEnabled = config('blockchain.upload.chunking.enabled', true);
    }

    /**
     * Upload a file directly to blockchain (on-chain storage)
     *
     * For files larger than the chunk threshold, the file is split into
     * multiple chunks and stored in the file.chunks stream. Metadata
     * contains references to all chunk transaction IDs for reassembly.
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
        $fileHash = hash('sha256', $fileContent);

        // Validate that we actually read the file content
        // Empty files should not be uploaded to blockchain
        if (empty($fileContent)) {
            Log::error('Empty file content detected during upload - rejecting upload', [
                'filename' => $file->getClientOriginalName(),
                'size' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'pr_number' => $prNumber,
                'document_type' => $documentType,
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

        // Check file size against absolute maximum
        if ($fileSize > $this->maxChunkSize) {
            throw new Exception("File size ({$fileSize} bytes) exceeds maximum on-chain storage limit ({$this->maxChunkSize} bytes or ".($this->maxChunkSize / 1048576).' MB)');
        }

        // Generate standardized file key with phase
        $fileKey = $this->generateFileKey($prNumber, $stageId, $documentType, $extension, $fileHash);
        $dataKey = str_replace('/', '_', $fileKey);

        // Determine if chunking is needed
        $needsChunking = $this->chunkingEnabled && $fileSize > $this->chunkThreshold;

        if ($needsChunking) {
            return $this->uploadFileChunked($fileContent, $filename, $fileKey, $dataKey, $fileSize, $mimeType, $fileHash, $prNumber, $stageId, $documentType, $metadata);
        }

        return $this->uploadFileSingleTransaction($fileContent, $filename, $fileKey, $dataKey, $fileSize, $mimeType, $fileHash, $prNumber, $stageId, $documentType, $metadata);
    }

    /**
     * Upload a small file in a single blockchain transaction
     */
    private function uploadFileSingleTransaction(
        string $fileContent,
        string $filename,
        string $fileKey,
        string $dataKey,
        int $fileSize,
        string $mimeType,
        string $fileHash,
        string $prNumber,
        int $stageId,
        string $documentType,
        array $metadata
    ): array {
        $fileHex = bin2hex($fileContent);

        Log::info('Storing file on blockchain (single transaction)', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'hash' => $fileHash,
            'hex_length' => strlen($fileHex),
        ]);

        $startTime = microtime(true);

        // Create FileMetadata DTO
        $fileMetadata = new FileMetadata(
            filename: $filename,
            fileKey: $fileKey,
            dataTxid: '',
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

        // Build items for batch publishing
        $items = [
            [
                'key' => $dataKey,
                'data' => $fileHex,
                'for' => StreamEnums::FILE_DATA->value,
            ],
            [
                'key' => $dataKey,
                'data' => ['json' => array_merge(
                    $fileMetadata->toBlockchainArray(),
                    ['data_txid' => 'BATCH_TXID']
                )],
                'for' => StreamEnums::FILE_METADATA->value,
            ],
        ];

        // Publish both data and metadata atomically
        $txid = $this->multichain->publishmulti(StreamEnums::FILE_DATA->value, $items);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('File stored successfully on blockchain (single transaction)', [
            'file_key' => $fileKey,
            'txid' => $txid,
            'filename' => $filename,
            'size' => $fileSize,
            'duration_ms' => $duration,
        ]);

        return [
            'file_key' => $fileKey,
            'data_txid' => $txid,
            'metadata_txid' => $txid,
            'filename' => $filename,
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'hash' => $fileHash,
            'storage_method' => 'on_chain',
            'chunked' => false,
        ];
    }

    /**
     * Upload a large file using chunked storage across multiple transactions
     */
    private function uploadFileChunked(
        string $fileContent,
        string $filename,
        string $fileKey,
        string $dataKey,
        int $fileSize,
        string $mimeType,
        string $fileHash,
        string $prNumber,
        int $stageId,
        string $documentType,
        array $metadata
    ): array {
        $chunks = str_split($fileContent, $this->chunkSize);
        $totalChunks = count($chunks);

        Log::info('Storing large file on blockchain (chunked)', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'hash' => $fileHash,
            'total_chunks' => $totalChunks,
            'chunk_size' => $this->chunkSize,
        ]);

        $startTime = microtime(true);
        $chunkTxids = [];

        // Upload each chunk to the file.chunks stream
        foreach ($chunks as $index => $chunk) {
            $chunkHex = bin2hex($chunk);
            $chunkKey = "{$dataKey}_chunk_{$index}";
            $chunkHash = hash('sha256', $chunk);

            $chunkData = [
                'json' => [
                    'file_key' => $fileKey,
                    'chunk_index' => $index,
                    'total_chunks' => $totalChunks,
                    'chunk_hash' => $chunkHash,
                    'chunk_size' => strlen($chunk),
                ],
            ];

            // Publish chunk data and chunk metadata
            $items = [
                [
                    'key' => $chunkKey,
                    'data' => $chunkHex,
                    'for' => StreamEnums::FILE_CHUNKS->value,
                ],
                [
                    'key' => $chunkKey.'_meta',
                    'data' => $chunkData,
                    'for' => StreamEnums::FILE_CHUNKS->value,
                ],
            ];

            $chunkTxid = $this->multichain->publishmulti(StreamEnums::FILE_CHUNKS->value, $items);
            $chunkTxids[] = [
                'txid' => $chunkTxid,
                'index' => $index,
                'key' => $chunkKey,
                'hash' => $chunkHash,
                'size' => strlen($chunk),
            ];

            Log::debug('Chunk uploaded', [
                'file_key' => $fileKey,
                'chunk' => ($index + 1).'/'.$totalChunks,
                'txid' => $chunkTxid,
            ]);
        }

        // Create FileMetadata with chunk references
        $fileMetadata = new FileMetadata(
            filename: $filename,
            fileKey: $fileKey,
            dataTxid: $chunkTxids[0]['txid'], // Reference first chunk as primary
            dataKey: $dataKey,
            mimeType: $mimeType,
            size: $fileSize,
            hash: $fileHash,
            storageMethod: 'on_chain_chunked',
            storedAt: now(),
            additionalMetadata: array_merge($metadata, [
                'pr_number' => $prNumber,
                'stage_id' => $stageId,
                'phase' => $this->getPhaseFromStage($stageId),
                'document_type' => $documentType,
                'chunked' => true,
                'total_chunks' => $totalChunks,
                'chunk_size' => $this->chunkSize,
                'chunk_txids' => $chunkTxids,
            ]),
        );

        // Publish file metadata to file.metadata stream
        $metadataTxid = $this->multichain->publish(
            StreamEnums::FILE_METADATA->value,
            $dataKey,
            ['json' => $fileMetadata->toBlockchainArray()]
        );

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Large file stored successfully on blockchain (chunked)', [
            'file_key' => $fileKey,
            'metadata_txid' => $metadataTxid,
            'filename' => $filename,
            'size' => $fileSize,
            'total_chunks' => $totalChunks,
            'duration_ms' => $duration,
        ]);

        return [
            'file_key' => $fileKey,
            'data_txid' => $chunkTxids[0]['txid'],
            'metadata_txid' => $metadataTxid,
            'filename' => $filename,
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'hash' => $fileHash,
            'storage_method' => 'on_chain_chunked',
            'chunked' => true,
            'total_chunks' => $totalChunks,
            'chunk_txids' => array_column($chunkTxids, 'txid'),
        ];
    }

    /**
     * Retrieve file from blockchain (handles both single and chunked storage)
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

        // Retrieve file metadata first to check storage method
        $metadataItems = $this->multichain->liststreamkeyitems(StreamEnums::FILE_METADATA->value, $dataKey, false, 1);
        $fileMetadata = null;
        $metadataJson = null;

        if (! empty($metadataItems)) {
            $metadataJson = $metadataItems[0]['data']['json'] ?? null;
            if ($metadataJson) {
                $fileMetadata = FileMetadata::fromBlockchainArray($metadataJson);
            }
        }

        // Check if this is a chunked file
        $isChunked = $metadataJson['chunked'] ?? false;
        $storageMethod = $metadataJson['storage_method'] ?? $fileMetadata?->storageMethod ?? 'on_chain';

        if ($isChunked || $storageMethod === 'on_chain_chunked') {
            return $this->retrieveChunkedFile($fileKey, $dataKey, $metadataJson, $fileMetadata);
        }

        return $this->retrieveSingleFile($fileKey, $dataKey, $dataTxid, $fileMetadata);
    }

    /**
     * Retrieve a single-transaction file from blockchain
     */
    private function retrieveSingleFile(string $fileKey, string $dataKey, ?string $dataTxid, ?FileMetadata $fileMetadata): array
    {
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

        // Get hex data from blockchain
        $fileHex = $this->extractHexFromDataItem($dataItem);

        if (! $fileHex) {
            throw new Exception('File data not found in blockchain item');
        }

        // Convert hex back to binary
        $fileContent = hex2bin($fileHex);
        if ($fileContent === false) {
            throw new Exception('Failed to decode file data from blockchain');
        }

        $fileHash = hash('sha256', $fileContent);

        // Verify integrity if metadata available
        if ($fileMetadata && $fileMetadata->hash !== $fileHash) {
            Log::warning('File hash mismatch during retrieval', [
                'file_key' => $fileKey,
                'expected_hash' => $fileMetadata->hash,
                'actual_hash' => $fileHash,
            ]);
        }

        return [
            'content' => $fileContent,
            'filename' => $fileMetadata?->filename ?? basename($fileKey),
            'mime_type' => $fileMetadata?->mimeType ?? 'application/octet-stream',
            'size' => strlen($fileContent),
            'hash' => $fileHash,
            'file_key' => $fileKey,
            'storage_method' => 'on_chain',
            'metadata' => $fileMetadata,
        ];
    }

    /**
     * Retrieve a chunked file from blockchain and reassemble
     */
    private function retrieveChunkedFile(string $fileKey, string $dataKey, ?array $metadataJson, ?FileMetadata $fileMetadata): array
    {
        $totalChunks = $metadataJson['total_chunks'] ?? $metadataJson['additional_metadata']['total_chunks'] ?? 0;
        $chunkTxids = $metadataJson['chunk_txids'] ?? $metadataJson['additional_metadata']['chunk_txids'] ?? [];
        $expectedHash = $metadataJson['hash'] ?? $fileMetadata?->hash ?? null;

        if ($totalChunks === 0) {
            throw new Exception('Chunked file metadata is missing chunk information');
        }

        Log::info('Retrieving chunked file from blockchain', [
            'file_key' => $fileKey,
            'total_chunks' => $totalChunks,
        ]);

        $fileContent = '';

        // Retrieve each chunk in order
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkKey = "{$dataKey}_chunk_{$i}";

            // Try to get chunk by key from file.chunks stream
            $chunkItems = $this->multichain->liststreamkeyitems(StreamEnums::FILE_CHUNKS->value, $chunkKey, false, 1);

            if (empty($chunkItems)) {
                throw new Exception("Chunk {$i} not found for file {$fileKey}");
            }

            $chunkHex = $this->extractHexFromDataItem($chunkItems[0]);

            if (! $chunkHex) {
                throw new Exception("Failed to extract chunk {$i} data for file {$fileKey}");
            }

            $chunkContent = hex2bin($chunkHex);
            if ($chunkContent === false) {
                throw new Exception("Failed to decode chunk {$i} for file {$fileKey}");
            }

            $fileContent .= $chunkContent;
        }

        $fileHash = hash('sha256', $fileContent);

        // Verify integrity
        if ($expectedHash && $expectedHash !== $fileHash) {
            Log::warning('Chunked file hash mismatch during retrieval', [
                'file_key' => $fileKey,
                'expected_hash' => $expectedHash,
                'actual_hash' => $fileHash,
            ]);
        }

        Log::info('Chunked file retrieved and reassembled', [
            'file_key' => $fileKey,
            'total_chunks' => $totalChunks,
            'final_size' => strlen($fileContent),
            'hash_verified' => $expectedHash === $fileHash,
        ]);

        return [
            'content' => $fileContent,
            'filename' => $fileMetadata?->filename ?? basename($fileKey),
            'mime_type' => $fileMetadata?->mimeType ?? 'application/octet-stream',
            'size' => strlen($fileContent),
            'hash' => $fileHash,
            'file_key' => $fileKey,
            'storage_method' => 'on_chain_chunked',
            'total_chunks' => $totalChunks,
            'metadata' => $fileMetadata,
        ];
    }

    /**
     * Extract hex data from a blockchain data item
     */
    private function extractHexFromDataItem(array $dataItem): ?string
    {
        if (is_string($dataItem['data'] ?? null)) {
            // Non-verbose mode - data is directly a hex string
            return $dataItem['data'];
        }

        if (is_array($dataItem['data'] ?? null)) {
            // Verbose mode - need to use gettxoutdata to get raw hex
            $txid = $dataItem['txid'] ?? $dataItem['data']['txid'] ?? null;
            $vout = $dataItem['vout'] ?? $dataItem['data']['vout'] ?? 0;

            if ($txid) {
                return $this->multichain->gettxoutdata($txid, $vout);
            }
        }

        return null;
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

    /**
     * Get recommended maximum file size for optimal blockchain performance
     *
     * @return int Recommended file size in bytes
     */
    public function getRecommendedMaxFileSize(): int
    {
        return $this->recommendedMaxSize;
    }

    /**
     * Get recommended maximum file size in human-readable format
     *
     * @return string Recommended file size (e.g., "2 MB")
     */
    public function getRecommendedMaxFileSizeFormatted(): string
    {
        $mb = $this->recommendedMaxSize / 1048576;

        return number_format($mb, 0).' MB';
    }

    /**
     * Check if a file exceeds the recommended size (but is still uploadable)
     *
     * @param  int  $fileSize  File size in bytes
     * @return bool True if file is larger than recommended
     */
    public function exceedsRecommendedSize(int $fileSize): bool
    {
        return $fileSize > $this->recommendedMaxSize;
    }

    /**
     * Get upload size configuration details
     *
     * @return array Configuration details for UI display
     */
    public function getUploadSizeConfig(): array
    {
        return [
            'max_size_bytes' => $this->maxChunkSize,
            'max_size_formatted' => $this->getMaxFileSizeFormatted(),
            'recommended_size_bytes' => $this->recommendedMaxSize,
            'recommended_size_formatted' => $this->getRecommendedMaxFileSizeFormatted(),
            'chunking_enabled' => config('blockchain.upload.chunking.enabled', false),
            'chunk_size' => config('blockchain.upload.chunking.chunk_size', 1048576),
        ];
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
