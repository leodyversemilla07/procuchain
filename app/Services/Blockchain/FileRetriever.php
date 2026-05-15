<?php

namespace App\Services\Blockchain;

use App\DataTransferObjects\FileMetadata;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Exception;
use Illuminate\Support\Facades\Log;

class FileRetriever
{
    public function __construct(
        private Manager $multichain,
    ) {}

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
 $dataKey = str_replace('/', '_', $fileKey);

 // Check if file is marked as deleted (unless explicitly including deleted files)
 if (! $includeDeleted && $this->isFileDeleted($dataKey)) {
 throw new Exception('File has been deleted and is not available. Contact an administrator to restore it.');
 }

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
 * Check if a file is currently marked as deleted on blockchain
 *
 * @param string $dataKey The data key (underscore-converted file key)
 * @return bool True if the latest action is 'deleted'
 */
 private function isFileDeleted(string $dataKey): bool
 {
 try {
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
 // If we can't check deletion status, assume not deleted
 Log::warning('Could not check file deletion status', [
 'data_key' => $dataKey,
 'error' => $e->getMessage(),
 ]);

 return false;
 }
 }
}
