<?php

use App\Contracts\CacheStrategyInterface;
use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\Manager;
use App\Services\ProcurementStageTransitionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to login for all dashboards', function () {
    // Test Bids and Awards Committee Secretariat Dashboard
    $this->get(route('bac-secretariat.dashboard'))->assertRedirect('/login');

    // Test Bids and Awards Committee Chairman Dashboard
    $this->get(route('bac-chairman.dashboard'))->assertRedirect('/login');

    // Test Head of Procuring Entity Dashboard
    $this->get(route('hope.dashboard'))->assertRedirect('/login');

    // Test Admin dashboard
    $this->get(route('admin.dashboard'))->assertRedirect('/login');
});

test('users can access their role-specific dashboard', function () {
    // Test BAC Secretariat user
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $secretariatUser = User::factory()->create();
    $secretariatUser->assignRole('bac_secretariat');
    $this->actingAs($secretariatUser);
    $this->get(route('bac-secretariat.dashboard'))->assertOk();
    $this->post('/logout');

    // Test BAC Chairman user
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
    $chairmanUser = User::factory()->create();
    $chairmanUser->assignRole('bac_chairman');
    $this->actingAs($chairmanUser);
    $this->get(route('bac-chairman.dashboard'))->assertOk();
    $this->post('/logout');

    // Test Hope user
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $hopeUser = User::factory()->create();
    $hopeUser->assignRole('hope');
    $this->actingAs($hopeUser);
    $this->get(route('hope.dashboard'))->assertOk();
    $this->post('/logout');

    // Test Admin user
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    $this->actingAs($adminUser);
    $this->get(route('admin.dashboard'))->assertOk();
});

test('users cannot access dashboards for other roles', function () {
    // BAC Secretariat user cannot access other dashboards
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $secretariatUser = User::factory()->create();
    $secretariatUser->assignRole('bac_secretariat');
    $this->actingAs($secretariatUser);
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // BAC Chairman user cannot access other dashboards
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
    $chairmanUser = User::factory()->create();
    $chairmanUser->assignRole('bac_chairman');
    $this->actingAs($chairmanUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // Hope user cannot access other dashboards
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $hopeUser = User::factory()->create();
    $hopeUser->assignRole('hope');
    $this->actingAs($hopeUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->post('/logout');

    // Admin user cannot access other dashboards
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    $this->actingAs($adminUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
});

test('bac secretariat dashboard uses user scoped cache keys and filtered procurements', function () {
    Cache::flush();

    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

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
        return $collection instanceof \Illuminate\Support\Collection
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

    $this->app->instance(Manager::class, $manager);
    $this->app->instance(DashboardService::class, $dashboardService);
    $this->app->instance(CacheStrategyInterface::class, $cacheStrategy);
    $this->app->instance(ProcurementRepository::class, $procurementRepository);
    $this->app->instance(ProcurementStageTransitionService::class, $stageTransitionService);

    $this->actingAs($secretariatUser);

    $this->get(route('bac-secretariat.dashboard'))->assertOk();
});

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
