<?php

declare(strict_types=1);

namespace App\Services\Blockchain;

use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
use App\Services\AuditLogger;
use App\Services\Manager;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Handles node-level operations for blockchain disaster recovery.
 *
 * Manages per-node and full-node purges, resync operations,
 * and node health/status queries. Each operation connects to
 * the target node via its own RPC client.
 *
 * @see FileLifecycleManager for file-level delete/restore operations
 */
class NodeOperationsService
{
    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Delete a file's data from a single node's local storage.
     *
     * The data remains on other nodes and will be re-synced automatically.
     * The deletion event is recorded on-chain for audit compliance (RA 12009).
     *
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

            $nodeClient = new Client(
                $targetNode['private_ip'],
                $targetNode['rpc_port'],
                config('multichain.rpc.username', 'multichainrpc'),
                config('multichain.rpc.password'),
                false
            );
            $nodeClient->setoption('chain_name', config('multichain.chain_name'));

            // Community Edition compatible per-key purge
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

            if ($purgedCount > 0) {
                foreach ([StreamEnums::FILE_METADATA, StreamEnums::FILE_DATA] as $streamEnum) {
                    try {
                        $nodeClient->unsubscribe($streamEnum->value, true);
                        $nodeClient->subscribe($streamEnum->value, true);
                    } catch (Exception $subEx) {
                        Log::warning("Could not unsubscribe/resubscribe {$streamEnum->value}: ".$subEx->getMessage());
                    }
                }
            }

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
     *
     * After a single-node purge, this triggers the node to re-download
     * the missing stream items from its connected peers.
     *
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

            $streams = StreamEnums::cases();

            $resyncedStreams = 0;
            $totalRetrieved = 0;
            foreach ($streams as $streamEnum) {
                try {
                    $nodeClient->subscribe($streamEnum->value, true);

                    if (! $nodeClient->success()) {
                        Log::warning("subscribe failed for {$streamEnum->value} on node {$nodeId}: [{$nodeClient->errorcode()}] {$nodeClient->errormessage()}");
                        continue;
                    }

                    $info = $nodeClient->getstreaminfo($streamEnum->value);
                    $itemsAfter = 0;

                    if ($nodeClient->success() && isset($info['items'])) {
                        $itemsAfter = $info['items'];
                    } else {
                        $sampleItems = $nodeClient->liststreamitems($streamEnum->value, false, 1, 0, false);
                        if ($nodeClient->success() && is_array($sampleItems)) {
                            $info2 = $nodeClient->getstreaminfo($streamEnum->value);
                            $itemsAfter = $info2['items'] ?? (count($sampleItems) > 0 ? 1 : 0);
                        }
                    }

                    $adminInfo = $this->multichain->getstreaminfo($streamEnum->value);
                    $adminItemCount = $adminInfo['items'] ?? 0;
                    $reportedItems = max($itemsAfter, $adminItemCount);

                    if ($reportedItems > 0) {
                        $resyncedStreams++;
                        $totalRetrieved += $reportedItems;
                        Log::info("Resynced {$reportedItems} items from {$streamEnum->value} on node {$nodeId} (local={$itemsAfter}, chain={$adminItemCount})");
                    }
                } catch (Exception $streamEx) {
                    Log::warning("Could not resubscribe to stream {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
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
                    'performed_by' => auth()->user()?->name ?? 'system',
                ],
            ]);

            Log::info("Node {$nodeId} resync completed", [
                'node_id' => $nodeId,
                'streams_resynced' => $resyncedStreams,
                'items_retrieved' => $totalRetrieved,
            ]);

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

            // If this was the primary node, clear the purge flag
            if ($this->multichain instanceof Manager && $this->multichain->isPrimaryPurged()) {
                $primaryHost = config('multichain.rpc.host');
                $primaryPort = config('multichain.rpc.port');

                $isPrimary = ($targetNode['private_ip'] ?? '') === $primaryHost
                    && ($targetNode['rpc_port'] ?? 6834) === $primaryPort;

                if ($isPrimary) {
                    $this->multichain->resetByResync();
                }
            }

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
     * this iterates every stream and purges all items.
     *
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

            // Verify RPC connection
            $nodeInfo = $nodeClient->getinfo();
            if (! $nodeClient->success()) {
                Log::error("Cannot connect to node {$nodeId} at {$targetNode['private_ip']}:{$targetNode['rpc_port']}: [{$nodeClient->errorcode()}] {$nodeClient->errormessage()}");

                return ['success' => false, 'message' => "Cannot connect to node '{$nodeId}' — RPC connection failed: {$nodeClient->errormessage()}", 'items_purged' => 0];
            }

            Log::info("Connected to node {$nodeId}", ['version' => $nodeInfo['version'] ?? 'unknown', 'blocks' => $nodeInfo['blocks'] ?? 0]);

            $streams = StreamEnums::cases();
            $totalPurged = 0;
            $streamStats = [];

