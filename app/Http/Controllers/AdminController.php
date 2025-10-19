<?php

namespace App\Http\Controllers;

use App\Services\AdminAnalyticsService;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\MultichainService;
use Illuminate\Support\Facades\Cache;

class AdminController extends BaseDashboardController
{
    public function __construct(
        MultichainService $multichainService,
        DashboardService $dashboardService,
        private AdminAnalyticsService $analyticsService
    ) {
        parent::__construct($multichainService, $dashboardService);
    }

    /**
     * Get the role name for middleware and cache keys
     */
    protected function getRoleName(): string
    {
        return 'admin';
    }

    /**
     * Get the human-readable role label for logging
     */
    protected function getRoleLabel(): string
    {
        return 'Admin';
    }

    /**
     * Get the Inertia view name
     */
    protected function getViewName(): string
    {
        return 'admin/dashboard';
    }

    /**
     * Get additional dashboard data specific to admin (analytics)
     */
    protected function getAdditionalDashboardData($procurementsByKey, string $roleName): array
    {
        $userActivityAnalytics = Cache::remember(
            DashboardCacheKeys::userActivityAnalytics($roleName),
            now()->addMinutes(config('dashboard.cache_ttl.user_analytics')),
            fn () => $this->analyticsService->getUserActivityAnalytics('30_days', null)
        );

        return [
            'analytics' => [
                'user_activity' => $userActivityAnalytics,
            ],
        ];
    }

    /**
     * Get empty additional data for error responses
     */
    protected function getEmptyAdditionalData(): array
    {
        return [
            'analytics' => [
                'user_activity' => null,
            ],
        ];
    }
}
