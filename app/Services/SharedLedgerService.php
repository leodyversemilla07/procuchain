<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Libraries\MultiChain\Client;
use App\Support\NodeClientFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class SharedLedgerService
{
    private const LEDGER_STREAMS = [
        Stream::METADATA->value,
        Stream::STATUS->value,
        Stream::DOCUMENTS->value,
        Stream::CORRECTIONS->value,
        Stream::PROCUREMENTS_CORRECTIONS->value,
        Stream::ARCHIVE->value,
        Stream::EVENTS->value,
        Stream::FILE_METADATA->value,
    ];

    private const PER_PAGE = 50;

    public ?array $nodePurgeState = null;

    public function __construct(
        private BlockchainRpcClient $multichain,
        private LedgerEntryBuilder $ledgerEntryBuilder = new LedgerEntryBuilder,
        private ?NodePurgeDetector $purgeDetector = null,
    ) {
        $this->purgeDetector ??= new NodePurgeDetector($multichain);
    }

    public function getLedgerPage(array $filters): array
    {
        $this->nodePurgeState = null;
        $selectedNode = $filters['node'] ?? 'all';

        $entries = $this->fetchLedgerEntries($selectedNode);

        if (! empty($filters['pr_number'])) {
            $search = strtolower($filters['pr_number']);
            $entries = array_filter($entries, fn (array $e) => str_contains(strtolower($e['pr_number']), $search));
        }

        if (! empty($filters['stream'])) {
            $stream = $filters['stream'];
            $entries = array_filter($entries, fn (array $e) => $e['stream'] === $stream);
        }

        if (! empty($filters['date_from'])) {
            $from = strtotime($filters['date_from']);
            $entries = array_filter($entries, fn (array $e) => $e['sortable_timestamp'] >= $from);
        }

        if (! empty($filters['date_to'])) {
            $to = strtotime($filters['date_to'].' 23:59:59');
            $entries = array_filter($entries, fn (array $e) => $e['sortable_timestamp'] <= $to);
        }

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $entries = array_filter($entries, fn (array $e) => str_contains(strtolower($e['pr_number']), $search)
                || str_contains(strtolower($e['summary']), $search)
                || str_contains(strtolower($e['action']), $search)
                || str_contains(strtolower($e['txid']), $search)
            );
        }

        usort($entries, fn (array $a, array $b) => $b['sortable_timestamp'] <=> $a['sortable_timestamp']);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = count($entries);
        $offset = ($page - 1) * self::PER_PAGE;
        $items = array_slice($entries, $offset, self::PER_PAGE);

        $mapped = array_map(fn (array $e) => [
            'timestamp' => $e['timestamp'],
            'formatted_timestamp' => $e['formatted_timestamp'],
            'stream' => $e['stream'],
            'stream_display' => $this->ledgerEntryBuilder->getStreamDisplayName($e['stream']),
            'key' => $e['key'],
            'pr_number' => $e['pr_number'],
            'action' => $e['action'],
            'summary' => $e['summary'],
            'actor_address' => $e['actor_address'],
            'txid' => $e['txid'],
            'raw_json' => $e['raw_json'],
            'procurement_title' => $e['procurement_title'],
            'old_values' => $e['old_values'],
            'new_values' => $e['new_values'],
            'original_txid' => $e['original_txid'],
        ], $items);

        $seen = [];
        $availableStreams = [];
        foreach ($entries as $e) {
            if (! isset($seen[$e['stream']])) {
                $seen[$e['stream']] = true;
                $availableStreams[] = [
                    'value' => $e['stream'],
                    'label' => $this->ledgerEntryBuilder->getStreamDisplayName($e['stream']),
                ];
            }
        }
        usort($availableStreams, fn ($a, $b) => strcmp($a['label'], $b['label']));

        $streamTotals = [];
        foreach ($entries as $e) {
            $streamTotals[$e['stream']] = ($streamTotals[$e['stream']] ?? 0) + 1;
        }

        return [
            'entries' => $mapped,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
                'per_page' => self::PER_PAGE,
                'total' => $total,
            ],
            'available_streams' => $availableStreams,
            'stream_totals' => $streamTotals,
            'available_nodes' => $this->purgeDetector->buildAvailableNodesList(),
            'node_purge_state' => $this->nodePurgeState,
        ];
    }

    public function getEmptyLedgerPage(array $filters, ?string $errorMessage = null): array
    {
        $selectedNode = $filters['node'] ?? 'all';

        return [
            'entries' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => self::PER_PAGE,
                'total' => 0,
            ],
            'available_streams' => [],
            'stream_totals' => [],
            'available_nodes' => collect(NodeClientFactory::getNodes())->map(fn ($node) => [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
            ])->values()->toArray(),
            'node_purge_state' => null,
            'selected_node' => $selectedNode,
            'filters' => $filters,
            'error' => $errorMessage ?? 'Failed to load the shared ledger. The blockchain node may be unavailable. Please try again.',
        ];
    }

    private function fetchLedgerEntries(string $nodeId = 'all'): array
    {
        Log::info('SharedLedger: fetchLedgerEntries called', ['node_id' => $nodeId]);

        if ($nodeId === 'all') {
            return $this->fetchFromAllNodes();
        }

        return $this->fetchFromNode($nodeId);
    }

    private function fetchFromNode(string $nodeId): array
    {
        Log::info('SharedLedger: fetchFromNode called', ['node_id' => $nodeId]);

        $nodeConfig = collect(NodeClientFactory::getNodes())->first(fn ($n) => $n['id'] === $nodeId);

        if ($nodeConfig === null) {
            Log::warning('SharedLedger: Node not found in config, falling back to default', ['node_id' => $nodeId]);

            return $this->fetchFromDefaultClient();
        }

        $purgeState = $this->purgeDetector->checkPurgeStateFromPrimary($nodeId);

        if ($purgeState['is_purged']) {
            Log::info('SharedLedger: Node is purged (detected via primary node) — returning empty entries', [
                'node_id' => $nodeId,
                'was_explicitly_purged' => $purgeState['was_explicitly_purged'],
            ]);

            $this->nodePurgeState = $purgeState;

            return [];
        }

        try {
            $client = NodeClientFactory::createNodeClient($nodeConfig);
            $client->setTimeout(3);

            $client->getinfo();
            if (! $client->success()) {
                $errCode = $client->errorcode();
                $errMsg = $client->errormessage();
                Log::warning('SharedLedger: Node connectivity check failed', [
                    'node_id' => $nodeId,
                    'error_code' => $errCode,
                    'error_message' => $errMsg,
                ]);

                $this->nodePurgeState = [
                    'is_purged' => false,
                    'was_explicitly_purged' => false,
                    'partially_purged' => false,
                    'unsubscribed_streams' => [],
                    'purge_reason' => null,
                    'purge_timestamp' => null,
                    'connection_error' => true,
                    'connection_error_message' => "Node '{$nodeId}' is unreachable: {$errMsg}",
                ];

                return [];
            }

            $client->setTimeout(15);

            $entries = [];
            $unsubscribedStreams = [];

            foreach (self::LEDGER_STREAMS as $stream) {
                $items = $client->liststreamitems($stream, true, 5000, 0, false);
                $errorCode = $client->errorcode();
                $success = $client->success();

                Log::info('SharedLedger: liststreamitems result', [
                    'node' => $nodeId,
                    'stream' => $stream,
                    'success' => $success,
                    'error_code' => $errorCode,
                    'items_count' => is_array($items) ? count($items) : 'null',
                ]);

                if (! $success && ($errorCode === -703 || $errorCode === -708)) {
                    $unsubscribedStreams[] = $stream;

                    continue;
                }

                if (! $items || ! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! isset($item['data']['json'])) {
                        continue;
                    }

                    try {
                        $entries[] = $this->ledgerEntryBuilder->fromStreamItem($stream, $item);
                    } catch (Exception $e) {
                        report($e);
                    }
                }
            }

            $hasPartialPurge = count($unsubscribedStreams) > 0 && count($unsubscribedStreams) < count(self::LEDGER_STREAMS);
            $this->nodePurgeState = [
                'is_purged' => false,
                'was_explicitly_purged' => false,
                'partially_purged' => $hasPartialPurge,
                'unsubscribed_streams' => $unsubscribedStreams,
                'purge_reason' => null,
                'purge_timestamp' => null,
                'connection_error' => false,
                'connection_error_message' => null,
            ];

            Log::info('SharedLedger: Node data loaded', [
                'node' => $nodeId,
                'entries_count' => count($entries),
            ]);

            return $entries;
        } catch (Exception $e) {
            report($e);
            Log::warning("SharedLedger: Failed to connect to node {$nodeId}", [
                'error' => $e->getMessage(),
            ]);

            $this->nodePurgeState = [
                'is_purged' => false,
                'was_explicitly_purged' => false,
                'partially_purged' => false,
                'unsubscribed_streams' => [],
                'purge_reason' => null,
                'purge_timestamp' => null,
                'connection_error' => true,
                'connection_error_message' => "Node '{$nodeId}' connection failed: {$e->getMessage()}",
            ];

            return [];
        }
    }

    private function fetchFromAllNodes(): array
    {
        $allEntries = [];
        $seenTxids = [];

        foreach (NodeClientFactory::getNodes() as $nodeConfig) {
            try {
                $purgeState = $this->purgeDetector->checkPurgeStateFromPrimary($nodeConfig['id']);
                if ($purgeState['is_purged']) {
                    Log::info('SharedLedger: Skipping purged node in all-nodes fetch', [
                        'node_id' => $nodeConfig['id'],
                    ]);

                    continue;
                }

                $client = NodeClientFactory::createNodeClient($nodeConfig);
                $client->setTimeout(3);

                $client->getinfo();
                if (! $client->success()) {
                    Log::info('SharedLedger: Skipping unreachable node in all-nodes fetch', [
                        'node_id' => $nodeConfig['id'],
                        'error' => $client->errormessage(),
                    ]);

                    continue;
                }

                $client->setTimeout(15);

                $this->ensureSubscribed($client, $nodeConfig['id']);

                $nodeEntries = $this->fetchEntriesFromClient($client);

                foreach ($nodeEntries as $entry) {
                    if (! isset($seenTxids[$entry['txid']])) {
                        $seenTxids[$entry['txid']] = true;
                        $allEntries[] = $entry;
                    }
                }
            } catch (Exception $e) {
                report($e);
                Log::warning("SharedLedger: Failed to query node {$nodeConfig['id']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allEntries)) {
            return $this->fetchFromDefaultClient();
        }

        return $allEntries;
    }

    private function fetchFromDefaultClient(): array
    {
        $entries = [];

        foreach (self::LEDGER_STREAMS as $stream) {
            try {
                $items = $this->multichain->liststreamitems($stream, true, 5000, 0, false);

                if (! $items || ! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! isset($item['data']['json'])) {
                        continue;
                    }

                    try {
                        $entries[] = $this->ledgerEntryBuilder->fromStreamItem($stream, $item);
                    } catch (Exception $e) {
                        report($e);
                        Log::warning('SharedLedger: Skipping invalid stream item', [
                            'stream' => $stream,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                report($e);
                Log::warning('SharedLedger: Failed to read stream', [
                    'stream' => $stream,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $entries;
    }

    private function fetchEntriesFromClient(Client $client): array
    {
        $entries = [];

        foreach (self::LEDGER_STREAMS as $stream) {
            try {
                $items = $client->liststreamitems($stream, true, 5000, 0, false);

                if (! $items || ! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! isset($item['data']['json'])) {
                        continue;
                    }

                    try {
                        $entries[] = $this->ledgerEntryBuilder->fromStreamItem($stream, $item);
                    } catch (Exception $e) {
                        report($e);
                        Log::warning('SharedLedger: Skipping invalid stream item', [
                            'stream' => $stream,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                report($e);
                Log::warning('SharedLedger: Failed to read stream from client', [
                    'stream' => $stream,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $entries;
    }

    private function ensureSubscribed(Client $client, string $nodeId = ''): void
    {
        if ($nodeId && $this->purgeDetector->isNodePurged($nodeId, $client)) {
            Log::info("SharedLedger: Skipping ensureSubscribed for purged node '{$nodeId}'");

            return;
        }

        foreach (self::LEDGER_STREAMS as $stream) {
            $client->liststreamitems($stream, false, 1, 0, false);

            if ($client->success()) {
                continue;
            }

            $client->subscribe($stream, true);

            if ($client->success()) {
                Log::info("SharedLedger: Auto-subscribed node to stream {$stream} with rescan");
            } else {
                Log::warning("SharedLedger: Failed to auto-subscribe to stream {$stream}: [{$client->errorcode()}] {$client->errormessage()}");
            }
        }
    }
}
