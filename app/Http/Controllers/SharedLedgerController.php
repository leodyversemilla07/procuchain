<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Services\Manager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Ledger Controller
 *
 * Displays the immutable blockchain record of all procurements.
 * Each row represents one procurement with its complete lifecycle:
 * metadata, current status, documents, events, corrections, and archive state.
 * This is NOT a per-transaction log — it's a procurement-level snapshot
 * of the blockchain state.
 */
class SharedLedgerController extends Controller
{
    /** Items per page. */
    private const PER_PAGE = 20;

    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Display the shared ledger page.
     */
    public function index(Request $request): Response
    {
        try {
            $procurements = $this->fetchAllProcurements();

            // Apply filters
            if ($prNumber = $request->string('pr_number')) {
                $search = strtolower((string) $prNumber);
                $procurements = array_filter($procurements, fn (array $p) =>
                    str_contains(strtolower($p['pr_number']), $search)
                );
            }

            if ($stage = $request->string('stage')) {
                $procurements = array_filter($procurements, fn (array $p) =>
                    $p['current_stage'] === (string) $stage
                );
            }

            if ($status = $request->string('status')) {
                $procurements = array_filter($procurements, fn (array $p) =>
                    $p['current_status'] === (string) $status
                );
            }

            if ($request->filled('date_from')) {
                $from = strtotime((string) $request->string('date_from'));
                $procurements = array_filter($procurements, fn (array $p) =>
                    $p['created_at'] && strtotime($p['created_at']) >= $from
                );
            }

            if ($request->filled('date_to')) {
                $to = strtotime((string) $request->string('date_to') . ' 23:59:59');
                $procurements = array_filter($procurements, fn (array $p) =>
                    $p['created_at'] && strtotime($p['created_at']) <= $to
                );
            }

            if ($request->filled('search')) {
                $search = strtolower((string) $request->string('search'));
                $procurements = array_filter($procurements, fn (array $p) =>
                    str_contains(strtolower($p['pr_number']), $search)
                    || str_contains(strtolower($p['title'] ?? ''), $search)
                    || str_contains(strtolower($p['office'] ?? ''), $search)
                );
            }

            // Sort: newest first by creation date
            usort($procurements, fn (array $a, array $b) =>
                ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '')
            );

            // Paginate
            $page = max(1, $request->integer('page', 1));
            $total = count($procurements);
            $offset = ($page - 1) * self::PER_PAGE;
            $items = array_slice($procurements, $offset, self::PER_PAGE);

            // Get available stages/statuses for filter dropdowns
            $availableStages = $this->getDistinctValues($procurements, 'current_stage');
            $availableStatuses = $this->getDistinctValues($procurements, 'current_status');

            return Inertia::render('shared-ledger', [
                'procurements' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
                    'per_page' => self::PER_PAGE,
                    'total' => $total,
                ],
                'available_stages' => $availableStages,
                'available_statuses' => $availableStatuses,
                'filters' => $request->only(['pr_number', 'stage', 'status', 'date_from', 'date_to', 'search', 'page']),
            ]);
        } catch (Exception $e) {
            Log::error('SharedLedger: Failed to fetch ledger', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('shared-ledger', [
                'procurements' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => self::PER_PAGE,
                    'total' => 0,
                ],
                'available_stages' => [],
                'available_statuses' => [],
                'filters' => $request->only(['pr_number', 'stage', 'status', 'date_from', 'date_to', 'search', 'page']),
                'error' => 'Failed to load blockchain records. The node may be unavailable.',
            ]);
        }
    }

    /**
     * Fetch all procurements from the blockchain and assemble their full state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllProcurements(): array
    {
        // Get the latest metadata for each PR number
        $metadataItems = $this->fetchLatestByKey(StreamEnums::METADATA->value);
        $statusItems = $this->fetchLatestByKey(StreamEnums::STATUS->value);
        $eventItems = $this->fetchLatestByKey(StreamEnums::EVENTS->value);

        if (empty($metadataItems)) {
            return [];
        }

        $procurements = [];

        foreach ($metadataItems as $prNumber => $meta) {
            $data = $meta['data']['json'] ?? [];
            $latestStatus = $statusItems[$prNumber] ?? null;
            $statusData = $latestStatus['data']['json'] ?? [];

            // Count documents, events, corrections, archives
            $docCount = $this->countByKey(StreamEnums::DOCUMENTS->value, $prNumber);
            $eventCount = $this->countByKey(StreamEnums::EVENTS->value, $prNumber, true);
            $correctionCount = $this->countByKey(StreamEnums::CORRECTIONS->value, $prNumber);
            $isArchived = $this->isArchived(StreamEnums::ARCHIVE->value, $prNumber);

            $stage = $data['stage'] ?? $statusData['stage'] ?? 'procurement_initiation';
            $currentStatus = $data['status'] ?? $statusData['current_status'] ?? 'procurement_initiated';

            $procurements[] = [
                'pr_number' => $prNumber,
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'abc_amount' => $data['abc_amount'] ?? '0',
                'procurement_mode' => $data['procurement_mode'] ?? '',
                'category' => $data['category'] ?? '',
                'office' => $data['office'] ?? '',
                'funding_source' => $data['funding_source'] ?? '',
                'prepared_by' => $data['prepared_by'] ?? '',
                'approved_by' => $data['approved_by'] ?? '',
                'approval_date' => $data['approval_date'] ?? null,
                'delivery_location' => $data['delivery_location'] ?? '',
                'delivery_date' => $data['delivery_date'] ?? null,
                'created_at' => $data['created_at'] ?? null,

                // Current state
                'current_stage' => $stage,
                'current_status' => $currentStatus,

                // Counts
                'document_count' => $docCount,
                'event_count' => $eventCount,
                'correction_count' => $correctionCount,
                'is_archived' => $isArchived,

                // Blockchain proof
                'metadata_txid' => $meta['txid'] ?? '',
                'status_txid' => $latestStatus['txid'] ?? '',
                'blocktime' => $meta['blocktime'] ?? null,
                'confirmations' => $meta['confirmations'] ?? 0,
            ];
        }

        return $procurements;
    }

    /**
     * Fetch only the latest item per key from a stream.
     *
     * @return array<string, array>
     */
    private function fetchLatestByKey(string $stream): array
    {
        $items = $this->multichain->liststreamitems($stream, true, 2000, 0, false);

        if (! is_array($items)) {
            return [];
        }

        $latest = [];

        foreach ($items as $item) {
            $key = $item['keys'][0] ?? '';
            if ($key === '') {
                continue;
            }

            // Keep the latest (highest blocktime) version per key
            $blocktime = $item['blocktime'] ?? 0;
            if (! isset($latest[$key]) || ($blocktime > ($latest[$key]['blocktime'] ?? 0))) {
                $latest[$key] = $item;
            }
        }

        return $latest;
    }

    /**
     * Count items for a specific key in a stream.
     */
    private function countByKey(string $stream, string $key, bool $keyStartsWith = false): int
    {
        $items = $this->multichain->liststreamitems($stream, true, 2000, 0, false);

        if (! is_array($items)) {
            return 0;
        }

        $count = 0;

        foreach ($items as $item) {
            $itemKey = $item['keys'][0] ?? '';
            if ($keyStartsWith) {
                if (str_starts_with($itemKey, $key)) {
                    $count++;
                }
            } elseif ($itemKey === $key) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a procurement is archived.
     */
    private function isArchived(string $stream, string $key): bool
    {
        $items = $this->multichain->liststreamitems($stream, true, 500, 0, false);

        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (($item['keys'][0] ?? '') === $key) {
                $data = $item['data']['json'] ?? [];
                if (! empty($data['archived'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get distinct values for a field from an array of records.
     *
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array{value: string, label: string}>
     */
    private function getDistinctValues(array $records, string $field): array
    {
        $values = [];

        foreach ($records as $record) {
            if (! empty($record[$field])) {
                $values[$record[$field]] = $record[$field];
            }
        }

        ksort($values);

        return array_map(fn (string $v) => [
            'value' => $v,
            'label' => str_replace('_', ' ', ucwords(str_replace('_', ' ', $v))),
        ], array_values($values));
    }
}