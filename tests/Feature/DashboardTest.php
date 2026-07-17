<?php

use App\Models\User;
use App\Services\AdminAnalyticsService;
use App\Services\BlockchainRpcClient;
use App\Services\CacheStrategyService;
use App\Services\DashboardCacheService;
use App\Services\DashboardService;
use App\Services\ProcurementStageTransitionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

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

test('role dashboards render the expected Inertia page and shared auth role', function () {
    $cases = [
        'bac_secretariat' => [
            'route' => 'bac-secretariat.dashboard',
            'component' => 'bac-secretariat/dashboard',
        ],
        'bac_chairman' => [
            'route' => 'bac-chairman.dashboard',
            'component' => 'bac-chairman/dashboard',
        ],
        'hope' => [
            'route' => 'hope.dashboard',
            'component' => 'hope/dashboard',
        ],
        'admin' => [
            'route' => 'admin.dashboard',
            'component' => 'admin/dashboard',
        ],
    ];

    foreach ($cases as $role => $case) {
        bindDashboardDependencies();

        $user = User::factory()->create([
            'name' => strtoupper($role).' User',
            'email' => "{$role}@example.com",
            'blockchain_address' => "{$role}-address",
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $this->get(route($case['route']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($case['component'])
                ->where('auth.role', $role)
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', "{$role}@example.com")
                ->where('auth.user.role', $role)
            );

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
        'PR-2025-001-0001' => [
            'id' => 'PR-2025-001-0001',
            'title' => 'Allowed Procurement',
            'stage' => 'procurement_initiation',
            'status' => 'draft',
            'user_address' => 'secretariat-address',
        ],
        'PR-2025-002-0001' => [
            'id' => 'PR-2025-002-0001',
            'title' => 'Blocked Procurement',
            'stage' => 'procurement_initiation',
            'status' => 'draft',
            'user_address' => 'different-address',
        ],
    ]);

    $filteredExpectation = function ($collection) {
        return $collection instanceof Collection
            && $collection->keys()->all() === ['PR-2025-001-0001'];
    };

    $blockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);

    $dashboardService = Mockery::mock(DashboardService::class);
    $dashboardService->shouldReceive('getProcurementsByKey')
        ->once()
        ->andReturn($procurementsByKey);
    $dashboardService->shouldReceive('getRecentActivities')
        ->once()
        ->andReturn([]);
    $dashboardService->shouldReceive('getTotalDocuments')
        ->once()
        ->with(Mockery::on($filteredExpectation))
        ->andReturn(0);
    $dashboardService->shouldReceive('calculateStats')
        ->once()
        ->with(Mockery::on($filteredExpectation), 0)
        ->andReturn(['total' => 1]);
    $dashboardService->shouldReceive('getProcurementDistributionData')
        ->once()
        ->with(Mockery::on($filteredExpectation))
        ->andReturn([]);
    $dashboardService->shouldReceive('getEmptyStats')
        ->zeroOrMoreTimes()
        ->andReturn([
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ]);

    $cacheStrategy = Mockery::mock(CacheStrategyService::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::recentActivities('bac_secretariat')) {
                return $callback();
            }

            if ($key === DashboardCacheService::procurementDistribution('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected large cache key [{$key}]");
        });
    $cacheStrategy->shouldReceive('rememberSmall')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::stats('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            if ($key === DashboardCacheService::totalDocuments('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected small cache key [{$key}]");
        });

    $stageTransitionService = Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->once()
        ->withAnyArgs()
        ->andReturn(null);

    $analyticsService = Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClient);
    $this->app->instance(DashboardService::class, $dashboardService);
    $this->app->instance(CacheStrategyService::class, $cacheStrategy);
    $this->app->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    $this->app->instance(AdminAnalyticsService::class, $analyticsService);

    $this->actingAs($secretariatUser);

    $this->get(route('bac-secretariat.dashboard'))->assertOk();

    expect(Cache::store('database')->get(
        DashboardCacheService::procurements('bac_secretariat', (string) $secretariatUser->id)
    ))->toBeArray()->toHaveKey('PR-2025-001-0001');

    expect(Cache::store('database')->get(
        DashboardCacheService::procurementsSnapshot('bac_secretariat', (string) $secretariatUser->id)
    ))->toBeArray()->toHaveKey('PR-2025-001-0001');
});

