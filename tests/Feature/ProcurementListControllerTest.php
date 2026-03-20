<?php

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\StatusData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementArchiveRepository;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\Procurement\UserNameResolverService;
use App\Services\ProcurementDataService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function () {
    // Create roles if they don't exist
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('bac_secretariat');
});

describe('ProcurementListController', function () {
    describe('indexProcurementsList', function () {
        it('returns procurements list page for bac secretariat', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for bac chairman', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-chairman.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for hope', function () {
            $hope = User::factory()->create();
            $hope->assignRole('hope');
            actingAs($hope);

            get(route('hope.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for admin', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');
            actingAs($admin);

            get(route('admin.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('passes procurements data to view', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('procurements')
                );
        });

        it('passes bac secretariat visibility filters to procurement aggregation', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            $repository = \Mockery::mock(ProcurementRepository::class);
            $repository->shouldReceive('findManyByProcurement')
                ->twice()
                ->with(['PR-OWNED', 'PR-OTHER'])
                ->andReturn([
                    'PR-OWNED' => procurementFixture('PR-OWNED', (string) $secretariat->id),
                    'PR-OTHER' => procurementFixture('PR-OTHER', '999'),
                ]);
            $repository->shouldReceive('findByProcurement')
                ->andReturn(null);

            bindProcurementControllerDependencies(
                $this,
                aggregatorFixture([
                    statusFixture('PR-OWNED', 'Owned Procurement', 'secretariat-address'),
                    statusFixture('PR-OTHER', 'Other Procurement', 'different-address'),
                ], $repository),
                detailDataServiceFixture('PR-OWNED'),
                $repository
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('procurements', 1)
                    ->where('procurements.0.id', 'PR-OWNED')
                );
        });

        it('does not apply visibility filters for admin procurement aggregation', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $repository = \Mockery::mock(ProcurementRepository::class);
            $repository->shouldReceive('findManyByProcurement')
                ->once()
                ->with(['PR-OWNED', 'PR-OTHER'])
                ->andReturn([
                    'PR-OWNED' => procurementFixture('PR-OWNED', '1'),
                    'PR-OTHER' => procurementFixture('PR-OTHER', '2'),
                ]);
            $repository->shouldReceive('findByProcurement')
                ->andReturn(null);

            bindProcurementControllerDependencies(
                $this,
                aggregatorFixture([
                    statusFixture('PR-OWNED', 'Owned Procurement', 'secretariat-address'),
                    statusFixture('PR-OTHER', 'Other Procurement', 'different-address'),
                ], $repository),
                detailDataServiceFixture('PR-OWNED'),
                $repository
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
        it('shows single procurement for bac secretariat', function () {
            allowSecretariatAccess($this, $this->user, 'TEST-001');
            actingAs($this->user);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for bac chairman', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-chairman.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for hope', function () {
            $hope = User::factory()->create();
            $hope->assignRole('hope');
            actingAs($hope);

            get(route('hope.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for admin', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');
            actingAs($admin);

            get(route('admin.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('loads procurement show page successfully', function () {
            allowSecretariatAccess($this, $this->user, 'TEST-001');
            actingAs($this->user);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('forbids bac secretariat from viewing procurements they do not own or touch', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            $repository = \Mockery::mock(ProcurementRepository::class);
            $repository->shouldReceive('findByProcurement')
                ->once()
                ->with('PR-LOCKED')
                ->andReturn(procurementFixture('PR-LOCKED', '999'));

            $dataService = \Mockery::mock(ProcurementDataService::class);
            $dataService->shouldReceive('fetchStatusItems')
                ->once()
                ->with('PR-LOCKED')
                ->andReturn(collect([
                    ['user_address' => 'different-address'],
                ]));

            bindProcurementControllerDependencies(
                $this,
                aggregatorFixture([], $repository),
                $dataService,
                $repository
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-LOCKED']))
                ->assertForbidden();
        });

        it('allows bac secretariat to view procurements they interacted with', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            $repository = \Mockery::mock(ProcurementRepository::class);
            $repository->shouldReceive('findByProcurement')
                ->twice()
                ->with('PR-OPEN')
                ->andReturn(procurementFixture('PR-OPEN', '999'));

            $dataService = detailDataServiceFixture('PR-OPEN', 'secretariat-address');
            bindProcurementControllerDependencies(
                $this,
                aggregatorFixture([], $repository),
                $dataService,
                $repository
            );

            actingAs($secretariat);

            get(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-OPEN']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('forbids bac secretariat from polling blockchain status for inaccessible procurements', function () {
            $secretariat = User::factory()->create([
                'blockchain_address' => 'secretariat-address',
            ]);
            $secretariat->assignRole('bac_secretariat');

            $repository = \Mockery::mock(ProcurementRepository::class);
            $repository->shouldReceive('findByProcurement')
                ->once()
                ->with('PR-LOCKED')
                ->andReturn(procurementFixture('PR-LOCKED', '999'));

            $dataService = \Mockery::mock(ProcurementDataService::class);
            $dataService->shouldReceive('fetchStatusItems')
                ->once()
                ->with('PR-LOCKED')
                ->andReturn(collect([
                    ['user_address' => 'different-address'],
                ]));

            bindProcurementControllerDependencies(
                $this,
                aggregatorFixture([], $repository),
                $dataService,
                $repository
            );

            actingAs($secretariat);

            getJson(route('procurements.blockchain-status', ['pr_number' => 'PR-LOCKED']))
                ->assertForbidden();
        });
    });

    describe('authorization', function () {
        it('requires authentication to view procurements list', function () {
            get(route('bac-secretariat.procurements.index'))
                ->assertRedirect(route('login'));
        });

        it('requires authentication to view single procurement', function () {
            get(route('bac-secretariat.procurements.show', ['pr_number' => 'TEST-001']))
                ->assertRedirect(route('login'));
        });

        it('requires correct role to access bac secretariat procurements list', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-secretariat.procurements.index'))
                ->assertForbidden();
        });

        it('requires correct role to access bac chairman procurements list', function () {
            actingAs($this->user); // bac_secretariat

            get(route('bac-chairman.procurements.index'))
                ->assertForbidden();
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

function statusFixture(string $prNumber, string $title, string $userAddress): StatusData
{
    return new StatusData(
        prNumber: $prNumber,
        procurementTitle: $title,
        stage: 'procurement_initiation',
        currentStatus: 'draft',
        userAddress: $userAddress,
        timestamp: now(),
    );
}

function detailDataServiceFixture(string $prNumber, ?string $userAddress = null): ProcurementDataService
{
    $dataService = \Mockery::mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->andReturn(collect([
            [
                'pr_number' => $prNumber,
                'procurement_title' => 'Fixture Procurement',
                'stage' => 'procurement_initiation',
                'current_status' => 'draft',
                'user_address' => $userAddress ?? 'fixture-address',
                'timestamp' => now()->toIso8601String(),
            ],
        ]));
    $dataService->shouldReceive('fetchAndProcessAllDocuments')
        ->andReturn([]);
    $dataService->shouldReceive('fetchAndProcessEvents')
        ->andReturn([]);
    $dataService->shouldReceive('preloadUserNames');
    $dataService->shouldReceive('buildProcurementData')
        ->andReturn([
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
        ]);

    return $dataService;
}

function aggregatorFixture(array $statusItems, ProcurementRepository $procurementRepository): ProcurementListAggregatorService
{
    $statusManager = \Mockery::mock(\App\Services\Manager::class);
    $statusManager->shouldReceive('liststreamitems')
        ->zeroOrMoreTimes()
        ->andReturn(array_map(
            fn (StatusData $status) => ['data' => ['json' => $status->toBlockchainArray()]],
            $statusItems
        ));

    $documentManager = \Mockery::mock(\App\Services\Manager::class);
    $documentManager->shouldReceive('liststreamitems')
        ->andReturn([]);

    $archiveManager = \Mockery::mock(\App\Services\Manager::class);
    $archiveManager->shouldReceive('liststreamitems')
        ->andReturn([]);

    $statusRepository = new StatusRepository($statusManager);
    $documentRepository = new DocumentRepository($documentManager);
    $archiveRepository = new ProcurementArchiveRepository($archiveManager);

    $formatter = new ProcurementFormatterService;
    $actionService = new ProcurementActionService($procurementRepository);

    $userService = \Mockery::mock(\App\Services\UserService::class);
    $userService->shouldReceive('preloadUserNames');
    $userService->shouldReceive('getUserNameByAddress')
        ->andReturn('Fixture User');
    $userNameResolver = new UserNameResolverService($userService);

    return new ProcurementListAggregatorService(
        $statusRepository,
        $documentRepository,
        $procurementRepository,
        $archiveRepository,
        $formatter,
        $actionService,
        $userNameResolver,
    );
}

function bindProcurementControllerDependencies(
    $testCase,
    ProcurementListAggregatorService $aggregator,
    ProcurementDataService $dataService,
    ProcurementRepository $procurementRepository
): void {
    $correctionRepository = \Mockery::mock(ProcurementCorrectionRepositoryInterface::class);
    $correctionRepository->shouldReceive('hasCorrections')
        ->andReturn(false);

    $detailService = new ProcurementDetailService($dataService, $procurementRepository, $correctionRepository);

    app()->instance(ProcurementListAggregatorService::class, $aggregator);
    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(ProcurementRepository::class, $procurementRepository);
    app()->instance(ProcurementDetailService::class, $detailService);
}

function allowSecretariatAccess($testCase, User $user, string $prNumber): void
{
    $repository = \Mockery::mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->andReturn(procurementFixture($prNumber, (string) $user->id));

    bindProcurementControllerDependencies(
        $testCase,
        aggregatorFixture([], $repository),
        detailDataServiceFixture($prNumber, $user->blockchain_address),
        $repository
    );
}
