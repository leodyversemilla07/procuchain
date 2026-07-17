<?php

use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Services\Procurement\BlockchainAddressResolverService;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\ProcurementDataService;
use App\Services\UserService;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\mock;

beforeEach(function () {
    foreach (['bac_secretariat', 'bac_chairman', 'hope', 'admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);
    $this->user->assignRole('bac_secretariat');
});

describe('ProcurementListController', function () {
    describe('indexProcurementsList', function () {
        it('returns the procurements list page for each supported role', function () {
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

                bindProcurementControllerMocks(
                    repositoryFixture: procurementFixture('PR-2025-001-0001', (string) $user->id),
                    detailFixture: detailPayload('PR-2025-001-0001'),
                );

                actingAs($user);

                get(route($case['route']))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page
                        ->component('procurements/procurements-list')
                        ->has('procurements', 0)
                    );
            }
        });

        it('shows only procurements available to the bac secretariat user', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            bindProcurementControllerMocks(
                listRepositoryFixtures: [
                    'PR-2025-300-0001' => procurementFixture('PR-2025-300-0001', (string) $secretariat->id),
                    'PR-2025-400-0001' => procurementFixture('PR-2025-400-0001', '999'),
                ],
                listStatusFixtures: [
                    listStatusFixture('PR-2025-300-0001', 'secretariat-address', 'Owned Procurement'),
                    listStatusFixture('PR-2025-400-0001', 'different-address', 'Other Procurement'),
                ],
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('procurements', 1)
                    ->where('procurements.0.id', 'PR-2025-300-0001')
                    ->where('procurements.0.title', 'Test Procurement')
                );
        });

        it('does not scope admin procurement list results', function () {
            $admin = User::factory()->create([
                'blockchain_address' => 'admin-address',
            ]);
            $admin->assignRole('admin');

            bindProcurementControllerMocks(
                listRepositoryFixtures: [
                    'PR-2025-300-0001' => procurementFixture('PR-2025-300-0001', '1'),
                    'PR-2025-400-0001' => procurementFixture('PR-2025-400-0001', '2'),
                ],
                listStatusFixtures: [
                    listStatusFixture('PR-2025-300-0001', 'secretariat-address', 'Owned Procurement'),
                    listStatusFixture('PR-2025-400-0001', 'different-address', 'Other Procurement'),
                ],
            );

            actingAs($admin);

            get(route('admin.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('procurements', 2)
                );
        });
    });

    describe('showProcurement', function () {
        it('shows procurement details for supported roles', function () {
            $cases = [
                ['role' => 'bac_secretariat', 'route' => 'bac-secretariat.procurements.show', 'pr' => 'PR-2025-001-0001'],
                ['role' => 'bac_chairman', 'route' => 'bac-chairman.procurements.show', 'pr' => 'PR-2025-002-0001'],
                ['role' => 'hope', 'route' => 'hope.procurements.show', 'pr' => 'PR-2025-003-0001'],
                ['role' => 'admin', 'route' => 'admin.procurements.show', 'pr' => 'PR-2025-004-0001'],
            ];

            foreach ($cases as $case) {
                $user = User::factory()->create([
                    'blockchain_address' => "{$case['role']}-address",
                ]);
                $user->assignRole($case['role']);

                bindProcurementControllerMocks(
                    repositoryFixture: procurementFixture($case['pr'], (string) $user->id),
                    statusItems: collect([
                        detailStatusItem($user->blockchain_address),
                    ]),
                    detailFixture: detailPayload($case['pr']),
                );

                actingAs($user);

                get(route($case['route'], ['pr_number' => $case['pr']]))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page
                        ->component('procurements/show-procurement')
                    );
            }
        });

        it('forbids bac secretariat from viewing procurements they do not own or touch', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            bindProcurementControllerMocks(
                repositoryFixture: procurementFixture('PR-2025-100-0001', '999'),
                statusItems: collect([
                    detailStatusItem('different-address'),
                ]),
                detailFixture: detailPayload('PR-2025-100-0001'),
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-2025-100-0001']))
                ->assertForbidden();
        });

        it('allows bac secretariat to view procurements they interacted with', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            bindProcurementControllerMocks(
                repositoryFixture: procurementFixture('PR-2025-200-0001', '999'),
                statusItems: collect([
                    detailStatusItem('secretariat-address'),
                ]),
                detailFixture: detailPayload('PR-2025-200-0001'),
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-2025-200-0001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('forbids inaccessible blockchain status polling for bac secretariat', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            bindProcurementControllerMocks(
                repositoryFixture: procurementFixture('PR-2025-100-0001', '999'),
                statusItems: collect([
                    detailStatusItem('different-address'),
                ]),
            );

            actingAs($secretariat);

            getJson(route('procurements.blockchain-status', ['pr_number' => 'PR-2025-100-0001']))
                ->assertForbidden();
        });
    });

    describe('authorization', function () {
        it('requires authentication for procurement list and detail pages', function () {
            get(route('bac-secretariat.procurements.index'))
                ->assertRedirect(route('login'));

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-2025-001-0001']))
                ->assertRedirect(route('login'));
        });

        it('requires the correct role for role-scoped procurement routes', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');

            actingAs($chairman);
            get(route('bac-secretariat.procurements.index'))->assertForbidden();

            actingAs($this->user);
            get(route('bac-chairman.procurements.index'))->assertForbidden();
        });
    });
});

