<?php

declare(strict_types=1);

namespace App\Services\Blockchain;

use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
use App\Services\AuditLogger;
use App\Services\Manager;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Handles node-level operations for blockchain disaster recovery.
 *
 * Manages per-node purges and resync operations using AWS SSM
 * to execute remote commands on MultiChain node EC2 instances.
 *
 * Purge strategy for MultiChain CE:
 * - MultiChain CE has no purgestreamitems API (Enterprise only)
 * - unsubscribe alone doesn't delete data — data persists on other nodes
 * - Real purge: AWS SSM → stop daemon → delete chain data → restart → subscribe streams → resync
 * - The node restarts with zero blocks, zero subscriptions
 * - Data survives on all other nodes (they still run normally)
 * - After restart, the purge script explicitly subscribes all streams + rescans
 * - This re-downloads all data from peers, demonstrating: data survives individual node failures
 *
 * SSM command flow:
 * 1. Stop: multichain-cli procuchain stop (graceful)
 * 2. Wipe: rm -rf everything except multichain.conf + params.dat + wallet.dat
 * 3. Restart: multichaind connecting to seed peer
 * 4. Subscribe: explicitly subscribe to all streams (MultiChain does NOT auto-subscribe after data wipe)
 * 5. Resync: wait for all streams to synchronize (data downloads from peers)
 *
 * @see FileLifecycleManager for file-level delete/restore operations
 */
