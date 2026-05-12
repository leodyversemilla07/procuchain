<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReportGenerationService
{
    public function __construct(
        private readonly ProcurementSearchService $procurementSearchService
    ) {}

    /**
     * Generate report with month/year/quarter filtering
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function generateReport(array $params): array
    {
        try {
            $filters = $this->buildFilters($params);
            $searchResults = $this->procurementSearchService->search($params['query'] ?? '', $filters);

            if (! $searchResults['success']) {
                return [
                    'success' => false,
                    'error' => $searchResults['error'] ?? 'Failed to generate report',
                ];
            }

            $statistics = $this->procurementSearchService->calculateStatistics($searchResults['results']);
            $timeSeriesData = $this->generateTimeSeriesData($searchResults['results'], $params);

            return [
                'success' => true,
                'report_generated_at' => now()->toIso8601String(),
                'parameters' => $params,
                'filters' => $filters,
                'summary' => $statistics,
                'time_series' => $timeSeriesData,
                'data' => $searchResults['results'],
            ];
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build filters from report parameters
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildFilters(array $params): array
    {
        $filters = [];

        // Add basic filters
        if (! empty($params['status'])) {
            $filters['status'] = $params['status'];
        }

        if (! empty($params['stage'])) {
            $filters['stage'] = $params['stage'];
        }

        if (! empty($params['mode'])) {
            $filters['mode'] = $params['mode'];
        }

        if (! empty($params['category'])) {
            $filters['category'] = $params['category'];
        }

        // Process date filters based on filter_type
        $filterType = $params['filter_type'] ?? 'date_range';

        match ($filterType) {
            'month' => $this->applyMonthFilter($filters, $params),
            'year' => $this->applyYearFilter($filters, $params),
            'quarter' => $this->applyQuarterFilter($filters, $params),
            'date_range' => $this->applyDateRangeFilter($filters, $params),
            default => null,
        };

        return $filters;
    }

    /**
     * Apply month filter
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function applyMonthFilter(array &$filters, array $params): void
    {
        if (empty($params['month']) || empty($params['year'])) {
            return;
        }

        $month = (int) $params['month'];
        $year = (int) $params['year'];

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $filters['date_from'] = $startDate->toDateString();
        $filters['date_to'] = $endDate->toDateString();
    }

    /**
     * Apply year filter
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function applyYearFilter(array &$filters, array $params): void
    {
        if (empty($params['year'])) {
            return;
        }

        $year = (int) $params['year'];

        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        $filters['date_from'] = $startDate->toDateString();
        $filters['date_to'] = $endDate->toDateString();
    }

    /**
     * Apply quarter filter
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function applyQuarterFilter(array &$filters, array $params): void
    {
        if (empty($params['quarter']) || empty($params['year'])) {
            return;
        }

        $quarter = (int) $params['quarter'];
        $year = (int) $params['year'];

        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        $filters['date_from'] = $startDate->toDateString();
        $filters['date_to'] = $endDate->toDateString();
    }

    /**
     * Apply custom date range filter
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $params
     */
    private function applyDateRangeFilter(array &$filters, array $params): void
    {
        if (! empty($params['date_from'])) {
            $filters['date_from'] = $params['date_from'];
        }

        if (! empty($params['date_to'])) {
            $filters['date_to'] = $params['date_to'];
        }
    }

    /**
     * Generate time series data for visualization
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function generateTimeSeriesData(array $results, array $params): array
    {
        $filterType = $params['filter_type'] ?? 'date_range';

        return match ($filterType) {
            'month' => $this->generateDailyTimeSeries($results),
            'year' => $this->generateMonthlyTimeSeries($results),
            'quarter' => $this->generateMonthlyTimeSeries($results),
            'date_range' => $this->generateDailyTimeSeries($results),
            default => [],
        };
    }

    /**
     * Generate daily time series (for month view)
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, array<string, int>>
     */
    private function generateDailyTimeSeries(array $results): array
    {
        $dailyData = [];

        foreach ($results as $procurement) {
            $createdAt = $procurement['created_at'] ?? $procurement['timestamp'] ?? null;
            if (! $createdAt) {
                continue;
            }

            // Handle Carbon instances
            if ($createdAt instanceof Carbon) {
                $date = $createdAt->toDateString();
            } else {
                $date = Carbon::parse($createdAt)->toDateString();
            }

            if (! isset($dailyData[$date])) {
                $dailyData[$date] = ['count' => 0, 'date' => $date];
            }
            $dailyData[$date]['count']++;
        }

        ksort($dailyData);

        return array_values($dailyData);
    }

    /**
     * Generate monthly time series (for year/quarter view)
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, array<string, int>>
     */
    private function generateMonthlyTimeSeries(array $results): array
    {
        $monthlyData = [];

        foreach ($results as $procurement) {
            $createdAt = $procurement['created_at'] ?? $procurement['timestamp'] ?? null;
            if (! $createdAt) {
                continue;
            }

            // Handle Carbon instances
            if ($createdAt instanceof Carbon) {
                $month = $createdAt->format('Y-m');
            } else {
                $month = Carbon::parse($createdAt)->format('Y-m');
            }

            if (! isset($monthlyData[$month])) {
                $monthlyData[$month] = ['count' => 0, 'month' => $month];
            }
            $monthlyData[$month]['count']++;
        }

        ksort($monthlyData);

        return array_values($monthlyData);
    }

    /**
     * Export report to various formats
     *
     * @param  array<string, mixed>  $reportData
     */
    public function exportReport(array $reportData, string $format = 'json'): string|array
    {
        return match ($format) {
            'csv' => $this->exportToCsv($reportData),
            'json' => $reportData,
            default => $reportData,
        };
    }

    /**
     * Export report to CSV format
     *
     * @param  array<string, mixed>  $reportData
     */
    private function exportToCsv(array $reportData): string
    {
        if (empty($reportData['data'])) {
            return '';
        }

        $headers = ['ID', 'Title', 'Status', 'Stage', 'Mode', 'Category', 'ABC Amount', 'Created At'];
        $rows = [$headers];

        foreach ($reportData['data'] as $item) {
            $rows[] = [
                $item['id'] ?? '',
                $item['title'] ?? '',
                $item['current_status'] ?? '',
                $item['stage'] ?? '',
                $item['mode'] ?? '',
                $item['category'] ?? '',
                $item['abc_amount'] ?? 0,
                $item['created_at'] ?? '',
            ];
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($field) => '"'.str_replace('"', '""', (string) $field).'"', $row))."\n";
        }

        return $csv;
    }
}
