<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcurementSearchService
{
    /**
     * Cache TTL for procurement list used by search/reports (seconds).
     * Short enough to avoid stale data, long enough to serve burst requests cheaply.
     */
    private const PROCUREMENT_CACHE_TTL = 120;

    public function __construct(
        private readonly ProcurementDataService $procurementDataService
    ) {}

    /**
     * Search procurements using keyword matching with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(string $query = '', array $filters = []): array
    {
        try {
            $procurements = $this->fetchCachedProcurements();

            $results = $this->applyFilters($procurements, $query, $filters);

            return [
                'success' => true,
                'query' => $query,
                'filters' => $filters,
                'total' => count($results),
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Procurement search failed', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'query' => $query,
                'filters' => $filters,
                'total' => 0,
                'results' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch all procurements with a short-lived cache.
     *
     * The main listing page fetches directly from the blockchain (no cache) to
     * stay real-time. Search and report endpoints can tolerate a small lag, so
     * we cache here to avoid repeated full blockchain fetches within a burst.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCachedProcurements(): array
    {
        return Cache::remember('search:procurements:all', self::PROCUREMENT_CACHE_TTL, function () {
            $data = $this->procurementDataService->fetchAndProcessProcurements(
                skipActions: false,
                filterByUserId: null,
                filterByUserAddress: null
            );

            // Ensure all timestamp fields are strings (never Carbon objects).
            // Laravel 13's serializable_classes=false will break Carbon deserialization,
            // causing __PHP_Incomplete_Class errors on cache reads.
            return array_map(function (array $procurement) {
                if (isset($procurement['timestamp']) && $procurement['timestamp'] instanceof \DateTimeInterface) {
                    $procurement['timestamp'] = Carbon::instance($procurement['timestamp'])->toIso8601String();
                }
                if (isset($procurement['created_at']) && $procurement['created_at'] instanceof \DateTimeInterface) {
                    $procurement['created_at'] = Carbon::instance($procurement['created_at'])->toIso8601String();
                }

                return $procurement;
            }, $data);
        });
    }

    /**
     * Minimum similarity percentage (0-100) for fuzzy matching fallback.
     * Below this threshold, a partial match is considered unrelated.
     */
    private const FUZZY_MIN_SIMILARITY = 60;

    /**
     * Apply filters to procurement data.
     *
     * Uses exact substring matching first, then falls back to fuzzy matching
     * (similar_text) to catch typos and minor misspellings.
     *
     * @param  array<int, array<string, mixed>>  $procurements
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $procurements, string $query, array $filters): array
    {
        return array_values(array_filter($procurements, function ($procurement) use ($query, $filters) {
            if (! empty($query)) {
                if (! $this->matchesQuery($query, $procurement)) {
                    return false;
                }
            }

            if (! empty($filters['status']) && $filters['status'] !== 'all') {
                if (($procurement['current_status'] ?? '') !== $filters['status']) {
                    return false;
                }
            }

            if (! empty($filters['stage']) && $filters['stage'] !== 'all') {
                if (($procurement['stage'] ?? '') !== $filters['stage']) {
                    return false;
                }
            }

            if (! empty($filters['mode']) && $filters['mode'] !== 'all') {
                if (($procurement['mode'] ?? '') !== $filters['mode']) {
                    return false;
                }
            }

            if (! empty($filters['category']) && $filters['category'] !== 'all') {
                if (($procurement['category'] ?? '') !== $filters['category']) {
                    return false;
                }
            }

            if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
                $dateField = $procurement['created_at'] ?? $procurement['timestamp'] ?? null;

                if (! $dateField) {
                    return false;
                }

                // Guard against __PHP_Incomplete_Class from stale cache
                // (Laravel 13 serializable_classes=false can break Carbon deserialization)
                if ($dateField instanceof Carbon) {
                    $timestamp = $dateField->timestamp;
                } elseif (is_string($dateField)) {
                    $timestamp = strtotime($dateField);

                    if ($timestamp === false) {
                        return false;
                    }
                } else {
                    // __PHP_Incomplete_Class or other non-string — skip date filtering
                    return false;
                }

                if (! empty($filters['date_from'])) {
                    $dateFrom = strtotime($filters['date_from'].' 00:00:00');

                    if ($dateFrom !== false && $timestamp < $dateFrom) {
                        return false;
                    }
                }

                if (! empty($filters['date_to'])) {
                    $dateTo = strtotime($filters['date_to'].' 23:59:59');

                    if ($dateTo !== false && $timestamp > $dateTo) {
                        return false;
                    }
                }
            }

            return true;
        }));
    }

    /**
     * Check if a procurement matches the search query.
     *
     * First tries exact substring match (fast path). If that fails,
     * falls back to fuzzy matching using similar_text on each word
     * in the procurement's text fields. This catches typos like
     * "infrastucture" -> "infrastructure" or "procurement" -> "procuement".
     */
    private function matchesQuery(string $query, array $procurement): bool
    {
        $searchLower = strtolower($query);
        $searchableText = implode(' ', [
            $procurement['title'] ?? '',
            $procurement['id'] ?? '',
            $procurement['description'] ?? '',
        ]);
        $searchableLower = strtolower($searchableText);

        // Fast path: exact substring match
        if (str_contains($searchableLower, $searchLower)) {
            return true;
        }

        // Fuzzy path: compare query against each word in the text
        $queryWords = explode(' ', $searchLower);
        $textWords = explode(' ', $searchableLower);

        foreach ($queryWords as $queryWord) {
            if ($queryWord === '') {
                continue;
            }

            foreach ($textWords as $textWord) {
                if ($textWord === '') {
                    continue;
                }

                similar_text($queryWord, $textWord, $percent);

                if ($percent >= self::FUZZY_MIN_SIMILARITY) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate aggregated statistics from search results.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    public function calculateStatistics(array $results): array
    {
        if (empty($results)) {
            return [
                'total_count' => 0,
                'by_status' => [],
                'by_stage' => [],
                'by_mode' => [],
                'by_category' => [],
                'total_abc_amount' => 0,
            ];
        }

        $byStatus = [];
        $byStage = [];
        $byMode = [];
        $byCategory = [];
        $totalAbcAmount = 0;

        foreach ($results as $procurement) {
            $status = $procurement['current_status'] ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            $stage = $procurement['stage'] ?? 'unknown';
            $byStage[$stage] = ($byStage[$stage] ?? 0) + 1;

            $mode = $procurement['mode'] ?? 'unknown';
            $byMode[$mode] = ($byMode[$mode] ?? 0) + 1;

            $category = $procurement['category'] ?? 'unknown';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;

            if (isset($procurement['abc_amount']) && is_numeric($procurement['abc_amount'])) {
                $totalAbcAmount += (float) $procurement['abc_amount'];
            }
        }

        return [
            'total_count' => count($results),
            'by_status' => $byStatus,
            'by_stage' => $byStage,
            'by_mode' => $byMode,
            'by_category' => $byCategory,
            'total_abc_amount' => $totalAbcAmount,
        ];
    }
}