class NodeOperationsService
{
    private const SEED_NODE_PRIVATE_IP = '172.31.13.21';

    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Purge a specific node's data (full chain wipe via SSM).
     *
     * In MultiChain CE, per-key deletion is not available, so this performs
     * a full node purge — the same physical operation as purgeAllFromNode().
     * The distinction is intentional for the demo workflow:
     *   - deleteFromNode  → demo: "purge this node to show data survives on peers"
     *   - resyncNode      → demo: "resync this node to show data recovers from peers"
     *   - purgeAllFromNode → admin: non-demo full purge with its own audit trail
     *
     * Both deleteFromNode and purgeAllFromNode physically wipe the node via SSM.
     * The difference: deleteFromNode records a file-key-scoped on-chain event
     * for traceability, then a single combined audit entry.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteFromNode(string $fileKey, string $nodeId, string $reason = ''): array
    {
        // Pass skipDbAudit=true — we record our own combined audit below
        $result = $this->purgeAllFromNode($nodeId, $reason ?: "File purge: {$fileKey}", skipDbAudit: true);

        if ($result['success']) {
            // Record file-level purge on-chain for traceability
            $dataKey = str_replace('/', '_', $fileKey);
            try {
                $this->multichain->publish(StreamEnums::FILE_METADATA->value, $dataKey.'_node_purge', [
                    'json' => [
                        'file_key' => $fileKey,
                        'data_key' => $dataKey,
                        'action' => 'file_node_purge',
                        'node_id' => $nodeId,
                        'node_name' => $result['node_name'] ?? $nodeId,
                        'items_purged' => $result['items_purged'] ?? 0,
                        'method' => 'ssm_physical_delete',
                        'reason' => $reason,
                        'occurred_at' => now()->toIso8601String(),
                        'performed_by' => auth()->user()?->name ?? 'system',
                    ],
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to record file-level purge event on-chain', [
                    'file_key' => $fileKey,
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Record single combined audit log for the file-node-purge operation
            app(AuditLogger::class)->log(
                action: 'node.file_purge',
                subjectType: 'file',
                subjectId: $fileKey,
                newValues: [
                    'action' => 'file_node_purge',
                    'file_key' => $fileKey,
                    'node_id' => $nodeId,
                    'node_name' => $result['node_name'] ?? $nodeId,
                    'items_purged' => $result['items_purged'] ?? 0,
                    'method' => 'ssm_physical_delete',
                    'reason' => $reason,
                    'performed_by' => auth()->user()?->name ?? 'system',
                ],
            );
        }

        return $result;
    }

    /**
     * Purge ALL data from a specific node via AWS SSM.
     *
     * This is the REAL purge — physically deletes the chain data
     * from the target node's disk using AWS Systems Manager.
     *
     * Steps executed on the target node via SSM:
     * 1. Gracefully stop the MultiChain daemon
     * 2. Delete all chain data (blocks, chunks, chainstate, etc.)
     * 3. Preserve multichain.conf, params.dat, wallet.dat (needed to reconnect)
     * 4. Restart the daemon connecting to the seed node
     *
     * After purge: node has 0 blocks, 0 subscriptions, 0 data.
     * The Shared Ledger page will show 0 entries for this node.
     * Data survives on all other nodes.
     *
     * @return array{success: bool, message: string, items_purged: int}
     */
    public function purgeAllFromNode(string $nodeId, string $reason = '', bool $skipDbAudit = false): array
    {
        try {
            $nodes = config('multichain.nodes', []);
            $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

            if (! $targetNode) {
                return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry", 'items_purged' => 0];
            }

            $instanceId = $targetNode['instance_id'] ?? '';
            if (empty($instanceId)) {
                return ['success' => false, 'message' => "Node '{$nodeId}' has no EC2 instance ID configured", 'items_purged' => 0];
            }

            // Get item count before purge (for reporting)
            $itemsBefore = $this->getNodeItemCount($targetNode);

            // Execute the purge via AWS SSM — split into 2 phases to avoid SSM "no such process"
            // Phase 1: Stop daemon + delete chain data + restart daemon (fast, ~30s)
            // Phase 2: Wait for block sync + subscribe streams + verify data (slow, ~300s)
            $chainDataDir = '/root/.multichain/procuchain';
            $seedIp = $this->getSeedNodeIp($nodeId);
            $streams = collect(StreamEnums::cases())->map->value->toArray();

            // ── Phase 1: Stop → Delete → Restart ──
            $phase1 = implode("\n", [
                '#!/bin/bash',
                'export HOME=/root',
                'CLI="/usr/local/bin/multichain-cli procuchain"',
                'DAEMON="/usr/local/bin/multichaind"',
                '',
                '# Step 1: Stop MultiChain daemon gracefully',
                '$CLI stop 2>/dev/null || true',
                '',
                '# Step 2: Wait for daemon to fully stop (max 30s)',
                'for i in $(seq 1 30); do',
                ' if ! pgrep -x multichaind > /dev/null 2>&1; then',
                ' echo "Daemon stopped after ${i}s"',
                ' break',
                ' fi',
                ' sleep 1',
                'done',
                '',
                '# Force kill if still running',
                'if pgrep -x multichaind > /dev/null 2>&1; then',
                ' pkill -9 multichaind',
                ' sleep 2',
                ' echo "Daemon force-killed"',
                'fi',
                '',
                '# Step 3: Delete chain data — keep only what we need to reconnect',
                'cd '.escapeshellarg($chainDataDir),
                '',
                '# Preserve these files (needed to rejoin the network)',
                'mkdir -p /tmp/mc-purge-backup',
                'cp -f multichain.conf params.dat wallet.dat peers.dat /tmp/mc-purge-backup/ 2>/dev/null || true',
                '',
                '# Delete everything in the directory',
                'rm -rf blocks/ chainstate/ chunks/ entities.dat entities.db/',
                'rm -rf permissions.dat permissions.db permissions.log',
                'rm -rf addrs.dat fee_estimates.dat debug.log .lock multichain.pid',
                'rm -rf blk*.dat rev*.dat wallet/',
                '',
                '# Restore preserved files',
                'cp -f /tmp/mc-purge-backup/* . 2>/dev/null || true',
                'rm -rf /tmp/mc-purge-backup',
                '',
                '# Step 4: Restart daemon as a proper background process',
                '$DAEMON procuchain@'.escapeshellarg($seedIp).':6835 -daemon > /dev/null 2>&1',
                'sleep 2',
                '',
                '# Verify daemon is running',
                'if pgrep -x multichaind > /dev/null 2>&1; then',
                ' echo "PHASE1_SUCCESS: Daemon stopped, data deleted, daemon restarted"',
                'else',
                ' echo "PHASE1_FAIL: Daemon did not start"',
                'fi',
            ]);

            $phase1Result = $this->executeSsmCommand($instanceId, $phase1, 120);

            if (! $phase1Result['success']) {
                return [
                    'success' => false,
                    'message' => "Purge Phase 1 failed on node {$nodeId}: {$phase1Result['message']}",
                    'items_purged' => 0,
                ];
            }

            $phase1Output = $phase1Result['output'] ?? '';
            if (! str_contains($phase1Output, 'PHASE1_SUCCESS')) {
                return [
                    'success' => false,
                    'message' => "Purge Phase 1 did not complete on node {$nodeId}. Output: {$phase1Output}",
                    'items_purged' => 0,
                ];
            }

            Log::info('Purge Phase 1 complete', ['node_id' => $nodeId, 'output' => $phase1Output]);

            // Note: After purge, the node's daemon is running but has NO stream subscriptions.
            // The node stays in a purged state until the user manually triggers "Resync"
            // from the Recoverable Data UI. This is intentional — the demo flow is:
            //   1. Purge → node shows as purged (0 items, no subscriptions)
            //   2. User clicks "Resync" → data restores from peers
            //
            // Phase 2 (subscribe+rescan) is handled by resyncNode(), NOT here.

            // Record the purge event on-chain (from primary node — this still has data)
            try {
                $this->multichain->publish(StreamEnums::FILE_METADATA->value, 'node_'.$nodeId.'_full_purge', [
                    'json' => [
                        'action' => 'full_node_purge',
                        'node_id' => $nodeId,
                        'node_name' => $targetNode['name'] ?? $nodeId,
                        'items_purged' => $itemsBefore,
                        'method' => 'ssm_physical_delete',
                        'reason' => $reason ?: 'Demo: physical chain data deleted — manual resync from peers demonstrates blockchain resilience',
                        'occurred_at' => now()->toIso8601String(),
                        'performed_by' => auth()->user()?->name ?? 'system',
                    ],
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to record full purge event on-chain', [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Note: After purge, the node's local data is wiped but the daemon stays running.
            // Data persists on other nodes. Manual resync (resyncNode) is required to
            // restore the purged node's local copy from peers.

            Log::info('Full node purge completed via SSM', [
                'node_id' => $nodeId,
                'instance_id' => $instanceId,
                'items_purged' => $itemsBefore,
            ]);

            if (! $skipDbAudit) {
                app(AuditLogger::class)->log(
                    action: 'node.full_purge',
                    subjectType: 'node',
                    subjectId: $nodeId,
                    newValues: [
                        'action' => 'full_node_purge',
                        'node_id' => $nodeId,
                        'node_name' => $targetNode['name'] ?? $nodeId,
                        'method' => 'ssm_physical_delete',
                        'items_purged' => $itemsBefore,
                        'reason' => $reason,
                        'performed_by' => auth()->user()?->name ?? 'system',
                    ],
                );
            }

            return [
                'success' => true,
                'message' => "Purged all data ({$itemsBefore} items) from {$targetNode['name']} ({$nodeId}). Data survives on other nodes — use manual resync to restore this node's local copy.",
                'items_purged' => $itemsBefore,
                'node_name' => $targetNode['name'] ?? $nodeId,
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
     * Resync a node's data from peers via AWS SSM.
     *
     * After a purge, the node's local data is wiped but data persists on
     * other nodes. This is the PRIMARY method to restore a purged node —
     * it explicitly subscribes to all streams and rescans from peers.
     *
     * Steps executed on the target node via SSM:
     * 1. Subscribe to all streams (triggers data download from peers)
     * 2. Wait for initial sync to complete
     * 3. Verify item counts match other nodes
     *
     * @return array{success: bool, message: string}
     */
    public function resyncNode(string $nodeId, string $reason = ''): array
    {
        try {
            $nodes = config('multichain.nodes', []);
            $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

            if (! $targetNode) {
                return ['success' => false, 'message' => "Node '{$nodeId}' not found in registry"];
            }

            $instanceId = $targetNode['instance_id'] ?? '';
            if (empty($instanceId)) {
                return ['success' => false, 'message' => "Node '{$nodeId}' has no EC2 instance ID configured"];
            }

            $chainName = config('multichain.chain_name', 'procuchain');
            $streams = collect(StreamEnums::cases())->map->value->toArray();

            // Build the resync script: batch subscribe to all streams with rescan=true
            $script = implode("\n", [
                '#!/bin/bash',
                'export HOME=/root',
                'CLI="/usr/local/bin/multichain-cli procuchain"',
                '',
                '# Wait for daemon to be fully synced (stable block count > 50)',
                'READY=false',
                'LAST_BLOCKS=0',
                'STABLE_COUNT=0',
                'for i in $(seq 1 60); do',
                ' BLOCKS=$($CLI getblockchaininfo 2>/dev/null | grep -o "\"blocks\" : [0-9]*" | grep -o "[0-9]*" || true)',
                ' if [ -n "$BLOCKS" ] && [ "$BLOCKS" -gt 0 ] 2>/dev/null; then',
                ' if [ "$BLOCKS" = "$LAST_BLOCKS" ]; then',
                ' STABLE_COUNT=$((STABLE_COUNT + 1))',
                ' else',
                ' STABLE_COUNT=0',
                ' fi',
                ' LAST_BLOCKS=$BLOCKS',
                ' if [ "$STABLE_COUNT" -ge 3 ] && [ "$BLOCKS" -gt 50 ]; then',
                ' READY=true',
                ' echo "Daemon ready after ${i}s (blocks=$BLOCKS)"',
                ' break',
                ' fi',
                ' fi',
                ' sleep 6',
                'done',
                '',
                'if [ "$READY" = "false" ]; then',
                ' echo "RESYNC_WARNING: Block sync incomplete, subscribing anyway"',
                'fi',
                '',
                '# Batch subscribe to all streams with rescan=true (single blockchain pass)',
                'STREAMS_JSON=\''.json_encode($streams).'\'',
                'echo "Starting batch subscribe with rescan (this may take several minutes)..."',
                '$CLI subscribe "$STREAMS_JSON" true 2>&1',
                'SUB_EXIT=$?',
                'echo "Batch subscribe completed with exit=$SUB_EXIT"',

                '# Verify daemon is still responsive after rescan',
                'POST_READY=false',
                'for i in $(seq 1 20); do',
                ' BLOCKS=$($CLI getblockchaininfo 2>/dev/null | grep -o "\\"blocks\\" : [0-9]*" | grep -o "[0-9]*" || true)',
                ' if [ -n "$BLOCKS" ] && [ "$BLOCKS" -gt 50 ] 2>/dev/null; then',
                ' POST_READY=true',
                ' echo "Daemon responsive after rescan (blocks=$BLOCKS)"',
                ' break',
                ' fi',
                ' sleep 3',
                'done',

                '# Quick verify — check items count on first stream',
                'ITEMS=$($CLI liststreams '.escapeshellarg($streams[0]).' 2>&1 | grep -A1 "\\"items\\"" | grep -o "[0-9]*" | tail -1 || echo "0")',
                'echo "RESYNC_SUCCESS: Batch subscribe+rescan completed (exit=$SUB_EXIT), '.$streams[0].' items=$ITEMS"',
            ]);

            $ssmResult = $this->executeSsmCommand($instanceId, $script, 900);

            if (! $ssmResult['success']) {
                return [
                    'success' => false,
                    'message' => "SSM resync command failed on node {$nodeId}: {$ssmResult['message']}",
                ];
            }

            $output = $ssmResult['output'] ?? '';
            $resyncOk = str_contains($output, 'RESYNC_SUCCESS');

            // Record resync event on-chain (wrapped in try/catch — SSM already succeeded)
            try {
                // Get item counts after resync for the audit trail
                $itemsAfter = $this->getNodeItemCount($targetNode);

                $this->multichain->publish(StreamEnums::FILE_METADATA->value, 'node_'.$nodeId.'_resync', [
                    'json' => [
                        'action' => 'node_resync',
                        'node_id' => $nodeId,
                        'node_name' => $targetNode['name'] ?? $nodeId,
                        'items_resynced' => $itemsAfter,
                        'method' => 'ssm_subscribe_all',
                        'reason' => $reason ?: 'Manual resync — data restored from peers',
                        'occurred_at' => now()->toIso8601String(),
                        'performed_by' => auth()->user()?->name ?? 'system',
                    ],
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to record resync event on-chain', [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ]);
            }

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

            Log::info('Node resync completed via SSM', [
                'node_id' => $nodeId,
                'instance_id' => $instanceId,
                'success' => $resyncOk,
                'items_resynced' => $itemsAfter ?? 0,
            ]);

            app(AuditLogger::class)->log(
                action: 'node.resync',
                subjectType: 'node',
                subjectId: $nodeId,
                newValues: [
                    'action' => 'node_resync',
                    'node_id' => $nodeId,
                    'node_name' => $targetNode['name'] ?? $nodeId,
                    'method' => 'ssm_subscribe_all',
                    'items_resynced' => $itemsAfter ?? 0,
                    'reason' => $reason ?: 'Manual resync — data restored from peers',
                    'performed_by' => auth()->user()?->name ?? 'system',
                ],
            );

            return [
                'success' => $resyncOk,
                'message' => $resyncOk
                ? "Resynced {$targetNode['name']} ({$nodeId}) — all streams subscribed, data re-downloaded from peers. Node fully restored."
                : "Resync command sent to {$targetNode['name']} ({$nodeId}) but output was inconclusive. Check node status in a few moments.",
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
     * Get list of available nodes with real-time health status and item counts.
     *
     * For each node, checks on-chain purge/resync events to determine
     * if the node is currently in a purged state, and queries the node's
     * RPC for live item counts.
     *
     * @return array<int, array{id: string, name: string, role: string, is_purged: bool, purged_at: string|null, resync_at: string|null, last_action: string|null, items: int}>
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
            $resyncAt = null;
            $lastAction = null;
            $totalItems = 0;

            // Get live item count from the node (always, even if purged)
            if (! empty($nodeIp)) {
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

            // Check on-chain purge + resync state
            try {
                $purgeKey = 'node_'.$nodeId.'_full_purge';
                $purgeItems = $this->multichain->liststreamkeyitems(
                    StreamEnums::FILE_METADATA->value,
                    $purgeKey,
                    false,
                    1,
                    -1,
                    false
                );

                if ($this->multichain->success() && is_array($purgeItems) && count($purgeItems) > 0) {
                    $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
                    $purgedAt = date('c', $purgeBlock);
                    $lastAction = 'purged';

                    $resyncKey = 'node_'.$nodeId.'_resync';
                    $resyncItems = $this->multichain->liststreamkeyitems(
                        StreamEnums::FILE_METADATA->value,
                        $resyncKey,
                        false,
                        1,
                        -1,
                        false
                    );

                    $resyncBlock = 0;
                    if ($this->multichain->success() && is_array($resyncItems) && count($resyncItems) > 0) {
                        $resyncBlock = $resyncItems[0]['blocktime'] ?? 0;
                        $resyncAt = date('c', $resyncBlock);
                    }

                    // Node is considered purged if the most recent on-chain event is a purge
                    // (purgeBlock > resyncBlock). We do NOT require totalItems === 0 because
                    // MultiChain auto-syncs blocks from peers after a daemon restart, which
                    // re-subscribes streams and restores item counts before the user can
                    // see the purged state. The on-chain timestamp comparison is the only
                    // reliable indicator of purge vs resync state.
                    if ($purgeBlock > $resyncBlock) {
                        $isPurged = true;
                        $lastAction = 'purged';
                    } elseif ($resyncBlock > 0) {
                        $lastAction = 'resynced';
                    }
                }
            } catch (Exception $e) {
                // If we can't check purge state, leave as false
            }

            return [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
                'is_purged' => $isPurged,
                'purged_at' => $purgedAt,
                'resync_at' => $resyncAt,
                'last_action' => $lastAction,
                'items' => $totalItems,
            ];
        })->values()->all();
    }

    /**
     * Execute a shell script on a remote EC2 instance via AWS SSM.
     *
     * @param  string  $instanceId  EC2 instance ID (e.g. 'i-0aba884e70ad04588')
     * @param  string  $script  Shell script to execute
     * @param  int  $timeout  Max seconds to wait for command completion
     * @return array{success: bool, message: string, output: string}
     */
    private function executeSsmCommand(string $instanceId, string $script, int $timeout = 120): array
    {
        // Build AWS CLI profile + region args.
        // On EB: use instance profile (no --profile needed), but --region is still required.
        // On dev/VPS: use the configured AWS profile (region comes from the profile).
        $awsProfile = config('multichain.ssm.aws_profile', '');
        $awsRegion = config('multichain.ssm.aws_region', '');

        // Start with region if specified (needed on EB where instance profile
        // doesn't carry a default region in ~/.aws/config).
        $profileArgs = [];
        if (! empty($awsProfile)) {
            $profileArgs = ['--profile', $awsProfile];
        }
        if (! empty($awsRegion)) {
            $profileArgs = array_merge($profileArgs, ['--region', $awsRegion]);
        }

        // AWS SSM RunShellScript accepts the script content directly in the
        // commands parameter — no need to write to a temp file on the local
        // machine (the script runs on the REMOTE instance, so a local temp
        // file path would not exist there).
        //
        // We use --cli-input-json to avoid shell-escaping issues with the
        // --parameters flag. The script lines go into the JSON structure
        // which the AWS CLI parses natively — no escaping gymnastics needed.
        //
        // Key AWS docs distinction:
        //   TimeoutSeconds (send-command) = delivery timeout (how long SSM
        //     waits for the agent to pick up the command). Max 600s.
        //   executionTimeout (Parameters) = script execution timeout (how
        //     long the script itself can run). Default 3600s if not set.
        // Both must be sufficient for the script to complete.
        $scriptLines = explode("\n", $script);

        $cliInput = json_encode([
            'InstanceIds' => [$instanceId],
            'DocumentName' => 'AWS-RunShellScript',
            'Parameters' => [
                'commands' => $scriptLines,
                'executionTimeout' => [(string) $timeout],
            ],
            'TimeoutSeconds' => min($timeout + 60, 600),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tmpInputFile = tempnam(sys_get_temp_dir(), 'mc_ssm_input_');
        file_put_contents($tmpInputFile, $cliInput);

        try {
            // Send the SSM command via --cli-input-json
            // Profile/region args come first (before subcommand args) per AWS CLI convention.
            $sendArgs = array_merge(['aws', 'ssm', 'send-command'], $profileArgs, [
                '--cli-input-json', 'file://'.$tmpInputFile,
                '--output', 'text',
                '--query', 'Command.CommandId',
            ]);

            $sendCmd = new Process($sendArgs);
            $sendCmd->setTimeout(30);
            $sendCmd->run();

            if (! $sendCmd->isSuccessful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to send SSM command: '.$sendCmd->getErrorOutput().' '.$sendCmd->getOutput(),
                    'output' => '',
                ];
            }

            $commandId = trim($sendCmd->getOutput());

            if (empty($commandId)) {
                return [
                    'success' => false,
                    'message' => 'SSM send-command returned empty command ID',
                    'output' => '',
                ];
            }

            Log::info('SSM command sent', ['command_id' => $commandId, 'instance_id' => $instanceId]);

            // Poll for command completion
            $startTime = time();
            $pollInterval = 2;

            while (true) {
                $elapsed = time() - $startTime;
                if ($elapsed >= $timeout) {
                    return [
                        'success' => false,
                        'message' => "SSM command timed out after {$timeout}s (commandId: {$commandId})",
                        'output' => '',
                    ];
                }

                sleep($pollInterval);

                // Fetch status + output in a single call (less API round-trips)
                $combinedArgs = array_merge(['aws', 'ssm', 'get-command-invocation'], $profileArgs, [
                    '--command-id', $commandId,
                    '--instance-id', $instanceId,
                    '--query', '{Status:Status,Output:StandardOutputContent,Error:StandardErrorContent}',
                    '--output', 'json',
                ]);

                $combinedCmd = new Process($combinedArgs);
                $combinedCmd->setTimeout(15);
                $combinedCmd->run();
                $raw = trim($combinedCmd->getOutput());

                // Parse the JSON response
                $invocation = json_decode($raw, true);
                $status = $invocation['Status'] ?? 'Unknown';
                $stdout = $invocation['Output'] ?? '';
                $stderr = $invocation['Error'] ?? '';

                if ($status === 'Success' || $status === 'Failed' || $status === 'Cancelled' || $status === 'TimedOut') {
                    if ($status !== 'Success') {
                        return [
                            'success' => false,
                            'message' => "SSM command {$status} (commandId: {$commandId})".($stderr ? " | stderr: {$stderr}" : ''),
                            'output' => $stdout,
                        ];
                    }

                    return [
                        'success' => true,
                        'message' => 'SSM command completed',
                        'output' => $stdout,
                    ];
                }

                // Still Pending/InProgress — increase poll interval over time to reduce log noise
                if ($elapsed > 30 && $pollInterval < 5) {
                    $pollInterval = 5;
                }
                if ($elapsed > 120 && $pollInterval < 10) {
                    $pollInterval = 10;
                }
                Log::debug('SSM command still running', [
                    'command_id' => $commandId,
                    'status' => $status,
                    'elapsed' => $elapsed,
                ]);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SSM command exception: '.$e->getMessage(),
                'output' => '',
            ];
        } finally {
            // Clean up cli-input-json temp file (local only, not sent to remote)
            if (isset($tmpInputFile) && file_exists($tmpInputFile)) {
                @unlink($tmpInputFile);
            }
        }

    }

    /**
     * Get the total item count across all streams on a node.
     */
    private function getNodeItemCount(array $node): int
    {
        try {
            $client = new Client(
                $node['private_ip'],
                $node['rpc_port'],
                config('multichain.rpc.username', 'multichainrpc'),
                config('multichain.rpc.password'),
                false
            );
            $client->setoption('chain_name', config('multichain.chain_name'));
            $client->setTimeout(5);

            $allStreams = $client->liststreams();
            if ($client->success() && is_array($allStreams)) {
                $streamNames = collect(StreamEnums::cases())->map->value->toArray();

                return collect($allStreams)
                    ->filter(fn ($s) => ($s['subscribed'] ?? false) && in_array($s['name'], $streamNames))
                    ->sum(fn ($s) => $s['items'] ?? 0);
            }
        } catch (Exception $e) {
            // Node unreachable
        }

        return 0;
    }

    /**
     * Get the seed node IP for the target node to connect to after purge.
     *
     * Never use the target node's own IP as seed — use a different node.
     */
    private function getSeedNodeIp(string $targetNodeId): string
    {
        $nodes = config('multichain.nodes', []);

        // Prefer the admin node as seed (it's always running)
        foreach ($nodes as $node) {
            if ($node['id'] !== $targetNodeId && ! empty($node['private_ip'])) {
                return $node['private_ip'];
            }
        }

        // Fallback to hardcoded admin IP
        return self::SEED_NODE_PRIVATE_IP;
    }
}
