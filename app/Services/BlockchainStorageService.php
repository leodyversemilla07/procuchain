<?php

namespace App\Services;

use App\Contracts\BlockchainStorageInterface;
use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
use App\Models\User;
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
     * @param  string  $fileKey  The file key
     * @param  string|null  $dataTxid  Optional data transaction ID for direct retrieval
     * @param  bool  $includeDeleted  If true, returns file even if marked as deleted (for recovery)
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
     * @param  string  $fileKey  The file key to restore
     * @param  string  $reason  Reason for restoration
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
     * @param  string  $fileKey  The file key to check
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
                    // Extract PR number from file key (format: PR-YYYY-NNN/phase/stage/...)
                    $prNumber = explode('/', $info['file_key'])[0];

                    $deletedFiles[$fileKey] = [
                        'file_key' => $info['file_key'],
                        'pr_number' => $prNumber,
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
     * Delete a file's data from a single node's local storage.
     * The data remains on other nodes and will be re-synced automatically.
     * The deletion event is recorded on-chain for audit compliance (RA 12009).
     *
     * @param  string  $fileKey  The file key to delete from the node
     * @param  string  $nodeId  The target node ID (e.g. 'admin', 'bac-secretariat')
     * @param  string  $reason  Reason for single-node deletion
     * @return array{success: bool, message: string}
     */
    public function deleteFromNode(string $fileKey, string $nodeId, string $reason = ''): array
    {
        try {
            $nodes = config('multichain.nodes', []);
            $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

            if (! $targetNode) {
                return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry"];
            }

            $dataKey = str_replace('/', '_', $fileKey);

            // Connect to the specific node and remove its local stream data
            $nodeClient = new Client(
                $targetNode['private_ip'],
                $targetNode['rpc_port'],
                config('multichain.rpc.username', 'multichainrpc'),
                config('multichain.rpc.password'),
                false
            );
            $nodeClient->setoption('chain_name', config('multichain.chain_name'));

            // Remove stream items from this node only (local purge)
            // MultiChain: liststreamkeyitems to get txids, then purge each
            $items = $nodeClient->liststreamkeyitems(
                StreamEnums::FILE_METADATA->value,
                $dataKey,
                false,
                100,
                0,
                false
            );

 $purgedCount = 0;

 // Purge retrieved items matching this key from FILE_METADATA stream
 $nodeClient->__call('purgestreamitems', [
 StreamEnums::FILE_METADATA->value,
 ['key' => $dataKey],
 ]);

 // Also purge file data chunks from this node
 $nodeClient->__call('purgestreamitems', [
 StreamEnums::FILE_DATA->value,
 ['key' => $dataKey],
 ]);

 // Count items for reporting
 $items = $nodeClient->liststreamkeyitems(
 StreamEnums::FILE_METADATA->value,
 $dataKey,
 false,
 100,
 0,
 false
 );
 $purgedCount = count($items);

 $chunkItems = $nodeClient->liststreamkeyitems(
 StreamEnums::FILE_DATA->value,
 $dataKey,
 false,
 1000,
 0,
 false
 );
 $purgedCount += count($chunkItems);

 // Record the single-node deletion on-chain (from the primary node)
            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $dataKey.'_node_purge', [
                'json' => [
                    'file_key' => $fileKey,
                    'data_key' => $dataKey,
                    'action' => 'node_purge',
                    'node_id' => $nodeId,
                    'node_name' => $targetNode['name'] ?? $nodeId,
                    'items_purged' => $purgedCount,
                    'reason' => $reason,
                    'purged_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info("File data purged from node {$nodeId}", [
                'file_key' => $fileKey,
                'node_id' => $nodeId,
                'items_purged' => $purgedCount,
            ]);

            // Audit log for RA 12009 (NGPA) compliance
            app(AuditLogger::class)->log(
                action: 'file.node_purge',
                subjectType: 'file',
                subjectId: $fileKey,
                oldValues: [
                    'file_key' => $fileKey,
                    'action' => 'node_purge',
                    'node_id' => $nodeId,
                    'items_purged' => $purgedCount,
                    'reason' => $reason,
                ],
            );

            return [
                'success' => true,
                'message' => "Purged {$purgedCount} items from {$targetNode['name']} ({$nodeId}). Data survives on remaining nodes and will resync automatically.",
            ];
        } catch (Exception $e) {
            Log::error('Single-node file purge failed', [
                'file_key' => $fileKey,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed: '.$e->getMessage()];
        }
    }

    /**
     * Resync a node's stream data from peers.
     * After a single-node purge, this triggers the node to re-download
     * the missing stream items from its connected peers.
     *
     * @param  string  $nodeId  The node to resync
     * @return array{success: bool, message: string}
     */
    public function resyncNode(string $nodeId): array
    {
        try {
            $nodes = config('multichain.nodes', []);
            $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

            if (! $targetNode) {
                return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry"];
            }

            $nodeClient = new Client(
                $targetNode['private_ip'],
                $targetNode['rpc_port'],
                config('multichain.rpc.username', 'multichainrpc'),
                config('multichain.rpc.password'),
                false
            );
            $nodeClient->setoption('chain_name', config('multichain.chain_name'));

 // Resync using MultiChain Enterprise retrievestreamitems API
 // This queues off-chain items for retrieval from the network,
 // including items that were previously purged.
 $streams = StreamEnums::cases();

 $resyncedStreams = 0;
 $totalRetrieved = 0;
 foreach ($streams as $streamEnum) {
 try {
 $result = $nodeClient->__call('retrievestreamitems', [
 $streamEnum->value,
 'all',
 ]);

 $itemsMatched = $result['items'] ?? 0;
 $chunksMatched = $result['chunks'] ?? 0;
 $totalRetrieved += $itemsMatched;

 if ($itemsMatched > 0) {
 $resyncedStreams++;
 Log::info("Retrieved {$itemsMatched} items ({$chunksMatched} chunks) from {$streamEnum->value} on node {$nodeId}");
 }
 } catch (Exception $streamEx) {
 Log::warning("Could not retrieve stream {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
 }
 }

            // Record resync event on-chain
            $dataKey = 'node_'.$nodeId.'_resync';
            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $dataKey, [
                'json' => [
 'action' => 'node_resync',
 'node_id' => $nodeId,
 'node_name' => $targetNode['name'] ?? $nodeId,
 'streams_resynced' => $resyncedStreams,
 'items_retrieved' => $totalRetrieved,
 'resynced_at' => now()->toIso8601String(),
                ],
            ]);

 Log::info("Node {$nodeId} resync completed", [
 'node_id' => $nodeId,
 'streams_resynced' => $resyncedStreams,
 'items_retrieved' => $totalRetrieved,
 ]);

 // Audit log for RA 12009 (NGPA) compliance
 app(AuditLogger::class)->log(
 action: 'node.resync',
 subjectType: 'node',
 subjectId: $nodeId,
 newValues: [
 'action' => 'node_resync',
 'node_id' => $nodeId,
 'streams_resynced' => $resyncedStreams,
 'items_retrieved' => $totalRetrieved,
 ],
 );

 return [
 'success' => true,
 'message' => "Resynced {$targetNode['name']} ({$nodeId}) — {$totalRetrieved} items retrieved across {$resyncedStreams} streams from peers.",
            ];
        } catch (Exception $e) {
            Log::error('Node resync failed', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed: '.$e->getMessage()];
        }
    }

 /**
 * Purge ALL stream data from a single node's local storage.
 *
 * Unlike deleteFromNode() which targets a specific file key,
 * this iterates every stream and purges all items — simulating
 * a catastrophic node data loss for the demo.
 *
 * The data survives on the remaining 3+ nodes and can be
 * fully restored by resyncNode(). The purge is recorded on-chain
 * as action: 'full_node_purge' for RA 12009 audit compliance.
 *
 * @param string $nodeId The target node ID (e.g. 'admin', 'bac-secretariat')
 * @param string $reason Reason for full-node purge
 * @return array{success: bool, message: string, items_purged: int}
 */
 public function purgeAllFromNode(string $nodeId, string $reason = ''): array
 {
 try {
 $nodes = config('multichain.nodes', []);
 $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

 if (! $targetNode) {
 return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry", 'items_purged' => 0];
 }

 $nodeClient = new Client(
 $targetNode['private_ip'],
 $targetNode['rpc_port'],
 config('multichain.rpc.username', 'multichainrpc'),
 config('multichain.rpc.password'),
 false
 );
 $nodeClient->setoption('chain_name', config('multichain.chain_name'));

 // Use MultiChain Enterprise purge commands:
 // - purgestreamitems: purges items RETRIEVED from the network (downloaded from peers)
 //   Takes stream name + items filter (e.g. "all")
 // - purgepublisheditems: purges items PUBLISHED by this node (created here)
 //   Takes only items filter (no stream param — operates across all streams)
 $streams = StreamEnums::cases();
 $totalItems = 0;
 $totalChunks = 0;
 $streamStats = [];

 foreach ($streams as $streamEnum) {
 try {
 // Purge all items this node retrieved from peers in this stream
 $retrievedResult = $nodeClient->__call('purgestreamitems', [
 $streamEnum->value,
 'all',
 ]);

 $itemsMatched = $retrievedResult['items'] ?? 0;
 $chunksPurged = $retrievedResult['chunks'] ?? 0;

 $totalItems += $itemsMatched;
 $totalChunks += $chunksPurged;
 if ($itemsMatched > 0 || $chunksPurged > 0) {
 $streamStats[$streamEnum->value] = [
 'items_matched' => $itemsMatched,
 'chunks_purged' => $chunksPurged,
 ];
 }
 } catch (Exception $streamEx) {
 Log::warning("Could not purge stream {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
 }
 }

 // Purge all items this node originally published (across ALL streams)
 try {
 $publishedResult = $nodeClient->__call('purgepublisheditems', ['all']);
 $pubItems = $publishedResult['items'] ?? 0;
 $pubChunks = $publishedResult['chunks'] ?? 0;
 $totalItems += $pubItems;
 $totalChunks += $pubChunks;
 if ($pubItems > 0 || $pubChunks > 0) {
 $streamStats['_published'] = [
 'items_matched' => $pubItems,
 'chunks_purged' => $pubChunks,
 ];
 }
 } catch (Exception $pubEx) {
 Log::warning("Could not purge published items on node {$nodeId}: ".$pubEx->getMessage());
 }

 // Record the full-node purge event on-chain (from the primary node)
 $this->multichain->publish(StreamEnums::FILE_METADATA->value, 'node_'.$nodeId.'_full_purge', [
 'json' => [
 'action' => 'full_node_purge',
 'node_id' => $nodeId,
 'node_name' => $targetNode['name'] ?? $nodeId,
 'items_matched' => $totalItems,
 'chunks_purged' => $totalChunks,
 'streams_affected' => $streamStats,
 'reason' => $reason ?: 'Demo: full node purge — all data removed from single node',
 'purged_at' => now()->toIso8601String(),
 ],
 ]);

 Log::info('Full node purge completed', [
 'node_id' => $nodeId,
 'items_matched' => $totalItems,
 'chunks_purged' => $totalChunks,
 'streams_affected' => count($streamStats),
 ]);

 // Audit log for RA 12009 (NGPA) compliance
 app(AuditLogger::class)->log(
 action: 'node.full_purge',
 subjectType: 'node',
 subjectId: $nodeId,
 oldValues: [
 'action' => 'full_node_purge',
 'node_id' => $nodeId,
 'items_matched' => $totalItems,
 'chunks_purged' => $totalChunks,
 'streams_affected' => $streamStats,
 'reason' => $reason,
 ],
 );

 return [
 'success' => true,
 'message' => "Purged all data ({$totalItems} items, {$totalChunks} chunks across ".count($streamStats)." streams) from {$targetNode['name']} ({$nodeId}). Data survives on remaining nodes — resync to restore.",
 'items_purged' => $totalItems,
 ];
 } catch (Exception $e) {
 Log::error('Full node purge failed', [
 'node_id' => $nodeId,
 'error' => $e->getMessage(),
 ]);

 return ['success' => false, 'message' => 'Failed: '.$e->getMessage(), 'items_purged' => 0];
 }
 }

 /**
 * Get list of available nodes for the purge/resync UI
 *
 * @return array<int, array{id: string, name: string, role: string}>
 */
 public function getAvailableNodes(): array
 {
        return collect(config('multichain.nodes', []))
            ->map(fn ($node) => [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
            ])
            ->values()
            ->all();
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
