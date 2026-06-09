<?php

namespace App\Http\Controllers;

use App\Contracts\CacheStrategyInterface;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\ProcurementRepository;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\Manager;
use App\Services\ProcurementStageTransitionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BacSecretariatController extends BaseDashboardController
{
    public function __construct(
        protected Manager $multichain,
        protected DashboardService $dashboardService,
        CacheStrategyInterface $cacheStrategy,
        ProcurementRepository $procurementRepository,
        private ProcurementStageTransitionService $stageTransitionService
    ) {
        parent::__construct($multichain, $dashboardService, $cacheStrategy, $procurementRepository);
    }

    protected function getRoleName(): string
    {
        return 'bac_secretariat';
    }

    protected function getRoleLabel(): string
    {
        return 'Bids and Awards Committee Secretariat';
    }

    protected function getViewName(): string
    {
        return 'bac-secretariat/dashboard';
    }

    public function dashboard()
    {
        $this->authorize('view-bac-secretariat-dashboard');

        return $this->index();
    }

    protected function getAdditionalDashboardData($procurementsByKey, string $roleName): array
    {
        $cacheUserId = $this->getDashboardCacheUserId($roleName);

        // Defer priority actions - they're heavy and can load after initial render
        return [
            'priorityActions' => Inertia::defer(function () use ($cacheUserId, $procurementsByKey, $roleName) {
                // Use database cache for potentially large priority actions list
                return Cache::store('database')->remember(
                    DashboardCacheKeys::priorityActions($roleName, $cacheUserId),
                    now()->addMinutes(config('dashboard.cache_ttl.priority_actions')),
                    function () use ($procurementsByKey) {
                        $allPriorityActions = $this->getPriorityActions($procurementsByKey);

                        return array_slice($allPriorityActions, 0, config('dashboard.display_limits.priority_actions'));
                    }
                );
            }),
        ];
    }

    protected function getEmptyAdditionalData(): array
    {
        return [
            'priorityActions' => [],
        ];
    }

    protected function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        $cacheUserId = $this->getDashboardCacheUserId($this->getRoleName());

        // Get all priority actions count - small data, can use default cache
        $allPriorityActionsCount = Cache::remember(
            DashboardCacheKeys::priorityActionsCount($this->getRoleName(), $cacheUserId),
            now()->addMinutes(config('dashboard.cache_ttl.priority_actions')),
            function () use ($procurementsByKey) {
                return count($this->getPriorityActions($procurementsByKey));
            }
        );

        $stats = parent::getDashboardStats($procurementsByKey, $allPriorityActionsCount);
        $stats['pendingActions'] = $allPriorityActionsCount;

        return $stats;
    }

    private function getPriorityActions($procurementsByKey)
    {
        try {
            $priorityActions = [];

            foreach ($procurementsByKey as $procurement) {
                try {
                    // Convert enum values to display names for priority action matching
                    $stageEnum = StageEnums::tryFrom($procurement['stage']);
                    $statusEnum = StatusEnums::tryFrom($procurement['status']);

                    $displayStage = $stageEnum ? $stageEnum->getDisplayName() : $procurement['stage'];
                    $displayStatus = $statusEnum ? $statusEnum->getDisplayName() : $procurement['status'];

                    $action = $this->stageTransitionService->getPriorityAction(
                        $displayStage,
                        $displayStatus,
                        $procurement['id'],
                        $procurement['title']
                    );

                    if ($action !== null) {
                        $priorityActions[] = $action;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to get priority action for procurement {$procurement['id']}", [
                        'error' => 'An error occurred processing the BAC secretariat request.',
                    ]);

                    continue;
                }
            }

            return $priorityActions;

        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to retrieve priority actions', [
                'error' => 'An error occurred processing the BAC secretariat request.',
            ]);

            return [];
        }
    }
}
