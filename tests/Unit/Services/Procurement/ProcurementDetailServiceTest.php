<?php

use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\StatusData;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementArchiveRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementMirrorRepository;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
                ->with('PR-2025-001-0001')
                ->once()
                ->andReturn(collect());

            $result = $this->service->getDetail('PR-2025-001-0001');

            expect($result)->toBeNull();
        });

        it('builds complete procurement detail when data exists', function () {
            $statusData = [
                'pr_number' => 'PR-2025-001-0001',
                'procurement_title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'current_status' => 'procurement_submitted',
                'user_address' => '1abc123',
                'timestamp' => now()->toIso8601String(),
            ];

            $this->dataService
                ->shouldReceive('fetchStatusItems')
                ->with('PR-2025-001-0001')
                ->once()
                ->andReturn(collect([$statusData]));

            $this->dataService
                ->shouldReceive('fetchAndProcessAllDocuments')
                ->with('PR-2025-001-0001')
                ->once()
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('fetchAndProcessEvents')
                ->with('PR-2025-001-0001')
                ->once()
                ->andReturn([]);

            $this->dataService
                ->shouldReceive('preloadUserNames')
                ->once();

            $this->dataService
                ->shouldReceive('buildProcurementData')
                ->once()
                ->andReturn([
                    'id' => 'PR-2025-001-0001',
                    'title' => 'Test Procurement',
                    'status' => 'procurement_submitted',
                    'stage' => 'procurement_initiation',
                ]);

            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->with('PR-2025-001-0001')
                ->once()
                ->andReturn(null);

            $result = $this->service->getDetail('PR-2025-001-0001');

            expect($result)
                ->toHaveKeys(['procurement', 'workflow'])
                ->and($result['procurement']['title'])->toBe('Test Procurement')
                ->and($result['workflow'])->toBeNull(); // null because no procurementDetails
        });

        it('includes workflow and details when procurement details exist', function () {
            $statusData = [
                'pr_number' => 'PR-2025-001-0001',
                'procurement_title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'current_status' => 'procurement_submitted',
                'user_address' => '1abc123',
                'timestamp' => now()->toIso8601String(),
            ];

            $procurementDetails = ProcurementData::fromBlockchainArray([
                'pr_number' => 'PR-2025-001-0001',
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
                    'id' => 'PR-2025-001-0001',
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

            $result = $this->service->getDetail('PR-2025-001-0001');

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
        $this->documentRepository = Mockery::mock(DocumentRepository::class);
        $this->procurementRepository = Mockery::mock(ProcurementRepository::class);
        $this->archiveRepository = Mockery::mock(ProcurementArchiveRepository::class);
        $this->userService = Mockery::mock(UserService::class);
        $this->mirrorRepository = Mockery::mock(ProcurementMirrorRepository::class);

        $this->aggregator = new ProcurementListAggregatorService(
        $this->mirrorRepository,
        new ProcurementFormatterService,
        new ProcurementActionService($this->procurementRepository),
        new UserNameResolverService($this->userService),
        );
    });

    describe('fetchAllProcurements', function () {
        it('returns empty array when no status items exist', function () {
        $this->mirrorRepository
        ->shouldReceive('getLatestStatusByProcurement')
        ->andReturn([]);

        $result = $this->aggregator->fetchAllProcurements();

        expect($result)->toBeEmpty();
        });

        it('fetches and processes procurements with skip actions', function () {
        $timestamp = Carbon::now()->toIso8601String();

        $statusDto = new StatusData(
        prNumber: 'PR-2025-001-0001',
        procurementTitle: 'Test Procurement',
        stage: 'procurement_initiation',
        currentStatus: 'procurement_submitted',
        userAddress: '1abc123',
        timestamp: Carbon::parse($timestamp),
        );

        $this->mirrorRepository
        ->shouldReceive('getLatestStatusByProcurement')
        ->once()
        ->andReturn([$statusDto]);

        $this->mirrorRepository
        ->shouldReceive('getAllDocuments')
        ->once()
        ->andReturn([]);

        $this->userService
        ->shouldReceive('preloadUserNames')
        ->once();

        $this->userService
        ->shouldReceive('getUserNameByAddress')
        ->andReturn('Test User');

        $this->mirrorRepository
        ->shouldReceive('findManyByProcurement')
        ->once()
        ->andReturn([]);

        $this->mirrorRepository
        ->shouldReceive('getArchivedPrNumbers')
        ->once()
        ->andReturn([]);

        $this->actingAs(createUserWithRole('admin'));

        $result = $this->aggregator->fetchAllProcurements(skipActions: true);

        expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe('PR-2025-001-0001')
        ->and($result[0]['title'])->toBe('Test Procurement');
        });
    });

    describe('filterByArchiveStatus', function () {
        it('filters correctly for active procurements', function () {
        // Use reflection to test the private method
        $method = new ReflectionMethod(ProcurementListAggregatorService::class, 'filterByArchiveStatus');
        $method->setAccessible(true);

        $activeDto = new StatusData(
        prNumber: 'PR-2025-991-0001',
        procurementTitle: 'Active',
        stage: 'procurement_initiation',
        currentStatus: 'procurement_submitted',
        userAddress: '1abc',
        timestamp: Carbon::now(),
        );

        $archivedDto = new StatusData(
        prNumber: 'PR-2025-991-0002',
        procurementTitle: 'Archived',
        stage: 'completed',
        currentStatus: 'completed',
        userAddress: '1abc',
        timestamp: Carbon::now(),
        );

        $collection = collect([$activeDto, $archivedDto]);

        $this->mirrorRepository
        ->shouldReceive('getArchivedPrNumbers')
        ->once()
        ->andReturn(['PR-2025-991-0002']);

        $result = $method->invoke($this->aggregator, $collection, false);

        expect($result)->toHaveCount(1)
        ->and($result->first()->prNumber)->toBe('PR-2025-991-0001');
        });

        it('filters correctly for archived procurements', function () {
        $method = new ReflectionMethod(ProcurementListAggregatorService::class, 'filterByArchiveStatus');
        $method->setAccessible(true);

        $activeDto = new StatusData(
        prNumber: 'PR-2025-991-0001',
        procurementTitle: 'Active',
        stage: 'procurement_initiation',
        currentStatus: 'procurement_submitted',
        userAddress: '1abc',
        timestamp: Carbon::now(),
        );

        $archivedDto = new StatusData(
        prNumber: 'PR-2025-991-0002',
        procurementTitle: 'Archived',
        stage: 'completed',
        currentStatus: 'completed',
        userAddress: '1abc',
        timestamp: Carbon::now(),
        );

        $collection = collect([$activeDto, $archivedDto]);

        $this->mirrorRepository
        ->shouldReceive('getArchivedPrNumbers')
        ->once()
        ->andReturn(['PR-2025-991-0002']);

        $result = $method->invoke($this->aggregator, $collection, true);

        expect($result)->toHaveCount(1)
        ->and($result->first()->prNumber)->toBe('PR-2025-991-0002');
        });
    });
});