test('bac secretariat dashboard renders without global error when multichain is unavailable', function () {
    Cache::flush();

    $secretariatUser = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);
    $secretariatUser->assignRole('bac_secretariat');

    $blockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);

    $dashboardService = Mockery::mock(DashboardService::class);
    $dashboardService->shouldReceive('getRecentActivities')
        ->once()
        ->andReturn([]);
    $dashboardService->shouldReceive('getTotalDocuments')
        ->once()
        ->with(Mockery::type(Collection::class))
        ->andReturn(0);
    $dashboardService->shouldReceive('calculateStats')
        ->once()
        ->with(Mockery::type(Collection::class), 0)
        ->andReturn([
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ]);
    $dashboardService->shouldReceive('getProcurementDistributionData')
        ->once()
        ->with(Mockery::type(Collection::class))
        ->andReturn([]);
    $dashboardService->shouldReceive('getEmptyStats')
        ->zeroOrMoreTimes()
        ->andReturn([
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ]);

    Cache::store('database')->put(
        DashboardCacheService::procurementsSnapshot('bac_secretariat', (string) $secretariatUser->id),
        [
            'PR-2025-001-0001' => [
                'id' => 'PR-2025-001-0001',
                'title' => 'Accessible Procurement',
                'stage' => 'procurement_initiation',
                'status' => 'draft',
                'user_address' => 'secretariat-address',
            ],
        ],
        now()->addDay()
    );

    $cacheStrategy = Mockery::mock(CacheStrategyService::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::recentActivities('bac_secretariat')) {
                return $callback();
            }

            if ($key === DashboardCacheService::procurementDistribution('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected large cache key [{$key}]");
        });
    $cacheStrategy->shouldReceive('rememberSmall')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::stats('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            if ($key === DashboardCacheService::totalDocuments('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected small cache key [{$key}]");
        });

    $stageTransitionService = Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $analyticsService = Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClient);
    $this->app->instance(DashboardService::class, $dashboardService);
    $this->app->instance(CacheStrategyService::class, $cacheStrategy);
    $this->app->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    $this->app->instance(AdminAnalyticsService::class, $analyticsService);

    $this->actingAs($secretariatUser);

    $response = $this->get(route('bac-secretariat.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('bac-secretariat/dashboard')
        ->where('stats.ongoingProjects', 0)
        ->where('stats.totalDocuments', 0)
        ->where('stats.pendingActions', 0)
        ->missing('error')
    );
});

test('bac secretariat dashboard reads procurements from array cache without blockchain call', function () {
    Cache::flush();

    $secretariatUser = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);
    $secretariatUser->assignRole('bac_secretariat');

    Cache::store('database')->put(
        DashboardCacheService::procurements('bac_secretariat', (string) $secretariatUser->id),
        [
            'PR-2025-001-0001' => [
                'id' => 'PR-2025-001-0001',
                'title' => 'Cached Procurement',
                'stage' => 'procurement_initiation',
                'status' => 'draft',
                'user_address' => 'secretariat-address',
            ],
        ],
        now()->addMinutes(config('dashboard.cache_ttl.procurements'))
    );

    $blockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $blockchainRpcClient->shouldReceive('liststreamitems')->never();

    $dashboardService = Mockery::mock(DashboardService::class);
    $dashboardService->shouldReceive('getRecentActivities')
        ->once()
        ->andReturn([]);
    $dashboardService->shouldReceive('getTotalDocuments')
        ->once()
        ->with(Mockery::type(Collection::class))
        ->andReturn(0);
    $dashboardService->shouldReceive('calculateStats')
        ->once()
        ->with(Mockery::type(Collection::class), 0)
        ->andReturn([
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ]);
    $dashboardService->shouldReceive('getProcurementDistributionData')
        ->once()
        ->with(Mockery::type(Collection::class))
        ->andReturn([]);
    $dashboardService->shouldReceive('getEmptyStats')
        ->zeroOrMoreTimes()
        ->andReturn([
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ]);

    $cacheStrategy = Mockery::mock(CacheStrategyService::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::recentActivities('bac_secretariat')) {
                return $callback();
            }

            if ($key === DashboardCacheService::procurementDistribution('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected large cache key [{$key}]");
        });
    $cacheStrategy->shouldReceive('rememberSmall')
        ->andReturnUsing(function ($key, $ttl, $callback) use ($secretariatUser) {
            if ($key === DashboardCacheService::stats('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            if ($key === DashboardCacheService::totalDocuments('bac_secretariat', (string) $secretariatUser->id)) {
                return $callback();
            }

            throw new RuntimeException("Unexpected small cache key [{$key}]");
        });

    $stageTransitionService = Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->once()
        ->withAnyArgs()
        ->andReturn(null);

    $analyticsService = Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClient);
    $this->app->instance(DashboardService::class, $dashboardService);
    $this->app->instance(CacheStrategyService::class, $cacheStrategy);
    $this->app->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    $this->app->instance(AdminAnalyticsService::class, $analyticsService);

    $this->actingAs($secretariatUser);

    $this->get(route('bac-secretariat.dashboard'))->assertOk();
});

function bindDashboardDependencies(): void
{
    $blockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $blockchainRpcClient->shouldReceive('liststreamitems')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $dashboardService = Mockery::mock(DashboardService::class);
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

    $cacheStrategy = Mockery::mock(CacheStrategyService::class);
    $cacheStrategy->shouldReceive('rememberLarge')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
    $cacheStrategy->shouldReceive('rememberSmall')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

    $stageTransitionService = Mockery::mock(ProcurementStageTransitionService::class);
    $stageTransitionService->shouldReceive('getPriorityAction')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $analyticsService = Mockery::mock(AdminAnalyticsService::class);
    $analyticsService->shouldReceive('getUserActivityAnalytics')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    app()->instance(BlockchainRpcClient::class, $blockchainRpcClient);
    app()->instance(DashboardService::class, $dashboardService);
    app()->instance(CacheStrategyService::class, $cacheStrategy);
    app()->instance(ProcurementStageTransitionService::class, $stageTransitionService);
    app()->instance(AdminAnalyticsService::class, $analyticsService);
}
