<?php

namespace App\Http\Controllers\Traits;

use App\Enums\StreamEnums;
use App\Services\DashboardCacheKeys;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

trait AdminDashboard
{
    /**
     * Display the admin dashboard
     */
    public function index(): Response
    {
        try {
            Log::info('Fetching Admin dashboard data');

            $procurementsByKey = Cache::remember(
                DashboardCacheKeys::procurements('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.procurements')),
                function () {
                    Log::info('Cache miss: Recalculating procurementsByKey for Admin dashboard');
                    $states = $this->multiChain->listStreamItems(
                        StreamEnums::STATUS->value,
                        true,
                        config('dashboard.stream_limits.status_items'),
                        0,
                        false
                    );
                    if ($states === null) {
                        throw new Exception('Failed to retrieve status stream items for Admin procurementsByKey cache');
                    }

                    return $this->dashboardService->getProcurementsByKey($states);
                }
            );

            if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
                Log::warning('Admin ProcurementsByKey is null or empty after cache check.');
                $procurementsByKey = collect();
            }

            $recentActivities = Cache::remember(
                DashboardCacheKeys::recentActivities('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.activities')),
                fn () => $this->dashboardService->getRecentActivities()
            );

            $stats = Cache::remember(
                DashboardCacheKeys::stats('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.stats')),
                function () use ($procurementsByKey) {
                    Log::info('Cache miss: Recalculating Admin dashboard stats');

                    return $this->getAdminDashboardStats($procurementsByKey);
                }
            );

            $procurementDistribution = Cache::remember(
                DashboardCacheKeys::procurementDistribution('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.distribution')),
                fn () => $this->dashboardService->getProcurementDistributionData($procurementsByKey)
            );

            $userActivityAnalytics = Cache::remember(
                DashboardCacheKeys::userActivityAnalytics('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.user_analytics')),
                fn () => $this->analyticsService->getUserActivityAnalytics('30_days', null)
            );

            $dashboardData = [
                'recentProcurements' => $this->dashboardService->getRecentProcurements($procurementsByKey),
                'procurementDistribution' => $procurementDistribution,
                'recentActivities' => $recentActivities,
                'stats' => $stats,
                'analytics' => [
                    'user_activity' => $userActivityAnalytics,
                ],
            ];

            return Inertia::render('admin/dashboard', $dashboardData);
        } catch (Exception $e) {
            Log::error('Failed to retrieve Admin dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DashboardCacheKeys::clearAll('admin');

            return Inertia::render('admin/dashboard', [
                'recentProcurements' => [],
                'procurementDistribution' => [],
                'recentActivities' => [],
                'stats' => $this->dashboardService->getEmptyStats(),
                'analytics' => [
                    'user_activity' => null,
                ],
                'error' => 'Failed to retrieve dashboard data. Please try again later.',
            ]);
        }
    }

    /**
     * Get admin dashboard statistics
     */
    private function getAdminDashboardStats($procurementsByKey): array
    {
        try {
            $totalDocuments = Cache::remember(
                DashboardCacheKeys::totalDocuments('admin'),
                now()->addMinutes(config('dashboard.cache_ttl.stats')),
                function () use ($procurementsByKey) {
                    Log::info('Cache miss: Recalculating total documents for Admin dashboard stats');

                    return $this->dashboardService->getTotalDocuments($procurementsByKey);
                }
            );

            return $this->dashboardService->calculateStats($procurementsByKey, $totalDocuments);
        } catch (Exception $e) {
            Log::error('Failed to calculate Admin dashboard stats', ['error' => $e->getMessage()]);
            Cache::forget(DashboardCacheKeys::totalDocuments('admin'));

            return $this->dashboardService->getEmptyStats();
        }
    }
}
