<?php

use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\StatusData;
use App\Repositories\ProcurementArchiveRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

describe('ProcurementDetailService', function () {
    beforeEach(function () {
        $this->dataService = Mockery::mock(ProcurementDataService::class);
        $this->procurementRepository = Mockery::mock(ProcurementRepository::class);
        $this->procCorrectionManager = Mockery::mock(Manager::class);

        $this->service = new ProcurementDetailService(
            $this->dataService,
            $this->procurementRepository,
            new ProcurementCorrectionRepository($this->procCorrectionManager),
        );
    });

    describe('getDetail', function () {
        it('returns null when no status items exist', function () {
            $this->dataService
                ->shouldReceive('fetchStatusItems')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(collect());

            $result = $this->service->getDetail('PR-2025-001');

            expect($result)->toBeNull();
        });

        it('builds complete procurement detail when data exists', function () {
            $statusData = [
                'pr_number' => 'PR-2025-001',
                'procurement_title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'current_status' => 'procurement_submitted',
                'user_address' => '1abc123',
                'timestamp' => now()->toIso8601String(),
            ];

            $this->dataService
                ->shouldReceive('fetchStatusItems')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(collect([$statusData]));

            $this->dataService
                ->shouldReceive('fetchAndProcessAllDocuments')
                ->with('PR-2025-001')
                ->once()
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('fetchAndProcessEvents')
                ->with('PR-2025-001')
                ->once()
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('preloadUserNames')
                ->once();

            $this->dataService
                ->shouldReceive('buildProcurementData')
                ->once()
                ->andReturn([
                    'id' => 'PR-2025-001',
                    'title' => 'Test Procurement',
                    'status' => 'procurement_submitted',
                    'stage' => 'procurement_initiation',
                ]);

            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(null);

            $result = $this->service->getDetail('PR-2025-001');

            expect($result)
                ->toHaveKeys(['procurement', 'workflow'])
                ->and($result['procurement']['title'])->toBe('Test Procurement')
                ->and($result['workflow'])->toBeNull(); // null because no procurementDetails
        });

        it('includes workflow and details when procurement details exist', function () {
            $statusData = [
                'pr_number' => 'PR-2025-001',
                'procurement_title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'current_status' => 'procurement_submitted',
                'user_address' => '1abc123',
                'timestamp' => now()->toIso8601String(),
            ];

            $procurementDetails = ProcurementData::fromBlockchainArray([
                'pr_number' => 'PR-2025-001',
                'title' => 'Test Procurement',
                'description' => 'Test description',
                'abc_amount' => '500000',
                'funding_source' => 'GAA',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering',
                'status' => 'procurement_submitted',
                'user_id' => '1',
                'created_at' => now()->toIso8601String(),
            ]);

            $this->dataService
                ->shouldReceive('fetchStatusItems')
                ->andReturn(collect([$statusData]));

            $this->dataService
                ->shouldReceive('fetchAndProcessAllDocuments')
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('fetchAndProcessEvents')
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('preloadUserNames');

            $this->dataService
                ->shouldReceive('buildProcurementData')
                ->andReturn([
                    'id' => 'PR-2025-001',
                    'title' => 'Test Procurement',
                    'status' => 'procurement_submitted',
                    'stage' => 'procurement_initiation',
                ]);

            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->andReturn($procurementDetails);

            // Mock correction manager to return no corrections
            $this->procCorrectionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([]);

            $result = $this->service->getDetail('PR-2025-001');

            expect($result['workflow'])->not->toBeNull()
                ->and($result['workflow'])->toHaveKeys(['mode', 'name', 'stages'])
                ->and($result['workflow']['mode'])->toBe('competitive_bidding')
                ->and($result['procurement']['details'])->toHaveKey('has_corrections');
        });
    });
});

