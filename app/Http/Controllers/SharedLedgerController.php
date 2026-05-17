<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\LedgerEntryData;
use App\Enums\StreamEnums;
use App\Libraries\MultiChain\Client;
use App\Services\Manager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Ledger Controller
 *
 * The true shared ledger: every meaningful blockchain transaction
 * across ALL streams, aggregated into a single chronological view.
 *
 * Supports node-based filtering:
 * - "all" (default): merged data from all nodes, deduplicated by txid
 * - Specific node (e.g. "admin", "bac-secretariat"): data from that node's perspective
 */
class SharedLedgerController extends Controller
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
 StreamEnums::FILE_DATA->value,
 StreamEnums::FILE_METADATA->value,
 StreamEnums::FILE_CHUNKS->value,
 ];

    /** @var array{is_purged: bool, partially_purged: bool, unsubscribed_streams: string[]}|null */
    private ?array $nodePurgeState = null;

    /** Items per page. */
    private const PER_PAGE = 50;

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
     * @return array<int, array{id: string, name: string, role: string, private_ip: string, rpc_port: int}>
     */
    private function getNodes(): array
    {
        return config('multichain.nodes', []);
    }

    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Display the shared ledger page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-shared-ledger');

        // Reset purge state for each request
        $this->nodePurgeState = null;

        try {
            $selectedNode = $request->string('node', 'all')->toString();

            // Fetch entries from the selected node(s)
            $entries = $this->fetchLedgerEntries($selectedNode);

            // Apply filters
            if ($request->filled('pr_number')) {
                $search = strtolower((string) $request->string('pr_number'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => str_contains(strtolower($e->prNumber), $search)
                );
            }

            if ($request->filled('stream')) {
                $stream = (string) $request->string('stream');
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->stream === $stream
                );
            }

            if ($request->filled('date_from')) {
                $from = strtotime((string) $request->string('date_from'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() >= $from
                );
            }

            if ($request->filled('date_to')) {
                $to = strtotime((string) $request->string('date_to').' 23:59:59');
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() <= $to
                );
            }

            if ($request->filled('search')) {
                $search = strtolower((string) $request->string('search'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => str_contains(strtolower($e->prNumber), $search)
                    || str_contains(strtolower($e->summary), $search)
                    || str_contains(strtolower($e->action), $search)
                    || str_contains(strtolower($e->txid), $search)
                );
            }

            // Sort by timestamp descending (newest first)
            usort($entries, fn (LedgerEntryData $a, LedgerEntryData $b) => $b->getSortableTimestamp() <=> $a->getSortableTimestamp()
            );

            // Paginate
            $page = max(1, $request->integer('page', 1));
            $total = count($entries);
            $offset = ($page - 1) * self::PER_PAGE;
            $items = array_slice($entries, $offset, self::PER_PAGE);

            // Map to array for Inertia
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

            // Get distinct streams that had items
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

            // Get totals per stream for summary stats
            $streamTotals = [];
            foreach ($entries as $e) {
                $streamTotals[$e->stream] = ($streamTotals[$e->stream] ?? 0) + 1;
            }

            // Build available nodes list
            $availableNodes = collect($this->getNodes())->map(fn ($node) => [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
            ])->values()->toArray();

            return Inertia::render('shared-ledger', [
                'entries' => $mapped,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
                    'per_page' => self::PER_PAGE,
                    'total' => $total,
                ],
                'available_streams' => $availableStreams,
                'stream_totals' => $streamTotals,
                'available_nodes' => $availableNodes,
                'selected_node' => $selectedNode,
                'node_purge_state' => $this->nodePurgeState,
                'filters' => $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'node', 'page']),
            ]);
        } catch (Exception $e) {
            report($e);
            Log::error('SharedLedger: Failed to fetch ledger entries', [
                'error' => 'An error occurred loading the shared ledger.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return Inertia::render('shared-ledger', [
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
                'selected_node' => 'all',
                'node_purge_state' => null,
                'filters' => $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'node', 'page']),
                'error' => 'Failed to load the shared ledger. The blockchain node may be unavailable. Please try again.',
            ]);
        }
    }

    /**
     * Fetch all entries from all ledger streams.
     *
     * @param  string  $nodeId  'all' to merge from all nodes, or a specific node ID
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

        try {
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
            $client->setTimeout(15);

            // Detect purge state by probing each stream.
            // MultiChain's getstreaminfo may NOT include a 'subscribed' field
            // on unsubscribed nodes (it's simply omitted, not set to false).
            // The reliable check is to try liststreamitems — error -703
            // means "Not subscribed to this stream".
            $unsubscribedStreams = [];
            $entries = [];

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

 if (! $success && $errorCode === -703) {
 // Stream is unsubscribed (purged) on this node
 $unsubscribedStreams[] = $stream;

 continue;
 }

 if (! $success && $errorCode === -708) {
 // Stream does not exist on this node — skip without marking as purged
 Log::info('SharedLedger: Stream not found on node, skipping', [
 'node' => $nodeId,
 'stream' => $stream,
 ]);

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

            // Determine purge state
            $allUnsubscribed = count($unsubscribedStreams) === count(self::LEDGER_STREAMS);
            $partiallyPurged = ! $allUnsubscribed && count($unsubscribedStreams) > 0;

            // Distinguish "was purged" from "never subscribed" by checking
            // if a full_node_purge event exists on-chain for this node.
            // If all streams return -703 but no purge event exists, the node
            // was likely never populated (not purged).
            $wasExplicitlyPurged = false;
            $purgeReason = null;
            $purgeTimestamp = null;

            if ($allUnsubscribed) {
                try {
                    $purgeKey = 'node_'.$nodeId.'_full_purge';
                    $purgeItems = $this->multichain->liststreamkeyitems(
                        'file.metadata',
                        $purgeKey,
                        false,
                        1,
                        0,
                        false
                    );

                    if ($this->multichain->success() && is_array($purgeItems) && count($purgeItems) > 0) {
                        $wasExplicitlyPurged = true;
                        $purgeData = $purgeItems[0]['data']['json'] ?? [];
                        $purgeReason = $purgeData['reason'] ?? null;
                        $purgeTimestamp = $purgeItems[0]['blocktime'] ?? $purgeData['purged_at'] ?? null;
                    }
                } catch (Exception $e) {
                    Log::warning('SharedLedger: Failed to check purge event', [
                        'node' => $nodeId,
                        'error' => 'An error occurred loading the shared ledger.',
                    ]);
                }
            }

            Log::info('SharedLedger: Node purge state determined', [
                'node' => $nodeId,
                'is_purged' => $allUnsubscribed,
                'was_explicitly_purged' => $wasExplicitlyPurged,
                'partially_purged' => $partiallyPurged,
                'unsubscribed_count' => count($unsubscribedStreams),
                'entries_count' => count($entries),
            ]);

            $this->nodePurgeState = [
                'is_purged' => $allUnsubscribed,
                'was_explicitly_purged' => $wasExplicitlyPurged,
                'partially_purged' => $partiallyPurged,
                'unsubscribed_streams' => $unsubscribedStreams,
                'purge_reason' => $purgeReason,
                'purge_timestamp' => $purgeTimestamp,
            ];

            return $entries;
        } catch (Exception $e) {
            report($e);
            Log::warning("SharedLedger: Failed to connect to node {$nodeId}, falling back to default", [
                'error' => 'An error occurred loading the shared ledger.',
            ]);

            return $this->fetchFromDefaultClient();
        }
    }

    /**
     * Ensure the client's node is subscribed to all ledger streams.
     *
     * If a node is not subscribed, liststreamitems returns an error.
     * We subscribe with rescan=true to ensure off-chain data is available.
     * This is idempotent — subscribing to an already-subscribed stream is a no-op.
     */
    private function ensureSubscribed(Client $client): void
    {
        foreach (self::LEDGER_STREAMS as $stream) {
            // Use liststreamitems to check subscription — getstreaminfo['subscribed']
            // is unreliable in MultiChain CE (field often missing).
            $client->liststreamitems($stream, false, 1, 0, false);

            if ($client->success()) {
                // Already subscribed — skip
                continue;
            }

            // Not subscribed (likely -703) — subscribe with rescan
            $client->subscribe($stream, true);

            if ($client->success()) {
                Log::info("SharedLedger: Auto-subscribed node to stream {$stream} with rescan");
            } else {
                Log::warning("SharedLedger: Failed to auto-subscribe to stream {$stream}: [{$client->errorcode()}] {$client->errormessage()}");
            }
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
                $client->setTimeout(15);

                // Ensure this node is subscribed before listing
                $this->ensureSubscribed($client);

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
                    'error' => 'An error occurred loading the shared ledger.',
                ]);
            }
        }

        // If all nodes failed, fall back to the default Manager
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
                            'error' => 'An error occurred loading the shared ledger.',
                        ]);
                    }
                }
            } catch (Exception $e) {
                report($e);
                Log::warning('SharedLedger: Failed to read stream', [
                    'stream' => $stream,
                    'error' => 'An error occurred loading the shared ledger.',
                ]);
            }
        }

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
                            'error' => 'An error occurred loading the shared ledger.',
                        ]);
                    }
                }
            } catch (Exception $e) {
                report($e);
                Log::warning('SharedLedger: Failed to read stream from client', [
                    'stream' => $stream,
                    'error' => 'An error occurred loading the shared ledger.',
                ]);
            }
        }

        return $entries;
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
}
