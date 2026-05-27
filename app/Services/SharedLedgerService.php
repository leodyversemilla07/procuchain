<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\LedgerEntryData;
use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Shared Ledger Service
 *
 * Handles all blockchain data retrieval, filtering, pagination,
 * and purge detection for the shared ledger view.
 *
 * Supports node-based filtering:
 * - "all" (default): merged data from all nodes, deduplicated by txid
 * - Specific node (e.g. "admin", "bac-secretariat"): data from that node's perspective
 */
class SharedLedgerService
{
    /** Streams to include in the shared ledger. */
    private const LEDGER_STREAMS = [
    StreamEnums::METADATA->value,
    StreamEnums::STATUS->value,
    StreamEnums::DOCUMENTS->value,
    StreamEnums::CORRECTIONS->value,
    StreamEnums::PROCUREMENTS_CORRECTIONS->value,
    StreamEnums::ARCHIVE->value,
    StreamEnums::EVENTS->value,
    StreamEnums::FILE_METADATA->value,
    ];

    /**
    * Stream used for purge/resync detection (also included in LEDGER_STREAMS
    * for full visibility — file upload metadata, node purge/resync events).
    */
    private const PURGE_CHECK_STREAM = StreamEnums::FILE_METADATA->value;

    /** Items per page. */
    private const PER_PAGE = 50;

    /** @var array{is_purged: bool, partially_purged: bool, unsubscribed_streams: string[]}|null */
    public ?array $nodePurgeState = null;

    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Get the ledger entries with filters applied, paginated.
     *
     * @return array{entries: array, pagination: array, available_streams: array, stream_totals: array, available_nodes: array, node_purge_state: array|null}
     */
    public function getLedgerPage(array $filters): array
    {
        $this->nodePurgeState = null;
        $selectedNode = $filters['node'] ?? 'all';

        $entries = $this->fetchLedgerEntries($selectedNode);

        // Apply filters
        if (! empty($filters['pr_number'])) {
            $search = strtolower($filters['pr_number']);
            $entries = array_filter($entries, fn (LedgerEntryData $e) => str_contains(strtolower($e->prNumber), $search));
        }

        if (! empty($filters['stream'])) {
            $stream = $filters['stream'];
            $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->stream === $stream);
        }

