<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class SemanticSearchService
{
    public function __construct(
        private readonly ProcurementDataService $procurementDataService
    ) {}

    /**
     * Search procurements using semantic search with optional filters
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(string $query = '', array $filters = []): array
    {
        try {
            $procurements = $this->procurementDataService->fetchAndProcessProcurements(
                skipActions: false,
                filterByUserId: null,
                filterByUserAddress: null
            );

            $results = $this->applyFilters($procurements, $query, $filters);

            return [
                'success' => true,
                'query' => $query,
                'filters' => $filters,
                'total' => count($results),
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Semantic search failed', [
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
     * Apply filters to procurement data
     *
     * @param  array<int, array<string, mixed>>  $procurements
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $procurements, string $query, array $filters): array
    {
        return array_values(array_filter($procurements, function ($procurement) use ($query, $filters) {
            // Text search
            if (! empty($query)) {
                $searchLower = strtolower($query);
                $matchesTitle = str_contains(strtolower($procurement['title'] ?? ''), $searchLower);
                $matchesId = str_contains(strtolower($procurement['id'] ?? ''), $searchLower);
                $matchesDescription = str_contains(strtolower($procurement['description'] ?? ''), $searchLower);

                if (! $matchesTitle && ! $matchesId && ! $matchesDescription) {
                    return false;
                }
            }

            // Status filter
            if (! empty($filters['status']) && $filters['status'] !== 'all') {
                if (($procurement['current_status'] ?? '') !== $filters['status']) {
                    return false;
                }
            }

            // Stage filter
            if (! empty($filters['stage']) && $filters['stage'] !== 'all') {
                if (($procurement['stage'] ?? '') !== $filters['stage']) {
                    return false;
                }
            }

            // Mode filter
            if (! empty($filters['mode']) && $filters['mode'] !== 'all') {
                if (($procurement['mode'] ?? '') !== $filters['mode']) {
                    return false;
                }
            }

            // Category filter
            if (! empty($filters['category']) && $filters['category'] !== 'all') {
                if (($procurement['category'] ?? '') !== $filters['category']) {
                    return false;
                }
            }

            // Date range filter (month/year/quarter)
            if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
                // Try both 'created_at' and 'timestamp' fields
                $dateField = $procurement['created_at'] ?? $procurement['timestamp'] ?? null;
                if (! $dateField) {
                    // If no date field, skip this procurement
                    return false;
                }

                // Handle both Carbon instances and string dates
                if ($dateField instanceof \Carbon\Carbon) {
                    $timestamp = $dateField->timestamp;
                } else {
                    $timestamp = strtotime($dateField);
                    if ($timestamp === false) {
                        return false;
                    }
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
     * Calculate aggregated statistics from search results
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
            // Count by status
            $status = $procurement['current_status'] ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            // Count by stage
            $stage = $procurement['stage'] ?? 'unknown';
            $byStage[$stage] = ($byStage[$stage] ?? 0) + 1;

            // Count by mode
            $mode = $procurement['mode'] ?? 'unknown';
            $byMode[$mode] = ($byMode[$mode] ?? 0) + 1;

            // Count by category
            $category = $procurement['category'] ?? 'unknown';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;

            // Sum ABC amounts
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
