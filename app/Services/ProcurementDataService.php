<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching and processing procurement data from blockchain
 */
class ProcurementDataService
{
    private MultichainService $multichainService;

    /**
     * @var array<string, string>
     */
    private array $userCache = [];

    private const STATUS_PAGE_SIZE = 1000;

    private const DOCUMENT_PAGE_SIZE = 10000;

    public function __construct(MultichainService $multichainService)
    {
        $this->multichainService = $multichainService;
    }

    /**
     * Fetch and process all procurements for listing page
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessProcurements(): array
    {
        Log::info('fetchAndProcessProcurements: Fetching status items from MultiChain');
        $statusItems = $this->multichainService->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            self::STATUS_PAGE_SIZE,
            0,
            false
        );

        if ($statusItems === null) {
            Log::error('fetchAndProcessProcurements: Failed to retrieve status stream items');
            throw new Exception('Failed to retrieve status stream items');
        }

        Log::info('fetchAndProcessProcurements: Retrieved status items', ['count' => count($statusItems)]);

        Log::info('fetchAndProcessProcurements: Fetching document items from MultiChain');
        $documentItems = $this->multichainService->listStreamItems(
            StreamEnums::DOCUMENTS->value,
            true,
            self::DOCUMENT_PAGE_SIZE,
            0,
            false
        );

        if ($documentItems === null) {
            Log::error('fetchAndProcessProcurements: Failed to retrieve document stream items');
            throw new Exception('Failed to retrieve document stream items');
        }

        Log::info('fetchAndProcessProcurements: Retrieved document items', ['count' => count($documentItems)]);

        // Preload user names
        $this->preloadUserNames(collect($statusItems));

        // Build a map: procurement_id => document count (unique by hash)
        $documentCountMap = collect($documentItems)
            ->filter(fn ($item) => isset($item['data']['json']['procurement_id']) && isset($item['data']['json']['hash']))
            ->groupBy(fn ($item) => $item['data']['json']['procurement_id'])
            ->map(function ($items) {
                return collect($items)
                    ->map(fn ($item) => $item['data']['json']['hash'])
                    ->unique()
                    ->count();
            });

        Log::info('fetchAndProcessProcurements: Built document count map', ['count' => $documentCountMap->count()]);

        // Map procurements, using the precomputed document count
        $result = collect($statusItems)
            ->map(function ($item) use ($documentCountMap) {
                $data = $item['data']['json'] ?? [];
                $originalTimestamp = $data['timestamp'] ?? null;
                $displayTimestamp = isset($data['timestamp'])
                    ? Carbon::parse($data['timestamp'])->toDateString()
                    : Carbon::now()->toDateString();

                return [
                    'id' => $data['procurement_id'] ?? null,
                    'title' => $data['procurement_title'] ?? null,
                    'stage' => $data['stage'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                    'timestamp' => $originalTimestamp,
                    'display_date' => $displayTimestamp,
                    'last_updated' => $displayTimestamp,
                    'user_address' => $data['user_address'] ?? '',
                    'user' => $this->getUserName($data['user_address'] ?? ''),
                    'document_count' => $documentCountMap[$data['procurement_id'] ?? ''] ?? 0,
                ];
            })
            ->groupBy('id')
            ->map(function ($group) {
                return $group->sortByDesc(function ($item) {
                    return strtotime($item['timestamp'] ?? '0');
                })->first();
            })
            ->values()
            ->all();

        Log::info('fetchAndProcessProcurements: Final procurements result count', ['count' => count($result)]);

        return $result;
    }

    /**
     * Fetch status items for a specific procurement
     *
     * @throws Exception
     */
    public function fetchStatusItems(string $procurementId): Collection
    {
        $statusStreamItems = $this->multichainService->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            self::STATUS_PAGE_SIZE,
            0,
            false
        );

        if (! $statusStreamItems) {
            throw new Exception('Status stream items not found');
        }

        return collect($statusStreamItems)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];

                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];

                return [
                    'stage' => $data['stage'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                    'status' => $data['current_status'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            })
            ->sortByDesc('timestamp');
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessAllDocuments(string $procurementId): array
    {
        $allDocumentItems = $this->multichainService->listStreamItems(
            StreamEnums::DOCUMENTS->value,
            true,
            self::DOCUMENT_PAGE_SIZE,
            0,
            false
        );

        if ($allDocumentItems === null) {
            Log::warning('Failed to retrieve any document stream items.', ['procurement_id' => $procurementId]);

            return [];
        }

        $totalFetched = count($allDocumentItems);

        $filteredItems = collect($allDocumentItems)
            ->filter(function ($item) use ($procurementId) {
                return isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $procurementId;
            });

        $totalAfterFilter = $filteredItems->count();

        Log::debug('Document Fetching Stats', [
            'procurement_id' => $procurementId,
            'total_fetched_from_stream' => $totalFetched,
            'total_after_filtering_by_id' => $totalAfterFilter,
        ]);

        return $filteredItems
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];
                $fileKey = $data['file_key'] ?? '';

                $secureUrl = ! empty($fileKey) ? route('secure.file.download', ['fileKey' => $fileKey]) : '';

                return [
                    'file_key' => $fileKey,
                    'document_type' => $data['document_type'] ?? '',
                    'spaces_url' => $secureUrl,
                    'hash' => $data['hash'] ?? '',
                    'file_size' => $data['file_size'] ?? null,
                    'stage' => $data['stage'] ?? '',
                    'stage_metadata' => $data['stage_metadata'] ?? null,
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Fetch and process events for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAndProcessEvents(string $procurementId): array
    {
        $events = $this->multichainService->listStreamItems(
            StreamEnums::EVENTS->value
        );

        return collect($events)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];

                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];

                return [
                    'timestamp' => $data['timestamp'] ?? '',
                    'event_type' => $data['event_type'] ?? '',
                    'details' => $data['details'] ?? '',
                    'stage' => $data['stage_identifier'] ?? '',
                    'document_count' => $data['document_count'] ?? null,
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'category' => $data['category'] ?? '',
                    'severity' => $data['severity'] ?? '',
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Build procurement data structure
     *
     * @param  array<string, mixed>  $currentStatus
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function buildProcurementData(
        string $procurementId,
        array $currentStatus,
        array $documents,
        array $events,
        Collection $statusItems
    ): array {
        return [
            'id' => $procurementId,
            'title' => $currentStatus['procurement_title'] ?? 'N/A',
            'status' => $currentStatus,
            'documents' => $documents,
            'events' => $events,
            'timeline' => $statusItems->values()->toArray(),
        ];
    }

    /**
     * Preload user names for batch user lookup
     */
    public function preloadUserNames(Collection $items): void
    {
        $addresses = $items->pluck('data.json.user_address')->unique()->filter()->all();
        if (empty($addresses)) {
            return;
        }

        $names = User::whereIn('blockchain_address', $addresses)
            ->pluck('name', 'blockchain_address')
            ->all();

        $this->userCache = $names;
    }

    /**
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        return $this->userCache[$address] ?? 'Unknown';
    }
}