        if (! empty($filters['date_from'])) {
            $from = strtotime($filters['date_from']);
            $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() >= $from);
        }

        if (! empty($filters['date_to'])) {
            $to = strtotime($filters['date_to'].' 23:59:59');
            $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() <= $to);
        }

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $entries = array_filter($entries, fn (LedgerEntryData $e) =>
                str_contains(strtolower($e->prNumber), $search)
                || str_contains(strtolower($e->summary), $search)
                || str_contains(strtolower($e->action), $search)
                || str_contains(strtolower($e->txid), $search)
            );
        }

        // Sort newest first
        usort($entries, fn (LedgerEntryData $a, LedgerEntryData $b) => $b->getSortableTimestamp() <=> $a->getSortableTimestamp());

        // Paginate
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = count($entries);
        $offset = ($page - 1) * self::PER_PAGE;
        $items = array_slice($entries, $offset, self::PER_PAGE);

        // Map to arrays for Inertia
        $mapped = array_map(fn (LedgerEntryData $e) => [
            'timestamp' => $e->timestamp,
            'formatted_timestamp' => $e->getFormattedTimestamp(),
            'stream' => $e->stream,
            'stream_display' => $this->getStreamDisplayName($e->stream),
            'key' => $e->key,
            'pr_number' => $e->prNumber,
            'action' => $e->action,
            'summary' => $e->summary,
            'actor_address' => $e->actorAddress,
            'txid' => $e->txid,
            'raw_json' => $e->rawJson,
            'procurement_title' => $e->procurementTitle,
            'old_values' => $e->oldValues,
            'new_values' => $e->newValues,
            'original_txid' => $e->originalTxid,
        ], $items);

        // Build available streams list
        $seen = [];
        $availableStreams = [];
        foreach ($entries as $e) {
            if (! isset($seen[$e->stream])) {
                $seen[$e->stream] = true;
                $availableStreams[] = [
                    'value' => $e->stream,
                    'label' => $this->getStreamDisplayName($e->stream),
                ];
            }
        }
        usort($availableStreams, fn ($a, $b) => strcmp($a['label'], $b['label']));

        // Stream totals
        $streamTotals = [];
        foreach ($entries as $e) {
            $streamTotals[$e->stream] = ($streamTotals[$e->stream] ?? 0) + 1;
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
            'available_nodes' => $this->buildAvailableNodesList(),
            'node_purge_state' => $this->nodePurgeState,
        ];
    }

    /**
     * Get empty ledger response (for error fallback).
     */
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
            'available_nodes' => collect($this->getNodes())->map(fn ($node) => [
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

    /**
     * Fetch all entries from all ledger streams.
     *
     * @param string $nodeId 'all' to merge from all nodes, or a specific node ID
     * @return LedgerEntryData[]
     */
    private function fetchLedgerEntries(string $nodeId = 'all'): array
    {
        Log::info('SharedLedger: fetchLedgerEntries called', ['node_id' => $nodeId]);

        if ($nodeId === 'all') {
            return $this->fetchFromAllNodes();
        }

        return $this->fetchFromNode($nodeId);
    }

    /**
     * Fetch ledger entries from a specific node by ID.
     *
     * @return LedgerEntryData[]
     */
    private function fetchFromNode(string $nodeId): array
    {
        Log::info('SharedLedger: fetchFromNode called', ['node_id' => $nodeId]);

        $nodeConfig = collect($this->getNodes())->first(fn ($n) => $n['id'] === $nodeId);

        if ($nodeConfig === null) {
            Log::warning('SharedLedger: Node not found in config, falling back to default', ['node_id' => $nodeId]);

            return $this->fetchFromDefaultClient();
        }

        // FIRST: Check purge state from the PRIMARY node (not the target node).
        // After a real SSM purge, the target node has 0 blocks and can't read on-chain data.
        // The primary node still has all data, including purge/resync event records.
        $purgeState = $this->checkPurgeStateFromPrimary($nodeId);

        if ($purgeState['is_purged']) {
            Log::info('SharedLedger: Node is purged (detected via primary node)', [
                'node_id' => $nodeId,
                'was_explicitly_purged' => $purgeState['was_explicitly_purged'],
            ]);

            $this->nodePurgeState = $purgeState;

            return [];
        }

        try {
            $client = $this->createNodeClient($nodeConfig);
            $client->setTimeout(3);

            // Quick connectivity check
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

            // -703 = not subscribed, -708 = stream not found (node has 0 blocks after real purge)
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
                    $entries[] = LedgerEntryData::fromStreamItem($stream, $item);
                } catch (Exception $e) {
                    report($e);
                }
            }
        }

        // Node is reachable — set purge state based on subscription status
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

        // Node operation events from file.metadata are now included via LEDGER_STREAMS

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

    /**
     * Check purge state from the PRIMARY node (not the target node).
     *
     * After a real SSM purge, the target node has 0 blocks and cannot
     * read on-chain events. The primary node still has all data.
     * We check the file.metadata stream for purge/resync event keys.
     *
     * @return array{is_purged: bool, was_explicitly_purged: bool, partially_purged: bool, unsubscribed_streams: string[], purge_reason: string|null, purge_timestamp: int|null, connection_error: bool, connection_error_message: string|null}
     */
    private function checkPurgeStateFromPrimary(string $nodeId): array
    {
        $default = [
            'is_purged' => false,
            'was_explicitly_purged' => false,
            'partially_purged' => false,
            'unsubscribed_streams' => [],
            'purge_reason' => null,
            'purge_timestamp' => null,
            'connection_error' => false,
            'connection_error_message' => null,
        ];

        try {
            // Use the primary (default) Manager client — it always has data
            $purgeKey = 'node_'.$nodeId.'_full_purge';
            $purgeItems = $this->multichain->liststreamkeyitems(
                self::PURGE_CHECK_STREAM,
                $purgeKey,
                false,
                1,
                -1,
                false
            );

            if (! $this->multichain->success() || ! is_array($purgeItems) || count($purgeItems) === 0) {
                return $default;
            }

            // Found a purge event — check if there's a more recent resync
            $resyncKey = 'node_'.$nodeId.'_resync';
            $resyncItems = $this->multichain->liststreamkeyitems(
                self::PURGE_CHECK_STREAM,
                $resyncKey,
                false,
                1,
                -1,
                false
            );

            $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
            $resyncBlock = ($this->multichain->success() && is_array($resyncItems) && count($resyncItems) > 0)
                ? ($resyncItems[0]['blocktime'] ?? 0)
                : 0;

            // Use > (not >=): when purge and resync are published in the same block,
            // they share the same blocktime. A resync at the same blocktime as a purge
            // means the node has already recovered — it should NOT show as purged.
            $isPurged = $purgeBlock > $resyncBlock;

            if (! $isPurged) {
            return $default;
            }

            $purgeData = $purgeItems[0]['data']['json'] ?? [];

        return [
            'is_purged' => true,
            'was_explicitly_purged' => true,
            'partially_purged' => false,
            'unsubscribed_streams' => [], // Full purge — banner shows "all data removed", not individual streams
            'purge_reason' => $purgeData['reason'] ?? 'Node data physically deleted (SSM purge)',
            'purge_timestamp' => $purgeBlock,
            'connection_error' => false,
            'connection_error_message' => null,
        ];
        } catch (Exception $e) {
            Log::warning("SharedLedger: Failed to check purge state from primary for node {$nodeId}", [
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Fetch from all nodes and merge, deduplicating by txid.
     *
     * @return LedgerEntryData[]
     */
    private function fetchFromAllNodes(): array
    {
        $allEntries = [];
        $seenTxids = [];

        foreach ($this->getNodes() as $nodeConfig) {
            try {
                $client = $this->createNodeClient($nodeConfig);
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
                    if (! isset($seenTxids[$entry->txid])) {
                        $seenTxids[$entry->txid] = true;
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

        /**
        * Fetch entries using the default Manager (singleton) connection.
        *
        * @return LedgerEntryData[]
        */
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
                        $entries[] = LedgerEntryData::fromStreamItem($stream, $item);
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

        // Node operation events from file.metadata are now included via LEDGER_STREAMS

        return $entries;
        }

        /**
        * Fetch entries from a specific Client instance.
     *
     * @return LedgerEntryData[]
     */
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
                        $entries[] = LedgerEntryData::fromStreamItem($stream, $item);
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

        // Node operation events from file.metadata are now included via LEDGER_STREAMS

        return $entries;
        }

        /**
        * Ensure the client's node is subscribed to all ledger streams.
     * Idempotent — subscribing to an already-subscribed stream is a no-op.
     * Skips nodes that were intentionally purged.
     */
    private function ensureSubscribed(Client $client, string $nodeId = ''): void
    {
        if ($nodeId && $this->isNodePurged($nodeId, $client)) {
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

    /**
     * Check if a node has been explicitly purged by looking for a
     * full_node_purge event on-chain.
     */
    private function isNodePurged(string $nodeId, ?Client $client = null): bool
    {
        try {
            $purgeKey = 'node_'.$nodeId.'_full_purge';

            if ($client) {
                $purgeItems = $client->liststreamkeyitems(self::PURGE_CHECK_STREAM, $purgeKey, false, 1, -1, false);
                $success = $client->success();
            } else {
                $purgeItems = $this->multichain->liststreamkeyitems(self::PURGE_CHECK_STREAM, $purgeKey, false, 1, -1, false);
                $success = $this->multichain->success();
            }

            if ($success && is_array($purgeItems) && count($purgeItems) > 0) {
                $resyncKey = 'node_'.$nodeId.'_resync';

                if ($client) {
                    $resyncItems = $client->liststreamkeyitems(self::PURGE_CHECK_STREAM, $resyncKey, false, 1, -1, false);
                    $resyncSuccess = $client->success();
                } else {
                    $resyncItems = $this->multichain->liststreamkeyitems(self::PURGE_CHECK_STREAM, $resyncKey, false, 1, -1, false);
                    $resyncSuccess = $this->multichain->success();
                }

                if ($resyncSuccess && is_array($resyncItems) && count($resyncItems) > 0) {
                $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
                $resyncBlock = $resyncItems[0]['blocktime'] ?? 0;

                // Use > (not >=): same-block purge+resync means node has recovered
                return $purgeBlock > $resyncBlock;
                }

                return true;
            }
        } catch (Exception $e) {
            Log::warning("SharedLedger: Failed to check purge status for node {$nodeId}", [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Build the available nodes list with purge status.
     * Cached for 60 seconds.
     *
     * @return array<int, array{id: string, name: string, role: string, is_purged: bool}>
     */
    private function buildAvailableNodesList(): array
    {
        return Cache::remember('shared_ledger:available_nodes', 60, function () {
            return $this->buildAvailableNodesListUncached();
        });
    }

    private function buildAvailableNodesListUncached(): array
    {
        // Pre-load purge states from primary node for ALL nodes at once
        // (more efficient than checking each node individually)
        $purgeStates = [];
        foreach ($this->getNodes() as $node) {
            $purgeStates[$node['id']] = $this->checkPurgeStateFromPrimary($node['id'])['is_purged'];
        }

        return collect($this->getNodes())->map(function ($node) use ($purgeStates) {
            return [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
                'is_purged' => $purgeStates[$node['id']] ?? false,
            ];
        })->values()->toArray();
    }

    /**
     * Create a configured MultiChain Client for a specific node.
     */
    private function createNodeClient(array $nodeConfig): Client
    {
        $client = new Client(
            $nodeConfig['private_ip'],
            $nodeConfig['rpc_port'],
            $this->getRpcUser(),
            $this->getRpcPassword(),
            false
        );
        $client->setoption('chain_name', config('multichain.chain_name'));
        $client->setoption('use_curl', true);
        $client->setoption('verify_ssl', false);

        return $client;
    }

    /**
     * Get a human-readable display name for a stream.
     */
    private function getStreamDisplayName(string $stream): string
    {
        return match ($stream) {
            'procurement.metadata' => 'Metadata',
            'procurement.status' => 'Status',
            'procurement.documents' => 'Documents',
            'procurement.corrections' => 'Document Corrections',
            'procurement.metadata.corrections' => 'Metadata Corrections',
            'procurement.archive' => 'Archive',
            'procurement.events' => 'Events',
            'file.data' => 'File Data',
            'file.metadata' => 'File Metadata',
            'file.chunks' => 'File Chunks',
            default => $stream,
        };
    }

    private function getRpcUser(): string
    {
        return config('multichain.rpc.username', 'multichainrpc');
    }

    private function getRpcPassword(): string
    {
        return config('multichain.rpc.password');
    }

    /**
     * @return array<int, array{id: string, name: string, role: string, private_ip: string, rpc_port: int}>
     */
    private function getNodes(): array
    {
        return config('multichain.nodes', []);
    }
}
