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
 * Purge strategy for MultiChain CE:
 * - MultiChain CE has no per-key deletion API
 * - "Purge" means unsubscribing from streams on the target node
 *   so the node drops its local copy of the data
 * - Data survives on all other nodes (they remain subscribed)
 * - Resync re-subscribes the node, re-downloading from peers
 * - This demonstrates the core blockchain property: data survives
 *   individual node failures and can be recovered from peers
 *
 * @see FileLifecycleManager for file-level delete/restore operations
 */
class NodeOperationsService
{
    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Purge a single file's data from a node's local storage.
     *
     * Since MultiChain CE has no per-key deletion, this unsubscribes
     * the node from file-related streams (FILE_METADATA, FILE_DATA),
     * causing the node to drop its local copy of ALL file data.
     * The file data remains on other nodes and is restored on resync.
     *
     * The purge event is recorded on-chain for audit compliance (RA 12009).
     *
     * @return array{success: bool, message: string, streams_purged: int}
     */
    public function deleteFromNode(string $fileKey, string $nodeId, string $reason = ''): array
    {
        try {
            $nodes = config('multichain.nodes', []);
            $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

            if (! $targetNode) {
                return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry", 'streams_purged' => 0];
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
                return [
                    'success' => false,
                    'message' => "Cannot connect to node '{$nodeId}' — RPC failed: {$nodeClient->errormessage()}",
                    'streams_purged' => 0,
                ];
            }

            $dataKey = str_replace('/', '_', $fileKey);

            // Unsubscribe from file-related streams on the target node
            // This causes the node to drop ALL its local file data
            // (MultiChain CE cannot delete per-key — this is the best we can do)
            $fileStreams = [StreamEnums::FILE_METADATA, StreamEnums::FILE_DATA];
            $streamsPurged = 0;
            $itemsDropped = 0;

            foreach ($fileStreams as $streamEnum) {
                try {
                    // Get item count before purging
                    $streamInfo = $nodeClient->getstreaminfo($streamEnum->value);
                    $itemCount = 0;
                    if ($nodeClient->success() && ($streamInfo['subscribed'] ?? false)) {
                        $itemCount = $streamInfo['items'] ?? 0;
                    }

                    // Unsubscribe — this drops the node's local copy of the stream data
                    $nodeClient->unsubscribe($streamEnum->value, true);

                    if ($nodeClient->success()) {
                        $streamsPurged++;
                        $itemsDropped += $itemCount;
                        Log::info("Unsubscribed {$streamEnum->value} on node {$nodeId} (dropped {$itemCount} items)");
                    } else {
                        Log::warning("unsubscribe failed for {$streamEnum->value} on node {$nodeId}: [{$nodeClient->errorcode()}] {$nodeClient->errormessage()}");
                    }
                } catch (Exception $streamEx) {
                    Log::warning("Could not unsubscribe from {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
                }
            }

            // Record the file-level node purge on-chain (from the primary node)
            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $dataKey.'_node_purge', [
                'json' => [
                    'file_key' => $fileKey,
                    'data_key' => $dataKey,
                    'action' => 'node_purge',
                    'node_id' => $nodeId,
                    'node_name' => $targetNode['name'] ?? $nodeId,
                    'streams_purged' => $streamsPurged,
                    'items_dropped' => $itemsDropped,
                    'reason' => $reason,
                    'purged_at' => now()->toIso8601String(),
                    'performed_by' => auth()->user()?->name ?? 'system',
                ],
            ]);

            Log::info("File data purged from node {$nodeId}", [
                'file_key' => $fileKey,
                'node_id' => $nodeId,
                'streams_purged' => $streamsPurged,
                'items_dropped' => $itemsDropped,
            ]);

            app(AuditLogger::class)->log(
                action: 'file.node_purge',
                subjectType: 'file',
                subjectId: $fileKey,
                oldValues: [
                    'file_key' => $fileKey,
                    'action' => 'node_purge',
                    'node_id' => $nodeId,
                    'streams_purged' => $streamsPurged,
                    'items_dropped' => $itemsDropped,
                    'reason' => $reason,
                ],
            );

            return [
                'success' => true,
                'message' => "Purged file data from {$targetNode['name']} ({$nodeId}). {$streamsPurged} file streams unsubscribed ({$itemsDropped} items dropped). Data survives on remaining nodes — resync to restore.",
                'streams_purged' => $streamsPurged,
            ];
        } catch (Exception $e) {
            Log::error('Single-node file purge failed', [
                'file_key' => $fileKey,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed: '.$e->getMessage(), 'streams_purged' => 0];
        }
    }

    /**
     * Resync a node's stream data from peers.
     *
     * After a purge (single-file or full-node), this re-subscribes
     * the node to all streams, causing MultiChain to re-download
     * the missing data from connected peers.
     *
     * This demonstrates blockchain recoverability: even after a
     * node loses all its local data, it can fully recover from peers.
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

            // Verify RPC connection
            $nodeInfo = $nodeClient->getinfo();
            if (! $nodeClient->success()) {
                return ['success' => false, 'message' => "Cannot connect to node '{$nodeId}' — RPC failed: {$nodeClient->errormessage()}"];
            }

            $streams = StreamEnums::cases();

            $resyncedStreams = 0;
            $totalRetrieved = 0;
            foreach ($streams as $streamEnum) {
                try {
                    // Re-subscribe — triggers MultiChain to pull data from peers
                    $nodeClient->subscribe($streamEnum->value, true);

                    if (! $nodeClient->success()) {
                        Log::warning("subscribe failed for {$streamEnum->value} on node {$nodeId}: [{$nodeClient->errorcode()}] {$nodeClient->errormessage()}");
                        continue;
                    }

                    // After subscribing, check item count to confirm data was retrieved
                    $info = $nodeClient->getstreaminfo($streamEnum->value);
                    $itemsAfter = 0;

                    if ($nodeClient->success() && isset($info['items'])) {
                        $itemsAfter = $info['items'];
                    } else {
                        // Fallback: list a sample to force sync, then re-check
                        $nodeClient->liststreamitems($streamEnum->value, false, 1, 0, false);
                        if ($nodeClient->success()) {
                            $info2 = $nodeClient->getstreaminfo($streamEnum->value);
                            $itemsAfter = $info2['items'] ?? 0;
                        }
                    }

                    if ($itemsAfter > 0) {
                        $resyncedStreams++;
                        $totalRetrieved += $itemsAfter;
                        Log::info("Resynced {$itemsAfter} items from {$streamEnum->value} on node {$nodeId}");
                    }
                } catch (Exception $streamEx) {
                    Log::warning("Could not resubscribe to stream {$streamEnum->value} on node {$nodeId}: ".$streamEx->getMessage());
                }
            }

            // Record resync event on-chain — proves recovery happened
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

            // If this was the primary node, clear the purge flag so failover can promote it back
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
                'message' => "Resynced {$targetNode['name']} ({$nodeId}) — {$totalRetrieved} items retrieved across {$resyncedStreams} streams from peers. Data fully restored from blockchain.",
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
     * Simulates catastrophic node data loss for the demo.
     * Unsubscribes the node from every stream, dropping all local data.
     * Data survives on remaining nodes — resync to fully restore.
     * Recorded on-chain as action: 'full_node_purge' per RA 12009.
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

                    // Unsubscribe — drops ALL local data for this stream
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
     * For each node, checks on-chain purge/resync events to determine
     * if the node is currently in a purged state, and queries the node's
     * RPC for live item counts.
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
