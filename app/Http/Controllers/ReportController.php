<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProcurementSearchRequest;
use App\Http\Requests\ReportExportRequest;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ProcurementSearchService;
use App\Services\ReportGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerationService $reportGenerationService,
        private readonly ProcurementSearchService $procurementSearchService
    ) {
        //
    }

    /**
     * Display the report generation page
     */
    public function index(): Response
    {
        $this->authorize('view-reports');

        return Inertia::render('reports/index', [
            'now' => now()->toIso8601String(),
        ]);
    }

    /**
     * Generate a report with filters
     */
    public function generate(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('generate-reports');

        $validated = $request->validated();

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
            report($e);
            Log::error('Report generation failed', [
                'error' => 'An error occurred generating the report.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
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
    public function export(ReportExportRequest $request): JsonResponse|StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('export-reports');

        $validated = $request->validated();

        try {
            $report = $this->reportGenerationService->generateReport($validated);

            if (! $report['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $report['error'] ?? 'Failed to generate report',
                ], 400);
            }

            $format = $validated['format'];

            if ($format === 'pdf') {
                $filename = 'procurement-report-'.now()->format('Y-m-d-His').'.pdf';

                $pdf = Pdf::loadView('reports.pdf', [
                    'report' => $report,
                    'filters' => $validated,
                ]);

                return $pdf->download($filename);
            }

            $exportData = $this->reportGenerationService->exportReport($report, $format);

            if ($format === 'csv') {
                $filename = 'procurement-report-'.now()->format('Y-m-d-His').'.csv';

                return response()->streamDownload(
                    fn () => print ($exportData),
                    $filename,
                    ['Content-Type' => 'text/csv']
                );
            }

            return response()->json($exportData);
        } catch (\Exception $e) {
            report($e);
            Log::error('Report export failed', [
                'error' => 'An error occurred generating the report.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while exporting the report',
            ], 500);
        }
    }

    /**
     * Perform procurement search.
     */
    public function search(ProcurementSearchRequest $request): JsonResponse
    {
        $this->authorize('view-reports');

        $validated = $request->validated();

        try {
            $query = $validated['query'];
            unset($validated['query']);

            $results = $this->procurementSearchService->search($query, $validated);

            return response()->json($results);
        } catch (\Exception $e) {
            report($e);
            Log::error('Procurement search failed', [
                'error' => 'An error occurred generating the report.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during search',
            ], 500);
        }
    }
}
