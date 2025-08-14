<?php

declare(strict_types=1);
/**
 * @phpstan-ignore-file
 *
 * @psalm-suppress TooManyArguments
 *
 * @noinspection Generic.StringHeavyFunctionArguments
 */

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\MultichainService;
use Carbon\Carbon;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ViewProcurementsController extends BaseController
{
    /**
     * @var MultichainService
     */
    private $multichainService;

    /**
     * @var array<string, string>
     */
    private array $userCache = []; // Corrected type hint

    private const STATUS_PAGE_SIZE = 1000;

    private const DOCUMENT_PAGE_SIZE = 10000;

    private const CACHE_DURATION_MINUTES = 5;

    private const CACHE_KEY_PROCUREMENTS_LIST = 'procurements_list_data';

    private const CACHE_KEY_PROCUREMENT_DETAILS_PREFIX = 'procurement_details_'; // Cache key prefix for details

    /**
     * Constructor
     */
    public function __construct(MultichainService $multichainService)
    {
        $this->multichainService = $multichainService;
        $this->setupMiddleware();
    }

    /**
     * Set up controller middleware
     */
    private function setupMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_chairman,bac_secretariat,hope,admin');
    }

    /**
     * Preload user names for batch user lookup
     */
    private function preloadUserNames(Collection $items): void
    {
        $addresses = $items->pluck('data.json.user_address')->unique()->filter()->all(); // Added ->all()
        if (empty($addresses)) {
            return; // Avoid query if no addresses
        }
        $names = User::whereIn('blockchain_address', $addresses)
            ->pluck('name', 'blockchain_address')
            ->all();
        $this->userCache = $names;
    }

    /**
     * Get username from blockchain address
     */
    private function getUserName(string $address): string
    {
        return $this->userCache[$address] ?? 'Unknown';
    }

    /**
     * Display a listing of procurements
     */
    public function indexProcurementsList(): Response
    {
        try {
            Log::info('Fetching procurements list');

            $procurements = Cache::remember(self::CACHE_KEY_PROCUREMENTS_LIST, now()->addMinutes(self::CACHE_DURATION_MINUTES), function () {
                Log::info('Cache miss: Recalculating procurements list data');

                return $this->fetchAndProcessProcurements();
            });

            Log::info('Successfully retrieved procurements list', [
                'count' => count($procurements),
                'from_cache' => Cache::has(self::CACHE_KEY_PROCUREMENTS_LIST),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $procurements,
            ]);
        } catch (Exception $e) { // Corrected catch block placement
            Log::error('Failed to retrieve procurements list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear cache on error to avoid storing potentially bad data
            Cache::forget(self::CACHE_KEY_PROCUREMENTS_LIST);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements. Please try again later.',
            ]);
        }
    }

    /**
     * Fetch and process procurements data (optimized: batch fetch, in-memory aggregation)
     *
     * @throws Exception
     */
    private function fetchAndProcessProcurements(): array
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
     * Display the specified procurement
     */
    public function showProcurement(string $procurementId): Response
    {
        $cacheKey = self::CACHE_KEY_PROCUREMENT_DETAILS_PREFIX.$procurementId;

        try {
            $this->validateProcurementId($procurementId);

            Log::info('Fetching procurement details', ['procurement_id' => $procurementId]);

            // Cache the entire procurement data structure
            $procurementData = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION_MINUTES), function () use ($procurementId) {
                Log::info('Cache miss: Recalculating procurement details', ['procurement_id' => $procurementId]);

                $statusItems = $this->fetchStatusItems($procurementId);
                $currentStatus = $statusItems->first();

                if (! $currentStatus) {
                    // Return null or throw an exception that can be caught outside the cache closure
                    // Returning null might be simpler if the outer code handles it.
                    return null;
                }

                // Fetch all documents using the updated method (no titles needed here)
                $documents = $this->fetchAndProcessAllDocuments($procurementId); // Pass only ID
                $events = $this->fetchAndProcessEvents($procurementId);

                // Preload user names for events (if not already done broadly)
                // Consider if getUserName calls within event processing need optimization
                $this->preloadUserNames(collect($events)); // Assuming events have user_address

                // Build procurement data
                /** @phpstan-ignore-next-line Excess number of function arguments */
                return $this->buildProcurementData(
                    $procurementId,
                    $currentStatus,
                    $documents,
                    $events,
                    $statusItems
                );
            });

            // Handle case where procurement was not found inside the cache closure
            if ($procurementData === null) {
                Log::warning('Procurement details not found after cache check', ['procurement_id' => $procurementId]);

                return $this->renderNotFound();
            }

            Log::info('Successfully retrieved procurement details', [
                'procurement_id' => $procurementId,
                'from_cache' => Cache::has($cacheKey),
            ]);

            // dd($procurementData); // Debugging line, remove in production

            return Inertia::render('procurements/show-procurement', [
                'procurement' => $procurementData,
                'now' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement details', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear cache for this specific procurement on error
            Cache::forget($cacheKey);

            return Inertia::render('procurements/show-procurement', [
                'error' => 'Failed to retrieve procurement details. Please try again later.',
            ]);
        }
    }

    /**
     * Validate the procurement ID
     *
     * @throws Exception
     */
    private function validateProcurementId(?string $procurementId): void
    {
        if (empty($procurementId)) {
            throw new Exception('Procurement ID is required');
        }
    }

    /**
     * Fetch and process status items
     *
     * @throws Exception
     */
    private function fetchStatusItems(string $procurementId): Collection
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
                    'status' => $data['current_status'] ?? '', // Added this to match the frontend expectation
                    'timestamp' => $data['timestamp'] ?? '',
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            })
            ->sortByDesc('timestamp');
    }

    /**
     * Fetch and process documents using procurement ID (more robust)
     *
     * @throws Exception // Added exception type hint
     */
    private function fetchAndProcessAllDocuments(string $procurementId): array // Removed $procurementTitles parameter
    {
        // Fetch all document items first
        $allDocumentItems = $this->multichainService->listStreamItems(
            StreamEnums::DOCUMENTS->value,
            true, // Verbose
            self::DOCUMENT_PAGE_SIZE, // Use the defined page size
            0, // Start from the beginning
            false // Don't fetch local order
        );

        if ($allDocumentItems === null) {
            // Consider throwing an exception or returning an empty array based on desired error handling
            Log::warning('Failed to retrieve any document stream items.', ['procurement_id' => $procurementId]);

            // throw new Exception('Failed to retrieve document stream items'); // Option 1: Throw
            return []; // Option 2: Return empty
        }

        $totalFetched = count($allDocumentItems); // Log total fetched

        // Filter documents by procurement_id in PHP
        $filteredItems = collect($allDocumentItems)
            ->filter(function ($item) use ($procurementId) {
                // Check if the necessary keys exist before accessing them
                return isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $procurementId;
            });

        $totalAfterFilter = $filteredItems->count(); // Log count after filtering

        Log::debug('Document Fetching Stats', [
            'procurement_id' => $procurementId,
            'total_fetched_from_stream' => $totalFetched,
            'total_after_filtering_by_id' => $totalAfterFilter,
        ]);

        // Continue mapping and sorting
        return $filteredItems
            ->map(function ($item) {
                $data = $item['data']['json'] ?? []; // Ensure data exists
                $fileKey = $data['file_key'] ?? '';

                // Generate secure download URL instead of temporary URL
                $secureUrl = ! empty($fileKey) ? route('secure.file.download', ['fileKey' => $fileKey]) : '';

                // Construct the document array structure
                return [
                    'file_key' => $fileKey,
                    'document_type' => $data['document_type'] ?? '',
                    'spaces_url' => $secureUrl, // Use secure URL instead of temporary URL
                    'hash' => $data['hash'] ?? '',
                    'file_size' => $data['file_size'] ?? null,
                    'stage' => $data['stage'] ?? '',
                    'stage_metadata' => $data['stage_metadata'] ?? null,
                    'procurement_id' => $data['procurement_id'] ?? '', // Keep procurement_id
                    'procurement_title' => $data['procurement_title'] ?? '', // Keep procurement_title
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                ];
            })
            ->sortByDesc('timestamp') // Sort by timestamp descending
            ->values() // Reset keys
            ->toArray(); // Convert to array
    }

    /**
     * Fetch and process events
     */
    private function fetchAndProcessEvents(string $procurementId): array
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
     */
    private function buildProcurementData(
        string $procurementId,
        array $currentStatus,
        array $documents,
        array $events,
        Collection $statusItems
    ): array {
        return [
            'id' => $procurementId,
            'title' => $currentStatus['procurement_title'] ?? 'N/A', // Added null check
            'status' => $currentStatus,
            'documents' => $documents,
            'events' => $events,
            'timeline' => $statusItems->values()->toArray(),
        ];
    }

    /**
     * Render not found response
     */
    private function renderNotFound(): Response
    {
        return Inertia::render('procurements/not-found', [
            'message' => 'Procurement not found',
        ]);
    }
}
