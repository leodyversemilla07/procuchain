<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Exception;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
        $this->middleware('auth');

        // Restrict analytics access to certain roles
        $this->middleware('role:bac_secretariat,bac_chairman,hope,admin');
    }

    /**
     * Display the main analytics dashboard
     */
    public function dashboard(Request $request): Response
    {
        try {
            Log::info('Analytics dashboard accessed', ['user_id' => Auth::id()]);

            $filters = [
                'time_range' => $request->get('time_range', '30_days'),
                'role' => Auth::user()->role,
            ];

            // Immediate (critical) data
            $procurement = $this->analyticsService->getProcurementAnalytics($filters);

            // Heavy / secondary data deferred (Inertia v2 deferred props)
            $documents = $this->analyticsService->getDocumentAnalytics($filters);
            $userActivity = $this->analyticsService->getUserActivityAnalytics($filters);
            $blockchain = $this->analyticsService->getBlockchainAnalytics($filters);

            return Inertia::render('analytics/dashboard', [
                'procurement' => $procurement,
                'documents' => $documents,
                'userActivity' => $userActivity,
                'blockchain' => $blockchain,
                'filters' => $filters,
                'timeRangeOptions' => [
                    ['value' => '7_days', 'label' => 'Last 7 Days'],
                    ['value' => '30_days', 'label' => 'Last 30 Days'],
                    ['value' => '90_days', 'label' => 'Last 90 Days'],
                    ['value' => '1_year', 'label' => 'Last Year'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to load analytics dashboard', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return Inertia::render('analytics/dashboard', [
                'error' => 'Failed to load analytics data. Please try again later.',
                'filters' => ['time_range' => '30_days'],
                'timeRangeOptions' => [
                    ['value' => '7_days', 'label' => 'Last 7 Days'],
                    ['value' => '30_days', 'label' => 'Last 30 Days'],
                    ['value' => '90_days', 'label' => 'Last 90 Days'],
                    ['value' => '1_year', 'label' => 'Last Year'],
                ],
            ]);
        }
    }

    // Removed separate JSON analytics methods (procurementAnalytics, documentAnalytics, blockchainAnalytics)
    // in favor of pure Inertia partial reloads & deferred props.

    /**
     * Get comprehensive analytics report
     */
    public function comprehensiveReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'time_range' => 'sometimes|string|in:7_days,30_days,90_days,1_year',
                'procurement_id' => 'sometimes|string',
                'format' => 'sometimes|string|in:json,csv,pdf',
            ]);

            $timeRange = $request->get('time_range', '30_days');
            $procurementId = $request->get('procurement_id');
            $format = $request->get('format', 'json');

            $options = array_filter([
                'time_range' => $timeRange,
                'procurement_id' => $procurementId,
            ]);

            // Generate all analytics data
            $report = [
                'metadata' => [
                    'generated_at' => now()->toISOString(),
                    'generated_by' => Auth::user()->name,
                    'time_range' => $timeRange,
                    'procurement_id' => $procurementId,
                    'format' => $format,
                ],
                'procurement_analytics' => $this->analyticsService->getProcurementAnalytics($options),
                'document_analytics' => $this->analyticsService->getDocumentAnalytics($options),
                'user_activity_analytics' => $this->analyticsService->getUserActivityAnalytics($options),
                'blockchain_analytics' => $this->analyticsService->getBlockchainAnalytics($options),
            ];

            Log::info('Comprehensive analytics report generated', [
                'user_id' => Auth::id(),
                'options' => $options,
                'format' => $format,
            ]);

            // Handle different output formats
            if ($format === 'csv') {
                return $this->generateCSVReport($report);
            } elseif ($format === 'pdf') {
                return $this->generatePDFReport($report);
            }

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to generate comprehensive analytics report', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate comprehensive report',
                'message' => 'Unable to process comprehensive analytics request.',
            ], 500);
        }
    }

    /**
     * Get real-time analytics data
     */
    public function realtimeData(Request $request): JsonResponse
    {
        try {
            // Get live data without caching for real-time updates
            $liveData = [
                'active_users' => $this->getActiveUsersCount(),
                'recent_activities' => $this->getRecentActivities(10),
                'current_stage_distribution' => $this->getCurrentStageDistribution(),
                'pending_actions' => $this->getPendingActionsCount(),
                'last_updated' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $liveData,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get real-time analytics data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get real-time data',
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:procurement,document,user_activity,blockchain',
                'format' => 'required|string|in:json,csv,excel',
                'time_range' => 'sometimes|string|in:7_days,30_days,90_days,1_year',
            ]);

            $type = $request->get('type');
            $format = $request->get('format');
            $timeRange = $request->get('time_range', '30_days');

            $options = ['time_range' => $timeRange];

            $data = match ($type) {
                'procurement' => $this->analyticsService->getProcurementAnalytics($options),
                'document' => $this->analyticsService->getDocumentAnalytics($options),
                'user_activity' => $this->analyticsService->getUserActivityAnalytics($options),
                'blockchain' => $this->analyticsService->getBlockchainAnalytics($options),
            };

            // Generate export file
            $exportUrl = $this->generateExportFile($data, $type, $format);

            Log::info('Analytics data exported', [
                'user_id' => Auth::id(),
                'type' => $type,
                'format' => $format,
                'time_range' => $timeRange,
            ]);

            return response()->json([
                'success' => true,
                'export_url' => $exportUrl,
                'filename' => "analytics_{$type}_{$timeRange}.{$format}",
                'generated_at' => now()->toISOString(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to export analytics data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to export data',
                'message' => 'Unable to generate export file.',
            ], 500);
        }
    }

    /**
     * Helper methods for real-time data
     */
    private function getActiveUsersCount(): int
    {
        // Get users who have been active in the last 30 minutes
        return \App\Models\UserLoginLog::where('login_at', '>=', now()->subMinutes(30))
            ->whereNull('logout_at')
            ->distinct('user_id')
            ->count('user_id');
    }

    private function getRecentActivities(int $limit): array
    {
        return \App\Models\DocumentView::with('user:id,name,role')
            ->orderBy('viewed_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($view) {
                return [
                    'user' => $view->user->name,
                    'role' => $view->user->role,
                    'action' => "Viewed {$view->document_type}",
                    'procurement_id' => $view->procurement_id,
                    'timestamp' => $view->viewed_at->toISOString(),
                ];
            })
            ->toArray();
    }

    private function getCurrentStageDistribution(): array
    {
        // Get current stage distribution from cached dashboard data
        // This is a simplified implementation
        return [
            'Procurement Initiation' => 5,
            'Pre-Procurement Conference' => 3,
            'Bidding Documents' => 8,
            'Bid Opening' => 2,
            'Completed' => 12,
        ];
    }

    private function getPendingActionsCount(): int
    {
        // Count procurements that need action
        // This would be based on business logic for what constitutes a "pending action"
        return 7; // Placeholder
    }

    /**
     * Export file generation methods
     */
    private function generateCSVReport(array $report): JsonResponse
    {
        // Implementation for CSV export
        return response()->json([
            'success' => true,
            'message' => 'CSV export not yet implemented',
            'data' => $report,
        ]);
    }

    private function generatePDFReport(array $report): JsonResponse
    {
        // Implementation for PDF export
        return response()->json([
            'success' => true,
            'message' => 'PDF export not yet implemented',
            'data' => $report,
        ]);
    }

    private function generateExportFile(array $data, string $type, string $format): string
    {
        // Implementation for generating export files
        // Return a temporary URL to the generated file
        return route('analytics.download', [
            'type' => $type,
            'format' => $format,
            'timestamp' => now()->timestamp,
        ]);
    }
}
