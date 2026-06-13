<?php

namespace App\Services\Blockchain;

use App\DataTransferObjects\FileMetadata;
use App\Enums\Stream;
use App\Models\User;
use App\Services\BlockchainRpcClient;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploader
{
    public function __construct(
        private BlockchainRpcClient $multichain,
        private int $maxChunkSize,
        private int $recommendedMaxSize,
        private int $chunkThreshold,
        private int $chunkSize,
        private bool $chunkingEnabled,
    ) {}

    /**
     * Upload a File directly to blockchain (on-chain storage)
     *
     * @param  UploadedFile  $File  The File to upload
     * @param  string  $prNumber  PR Number (e.g., PR-2025-001)
     * @param  int  $stageId  Stage ID (1-17, per RA 12009 NGPA)
     * @param  string  $documentType  Document type (e.g., "Purchase Request")
     * @param  array  $metadata  Additional metadata to store on blockchain
     * @return array File storage information including file_key, data_txid and metadata_txid
     *
     * @throws Exception If storage fails
     */
    public function uploadFile(UploadedFile $File, string $prNumber, int $stageId, string $documentType, array $metadata = []): array
    {
        $filename = $File->getClientOriginalName();
        $extension = $File->getClientOriginalExtension();
        $fileSize = $File->getSize();
        $mimeType = $File->getMimeType();

        // Read File content using Laravel's UploadedFile::get() method
        // This is more reliable than file_get_contents(getRealPath()) for uploaded BlockchainFiles
        $BlockchainFileContent = $File->get();
        $BlockchainFileHash = hash('sha256', $BlockchainFileContent);

        // Validate that we actually read the File content
        // Empty BlockchainFiles should not be uploaded to blockchain
        if (empty($BlockchainFileContent)) {
            Log::error('Empty File content detected during upload - rejecting upload', [
                'filename' => $File->getClientOriginalName(),
                'size' => $fileSize,
                'mime_type' => $File->getMimeType(),
                'pr_number' => $prNumber,
                'document_type' => $documentType,
            ]);

            throw new Exception("Failed to read File content. File appears to be empty or inaccessible. Reported size: {$fileSize} bytes");
        }

        if (strlen($BlockchainFileContent) !== $fileSize) {
            Log::warning('File content size mismatch', [
                'filename' => $filename,
                'reported_size' => $fileSize,
                'actual_content_size' => strlen($BlockchainFileContent),
            ]);
        }

        // Check File size against absolute maximum
        if ($fileSize > $this->maxChunkSize) {
            throw new Exception("File size ({$fileSize} bytes) exceeds maximum on-chain storage limit ({$this->maxChunkSize} bytes or ".($this->maxChunkSize / 1048576).' MB)');
        }

        // Generate standardized File key with phase
        $fileKey = $this->generatefileKey($prNumber, $stageId, $documentType, $extension, $BlockchainFileHash);
        $dataKey = str_replace('/', '_', $fileKey);

        // Determine if chunking is needed
        $needsChunking = $this->chunkingEnabled && $fileSize > $this->chunkThreshold;

        if ($needsChunking) {
            return $this->uploadBlockchainFileChunked($BlockchainFileContent, $filename, $fileKey, $dataKey, $fileSize, $mimeType, $BlockchainFileHash, $prNumber, $stageId, $documentType, $metadata);
        }

        return $this->uploadBlockchainFileSingleTransaction($BlockchainFileContent, $filename, $fileKey, $dataKey, $fileSize, $mimeType, $BlockchainFileHash, $prNumber, $stageId, $documentType, $metadata);
    }

    /**
     * Upload BlockchainFiles and prepare metadata for procurement documents with blockchain transaction IDs.
     *
     * @param  UploadedFile[]  $BlockchainFiles
     * @param  array  $metadata  Metadata for each File
     * @param  string  $prNumber  PR Number
     * @param  int  $stageId  Stage ID (1-17, per RA 12009 NGPA)
     * @param  string  $procurementTitle  Procurement title
     * @return array Complete metadata array with blockchain transaction IDs
     */
    public function uploadAndPrepare(array $BlockchainFiles, array $metadata, string $prNumber, int $stageId, string $procurementTitle, ?User $authUser = null): array
    {
        $results = [];

        foreach ($BlockchainFiles as $index => $File) {
            $meta = $metadata[$index] ?? [];
            $documentType = $meta['document_type'] ?? 'General Document';

            // Upload with standardized phase-based naming
            $result = $this->uploadFile(
                $File,
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
     * Upload a small File in a single blockchain transaction
     */
    private function uploadBlockchainFileSingleTransaction(
        string $BlockchainFileContent,
        string $filename,
        string $fileKey,
        string $dataKey,
        int $fileSize,
        string $mimeType,
        string $BlockchainFileHash,
        string $prNumber,
        int $stageId,
        string $documentType,
        array $metadata
    ): array {
        $BlockchainFileHex = bin2hex($BlockchainFileContent);

        Log::info('Storing File on blockchain (single transaction)', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'hash' => $BlockchainFileHash,
            'hex_length' => strlen($BlockchainFileHex),
        ]);

        $startTime = microtime(true);

        // Create FileMetadata DTO
        $FileMetadata = new FileMetadata(
            filename: $filename,
            fileKey: $fileKey,
            dataTxid: '',
            dataKey: $dataKey,
            mimeType: $mimeType,
            size: $fileSize,
            hash: $BlockchainFileHash,
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
                'data' => $BlockchainFileHex,
                'for' => Stream::FILE_DATA->value,
            ],
            [
                'key' => $dataKey,
                'data' => ['json' => array_merge(
                    $FileMetadata->toBlockchainArray(),
                    ['data_txid' => 'BATCH_TXID']
                )],
                'for' => Stream::FILE_METADATA->value,
            ],
        ];

        // Publish both data and metadata atomically
        $txid = $this->multichain->publishmulti(Stream::FILE_DATA->value, $items);

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
            'hash' => $BlockchainFileHash,
            'storage_method' => 'on_chain',
            'chunked' => false,
        ];
    }

    /**
     * Upload a large File using chunked storage across multiple transactions
     */
    private function uploadBlockchainFileChunked(
        string $BlockchainFileContent,
        string $filename,
        string $fileKey,
        string $dataKey,
        int $fileSize,
        string $mimeType,
        string $BlockchainFileHash,
        string $prNumber,
        int $stageId,
        string $documentType,
        array $metadata
    ): array {
        $chunks = str_split($BlockchainFileContent, $this->chunkSize);
        $totalChunks = count($chunks);

        Log::info('Storing large File on blockchain (chunked)', [
            'filename' => $filename,
            'file_key' => $fileKey,
            'size' => $fileSize,
            'hash' => $BlockchainFileHash,
            'total_chunks' => $totalChunks,
            'chunk_size' => $this->chunkSize,
        ]);

        $startTime = microtime(true);
        $chunkTxids = [];

        // Upload each chunk to the File.chunks stream
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
                    'for' => Stream::FILE_CHUNKS->value,
                ],
                [
                    'key' => $chunkKey.'_meta',
                    'data' => $chunkData,
                    'for' => Stream::FILE_CHUNKS->value,
                ],
            ];

            $chunkTxid = $this->multichain->publishmulti(Stream::FILE_CHUNKS->value, $items);
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
        $FileMetadata = new FileMetadata(
            filename: $filename,
            fileKey: $fileKey,
            dataTxid: $chunkTxids[0]['txid'], // Reference first chunk as primary
            dataKey: $dataKey,
            mimeType: $mimeType,
            size: $fileSize,
            hash: $BlockchainFileHash,
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

        // Publish File metadata to File.metadata stream
        $metadataTxid = $this->multichain->publish(
            Stream::FILE_METADATA->value,
            $dataKey,
            ['json' => $FileMetadata->toBlockchainArray()]
        );

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Large File stored successfully on blockchain (chunked)', [
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
            'hash' => $BlockchainFileHash,
            'storage_method' => 'on_chain_chunked',
            'chunked' => true,
            'total_chunks' => $totalChunks,
            'chunk_txids' => array_column($chunkTxids, 'txid'),
        ];
    }

    /**
     * Generate a standardized blockchain File key with comprehensive metadata
     *
     * Pattern: {pr_number}/{phase}/{stage_id}/{document_type}_{timestamp}_{hash_short}.{ext}
     * Example: PR-2025-001/pre-procurement/stage-01/purchase_request_20251115143022_a3f5b8c.pdf
     */
    private function generatefileKey(
        string $prNumber,
        int $stageId,
        string $documentType,
        string $extension,
        string $BlockchainFileHash
    ): string {
        // Sanitize document type (e.g., "Purchase Request" -> "purchase_request")
        // Remove any potentially dangerous characters
        $sanitizedType = Str::slug($documentType, '_');

        // Validate sanitized type contains only safe characters
        if (! preg_match('/^[a-z0-9_]+$/', $sanitizedType)) {
            throw new \InvalidArgumentException(
                "Invalid document type after sanitization: {$sanitizedType}"
            );
        }

        // Get short hash (first 7 chars) for uniqueness verification
        $hashShort = substr($BlockchainFileHash, 0, 7);

        // Generate timestamp (YmdHis format for sortability)
        $timestamp = now()->format('YmdHis');

        // Format stage with leading zeros (stage-01, stage-02, etc.)
        $stageFormatted = sprintf('stage-%02d', $stageId);

        // Determine phase based on stage ID
        $phase = $this->getPhaseFromStage($stageId);

        // Build hierarchical File key with phase
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
     * Get phase name based on stage ID.
     *
     * @see StageEnums::getPhase()
     */
    private function getPhaseFromStage(int $stageId): string
    {
        return match (true) {
            $stageId >= 1 && $stageId <= 4 => 'pre-procurement',
            $stageId >= 5 && $stageId <= 11 => 'procurement',
            $stageId >= 12 && $stageId <= 17 => 'post-procurement',
            default => 'unknown-phase',
        };
    }
}
