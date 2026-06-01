<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainStorageInterface;
use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\Blockchain\FileLifecycleManager;
use App\Services\Blockchain\FileRetriever;
use App\Services\Blockchain\FileUploader;
use App\Services\Blockchain\NodeOperationsService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Storage Service — thin facade for MultiChain file operations.
 *
 * Delegates to four focused services:
 * - FileUploader: upload + chunked encoding
 * - FileRetriever: retrieval + chunk reassembly
 * - FileLifecycleManager: delete, restore, status checks
 * - NodeOperationsService: per-node purge/resync/health
 *
 * @see config/blockchain.php for upload limits and chunking configuration
 */
class BlockchainStorageService implements BlockchainStorageInterface
{
    private int $maxChunkSize;

    private int $recommendedMaxSize;

    private FileUploader $uploader;

    private FileRetriever $retriever;

    private FileLifecycleManager $lifecycle;

    private NodeOperationsService $nodeOps;

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
        $this->lifecycle = new FileLifecycleManager($multichain);
        $this->nodeOps = new NodeOperationsService($multichain);
    }

    // ── Upload ──────────────────────────────────────────────

    public function uploadFile(UploadedFile $file, string $prNumber, int $stageId, string $documentType, array $metadata = []): array
    {
        return $this->uploader->uploadFile($file, $prNumber, $stageId, $documentType, $metadata);
    }

    public function uploadAndPrepare(array $files, array $metadata, string $prNumber, int $stageId, string $procurementTitle, ?User $authUser = null): array
    {
        return $this->uploader->uploadAndPrepare($files, $metadata, $prNumber, $stageId, $procurementTitle, $authUser);
    }

    // ── Retrieval ───────────────────────────────────────────

    public function retrieveFile(string $fileKey, ?string $dataTxid = null, bool $includeDeleted = false): array
    {
        return $this->retriever->retrieveFile($fileKey, $dataTxid, $includeDeleted);
    }

    public function verifyFileIntegrity(string $fileKey, string $metadataTxid): bool
    {
        try {
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

            $fileData = $this->retrieveFile($fileKey, $dataTxid);

            return $fileData['hash'] === $blockchainHash;
        } catch (Exception $e) {
            Log::error('File integrity verification failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getFileMetadata(string $metadataTxid): array
    {
        $metadataItem = $this->multichain->getstreamitem(StreamEnums::FILE_METADATA->value, $metadataTxid, true);

        return $metadataItem['data']['json'] ?? [];
    }

    // ── Lifecycle (delete/restore/status) ───────────────────

    public function deleteFile(string $fileKey, string $reason = ''): bool
    {
        return $this->lifecycle->deleteFile($fileKey, $reason);
    }

    public function restoreFile(string $fileKey, string $reason = ''): bool
    {
        return $this->lifecycle->restoreFile($fileKey, $reason);
    }

    public function isFileDeleted(string $fileKey): bool
    {
        return $this->lifecycle->isFileDeleted($fileKey);
    }

    public function getDeletedFiles(): array
    {
        return $this->lifecycle->getDeletedFiles();
    }

    // ── Node Operations (purge/resync/health) ───────────────

    public function deleteFromNode(string $fileKey, string $nodeId, string $reason = ''): array
    {
        return $this->nodeOps->deleteFromNode($fileKey, $nodeId, $reason);
    }

    public function resyncNode(string $nodeId, string $reason = ''): array
    {
        return $this->nodeOps->resyncNode($nodeId, $reason);
    }

    public function purgeAllFromNode(string $nodeId, string $reason = ''): array
    {
        return $this->nodeOps->purgeAllFromNode($nodeId, $reason);
    }

    public function getAvailableNodes(): array
    {
        return $this->nodeOps->getAvailableNodes();
    }

    // ── Config helpers ──────────────────────────────────────

    public function getMaxFileSize(): int
    {
        return $this->maxChunkSize;
    }

    public function getMaxFileSizeFormatted(): string
    {
        return number_format($this->maxChunkSize / 1048576, 0).' MB';
    }

    public function getRecommendedMaxFileSize(): int
    {
        return $this->recommendedMaxSize;
    }

    public function getRecommendedMaxFileSizeFormatted(): string
    {
        return number_format($this->recommendedMaxSize / 1048576, 0).' MB';
    }

    public function exceedsRecommendedSize(int $fileSize): bool
    {
        return $fileSize > $this->recommendedMaxSize;
    }

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
}