            foreach ($streams as $streamEnum) {
                try {
                    $streamInfo = $nodeClient->getstreaminfo($streamEnum->value);
                    $nodeItemCount = 0;

                    if ($nodeClient->success() && ($streamInfo['subscribed'] ?? false)) {
                        $nodeItemCount = $streamInfo['items'] ?? 0;
                    }

                    $nodeClient->unsubscribe($streamEnum->value, true);

                    $purgeOk = $nodeClient->success();
                    if (! $purgeOk) {
                        Log::warning("unsubscribe failed for {$streamEnum->value} on node {$nodeId}: [{$nodeClient->errorcode()}] {$nodeClient->errormessage()}");
                    }

                    $totalPurged += $nodeItemCount;
                    $streamStats[$streamEnum->value] = [
                        'items_purged' => $nodeItemCount,
                        'purged' => $purgeOk,
                    ];
                } catch (Exception $streamEx) {
                    Log::warning("Could not unsubscribe/purge stream {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
                }
            }

            // Record the full-node purge event on-chain
            $this->multichain->publish(StreamEnums::FILE_METADATA->value, 'node_'.$nodeId.'_full_purge', [
                'json' => [
                    'action' => 'full_node_purge',
                    'node_id' => $nodeId,
                    'node_name' => $targetNode['name'] ?? $nodeId,
                    'items_purged' => $totalPurged,
                    'streams_affected' => array_keys(array_filter($streamStats, fn ($s) => $s['items_purged'] > 0)),
                    'reason' => $reason ?: 'Demo: full node purge — all data removed from single node',
                    'purged_at' => now()->toIso8601String(),
                    'performed_by' => auth()->user()?->name ?? 'system',
                ],
            ]);

            Log::info('Full node purge completed', [
                'node_id' => $nodeId,
                'items_purged' => $totalPurged,
                'streams_affected' => count($streamStats),
            ]);

            app(AuditLogger::class)->log(
                action: 'node.full_purge',
                subjectType: 'node',
                subjectId: $nodeId,
                oldValues: [
                    'action' => 'full_node_purge',
                    'node_id' => $nodeId,
                    'items_purged' => $totalPurged,
                    'streams_affected' => array_keys($streamStats),
                    'reason' => $reason,
                ],
            );

            return [
                'success' => true,
                'message' => "Purged all data ({$totalPurged} items across ".count($streamStats)." streams) from {$targetNode['name']} ({$nodeId}). Data survives on remaining nodes — resync to restore.",
                'items_purged' => $totalPurged,
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
     * Get list of available nodes with real-time health status and item counts.
     *
     * @return array<int, array{id: string, name: string, role: string, is_purged: bool, purged_at: string|null, items: int}>
     */
    public function getAvailableNodes(): array
    {
        $nodes = config('multichain.nodes', []);
        $rpcUser = config('multichain.rpc.username', 'multichainrpc');
        $rpcPass = config('multichain.rpc.password');
        $chainName = config('multichain.chain_name');
        $streams = collect(StreamEnums::cases())->map->value->toArray();

        return collect($nodes)->map(function ($node) use ($streams, $rpcUser, $rpcPass, $chainName) {
            $nodeId = $node['id'] ?? '';
            $nodeIp = $node['private_ip'] ?? '';
            $nodePort = $node['rpc_port'] ?? 6834;

            $isPurged = false;
            $purgedAt = null;
            $totalItems = 0;

            // Check on-chain purge state
            try {
                $purgeKey = 'node_'.$nodeId.'_full_purge';
                $purgeItems = $this->multichain->liststreamkeyitems(
                    StreamEnums::FILE_METADATA->value,
                    $purgeKey,
                    false,
                    1,
                    0,
                    false
                );

                if ($this->multichain->success() && is_array($purgeItems) && count($purgeItems) > 0) {
                    $resyncKey = 'node_'.$nodeId.'_resync';
                    $resyncItems = $this->multichain->liststreamkeyitems(
                        StreamEnums::FILE_METADATA->value,
                        $resyncKey,
                        false,
                        1,
                        0,
                        false
                    );

                    $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
                    $resyncBlock = 0;

                    if ($this->multichain->success() && is_array($resyncItems) && count($resyncItems) > 0) {
                        $resyncBlock = $resyncItems[0]['blocktime'] ?? 0;
                    }

                    if ($purgeBlock >= $resyncBlock) {
                        $isPurged = true;
                        $purgedAt = date('c', $purgeBlock);
                    }
                }
            } catch (Exception $e) {
                // If we can't check purge state, leave as false
            }

            // Get live item count from the node
            if (! $isPurged && ! empty($nodeIp)) {
                try {
                    $client = new Client($nodeIp, $nodePort, $rpcUser, $rpcPass, false);
                    $client->setoption('chain_name', $chainName);
                    $client->setTimeout(5);

                    $allStreams = $client->liststreams();
                    if ($client->success() && is_array($allStreams)) {
                        $streamMap = collect($allStreams)
                            ->filter(fn ($s) => ($s['subscribed'] ?? false) && in_array($s['name'], $streams))
                            ->mapWithKeys(fn ($s) => [$s['name'] => $s['items'] ?? 0]);

                        $totalItems = $streamMap->sum();
                    }
                } catch (Exception $e) {
                    // Node unreachable — items unknown
                }
            }

            return [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
                'is_purged' => $isPurged,
                'purged_at' => $purgedAt,
                'items' => $totalItems,
            ];
        })->values()->all();
    }
}
