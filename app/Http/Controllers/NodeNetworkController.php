<?php

namespace App\Http\Controllers;

use App\Libraries\MultiChain\Client;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class NodeNetworkController extends Controller
{
    /** RPC credentials (read from config for consistency with .env) */
    private function getRpcUser(): string
    {
        return config('multichain.rpc.username', 'multichainrpc');
    }

    private function getRpcPassword(): string
    {
        return config('multichain.rpc.password');
    }

    /**
     * Node registry — sourced from config/multichain.php (env-driven).
     * Falls back to localhost defaults if no env vars are set.
     *
     * @return array<int, array{id: string, name: string, role: string, ip: string, private_ip: string, p2p_port: int, rpc_port: int}>
     */
    private function getNodes(): array
    {
        return config('multichain.nodes', []);
    }

    /**
     * Build a reverse-lookup map: IP address → node ID.
     * Covers both public and private IPs for peer matching.
     *
     * @return array<string, string>
     */
    private function getIpMap(): array
    {
        $map = [];
        foreach ($this->getNodes() as $node) {
            $map[$node['ip']] = $node['id'];
            $map[$node['private_ip']] = $node['id'];
        }

        return $map;
    }

    public function index(): Response
    {
        $this->authorize('view-blockchain-network');

        return Inertia::render('admin/network-visualization', $this->getNetworkData());
    }

    public function data(): JsonResponse
    {
        $this->authorize('view-blockchain-network');

        return response()->json($this->getNetworkData());
    }

    private function getNetworkData(): array
    {
        $nodes = $this->getNodes();
        $allPeers = [];
        $liveNodeData = [];

        foreach ($nodes as $node) {
            $nodeData = $this->queryNode($node['id'], $node['private_ip'], $node['rpc_port']);
            $liveNodeData[$node['id']] = $nodeData;
            $allPeers[$node['id']] = $nodeData['peerInfo'] ?? [];
        }

        $connections = $this->buildFullMeshConnections($allPeers);

        $enrichedNodes = array_map(fn ($node) => [
            'id' => $node['id'],
            'name' => $node['name'],
            'role' => $node['role'],
            'ip' => $this->maskIp($node['ip']),
            'p2p_port' => $node['p2p_port'],
            'rpc_port' => $node['rpc_port'],
            'blocks' => $liveNodeData[$node['id']]['blocks'] ?? 0,
            'connected' => $liveNodeData[$node['id']]['connected'] ?? false,
            'peers' => count($liveNodeData[$node['id']]['peerInfo'] ?? []),
            'lastSeen' => $liveNodeData[$node['id']]['lastSeen'] ?? 0,
            'subver' => $liveNodeData[$node['id']]['subver'] ?? 'Unknown',
        ], $nodes);

        $connectedNodes = count(array_filter($liveNodeData, fn ($d) => $d['connected'] ?? false));
        $totalPeers = array_sum(array_map(fn ($d) => count($d['peerInfo'] ?? []), $liveNodeData));
        $blocks = max(array_map(fn ($d) => $d['blocks'] ?? 0, $liveNodeData));

        return [
            'nodes' => $enrichedNodes,
            'connections' => $connections,
            'overview' => [
                'blocks' => $blocks,
                'connected_nodes' => $connectedNodes,
                'total_nodes' => count($nodes),
                'total_peers' => $totalPeers,
                'chain_name' => config('multichain.chain_name', 'procuchain'),
                'version' => $liveNodeData['admin']['subver'] ?? 'Unknown',
                'all_connected' => $connectedNodes === count($nodes),
            ],
        ];
    }

    private function queryNode(string $nodeId, string $host, int $port): array
    {
        try {
            $client = new Client($host, $port, $this->getRpcUser(), $this->getRpcPassword(), false);
            $info = $client->getinfo();
            $peers = $client->getpeerinfo();
            $ipMap = $this->getIpMap();

            $mappedPeers = [];
            foreach ($peers as $peer) {
                $addr = $peer['addr'] ?? '';
                $matchedId = null;

                foreach ($ipMap as $ip => $nid) {
                    if (str_contains($addr, $ip)) {
                        $matchedId = $nid;
                        break;
                    }
                }

                if ($matchedId && $matchedId !== $nodeId) {
                    $mappedPeers[] = [
                        'id' => $matchedId,
                        'bytes_sent' => (int) ($peer['bytessent'] ?? 0),
                        'bytes_recv' => (int) ($peer['bytesrecv'] ?? 0),
                        'ping_time' => (float) ($peer['pingtime'] ?? 0),
                        'connected_since' => (int) ($peer['conntime'] ?? 0),
                        'subver' => $info['version'] ?? 'Unknown',
                    ];
                }
            }

            return [
                'blocks' => $info['blocks'] ?? 0,
                'connected' => true,
                'lastSeen' => time(),
                'subver' => $info['version'] ?? 'Unknown',
                'peerInfo' => $mappedPeers,
            ];
        } catch (Exception $e) {
            report($e);
            Log::warning("NodeNetwork: Failed to query node {$nodeId} at {$host}:{$port}: ".$e->getMessage());

            return [
                'blocks' => 0, 'connected' => false, 'lastSeen' => 0,
                'subver' => 'Unknown', 'peerInfo' => [],
            ];
        }
    }

    /**
     * Mask an IP address for safe frontend display.
     * Preserves first octet for subnet awareness: "32.xxx.xxx.xxx"
     */
    private function maskIp(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0].'.xxx.xxx.xxx';
        }

        return '***';
    }

    private function buildFullMeshConnections(array $allPeers): array
    {
        $bestData = [];

        foreach ($allPeers as $nodeId => $peers) {
            foreach ($peers as $peer) {
                if (! isset($peer['id'])) {
                    continue;
                }
                $from = $nodeId;
                $to = $peer['id'];
                $pairKey = $from < $to ? "{$from}-{$to}" : "{$to}-{$from}";

                if (! isset($bestData[$pairKey]) || ($peer['ping_time'] ?? 0) > ($bestData[$pairKey]['ping_time'] ?? 0)) {
                    $bestData[$pairKey] = [
                        'bytes_sent' => (int) ($peer['bytes_sent'] ?? 0),
                        'bytes_recv' => (int) ($peer['bytes_recv'] ?? 0),
                        'ping_time' => (float) ($peer['ping_time'] ?? 0),
                        'connected_since' => (int) ($peer['connected_since'] ?? 0),
                        'subver' => $peer['subver'] ?? 'Unknown',
                    ];
                }
            }
        }

        $allIds = array_map(fn ($node) => $node['id'], $this->getNodes());
        $connections = [];

        for ($i = 0; $i < count($allIds); $i++) {
            for ($j = $i + 1; $j < count($allIds); $j++) {
                $from = $allIds[$i];
                $to = $allIds[$j];
                $pairKey = $from < $to ? "{$from}-{$to}" : "{$to}-{$from}";

                if (isset($bestData[$pairKey])) {
                    $d = $bestData[$pairKey];
                    $connections[] = [
                        'id' => $pairKey,
                        'from' => $allIds[$i],
                        'to' => $allIds[$j],
                        'status' => 'active',
                        'bytes_sent' => $d['bytes_sent'],
                        'bytes_recv' => $d['bytes_recv'],
                        'ping_time' => $d['ping_time'],
                        'connected_since' => $d['connected_since'],
                        'subver' => $d['subver'],
                    ];
                } else {
                    $connections[] = [
                        'id' => $pairKey,
                        'from' => $allIds[$i],
                        'to' => $allIds[$j],
                        'status' => 'inactive',
                        'bytes_sent' => 0, 'bytes_recv' => 0,
                        'ping_time' => 0, 'connected_since' => 0, 'subver' => 'Unknown',
                    ];
                }
            }
        }

        return $connections;
    }
}
