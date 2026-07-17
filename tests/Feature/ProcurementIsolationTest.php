<?php

use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Services\DashboardCacheService;
use App\Services\Procurement\BlockchainAddressResolverService;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\UserService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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
    expect(DashboardCacheService::procurements('bac_secretariat', '1'))
        ->toBe('dashboard:bac_secretariat:procurements_by_key:user:1');

    expect(DashboardCacheService::stats('bac_secretariat', '1'))
        ->toBe('dashboard:bac_secretariat:stats:user:1');

    expect(DashboardCacheService::procurements('admin'))
        ->toBe('dashboard:admin:procurements_by_key');

    expect(DashboardCacheService::stats('admin'))
        ->toBe('dashboard:admin:stats');
});

function buildIsolationAggregator(array $repositoryFixtures = [], array $statusFixtures = []): ProcurementListAggregatorService
{
    seedIsolationProcurementMirror($repositoryFixtures, $statusFixtures);

    return new ProcurementListAggregatorService(
        new ProcurementFormatterService,
        new ProcurementActionService,
        new BlockchainAddressResolverService(app(UserService::class)),
    );
}

function seedIsolationProcurementMirror(array $repositoryFixtures, array $statusFixtures): void
{
    foreach ($repositoryFixtures as $fixture) {
        Procurement::updateOrCreate(
            ['pr_number' => $fixture['pr_number']],
            [
                'app_reference' => $fixture['app_reference'] ?? null,
                'title' => $fixture['title'],
                'description' => $fixture['description'],
                'category' => $fixture['category'],
                'procurement_mode' => $fixture['procurement_mode'],
                'office' => $fixture['office'],
                'end_user' => $fixture['end_user'] ?? null,
                'fund_source' => $fixture['funding_source'],
                'prepared_by' => $fixture['prepared_by'] ?? null,
                'abc_amount' => $fixture['abc_amount'],
                'current_status' => $fixture['status'],
                'user_id' => $fixture['user_id'],
                'user_address' => $fixture['user_address'] ?? null,
                'initiated_at' => $fixture['created_at'],
                'last_updated_at' => $fixture['created_at'],
            ],
        );
    }

    foreach ($statusFixtures as $index => $status) {
        $procurement = Procurement::firstOrCreate(
            ['pr_number' => $status['pr_number']],
            [
                'title' => $status['procurement_title'],
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
            ],
        );

        $procurement->update([
            'current_stage' => $status['stage'],
            'current_status' => $status['current_status'],
        ]);

        ProcurementStage::create([
            'procurement_id' => $procurement->id,
            'stage' => $status['stage'],
            'status' => $status['current_status'],
            'previous_status' => $status['previous_status'],
            'entered_at' => $status['timestamp'],
            'user_address' => $status['user_address'],
            'txid' => "isolation-status-{$index}",
            'metadata' => $status['metadata'],
        ]);
    }
}

function isolationProcurementFixture(string $prNumber, string $userId): array
{
    return [
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
    ];
}

function isolationStatusFixture(
    string $prNumber,
    string $userAddress,
    string $title,
): array {
    return [
        'pr_number' => $prNumber,
        'procurement_title' => $title,
        'stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'user_address' => $userAddress,
        'timestamp' => now(),
        'previous_status' => null,
        'metadata' => [],
    ];
}
