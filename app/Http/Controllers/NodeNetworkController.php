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
    private const RPC_USER = 'multichainrpc';
    private const RPC_PASSWORD = 'multichainrpc';

    private const NODES = [
        [
            'id' => 'admin', 'name' => 'Primary Node', 'role' => 'Administrator',
            'ip' => '98.92.215.176', 'p2p_port' => 7449, 'rpc_port' => 7450,
            'private_ip' => '172.31.11.120',
        ],
        [
            'id' => 'bac-secretariat', 'name' => 'BAC Secretariat', 'role' => 'Secretariat',
            'ip' => '13.220.241.131', 'p2p_port' => 7549, 'rpc_port' => 7550,
            'private_ip' => '172.31.4.20',
        ],
        [
            'id' => 'bac-chairman', 'name' => 'BAC Chairman', 'role' => 'Chairman',
            'ip' => '3.231.53.193', 'p2p_port' => 7649, 'rpc_port' => 7650,
            'private_ip' => '172.31.9.45',
        ],
        [
            'id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE',
            'ip' => '44.200.134.154', 'p2p_port' => 7749, 'rpc_port' => 7750,
            'private_ip' => '172.31.15.37',
        ],
    ];

    private const IP_MAP = [
        '13.220.241.131' => 'bac-secretariat', '3.231.53.193' => 'bac-chairman',
        '44.200.134.154' => 'hope', '98.92.215.176' => 'admin',
        '172.31.4.20' => 'bac-secretariat', '172.31.9.45' => 'bac-chairman',
        '172.31.15.37' => 'hope', '172.31.11.120' => 'admin',
    ];

    public function index(): Response
    {
        return Inertia::render('admin/network-visualization', $this->getNetworkData());
    }

    public function data(): JsonResponse
    {
        return response()->json($this->getNetworkData());
    }

    private function getNetworkData(): array
    {
        $nodes = self::NODES;
        $allPeers = [];
        $liveNodeData = [];

        foreach ($nodes as $node) {
            $nodeData = $this->queryNode($node['id'], $node['private_ip'], $node['rpc_port']);
            $liveNodeData[$node['id']] = $nodeData;
            $allPeers[$node['id']] = $nodeData['peerInfo'] ?? [];
        }

        $connections = $this->buildFullMeshConnections($allPeers);

        $enrichedNodes = array_map(fn($node) => [
            'id' => $node['id'],
            'name' => $node['name'],
            'role' => $node['role'],
            'ip' => $node['ip'],
            'p2p_port' => $node['p2p_port'],
            'rpc_port' => $node['rpc_port'],
            'blocks' => $liveNodeData[$node['id']]['blocks'] ?? 0,
            'connected' => $liveNodeData[$node['id']]['connected'] ?? false,
            'peers' => count($liveNodeData[$node['id']]['peerInfo'] ?? []),
            'lastSeen' => $liveNodeData[$node['id']]['lastSeen'] ?? 0,
            'subver' => $liveNodeData[$node['id']]['subver'] ?? 'Unknown',
        ], $nodes);

        $connectedNodes = count(array_filter($liveNodeData, fn($d) => $d['connected'] ?? false));
        $totalPeers = array_sum(array_map(fn($d) => count($d['peerInfo'] ?? []), $liveNodeData));
        $blocks = max(array_map(fn($d) => $d['blocks'] ?? 0, $liveNodeData));

        return [
            'nodes' => $enrichedNodes,
            'connections' => $connections,
            'overview' => [
                'blocks' => $blocks,
                'connected_nodes' => $connectedNodes,
                'total_nodes' => count($nodes),
                'total_peers' => $totalPeers,
                'chain_name' => 'procuchain-dev',
                'version' => $liveNodeData['admin']['subver'] ?? 'Unknown',
                'all_connected' => $connectedNodes === count($nodes),
            ],
        ];
    }

    private function queryNode(string $nodeId, string $host, int $port): array
    {
        try {
            $client = new Client($host, $port, self::RPC_USER, self::RPC_PASSWORD, false);
            $info = $client->getinfo();
            $peers = $client->getpeerinfo();

            $mappedPeers = [];
            foreach ($peers as $peer) {
                $addr = $peer['addr'] ?? '';
                $matchedId = null;

                foreach (self::IP_MAP as $ip => $nid) {
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
            Log::warning("NodeNetwork: Failed to query node {$nodeId} at {$host}:{$port}: " . $e->getMessage());
            return [
                'blocks' => 0, 'connected' => false, 'lastSeen' => 0,
                'subver' => 'Unknown', 'peerInfo' => [],
            ];
        }
    }

    private function buildFullMeshConnections(array $allPeers): array
    {
        $bestData = [];

        foreach ($allPeers as $nodeId => $peers) {
            foreach ($peers as $peer) {
                if (!isset($peer['id'])) {
                    continue;
                }
                $from = $nodeId;
                $to = $peer['id'];
                $pairKey = $from < $to ? "{$from}-{$to}" : "{$to}-{$from}";

                if (!isset($bestData[$pairKey]) || ($peer['ping_time'] ?? 0) > ($bestData[$pairKey]['ping_time'] ?? 0)) {
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

        $allIds = ['admin', 'bac-secretariat', 'bac-chairman', 'hope'];
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