<?php

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
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

        $streams = collect(StreamEnums::cases())->map->value->toArray();
        $rpcUser = config('multichain.rpc.username', 'multichainrpc');
        $rpcPass = config('multichain.rpc.password');
        $chainName = config('multichain.chain_name');

        $healthyNodes = 0;
        $repairedNodes = 0;
        $unhealthyNodes = 0;

        foreach ($nodes as $node) {
            $nodeName = $node['name'] ?? ($node['id'] ?? 'unknown');
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

                // Node has unsubscribed streams
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
                            'tip' => 'Run with --fix to auto-subscribe, or use Recoverable Data → Resync in the UI',
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

        $this->info("Health check complete: {$healthyNodes} healthy, {$repairedNodes} repaired, {$unhealthyNodes} unhealthy");

        return $unhealthyNodes > 0 && ! $fix ? self::FAILURE : self::SUCCESS;
    }
}
