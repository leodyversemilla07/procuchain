<?php

use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\StatusData;
use App\Models\User;
use App\Repositories\ProcurementRecordRepository;
use App\Repositories\ProcurementRepository;
use App\Services\DashboardCacheKeys;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\Procurement\UserNameResolverService;
use App\Services\UserService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;

beforeEach(function () {
    foreach (['admin', 'bac_secretariat', 'bac_chairman', 'hope'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('renders the procurement list page for each supported role', function () {
    $cases = [
        ['role' => 'bac_secretariat', 'route' => 'bac-secretariat.procurements.index'],
        ['role' => 'bac_chairman', 'route' => 'bac-chairman.procurements.index'],
        ['role' => 'hope', 'route' => 'hope.procurements.index'],
        ['role' => 'admin', 'route' => 'admin.procurements.index'],
    ];

    foreach ($cases as $case) {
        $user = User::factory()->create([
            'blockchain_address' => "{$case['role']}-address",
        ]);
        $user->assignRole($case['role']);

        app()->instance(ProcurementListAggregatorService::class, buildIsolationAggregator());

        actingAs($user);

        get(route($case['route']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/procurements-list')
                ->has('procurements', 0)
            );
    }
});

it('filters procurements for bac secretariat ownership or blockchain interaction', function () {
    $secretariat = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);

    $aggregator = buildIsolationAggregator(
        repositoryFixtures: [
            'PR-2025-994-0001' => isolationProcurementFixture('PR-2025-994-0001', (string) $secretariat->id),
            'PR-2025-994-0002' => isolationProcurementFixture('PR-2025-994-0002', '999'),
            'PR-2025-994-0003' => isolationProcurementFixture('PR-2025-994-0003', '888'),
        ],
        statusFixtures: [
            isolationStatusFixture('PR-2025-994-0001', 'different-address', 'Owned Procurement'),
            isolationStatusFixture('PR-2025-994-0002', 'secretariat-address', 'Touched Procurement'),
            isolationStatusFixture('PR-2025-994-0003', 'different-address', 'Blocked Procurement'),
        ],
    );

    $procurements = $aggregator->fetchAllProcurements(
        skipActions: true,
        filterByUserId: (string) $secretariat->id,
        filterByUserAddress: 'secretariat-address',
    );

    expect(collect($procurements)->pluck('id')->all())
        ->toHaveCount(2)
        ->toContain('PR-2025-994-0001', 'PR-2025-994-0002');
});

it('returns all procurements when no visibility filters are applied', function () {
    $aggregator = buildIsolationAggregator(
        repositoryFixtures: [
            'PR-2025-994-0001' => isolationProcurementFixture('PR-2025-994-0001', '1'),
            'PR-2025-994-0002' => isolationProcurementFixture('PR-2025-994-0002', '2'),
            'PR-2025-994-0003' => isolationProcurementFixture('PR-2025-994-0003', '3'),
        ],
        statusFixtures: [
            isolationStatusFixture('PR-2025-994-0001', 'first-address', 'Owned Procurement'),
            isolationStatusFixture('PR-2025-994-0002', 'second-address', 'Touched Procurement'),
            isolationStatusFixture('PR-2025-994-0003', 'third-address', 'Blocked Procurement'),
        ],
    );

    $procurements = $aggregator->fetchAllProcurements(skipActions: true);

    expect(collect($procurements)->pluck('id')->all())
        ->toHaveCount(3)
        ->toContain('PR-2025-994-0001', 'PR-2025-994-0002', 'PR-2025-994-0003');
});

it('builds distinct dashboard cache keys for scoped and unscoped access', function () {
    expect(DashboardCacheKeys::procurements('bac_secretariat', '1'))
        ->toBe('dashboard:bac_secretariat:procurements_by_key:user:1');

    expect(DashboardCacheKeys::stats('bac_secretariat', '1'))
        ->toBe('dashboard:bac_secretariat:stats:user:1');

    expect(DashboardCacheKeys::procurements('admin'))
        ->toBe('dashboard:admin:procurements_by_key');

    expect(DashboardCacheKeys::stats('admin'))
        ->toBe('dashboard:admin:stats');
});

/**
 * @param  array<string, ProcurementData>  $repositoryFixtures
 * @param  array<int, StatusData>  $statusFixtures
 */
function buildIsolationAggregator(array $repositoryFixtures = [], array $statusFixtures = []): ProcurementListAggregatorService
{
    $repository = mock(ProcurementRepository::class);
    $repository->shouldReceive('findManyByProcurement')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $prNumbers) use ($repositoryFixtures): array {
            $result = [];
            foreach ($prNumbers as $prNumber) {
                $result[$prNumber] = $repositoryFixtures[$prNumber] ?? null;
            }

            return $result;
        });

    $mirrorRepository = mock(ProcurementRecordRepository::class);
    $mirrorRepository->shouldReceive('getLatestStatusByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn($statusFixtures);
    $mirrorRepository->shouldReceive('getAllDocuments')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $mirrorRepository->shouldReceive('findManyByProcurement')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $prNumbers) use ($repositoryFixtures): array {
            $result = [];
            foreach ($prNumbers as $prNumber) {
                $result[$prNumber] = $repositoryFixtures[$prNumber] ?? null;
            }

            return $result;
        });
    $mirrorRepository->shouldReceive('getArchivedPrNumbers')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $mirrorRepository->shouldReceive('procurementExists')
        ->zeroOrMoreTimes()
        ->andReturn(false);

    return new ProcurementListAggregatorService(
        $mirrorRepository,
        new ProcurementFormatterService,
        new ProcurementActionService($repository),
        new UserNameResolverService(app(UserService::class)),
    );
}

function isolationProcurementFixture(string $prNumber, string $userId): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => $prNumber,
        'title' => 'Isolation Fixture',
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

function isolationStatusFixture(
    string $prNumber,
    string $userAddress,
    string $title,
): StatusData {
    return new StatusData(
        prNumber: $prNumber,
        procurementTitle: $title,
        stage: 'procurement_initiation',
        currentStatus: 'draft',
        userAddress: $userAddress,
        timestamp: now(),
        previousStatus: null,
        metadata: [],
    );
}
