<?php

namespace App\Console\Commands;

use App\Libraries\MultiChain\Client;
use Illuminate\Console\Command;

/**
 * Debug command to test RPC connectivity to a specific MultiChain node.
 * Usage: php artisan debug:node-rpc hope
 */
class DebugNodeRpcCommand extends Command
{
    protected $signature = 'debug:node-rpc {node_id : The node ID to test (e.g. hope, admin)}';

    protected $description = 'Test RPC connection to a specific MultiChain node and list stream info';

    public function handle(): int
    {
        $nodeId = $this->argument('node_id');
        $nodes = config('multichain.nodes', []);
        $targetNode = collect($nodes)->first(fn ($n) => $n['id'] === $nodeId);

        if (! $targetNode) {
            $this->error("Node '{$nodeId}' not found in registry.");
            $this->info('Available nodes: '.collect($nodes)->pluck('id')->join(', '));

            return 1;
        }

        $this->info("Testing RPC connection to {$targetNode['name']} ({$nodeId})...");
        $this->info("  Host: {$targetNode['private_ip']}");
        $this->info("  RPC Port: {$targetNode['rpc_port']}");

        $client = new Client(
            $targetNode['private_ip'],
            $targetNode['rpc_port'],
            config('multichain.rpc.username', 'multichainrpc'),
            config('multichain.rpc.password'),
            false
        );
        $client->setoption('chain_name', config('multichain.chain_name'));

        // Test 1: getinfo
        $this->newLine();
        $this->info('=== getinfo ===');
        $info = $client->getinfo();

        if (! $client->success()) {
            $this->error("FAILED: [{$client->errorcode()}] {$client->errormessage()}");

            return 1;
        }

        $this->info("Version: ".($info['version'] ?? 'unknown'));
        $this->info("Blocks: ".($info['blocks'] ?? 0));
        $this->info("Connections: ".($info['connections'] ?? 0));
        $this->info("Chain: ".($info['chainname'] ?? 'unknown'));

        // Test 2: liststreams
        $this->newLine();
        $this->info('=== liststreams ===');
        $streams = $client->__call('liststreams', []);
        if (! $client->success()) {
            $this->error("FAILED: [{$client->errorcode()}] {$client->errormessage()}");
        } else {
            foreach ($streams as $stream) {
                $this->info("  {$stream['name']}: items={$stream['items']}, subscribed=".($stream['subscribed'] ? 'yes' : 'no').", keys={$stream['keys']}");
            }
        }

        // Test 3: getstreaminfo for each enum stream
        $this->newLine();
        $this->info('=== getstreaminfo (app streams) ===');
        $appStreams = \App\Enums\StreamEnums::cases();
        foreach ($appStreams as $streamEnum) {
            $streamInfo = $client->getstreaminfo($streamEnum->value);
            if (! $client->success()) {
                $this->warn("  {$streamEnum->value}: FAILED [{$client->errorcode()}] {$client->errormessage()}");
            } else {
                $items = $streamInfo['items'] ?? 0;
                $subscribed = ($streamInfo['subscribed'] ?? false) ? 'yes' : 'no';
                $this->info("  {$streamEnum->value}: items={$items}, subscribed={$subscribed}");
            }
        }

        // Test 4: unsubscribe (dry-run — we don't actually unsubscribe)
        $this->newLine();
        $this->info('=== unsubscribe capability test ===');
        $this->info('Skipping actual unsubscribe to avoid data loss.');
        $this->info('The above stream info confirms whether the RPC can read stream data.');

        return 0;
    }
}
