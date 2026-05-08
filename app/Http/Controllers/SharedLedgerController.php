<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\LedgerEntryData;
use App\Services\Manager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Ledger Controller
 *
 * Aggregates all blockchain stream items into a unified,
 * chronological, filterable view for all authenticated users.
 */
class SharedLedgerController extends Controller
{
    /** Streams to include in the shared ledger. */
    private const LEDGER_STREAMS = [
        'procurement.events',
        'procurement.status',
        'procurement.metadata',
        'procurement.documents',
        'procurement.corrections',
        'procurement.metadata.corrections',
        'procurement.archive',
    ];

    /** Items per page. */
    private const PER_PAGE = 50;

    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Display the shared ledger page.
     */
    public function index(Request $request): Response
    {
        try {
            $entries = $this->fetchLedgerEntries();

            // Apply filters
            if ($request->filled('pr_number')) {
                $search = strtolower((string) $request->string('pr_number'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => str_contains(strtolower($e->prNumber), $search));
            }

            if ($request->filled('stream')) {
                $stream = (string) $request->string('stream');
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->stream === $stream);
            }

            if ($request->filled('date_from')) {
                $from = strtotime((string) $request->string('date_from'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() >= $from);
            }

            if ($request->filled('date_to')) {
                $to = strtotime((string) $request->string('date_to').' 23:59:59');
                $entries = array_filter($entries, fn (LedgerEntryData $e) => $e->getSortableTimestamp() <= $to);
            }

            if ($request->filled('search')) {
                $search = strtolower((string) $request->string('search'));
                $entries = array_filter($entries, fn (LedgerEntryData $e) => str_contains(strtolower($e->prNumber), $search)
                    || str_contains(strtolower($e->summary), $search)
                    || str_contains(strtolower($e->action), $search)
                    || str_contains(strtolower($e->txid), $search)
                );
            }

            // Sort by timestamp descending
            usort($entries, fn (LedgerEntryData $a, LedgerEntryData $b) => $b->getSortableTimestamp() <=> $a->getSortableTimestamp());

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

            return Inertia::render('shared-ledger', [
                'entries' => $mapped,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
                    'per_page' => self::PER_PAGE,
                    'total' => $total,
                ],
                'available_streams' => $availableStreams,
                'filters' => $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'page']),
            ]);
        } catch (Exception $e) {
            Log::error('SharedLedger: Failed to fetch ledger entries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                'filters' => $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'page']),
                'error' => 'Failed to load ledger entries. The blockchain node may be unavailable. Please try again.',
            ]);
        }
    }

    /**
     * Fetch all entries from all ledger streams.
     *
     * @return LedgerEntryData[]
     */
    private function fetchLedgerEntries(): array
    {
        $entries = [];

        foreach (self::LEDGER_STREAMS as $stream) {
            try {
                $items = $this->multichain->liststreamitems($stream, true, 500, 0, false);

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
                        Log::warning('SharedLedger: Skipping invalid stream item', [
                            'stream' => $stream,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::warning('SharedLedger: Failed to read stream', [
                    'stream' => $stream,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $entries;
    }

    /**
     * Quick check whether the MultiChain RPC node is reachable.
     * Returns false immediately if the connection fails, avoiding
     * sequential timeouts on every stream call.
     */
    private function isNodeReachable(): bool
    {
        // Skip check in console/testing — those environments mock the Manager
        if (app()->runningInConsole() || app()->environment('testing')) {
            return true;
        }

        $host = config('multichain.rpc.host', '127.0.0.1');
        $port = (int) config('multichain.rpc.port', 4786);

        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($fp !== false) {
                fclose($fp);
                return true;
            }
        } catch (\Exception $e) {
            // Fall through
        }

        Log::info('SharedLedger: MultiChain node not reachable, returning empty ledger', [
            'host' => $host,
            'port' => $port,
        ]);

        return false;
    }

    /**
     * Get a human-readable display name for a stream.
     */
    private function getStreamDisplayName(string $stream): string
    {
        return match ($stream) {
            'procurement.events' => 'Events',
            'procurement.status' => 'Status',
            'procurement.metadata' => 'Metadata',
            'procurement.documents' => 'Documents',
            'procurement.corrections' => 'Document Corrections',
            'procurement.metadata.corrections' => 'Metadata Corrections',
            'procurement.archive' => 'Archive',
            default => $stream,
        };
    }
}
