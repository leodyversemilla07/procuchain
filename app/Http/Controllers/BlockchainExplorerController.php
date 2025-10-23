<?php

namespace App\Http\Controllers;

use App\Services\BlockchainMonitoringService;
use App\Services\MultichainService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BlockchainExplorerController extends Controller
{
    public function __construct(
        private MultichainService $multichainService,
        private BlockchainMonitoringService $healthService
    ) {}

    /**
     * Display the blockchain explorer dashboard
     */
    public function index(Request $request): Response
    {
        try {
            // Cache blockchain info for 30 seconds to prevent repeated RPC calls
            $blockchainInfo = Cache::remember('blockchain:info', 30, fn () => $this->multichainService->getBlockchainInfo());
            $networkInfo = Cache::remember('blockchain:network_info', 30, fn () => $this->multichainService->getNetworkInfo());
            $nodeInfo = Cache::remember('blockchain:node_info', 30, fn () => $this->multichainService->getInfo());
            $peerInfo = Cache::remember('blockchain:peer_info', 30, fn () => $this->multichainService->getPeerInfo());

            // Get latest blocks (last 10) - cache for 15 seconds as new blocks arrive
            $currentHeight = $blockchainInfo['blocks'];
            $latestBlocks = Cache::remember("blockchain:latest_blocks:{$currentHeight}", 15, function () use ($currentHeight) {
                $blocks = [];
                for ($i = 0; $i < min(10, $currentHeight + 1); $i++) {
                    try {
                        $block = $this->multichainService->getBlock($currentHeight - $i, 1);
                        $blocks[] = [
                            'height' => $block['height'],
                            'hash' => $block['hash'],
                            'time' => $block['time'],
                            'miner' => $block['miner'] ?? 'Unknown',
                            'tx_count' => count($block['tx'] ?? []),
                            'size' => $block['size'] ?? 0,
                        ];
                    } catch (Exception $e) {
                        Log::warning('Failed to fetch block at height '.($currentHeight - $i), [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return $blocks;
            });

            // Get streams - cache for 60 seconds (streams don't change often)
            $streamsList = Cache::remember('blockchain:streams', 60, function () {
                $streams = $this->multichainService->listStreams('*', true, 1000, 0);
                // Filter out system streams and re-index array
                $streams = array_values(array_filter($streams, fn ($stream) => $stream['name'] !== 'root'));

                return array_map(function ($stream) {
                    return [
                        'name' => $stream['name'],
                        'createtxid' => $stream['createtxid'] ?? null,
                        'streamref' => $stream['streamref'] ?? null,
                        'items' => $stream['items'] ?? 0,
                        'confirmed' => $stream['confirmed'] ?? 0,
                        'keys' => $stream['keys'] ?? 0,
                        'publishers' => $stream['publishers'] ?? 0,
                        'subscribed' => $stream['subscribed'] ?? false,
                        'synchronized' => $stream['synchronized'] ?? false,
                    ];
                }, $streams);
            });

            // Get addresses - cache for 60 seconds (addresses don't change often)
            $addressesList = Cache::remember('blockchain:addresses', 60, function () {
                $addresses = $this->multichainService->getAddresses();

                return array_map(function ($address) {
                    return [
                        'address' => $address,
                        'ismine' => true,
                    ];
                }, $addresses);
            });

            // Get health status
            $health = $this->healthService->getHealthStatus();

            return Inertia::render('admin/blockchain-explorer', [
                'overview' => [
                    'chain' => $nodeInfo['chainname'] ?? 'Unknown',
                    'protocol' => $nodeInfo['protocol'] ?? 'Unknown',
                    'blocks' => $blockchainInfo['blocks'],
                    'difficulty' => $blockchainInfo['difficulty'] ?? 0,
                    'connections' => $networkInfo['connections'] ?? 0,
                    'version' => $nodeInfo['version'] ?? 'Unknown',
                    'nodeaddress' => $nodeInfo['nodeaddress'] ?? 'Unknown',
                ],
                'latestBlocks' => $latestBlocks,
                'streams' => $streamsList,
                'addresses' => $addressesList,
                'peers' => array_map(function ($peer) {
                    return [
                        'id' => $peer['id'] ?? 0,
                        'addr' => $peer['addr'] ?? 'Unknown',
                        'addrlocal' => $peer['addrlocal'] ?? null,
                        'services' => $peer['services'] ?? '0000000000000000',
                        'relaytxes' => $peer['relaytxes'] ?? true,
                        'lastsend' => $peer['lastsend'] ?? 0,
                        'lastrecv' => $peer['lastrecv'] ?? 0,
                        'bytessent' => $peer['bytessent'] ?? 0,
                        'bytesrecv' => $peer['bytesrecv'] ?? 0,
                        'conntime' => $peer['conntime'] ?? 0,
                        'timeoffset' => $peer['timeoffset'] ?? 0,
                        'pingtime' => $peer['pingtime'] ?? null,
                        'minping' => $peer['minping'] ?? null,
                        'pingwait' => $peer['pingwait'] ?? null,
                        'version' => $peer['version'] ?? 0,
                        'subver' => $peer['subver'] ?? 'Unknown',
                        'inbound' => $peer['inbound'] ?? false,
                        'startingheight' => $peer['startingheight'] ?? 0,
                        'banscore' => $peer['banscore'] ?? 0,
                        'synced_headers' => $peer['synced_headers'] ?? 0,
                        'synced_blocks' => $peer['synced_blocks'] ?? 0,
                        'inflight' => $peer['inflight'] ?? [],
                        'whitelisted' => $peer['whitelisted'] ?? false,
                        'minfeefilter' => $peer['minfeefilter'] ?? 0,
                        'bytesrecv_per_msg' => $peer['bytesrecv_per_msg'] ?? (object) [],
                        'bytesent_per_msg' => $peer['bytesent_per_msg'] ?? (object) [],
                    ];
                }, $peerInfo),
                'health' => $health,
            ]);
        } catch (Exception $e) {
            Log::error('Blockchain explorer error', [
                'error' => $e->getMessage(),
            ]);

            // Still get health status even if blockchain connection fails
            try {
                $health = $this->healthService->getHealthStatus();
            } catch (Exception $healthError) {
                $health = null;
            }

            return Inertia::render('admin/blockchain-explorer', [
                'error' => 'Failed to connect to blockchain node: '.$e->getMessage(),
                'overview' => null,
                'latestBlocks' => [],
                'streams' => [],
                'addresses' => [],
                'peers' => [],
                'health' => $health,
            ]);
        }
    }

    /**
     * Get block details
     */
    public function getBlock(Request $request): array
    {
        try {
            $hashOrHeight = $request->input('block');

            if (! $hashOrHeight) {
                throw new Exception('Block hash or height is required');
            }

            $block = $this->multichainService->getBlock($hashOrHeight, 4);

            return [
                'success' => true,
                'block' => $block,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction(Request $request): array
    {
        try {
            $txid = $request->input('txid');

            if (! $txid) {
                throw new Exception('Transaction ID is required');
            }

            $transaction = $this->multichainService->getRawTransaction($txid, true);

            return [
                'success' => true,
                'transaction' => $transaction,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get stream items
     */
    public function getStreamItems(Request $request, string $streamName): array
    {
        try {
            $count = $request->input('count', 100);
            $start = $request->input('start', -100);

            $items = $this->multichainService->listStreamItems($streamName, true, $count, $start);

            return [
                'success' => true,
                'items' => $items,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get address details
     */
    public function getAddress(Request $request, string $address): array
    {
        try {
            $addressInfo = $this->multichainService->validateAddress($address);
            $addressDetails = $this->multichainService->listAddresses($address, true);

            return [
                'success' => true,
                'address' => array_merge($addressInfo, $addressDetails[0] ?? []),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search blockchain
     */
    public function search(Request $request): array
    {
        try {
            $query = $request->input('query');

            if (! $query) {
                throw new Exception('Search query is required');
            }

            $results = [];

            // Try as block height
            if (is_numeric($query)) {
                try {
                    $block = $this->multichainService->getBlock((int) $query, 1);
                    $results['block'] = $block;
                } catch (Exception $e) {
                    // Not a valid block height
                }
            }

            // Try as block hash
            try {
                $block = $this->multichainService->getBlock($query, 1);
                $results['block'] = $block;
            } catch (Exception $e) {
                // Not a valid block hash
            }

            // Try as transaction
            try {
                $tx = $this->multichainService->getRawTransaction($query, true);
                $results['transaction'] = $tx;
            } catch (Exception $e) {
                // Not a valid transaction
            }

            // Try as address
            try {
                $address = $this->multichainService->validateAddress($query);
                if ($address['isvalid']) {
                    $results['address'] = $address;
                }
            } catch (Exception $e) {
                // Not a valid address
            }

            return [
                'success' => true,
                'results' => $results,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reset circuit breaker (admin only)
     */
    public function resetCircuitBreaker(): RedirectResponse
    {
        $this->healthService->resetCircuitBreaker();

        return redirect()->back()->with('success', 'Circuit breaker has been reset successfully.');
    }
}
