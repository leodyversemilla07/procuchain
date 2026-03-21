<?php

use App\Contracts\CacheStrategyInterface;
use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\AdminAnalyticsService;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\Manager;
use App\Services\ProcurementStageTransitionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

test('guests are redirected to login for all dashboards', function () {
    foreach ([
        'bac-secretariat.dashboard',
        'bac-chairman.dashboard',
        'hope.dashboard',
        'admin.dashboard',
    ] as $routeName) {
        $this->get(route($routeName))->assertRedirect('/login');
    }
});

test('users can access only their role-specific dashboard', function () {
    $cases = [
        [
            'role' => 'bac_secretariat',
            'allowed' => 'bac-secretariat.dashboard',
            'blocked' => ['bac-chairman.dashboard', 'hope.dashboard', 'admin.dashboard'],
        ],
        [
            'role' => 'bac_chairman',
            'allowed' => 'bac-chairman.dashboard',
            'blocked' => ['bac-secretariat.dashboard', 'hope.dashboard', 'admin.dashboard'],
        ],
        [
            'role' => 'hope',
            'allowed' => 'hope.dashboard',
            'blocked' => ['bac-secretariat.dashboard', 'bac-chairman.dashboard', 'admin.dashboard'],
        ],
        [
            'role' => 'admin',
            'allowed' => 'admin.dashboard',
            'blocked' => ['bac-secretariat.dashboard', 'bac-chairman.dashboard', 'hope.dashboard'],
        ],
    ];

    foreach ($cases as $case) {
        bindDashboardDependencies();

        $user = User::factory()->create([
            'blockchain_address' => "{$case['role']}-address",
        ]);
        $user->assignRole($case['role']);

        $this->actingAs($user);
        $this->get(route($case['allowed']))->assertOk();

        foreach ($case['blocked'] as $blockedRoute) {
            $this->get(route($blockedRoute))->assertForbidden();
        }

        $this->post('/logout');
    }
});

test('bac secretariat dashboard uses user scoped cache keys and filtered procurements', function () {
    Cache::flush();

    $secretariatUser = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);
    $secretariatUser->assignRole('bac_secretariat');

    $procurementsByKey = collect([
        'PR-001' => [
            'id' => 'PR-001',
            'title' => 'Allowed Procurement',
            'stage' => 'procurement_initiation',
            'status' => 'draft',
            'user_address' => 'secretariat-address',
        ],
        'PR-002' => [
            'id' => 'PR-002',
            'title' => 'Blocked Procurement',
            'stage' => 'procurement_initiation',
            'status' => 'draft',
            'user_address' => 'different-address',
        ],
    ]);

    $filteredExpectation = function ($collection) {
        return $collection instanceof Collection
            && $collection->keys()->all() === ['PR-001'];
    };

    $manager = \Mockery::mock(Manager::class);
    $manager->shouldReceive('liststreamitems')
        ->once()
        ->withAnyArgs()
        ->andReturn([
            ['data' => ['json' => ['pr_number' => 'PR-001', 'procurement_title' => 'Allowed Procurement']]],
            ['data' => ['json' => ['pr_number' => 'PR-002', 'procurement_title' => 'Blocked Procurement']]],
        ]);

    $dashboardService = \Mockery::mock(DashboardService::class);
    $dashboardService->shouldReceive('getProcurementsByKey')
        ->once()
        ->andReturn($procurementsByKey);
    $dashboardService->shouldReceive('getRecentActivities')
        ->once()
        ->andReturn([]);
    $dashboardService->shouldReceive('getTotalDocuments')
        ->once()
        ->with(\Mockery::on($filteredExpectation))
        ->andReturn(0);
    $dashboardService->shouldReceive('calculateStats')
        ->once()
        ->with(\Mockery::on($filteredExpectation), 0)
        ->andReturn(['total' => 1]);
    $dashboardService->shouldReceive('getProcurementDistributionData')
        ->once()
        ->with(\Mockery::on($filteredExpectation))
        ->andReturn([]);

    $cacheStrategy = \Mockery::mock(CacheStrategyInterface::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheKeys::procurements('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            if ($key === DashboardCacheKeys::recentActivities('bac_secretariat')) {
                return $callback();
            }

            if ($key === DashboardCacheKeys::procurementDistribution('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected large cache key [{$key}]");
        });
    $cacheStrategy->shouldReceive('rememberSmall')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheKeys::stats('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            if ($key === DashboardCacheKeys::totalDocuments('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected small cache key [{$key}]");
        });

    $procurementRepository = \Mockery::mock(ProcurementRepository::class);
    $procurementRepository->shouldReceive('findManyByProcurement')
        ->once()
        ->with(['PR-001', 'PR-002'])
        ->andReturn([
            'PR-001' => dashboardProcurementFixture('PR-001', (string) $secretariatUser->id),
            'PR-002' => dashboardProcurementFixture('PR-002', '999'),
        ]);

    $stageTransitionService = \Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->once()
        ->withAnyArgs()
        ->andReturn(null);

    $analyticsService = \Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $this->app->instance(Manager::class, $manager);
    $this->app->instance(DashboardService::class, $dashboardService);
    $this->app->instance(CacheStrategyInterface::class, $cacheStrategy);
    $this->app->instance(ProcurementRepository::class, $procurementRepository);
    $this->app->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    $this->app->instance(AdminAnalyticsService::class, $analyticsService);

    $this->actingAs($secretariatUser);

    $this->get(route('bac-secretariat.dashboard'))->assertOk();
});

function bindDashboardDependencies(): void
{
    $manager = \Mockery::mock(Manager::class);
    $manager->shouldReceive('liststreamitems')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $dashboardService = \Mockery::mock(DashboardService::class);
    $dashboardService->shouldReceive('getProcurementsByKey')
        ->zeroOrMoreTimes()
        ->andReturn(collect());
    $dashboardService->shouldReceive('getRecentActivities')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('getTotalDocuments')
        ->zeroOrMoreTimes()
        ->andReturn(0);
    $dashboardService->shouldReceive('calculateStats')
        ->zeroOrMoreTimes()
        ->andReturn([
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'pendingActions' => 0,
        ]);
    $dashboardService->shouldReceive('getProcurementDistributionData')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('getRecentProcurements')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('getPhaseStatistics')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('groupProcurementsByPhase')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('getModeStatistics')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $dashboardService->shouldReceive('getEmptyStats')
        ->zeroOrMoreTimes()
        ->andReturn([
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'pendingActions' => 0,
        ]);

    $cacheStrategy = \Mockery::mock(CacheStrategyInterface::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
    $cacheStrategy->shouldReceive('rememberSmall')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

    $procurementRepository = \Mockery::mock(ProcurementRepository::class);
    $procurementRepository->shouldReceive('findManyByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $stageTransitionService = \Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $analyticsService = \Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    app()->instance(Manager::class, $manager);
    app()->instance(DashboardService::class, $dashboardService);
    app()->instance(CacheStrategyInterface::class, $cacheStrategy);
    app()->instance(ProcurementRepository::class, $procurementRepository);
    app()->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    app()->instance(AdminAnalyticsService::class, $analyticsService);
}

function dashboardProcurementFixture(string $prNumber, string $userId): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => $prNumber,
        'title' => 'Dashboard Fixture',
        'description' => 'Fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => $userId,
        'created_at' => now()->toIso8601String(),
    ]);
}
