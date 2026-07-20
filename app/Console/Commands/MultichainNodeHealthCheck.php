<?php

namespace App\Console\Commands;

use App\Enums\Stream;
use App\Libraries\MultiChain\Client;
use App\Services\BlockchainRpcClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MultiChain Node Health Check Command
 *
 * Verifies all configured peer nodes are subscribed to procurement streams.
 * Auto-subscribes any node that has dropped its subscription.
 * Run via scheduler to ensure continuous data availability.
 */
class MultichainNodeHealthCheck extends Command
{
    public function __construct(
        private readonly BlockchainRpcClient $blockchainRpc,
    ) {
        parent::__construct();
    }

    protected $signature = 'multichain:node-health
        {--fix : Auto-subscribe unsubscribed nodes}
        {--notify : Log warnings for unsubscribed nodes}';

    protected $description = 'Check peer node subscription health and optionally auto-repair';

    public function handle(): int
    {
        $nodes = config('multichain.nodes', []);
        $fix = $this->option('fix');
        $notify = $this->option('notify');

        if (empty($nodes)) {
            $this->line('No peer nodes configured');

            return self::SUCCESS;
        }

        $streams = collect(Stream::cases())->map->value->toArray();
        $rpcUser = config('multichain.rpc.username', 'multichainrpc');
        $rpcPass = config('multichain.rpc.password');
        $chainName = config('multichain.chain_name');

        $healthyNodes = 0;
        $repairedNodes = 0;
        $unhealthyNodes = 0;
        $skippedPurgedNodes = 0;

        foreach ($nodes as $node) {
            $nodeName = $node['name'] ?? ($node['id'] ?? 'unknown');
            $nodeId = $node['id'] ?? '';
            $nodeIp = $node['private_ip'] ?? '';
            $nodePort = $node['rpc_port'] ?? 6834;

            if (empty($nodeIp)) {
                continue;
            }

            try {
                $client = new Client($nodeIp, $nodePort, $rpcUser, $rpcPass, false);
                $client->setoption('chain_name', $chainName);
                $client->setTimeout(10);

                $client->getinfo();

                if (! $client->success()) {
                    $unhealthyNodes++;

                    if ($notify) {
                        Log::warning("MultichainNodeHealth: {$nodeName} unreachable at {$nodeIp}");
                    }

                    continue;
                }

                $unsubscribedStreams = [];

                foreach ($streams as $stream) {
                    $client->liststreamitems($stream, false, 1, 0, false);

                    if (! $client->success()) {
                        $unsubscribedStreams[] = $stream;
                    }
                }

                if (empty($unsubscribedStreams)) {
                    $healthyNodes++;

                    continue;
                }

                // Node has unsubscribed streams — check if this was an intentional
                // demo purge before auto-repairing. If a full_node_purge event exists
                // on-chain (and no newer resync event), skip this node entirely.
                if ($this->isNodePurged($blockchainRpcClient, $nodeId)) {
                    $skippedPurgedNodes++;
                    $this->line(" {$nodeName} -- intentionally purged, skipping auto-repair");

                    if ($notify) {
                        Log::info("MultichainNodeHealth: {$nodeName} is intentionally purged — skipping auto-subscribe", [
                            'node_id' => $nodeId,
                            'missing_streams' => count($unsubscribedStreams),
                            'tip' => 'Use Recoverable Data -> Resync in the UI to restore when ready',
                        ]);
                    }

                    continue;
                }

                if ($fix) {
                    $fixedCount = 0;

                    foreach ($unsubscribedStreams as $stream) {
                        $client->subscribe($stream, true);

                        if ($client->success()) {
                            $fixedCount++;
                        }
                    }

                    if ($fixedCount === count($unsubscribedStreams)) {
                        $repairedNodes++;
                        Log::info("MultichainNodeHealth: {$nodeName} auto-repaired — subscribed to {$fixedCount} stream(s)");
                    } else {
                        $unhealthyNodes++;
                        Log::warning("MultichainNodeHealth: {$nodeName} partial repair — {$fixedCount}/".count($unsubscribedStreams).' streams subscribed');
                    }
                } else {
                    $unhealthyNodes++;

                    if ($notify) {
                        Log::warning("MultichainNodeHealth: {$nodeName} missing ".count($unsubscribedStreams).' stream subscriptions', [
                            'missing_streams' => $unsubscribedStreams,
                            'tip' => 'Run with --fix to auto-subscribe, or use Recoverable Data -> Resync in the UI',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $unhealthyNodes++;

                if ($notify) {
                    Log::warning("MultichainNodeHealth: {$nodeName} error — {$e->getMessage()}");
                }
            }
        }

        $summary = "Health check complete: {$healthyNodes} healthy, {$repairedNodes} repaired, {$unhealthyNodes} unhealthy";
        if ($skippedPurgedNodes > 0) {
            $summary .= ", {$skippedPurgedNodes} purged (skipped)";
        }
        $this->info($summary);

        return $unhealthyNodes > 0 && ! $fix ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Check if a node has been intentionally purged by looking for a
     * full_node_purge event on-chain. Returns true if a purge event
     * exists and no newer resync event has been recorded.
     *
     * This prevents the health check from auto-repairing (re-subscribing)
     * a node that was intentionally purged via the demo purge page.
     */
    private function isNodePurged(BlockchainRpcClient $blockchainRpcClient, string $nodeId): bool
    {
        if (empty($nodeId)) {
            return false;
        }

        $purgeCheckStream = Stream::FILE_METADATA->value;

        try {
            $purgeKey = 'node_'.$nodeId.'_full_purge';
            $purgeItems = $blockchainRpcClient->liststreamkeyitems(
                $purgeCheckStream,
                $purgeKey,
                false,
                1,
                0,
                false
            );

            if ($blockchainRpcClient->success() && is_array($purgeItems) && count($purgeItems) > 0) {
                // Check for a newer resync event
                $resyncKey = 'node_'.$nodeId.'_resync';
                $resyncItems = $blockchainRpcClient->liststreamkeyitems(
                    $purgeCheckStream,
                    $resyncKey,
                    false,
                    1,
                    0,
                    false
                );

                if ($blockchainRpcClient->success() && is_array($resyncItems) && count($resyncItems) > 0) {
                    $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
                    $resyncBlock = $resyncItems[0]['blocktime'] ?? 0;

                    // Purged if purge event is newer than or equal to resync
                    return $purgeBlock >= $resyncBlock;
                }

                return true;
            }
        } catch (\Exception $e) {
            $this->warn("Could not check purge status for {$nodeId}: {$e->getMessage()}");
        }

        return false;
    }
}
