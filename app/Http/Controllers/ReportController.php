<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportGenerationService;
use App\Services\SemanticSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerationService $reportGenerationService,
        private readonly SemanticSearchService $semanticSearchService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display the report generation page
     */
    public function index(): Response
    {
        return Inertia::render('reports/index', [
            'now' => now()->toIso8601String(),
        ]);
    }

    /**
     * Generate a report with filters
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter_type' => 'nullable|string|in:month,year,quarter,date_range',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|integer|min:1|max:4',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'query' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'stage' => 'nullable|string',
            'mode' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        try {
            Log::info('Generating report', ['params' => $validated]);

            $report = $this->reportGenerationService->generateReport($validated);

            if (! $report['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $report['error'] ?? 'Failed to generate report',
                ], 400);
            }

            Log::info('Report generated successfully', [
                'total_results' => $report['summary']['total_count'] ?? 0,
            ]);

            return response()->json($report);
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the report',
            ], 500);
        }
    }

    /**
     * Export report in various formats
     */
    public function export(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate([
            'filter_type' => 'nullable|string|in:month,year,quarter,date_range',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|integer|min:1|max:4',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'query' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'stage' => 'nullable|string',
            'mode' => 'nullable|string',
            'category' => 'nullable|string',
            'format' => 'required|string|in:json,csv',
        ]);

        try {
            $report = $this->reportGenerationService->generateReport($validated);

            if (! $report['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $report['error'] ?? 'Failed to generate report',
                ], 400);
            }

            $format = $validated['format'];
            $exportData = $this->reportGenerationService->exportReport($report, $format);

            if ($format === 'csv') {
                $filename = 'procurement-report-'.now()->format('Y-m-d-His').'.csv';

                return response()->streamDownload(
                    fn () => print($exportData),
                    $filename,
                    ['Content-Type' => 'text/csv']
                );
            }

            return response()->json($exportData);
        } catch (\Exception $e) {
            Log::error('Report export failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while exporting the report',
            ], 500);
        }
    }

    /**
     * Perform semantic search
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'status' => 'nullable|string',
            'stage' => 'nullable|string',
            'mode' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        try {
            $query = $validated['query'];
            unset($validated['query']);

            $results = $this->semanticSearchService->search($query, $validated);

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Semantic search failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during search',
            ], 500);
        }
    }
}
