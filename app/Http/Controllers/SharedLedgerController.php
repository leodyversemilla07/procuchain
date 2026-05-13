<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\LedgerEntryData;
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
        'procurement.metadata',
        'procurement.status',
        'procurement.documents',
        'procurement.corrections',
        'procurement.metadata.corrections',
        'procurement.archive',
    ];

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
        $nodeConfig = collect($this->getNodes())->first(fn ($n) => $n['id'] === $nodeId);

        if ($nodeConfig === null) {
            // Fallback to the default Manager connection
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

            return $this->fetchEntriesFromClient($client);
        } catch (Exception $e) {
            report($e);
            Log::warning("SharedLedger: Failed to connect to node {$nodeId}, falling back to default", [
                'error' => 'An error occurred loading the shared ledger.',
            ]);

            return $this->fetchFromDefaultClient();
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
            default => $stream,
        };
    }
}
