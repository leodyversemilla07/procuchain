<?php

namespace App\Services;

use App\Contracts\BlockchainStorageInterface;
use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Blockchain\FileRetriever;
use App\Services\Blockchain\FileUploader;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

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
class BlockchainStorageService implements BlockchainStorageInterface
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

    private FileUploader $uploader;

    private FileRetriever $retriever;

    public function __construct(
        private Manager $multichain
    ) {
        $this->maxChunkSize = config('blockchain.upload.absolute_max_file_size', 52428800);
        $this->recommendedMaxSize = config('blockchain.upload.max_file_size', 2097152);

        $this->uploader = new FileUploader(
            $multichain,
            $this->maxChunkSize,
            $this->recommendedMaxSize,
            config('blockchain.upload.chunking.chunk_threshold', 1572864),
            config('blockchain.upload.chunking.chunk_size', 1048576),
            config('blockchain.upload.chunking.enabled', true),
        );

        $this->retriever = new FileRetriever($multichain);
    }

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
        return $this->uploader->uploadFile($file, $prNumber, $stageId, $documentType, $metadata);
    }

 /**
 * Retrieve file from blockchain (handles both single and chunked storage)
 *
 * @param string $fileKey The file key
 * @param string|null $dataTxid Optional data transaction ID for direct retrieval
 * @param bool $includeDeleted If true, returns file even if marked as deleted (for recovery)
 * @return array File content and metadata
 *
 * @throws Exception If file not found
 */
 public function retrieveFile(string $fileKey, ?string $dataTxid = null, bool $includeDeleted = false): array
 {
 return $this->retriever->retrieveFile($fileKey, $dataTxid, $includeDeleted);
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
 * @param string $fileKey The file key to mark as deleted
 * @param string $reason Reason for deletion
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

 // Audit log for RA 12009 (NGPA) compliance
 app(AuditLogger::class)->log(
 action: 'file.deleted',
 subjectType: 'file',
 subjectId: $fileKey,
 oldValues: ['file_key' => $fileKey, 'action' => 'deleted', 'reason' => $reason],
 );

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
 * Restore a previously deleted file on blockchain
 * Publishes a 'restored' action marker — the on-chain data was never removed.
 *
 * @param string $fileKey The file key to restore
 * @param string $reason Reason for restoration
 * @return bool Success status
 */
 public function restoreFile(string $fileKey, string $reason = ''): bool
 {
 try {
 $dataKey = str_replace('/', '_', $fileKey);
 $deletionKey = $dataKey.'_deleted';

 // Publish restoration marker to blockchain
 $this->multichain->publish(StreamEnums::FILE_METADATA->value, $deletionKey, [
 'json' => [
 'file_key' => $fileKey,
 'data_key' => $dataKey,
 'action' => 'restored',
 'reason' => $reason,
 'restored_at' => now()->toIso8601String(),
 ],
 ]);

 Log::info('File restored on blockchain', [
 'file_key' => $fileKey,
 'reason' => $reason,
 ]);

 // Audit log for RA 12009 (NGPA) compliance
 app(AuditLogger::class)->log(
 action: 'file.restored',
 subjectType: 'file',
 subjectId: $fileKey,
 newValues: ['file_key' => $fileKey, 'action' => 'restored', 'reason' => $reason],
 );

 return true;
 } catch (Exception $e) {
 Log::error('File restoration failed', [
 'file_key' => $fileKey,
 'error' => $e->getMessage(),
 ]);

 return false;
 }
 }

 /**
 * Check if a file is currently marked as deleted on blockchain
 *
 * @param string $fileKey The file key to check
 * @return bool True if the latest action is 'deleted'
 */
 public function isFileDeleted(string $fileKey): bool
 {
 try {
 $dataKey = str_replace('/', '_', $fileKey);
 $deletionKey = $dataKey.'_deleted';

 $items = $this->multichain->liststreamkeyitems(
 StreamEnums::FILE_METADATA->value,
 $deletionKey,
 false,
 100,
 0,
 false
 );

 if (empty($items)) {
 return false;
 }

 $latestItem = collect($items)->last();
 $action = $latestItem['data']['json']['action'] ?? 'restored';

 return $action === 'deleted';
 } catch (Exception $e) {
 Log::error('File deletion status check failed', [
 'file_key' => $fileKey,
 'error' => $e->getMessage(),
 ]);

 return false;
 }
 }

 /**
 * Get all currently deleted file keys from blockchain
 * Returns an array of file keys where the latest action is 'deleted'
 *
 * @return array<string, array{file_key: string, reason: string, deleted_at: string}>
 */
 public function getDeletedFiles(): array
 {
 try {
 $items = $this->multichain->liststreamitems(
 StreamEnums::FILE_METADATA->value,
 true,
 10000,
 0,
 false
 );

 $deletedFiles = [];
 $statusMap = [];

 foreach ($items as $item) {
 $data = $item['data']['json'] ?? null;
 if (! $data) {
 continue;
 }

 $action = $data['action'] ?? null;
 $fileKey = $data['file_key'] ?? null;

 // Only track deleted/restored action markers
 if (! in_array($action, ['deleted', 'restored']) || ! $fileKey) {
 continue;
 }

 // Track latest action per file key
 $statusMap[$fileKey] = [
 'file_key' => $fileKey,
 'action' => $action,
 'reason' => $data['reason'] ?? '',
 'timestamp' => $data['deleted_at'] ?? $data['restored_at'] ?? now()->toIso8601String(),
 ];
 }

 // Filter only those where latest action is 'deleted'
 foreach ($statusMap as $fileKey => $info) {
 if ($info['action'] === 'deleted') {
 $deletedFiles[$fileKey] = [
 'file_key' => $info['file_key'],
 'reason' => $info['reason'],
 'deleted_at' => $info['timestamp'],
 ];
 }
 }

 return $deletedFiles;
 } catch (Exception $e) {
 Log::error('Failed to get deleted files', [
 'error' => $e->getMessage(),
 ]);

 return [];
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

    /**
     * Upload files and prepare metadata for procurement documents.
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
        return $this->uploader->uploadAndPrepare($files, $metadata, $prNumber, $stageId, $procurementTitle, $authUser);
    }
}