describe('ProcurementListAggregatorService', function () {
    beforeEach(function () {
        $this->statusManager = Mockery::mock(Manager::class);
        $this->documentRepository = Mockery::mock(\App\Repositories\DocumentRepository::class);
        $this->procurementRepository = Mockery::mock(ProcurementRepository::class);
        $this->archiveRepository = Mockery::mock(ProcurementArchiveRepository::class);
        $this->userService = Mockery::mock(\App\Services\UserService::class);

        $this->aggregator = new ProcurementListAggregatorService(
            new \App\Repositories\StatusRepository($this->statusManager),
            $this->documentRepository,
            $this->procurementRepository,
            $this->archiveRepository,
            new \App\Services\Procurement\ProcurementFormatterService,
            new \App\Services\Procurement\ProcurementActionService($this->procurementRepository),
            new \App\Services\Procurement\UserNameResolverService($this->userService),
        );
    });

    describe('fetchAllProcurements', function () {
        it('returns empty array when no status items exist', function () {
            // Mock the Manager liststreamitems to return empty
            $this->statusManager
                ->shouldReceive('liststreamitems')
                ->andReturn([]);

            $result = $this->aggregator->fetchAllProcurements();

            expect($result)->toBeEmpty();
        });

        it('fetches and processes procurements with skip actions', function () {
            $timestamp = \Carbon\Carbon::now()->toIso8601String();

            // Mock the Manager to return status data
            $this->statusManager
                ->shouldReceive('liststreamitems')
                ->andReturn([
                    [
                        'data' => [
                            'json' => [
                                'pr_number' => 'PR-2025-001',
                                'procurement_title' => 'Test Procurement',
                                'stage' => 'procurement_initiation',
                                'current_status' => 'procurement_submitted',
                                'user_address' => '1abc123',
                                'timestamp' => $timestamp,
                            ],
                        ],
                    ],
                ]);

            $this->documentRepository
                ->shouldReceive('all')
                ->once()
                ->andReturn([]);

            $this->userService
                ->shouldReceive('preloadUserNames')
                ->once();

            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->andReturn('Test User');

            $this->procurementRepository
                ->shouldReceive('findManyByProcurement')
                ->once()
                ->andReturn([]);

            $this->archiveRepository
                ->shouldReceive('getArchivedPrNumbers')
                ->once()
                ->andReturn([]);

            $this->actingAs(createUserWithRole('admin'));

            $result = $this->aggregator->fetchAllProcurements(skipActions: true);

            expect($result)->toHaveCount(1)
                ->and($result[0]['id'])->toBe('PR-2025-001')
                ->and($result[0]['title'])->toBe('Test Procurement');
        });
    });

    describe('filterByArchiveStatus', function () {
        it('filters correctly for active procurements', function () {
            // Use reflection to test the private method
            $method = new ReflectionMethod(ProcurementListAggregatorService::class, 'filterByArchiveStatus');
            $method->setAccessible(true);

            $activeDto = new StatusData(
                prNumber: 'PR-ACTIVE',
                procurementTitle: 'Active',
                stage: 'procurement_initiation',
                currentStatus: 'procurement_submitted',
                userAddress: '1abc',
                timestamp: \Carbon\Carbon::now(),
            );

            $archivedDto = new StatusData(
                prNumber: 'PR-ARCHIVED',
                procurementTitle: 'Archived',
                stage: 'completed',
                currentStatus: 'completed',
                userAddress: '1abc',
                timestamp: \Carbon\Carbon::now(),
            );

            $collection = collect([$activeDto, $archivedDto]);

            $this->archiveRepository
                ->shouldReceive('getArchivedPrNumbers')
                ->once()
                ->andReturn(['PR-ARCHIVED']);

            $result = $method->invoke($this->aggregator, $collection, false);

            expect($result)->toHaveCount(1)
                ->and($result->first()->prNumber)->toBe('PR-ACTIVE');
        });

        it('filters correctly for archived procurements', function () {
            $method = new ReflectionMethod(ProcurementListAggregatorService::class, 'filterByArchiveStatus');
            $method->setAccessible(true);

            $activeDto = new StatusData(
                prNumber: 'PR-ACTIVE',
                procurementTitle: 'Active',
                stage: 'procurement_initiation',
                currentStatus: 'procurement_submitted',
                userAddress: '1abc',
                timestamp: \Carbon\Carbon::now(),
            );

            $archivedDto = new StatusData(
                prNumber: 'PR-ARCHIVED',
                procurementTitle: 'Archived',
                stage: 'completed',
                currentStatus: 'completed',
                userAddress: '1abc',
                timestamp: \Carbon\Carbon::now(),
            );

            $collection = collect([$activeDto, $archivedDto]);

            $this->archiveRepository
                ->shouldReceive('getArchivedPrNumbers')
                ->once()
                ->andReturn(['PR-ARCHIVED']);

            $result = $method->invoke($this->aggregator, $collection, true);

            expect($result)->toHaveCount(1)
                ->and($result->first()->prNumber)->toBe('PR-ARCHIVED');
        });
    });
});
