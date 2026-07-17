<?php

use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementStage;
use App\Services\Procurement\BlockchainAddressResolverService;
use App\Services\Procurement\ProcurementActionService;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementFormatterService;
use App\Services\Procurement\ProcurementListAggregatorService;
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

        $this->service = new ProcurementDetailService(
            $this->dataService,
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

            $result = $this->service->getDetail('PR-2025-001-0001');

            expect($result)
                ->toHaveKeys(['procurement', 'workflow'])
                ->and($result['procurement']['title'])->toBe('Test Procurement')
                ->and($result['workflow'])->toBeNull();
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

            Procurement::create([
                'pr_number' => 'PR-2025-001-0001',
                'title' => 'Test Procurement',
                'description' => 'Test description',
                'abc_amount' => 500000,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering',
                'current_status' => 'procurement_submitted',
                'current_stage' => 'procurement_initiation',
                'user_id' => '1',
                'initiated_at' => now(),
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
        $this->userService = Mockery::mock(UserService::class);

        $this->aggregator = new ProcurementListAggregatorService(
            new ProcurementFormatterService,
            new ProcurementActionService,
            new BlockchainAddressResolverService($this->userService),
        );
    });

    describe('fetchAllProcurements', function () {
        it('returns empty array when no status items exist', function () {
            $result = $this->aggregator->fetchAllProcurements();

            expect($result)->toBeEmpty();
        });

        it('fetches and processes procurements with skip actions', function () {
            $procurement = Procurement::create([
                'pr_number' => 'PR-2025-001-0001',
                'title' => 'Test Procurement',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'abc_amount' => 500000,
                'current_stage' => 'procurement_initiation',
                'current_status' => 'procurement_submitted',
            ]);

            ProcurementStage::create([
                'procurement_id' => $procurement->id,
                'stage' => 'procurement_initiation',
                'status' => 'procurement_submitted',
                'entered_at' => Carbon::now(),
                'user_address' => '1abc123',
            ]);

            $this->userService
                ->shouldReceive('preloadUserNames')
                ->once();

            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->andReturn('Test User');

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

            $activeDto = [
                'prNumber' => 'PR-2025-991-0001',
                'procurementTitle' => 'Active',
                'stage' => 'procurement_initiation',
                'currentStatus' => 'procurement_submitted',
                'userAddress' => '1abc',
                'timestamp' => Carbon::now(),
            ];

            $archivedDto = [
                'prNumber' => 'PR-2025-991-0002',
                'procurementTitle' => 'Archived',
                'stage' => 'completed',
                'currentStatus' => 'completed',
                'userAddress' => '1abc',
                'timestamp' => Carbon::now(),
            ];

            $collection = collect([$activeDto, $archivedDto]);

            $archivedProcurement = Procurement::create([
                'pr_number' => 'PR-2025-991-0002',
                'title' => 'Archived',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'abc_amount' => 500000,
                'current_stage' => 'completed',
                'current_status' => 'completed',
            ]);

            ProcurementArchive::create([
                'procurement_id' => $archivedProcurement->id,
                'action' => 'archive',
                'reason' => 'Done',
                'user_id' => '1',
                'archived_at' => Carbon::now(),
            ]);

            $result = $method->invoke($this->aggregator, $collection, false);

            expect($result)->toHaveCount(1)
                ->and($result->first()['prNumber'])->toBe('PR-2025-991-0001');
        });

        it('filters correctly for archived procurements', function () {
            $method = new ReflectionMethod(ProcurementListAggregatorService::class, 'filterByArchiveStatus');
            $method->setAccessible(true);

            $activeDto = [
                'prNumber' => 'PR-2025-991-0001',
                'procurementTitle' => 'Active',
                'stage' => 'procurement_initiation',
                'currentStatus' => 'procurement_submitted',
                'userAddress' => '1abc',
                'timestamp' => Carbon::now(),
            ];

            $archivedDto = [
                'prNumber' => 'PR-2025-991-0002',
                'procurementTitle' => 'Archived',
                'stage' => 'completed',
                'currentStatus' => 'completed',
                'userAddress' => '1abc',
                'timestamp' => Carbon::now(),
            ];

            $collection = collect([$activeDto, $archivedDto]);

            $archivedProcurement = Procurement::create([
                'pr_number' => 'PR-2025-991-0002',
                'title' => 'Archived',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'abc_amount' => 500000,
                'current_stage' => 'completed',
                'current_status' => 'completed',
            ]);

            ProcurementArchive::create([
                'procurement_id' => $archivedProcurement->id,
                'action' => 'archive',
                'reason' => 'Done',
                'user_id' => '1',
                'archived_at' => Carbon::now(),
            ]);

            $result = $method->invoke($this->aggregator, $collection, true);

            expect($result)->toHaveCount(1)
                ->and($result->first()['prNumber'])->toBe('PR-2025-991-0002');
        });
    });
});
