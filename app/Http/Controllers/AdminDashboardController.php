<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\AdminAnalyticsService;
use App\Services\BlockchainRpcClient;
use App\Services\CacheStrategyService;
use App\Services\DashboardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AdminDashboardController extends BaseDashboardController
{
    public function __construct(
        BlockchainRpcClient $multichain,
        DashboardService $dashboardService,
        CacheStrategyService $cacheStrategy,
        private AdminAnalyticsService $analyticsService
    ) {
        parent::__construct($multichain, $dashboardService, $cacheStrategy);
    }

    /**
     * Get the role name for middleware and cache keys
     */
    protected function getRoleName(): string
    {
        return UserRole::ADMIN->value;
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
     * Now deferred for better performance
     */
    protected function getAdditionalDashboardData(Collection $procurementsByKey, string $roleName): array
    {
        return [
            'analytics' => Inertia::defer(fn () => [
                'user_activity' => $this->analyticsService->getUserActivityAnalytics('30_days', null),
            ]),
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
