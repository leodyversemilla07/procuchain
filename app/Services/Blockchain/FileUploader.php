<?php

namespace App\Services\Blockchain;

use App\DataTransferObjects\FileMetadata;
use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\Manager;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploader
{
    public function __construct(
        private Manager $multichain,
        private int $maxChunkSize,
        private int $recommendedMaxSize,
        private int $chunkThreshold,
        private int $chunkSize,
        private bool $chunkingEnabled,
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
    public function uploadAndPrepare(array $files, array $metadata, string $prNumber, int $stageId, string $procurementTitle, ?User $authUser = null): array
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
                    'uploaded_by' => $authUser?->id,
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
     * Generate a standardized blockchain file key with comprehensive metadata
     *
     * Pattern: {pr_number}/{phase}/{stage_id}/{document_type}_{timestamp}_{hash_short}.{ext}
     * Example: PR-2025-001/pre-procurement/stage-01/purchase_request_20251115143022_a3f5b8c.pdf
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
 * Stage 4-11: Procurement (Bidding & Evaluation) — includes BAC Resolution (11)
 * Stage 12-17: Post-Procurement (Award & Implementation) — includes Completion (16) and Completed (17)
 *
 * Aligned with StageEnums::getPhase() — BAC Resolution is procurement phase per RA 9184.
 */
private function getPhaseFromStage(int $stageId): string
{
    return match (true) {
        $stageId >= 1 && $stageId <= 3 => 'pre-procurement',
        $stageId >= 4 && $stageId <= 11 => 'procurement',
        $stageId >= 12 && $stageId <= 17 => 'post-procurement',
        default => 'unknown-phase',
    };
    }
}
