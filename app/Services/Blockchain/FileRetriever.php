<?php

namespace App\Services\Blockchain;

use App\DataTransferObjects\FileMetadata;
use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Exception;
use Illuminate\Support\Facades\Log;

class FileRetriever
{
    public function __construct(
        private BlockchainRpcClient $multichain,
    ) {}

    /**
     * Retrieve File from blockchain (handles both single and chunked storage)
     *
     * @param  string  $fileKey  The File key
     * @param  string|null  $dataTxid  Optional data transaction ID for direct retrieval
     * @param  bool  $includeDeleted  If true, returns File even if marked as deleted (for recovery)
     * @return array File content and metadata
     *
     * @throws Exception If File not found
     */
    public function retrieveFile(string $fileKey, ?string $dataTxid = null, bool $includeDeleted = false): array
    {
        $dataKey = str_replace('/', '_', $fileKey);

        // Check if File is marked as deleted (unless explicitly including deleted BlockchainFiles)
        if (! $includeDeleted && $this->isBlockchainFileDeleted($dataKey)) {
            throw new Exception('File has been deleted and is not available. Contact an administrator to restore it.');
        }

        // Retrieve File metadata first to check storage method
        $metadataItems = $this->multichain->liststreamkeyitems(Stream::FILE_METADATA->value, $dataKey, false, 1);
        $fileMetadata = null;
        $metadataJson = null;

        if (! empty($metadataItems)) {
            $metadataJson = $metadataItems[0]['data']['json'] ?? null;
            if ($metadataJson) {
                $fileMetadata = FileMetadata::fromBlockchainArray($metadataJson);
            }
        }

        // Check if this is a chunked File
        $isChunked = $metadataJson['chunked'] ?? false;
        $storageMethod = $metadataJson['storage_method'] ?? $fileMetadata?->storageMethod ?? 'on_chain';

        if ($isChunked || $storageMethod === 'on_chain_chunked') {
            return $this->retrieveChunkedFile($fileKey, $dataKey, $metadataJson, $fileMetadata);
        }

        return $this->retrieveSingleFile($fileKey, $dataKey, $dataTxid, $fileMetadata);
    }

    /**
     * Retrieve a single-transaction File from blockchain
     */
    private function retrieveSingleFile(string $fileKey, string $dataKey, ?string $dataTxid, ?FileMetadata $fileMetadata): array
    {
        // If dataTxid provided, retrieve directly
        if ($dataTxid) {
            $dataItem = $this->multichain->getstreamitem(Stream::FILE_DATA->value, $dataTxid, true);
        } elseif ($fileMetadata) {
            // Use data_txid from metadata
            $dataItem = $this->multichain->getstreamitem(Stream::FILE_DATA->value, $fileMetadata->dataTxid, true);
        } else {
            // Otherwise, find by key
            $items = $this->multichain->liststreamkeyitems(Stream::FILE_DATA->value, $dataKey, false, 1);
            if (empty($items)) {
                throw new Exception('File not found on blockchain');
            }
            $dataItem = $items[0];
        }

        // Get hex data from blockchain
        $blockchainFileHex = $this->extractHexFromDataItem($dataItem);

        if (! $blockchainFileHex) {
            throw new Exception('File data not found in blockchain item');
        }

        // Convert hex back to binary
        $blockchainFileContent = hex2bin($blockchainFileHex);
        if ($blockchainFileContent === false) {
            throw new Exception('Failed to decode File data from blockchain');
        }

        $blockchainFileHash = hash('sha256', $blockchainFileContent);

        // Verify integrity if metadata available
        if ($fileMetadata && $fileMetadata->hash !== $blockchainFileHash) {
            Log::warning('File hash mismatch during retrieval', [
                'file_key' => $fileKey,
                'expected_hash' => $fileMetadata->hash,
                'actual_hash' => $blockchainFileHash,
            ]);
        }

        return [
            'content' => $blockchainFileContent,
            'filename' => $fileMetadata?->filename ?? basename($fileKey),
            'mime_type' => $fileMetadata?->mimeType ?? 'application/octet-stream',
            'size' => strlen($blockchainFileContent),
            'hash' => $blockchainFileHash,
            'file_key' => $fileKey,
            'storage_method' => 'on_chain',
            'metadata' => $fileMetadata,
        ];
    }

    /**
     * Retrieve a chunked File from blockchain and reassemble
     */
    private function retrieveChunkedFile(string $fileKey, string $dataKey, ?array $metadataJson, ?FileMetadata $fileMetadata): array
    {
        $totalChunks = $metadataJson['total_chunks'] ?? $metadataJson['additional_metadata']['total_chunks'] ?? 0;
        $chunkTxids = $metadataJson['chunk_txids'] ?? $metadataJson['additional_metadata']['chunk_txids'] ?? [];
        $expectedHash = $metadataJson['hash'] ?? $fileMetadata?->hash ?? null;

        if ($totalChunks === 0) {
            throw new Exception('Chunked File metadata is missing chunk information');
        }

        Log::info('Retrieving chunked File from blockchain', [
            'file_key' => $fileKey,
            'total_chunks' => $totalChunks,
        ]);

        $blockchainFileContent = '';

        // Retrieve each chunk in order
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkKey = "{$dataKey}_chunk_{$i}";

            // Try to get chunk by key from File.chunks stream
            $chunkItems = $this->multichain->liststreamkeyitems(Stream::FILE_CHUNKS->value, $chunkKey, false, 1);

            if (empty($chunkItems)) {
                throw new Exception("Chunk {$i} not found for File {$fileKey}");
            }

            $chunkHex = $this->extractHexFromDataItem($chunkItems[0]);

            if (! $chunkHex) {
                throw new Exception("Failed to extract chunk {$i} data for File {$fileKey}");
            }

            $chunkContent = hex2bin($chunkHex);
            if ($chunkContent === false) {
                throw new Exception("Failed to decode chunk {$i} for File {$fileKey}");
            }

            $blockchainFileContent .= $chunkContent;
        }

        $blockchainFileHash = hash('sha256', $blockchainFileContent);

        // Verify integrity
        if ($expectedHash && $expectedHash !== $blockchainFileHash) {
            Log::warning('Chunked File hash mismatch during retrieval', [
                'file_key' => $fileKey,
                'expected_hash' => $expectedHash,
                'actual_hash' => $blockchainFileHash,
            ]);
        }

        Log::info('Chunked File retrieved and reassembled', [
            'file_key' => $fileKey,
            'total_chunks' => $totalChunks,
            'final_size' => strlen($blockchainFileContent),
            'hash_verified' => $expectedHash === $blockchainFileHash,
        ]);

        return [
            'content' => $blockchainFileContent,
            'filename' => $fileMetadata?->filename ?? basename($fileKey),
            'mime_type' => $fileMetadata?->mimeType ?? 'application/octet-stream',
            'size' => strlen($blockchainFileContent),
            'hash' => $blockchainFileHash,
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
     * Check if a File is currently marked as deleted on blockchain
     *
     * @param  string  $dataKey  The data key (underscore-converted File key)
     * @return bool True if the latest action is 'deleted'
     */
    private function isBlockchainFileDeleted(string $dataKey): bool
    {
        try {
            $deletionKey = $dataKey.'_deleted';

            $items = $this->multichain->liststreamkeyitems(
                Stream::FILE_METADATA->value,
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
            // If we can't check deletion status, assume not deleted
            Log::warning('Could not check File deletion status', [
                'data_key' => $dataKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