function procurementFixture(string $prNumber, string $userId): array
{
    return [
        'pr_number' => $prNumber,
        'title' => 'Test Procurement',
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

function listStatusFixture(
    string $prNumber,
    string $userAddress,
    string $title,
    string $stage = 'procurement_initiation',
    string $currentStatus = 'draft',
): array {
    return [
        'pr_number' => $prNumber,
        'procurement_title' => $title,
        'stage' => $stage,
        'current_status' => $currentStatus,
        'user_address' => $userAddress,
        'timestamp' => now(),
        'previous_status' => null,
        'metadata' => [],
    ];
}

function detailPayload(string $prNumber): array
{
    return [
        'procurement' => [
            'id' => $prNumber,
            'title' => 'Fixture Procurement',
            'status' => [
                'stage' => 'procurement_initiation',
                'current_status' => 'draft',
            ],
            'documents' => [],
            'events' => [],
            'timeline' => [],
            'is_archived' => false,
        ],
        'workflow' => [
            'mode' => null,
            'stages' => [],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function detailStatusItem(
    string $userAddress,
    string $stage = 'procurement_initiation',
    string $currentStatus = 'draft',
): array {
    return [
        'user_address' => $userAddress,
        'stage' => $stage,
        'current_status' => $currentStatus,
    ];
}

function bindProcurementControllerMocks(
    array $listRepositoryFixtures = [],
    array $listStatusFixtures = [],
    ?array $repositoryFixture = null,
    ?Collection $statusItems = null,
    ?array $detailFixture = null,
): void {
    seedProcurementListMirror($listRepositoryFixtures, $listStatusFixtures);

    $dataService = mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->zeroOrMoreTimes()
        ->andReturn($statusItems ?? collect());
    $dataService->shouldReceive('fetchAndProcessAllDocuments')
        ->zeroOrMoreTimes()
        ->andReturn($detailFixture['procurement']['documents'] ?? []);
    $dataService->shouldReceive('fetchAndProcessEvents')
        ->zeroOrMoreTimes()
        ->andReturn($detailFixture['procurement']['events'] ?? []);
    $dataService->shouldReceive('preloadUserNames')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $dataService->shouldReceive('buildProcurementData')
        ->zeroOrMoreTimes()
        ->andReturn($detailFixture['procurement'] ?? null);

    $aggregator = new ProcurementListAggregatorService(
        new ProcurementFormatterService,
        new ProcurementActionService,
        new BlockchainAddressResolverService(app(UserService::class)),
    );

    $detailService = new ProcurementDetailService($dataService);

    app()->instance(ProcurementListAggregatorService::class, $aggregator);
    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(ProcurementDetailService::class, $detailService);
}

function seedProcurementListMirror(array $repositoryFixtures, array $statusFixtures): void
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
            'txid' => "list-status-{$index}",
            'metadata' => $status['metadata'],
        ]);
    }
}
