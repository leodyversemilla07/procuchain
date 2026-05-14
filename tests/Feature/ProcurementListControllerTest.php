<?php

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\StatusData;
use App\Enums\StreamEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementArchiveRepository;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Services\Manager;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\Procurement\UserNameResolverService;
use App\Services\ProcurementDataService;
use App\Services\UserService;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
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
                    ->where('procurements.0.title', 'Owned Procurement')
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
            // TODO: Scoped procurement access control for blockchain status polling
            // is not yet implemented. Currently, any user with 'view blockchain transactions'
            // permission can poll any procurement's blockchain status.
            // This test should be enabled once per-procurement scoped access checks are added.
            $this->markTestSkipped('Scoped blockchain status access not yet implemented — tracked as future enhancement');
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

function procurementFixture(string $prNumber, string $userId): ProcurementData
{
    return ProcurementData::fromArray([
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
    ]);
}

function listStatusFixture(
    string $prNumber,
    string $userAddress,
    string $title,
    string $stage = 'procurement_initiation',
    string $currentStatus = 'draft',
): StatusData {
    return new StatusData(
        prNumber: $prNumber,
        procurementTitle: $title,
        stage: $stage,
        currentStatus: $currentStatus,
        userAddress: $userAddress,
        timestamp: now(),
        previousStatus: null,
        metadata: [],
    );
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

/**
 * @param  array<string, ProcurementData>  $listRepositoryFixtures
 * @param  array<int, StatusData>  $listStatusFixtures
 */
function bindProcurementControllerMocks(
    array $listRepositoryFixtures = [],
    array $listStatusFixtures = [],
    ?ProcurementData $repositoryFixture = null,
    ?Collection $statusItems = null,
    ?array $detailFixture = null,
): void {
    $repository = mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $prNumber) use ($listRepositoryFixtures, $repositoryFixture): ?ProcurementData {
            if (isset($listRepositoryFixtures[$prNumber])) {
                return $listRepositoryFixtures[$prNumber];
            }

            if ($repositoryFixture !== null && $repositoryFixture->prNumber === $prNumber) {
                return $repositoryFixture;
            }

            return $repositoryFixture;
        });
    $repository->shouldReceive('findManyByProcurement')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $prNumbers) use ($listRepositoryFixtures, $repositoryFixture): array {
            $fixtures = $listRepositoryFixtures;

            if ($repositoryFixture !== null) {
                $fixtures[$repositoryFixture->prNumber] = $repositoryFixture;
            }

            $result = [];
            foreach ($prNumbers as $prNumber) {
                $result[$prNumber] = $fixtures[$prNumber] ?? null;
            }

            return $result;
        });

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

    $manager = mock(Manager::class);
    $manager->shouldReceive('liststreamitems')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $stream) use ($listStatusFixtures): array {
            return match ($stream) {
                StreamEnums::STATUS->value => statusStreamItems($listStatusFixtures),
                StreamEnums::DOCUMENTS->value => [],
                StreamEnums::ARCHIVE->value => [],
                default => [],
            };
        });

    $aggregator = new ProcurementListAggregatorService(
        new StatusRepository($manager),
        new DocumentRepository($manager),
        $repository,
        new ProcurementArchiveRepository($manager),
        new ProcurementFormatterService,
        new ProcurementActionService($repository),
        new UserNameResolverService(app(UserService::class)),
    );

    $correctionRepository = mock(ProcurementCorrectionRepositoryInterface::class);
    $correctionRepository->shouldReceive('hasCorrections')
        ->zeroOrMoreTimes()
        ->andReturn(false);
    $correctionRepository->shouldReceive('getLatest')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $correctionRepository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $detailService = new ProcurementDetailService(
        $dataService,
        $repository,
        $correctionRepository,
    );

    app()->instance(ProcurementListAggregatorService::class, $aggregator);
    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(ProcurementRepository::class, $repository);
    app()->instance(ProcurementDetailService::class, $detailService);
    app()->instance(ProcurementCorrectionRepositoryInterface::class, $correctionRepository);
}

/**
 * @param  array<int, StatusData>  $statusDtos
 * @return array<int, array<string, mixed>>
 */
function statusStreamItems(array $statusDtos): array
{
    return array_map(function (StatusData $statusDto): array {
        return [
            'keys' => [$statusDto->prNumber],
            'data' => [
                'json' => $statusDto->toBlockchainArray(),
            ],
            'blocktime' => $statusDto->timestamp->timestamp,
        ];
    }, $statusDtos);
}
