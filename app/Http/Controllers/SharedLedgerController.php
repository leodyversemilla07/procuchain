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
 * The true shared ledger: every meaningful blockchain transaction
 * across ALL streams, aggregated into a single chronological view.
 *
 * This is the immutable audit trail — the single source of truth
 * for every action ever taken in the procurement system.
 *
 * Each row = one blockchain transaction with:
 *   - What happened (summary)
 *   - When it happened (timestamp)
 *   - Who did it (actor address/name)
 *   - What changed (diff of old → new values)
 *   - Cryptographic proof (TX ID)
 */
class SharedLedgerController extends Controller
{
    /** Streams to include in the shared ledger. */
    private const LEDGER_STREAMS = [
        'procurement.metadata',       // Procurement created / updated
        'procurement.status',         // Status changes / stage transitions
        'procurement.documents',      // Document uploads
        'procurement.corrections',     // Document corrections
        'procurement.metadata.corrections', // Metadata corrections
        'procurement.archive',        // Archive / restore
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
                'stream_totals' => [],
                'filters' => $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'page']),
                'error' => 'Failed to load the shared ledger. The blockchain node may be unavailable. Please try again.',
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
                // Fetch plenty of items — the ledger is meant to contain everything
                $items = $this->multichain->liststreamitems($stream, true, 5000, 0, false);

                if (! $items || ! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! isset($item['data']['json'])) {
                        continue;
                    }

                    try {
                        $entry = LedgerEntryData::fromStreamItem($stream, $item);
                        $entries[] = $entry;
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
