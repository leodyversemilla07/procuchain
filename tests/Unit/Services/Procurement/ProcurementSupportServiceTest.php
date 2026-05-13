<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;
use App\Services\Procurement\ProcurementSupportService;
use App\Services\Procurement\StageStatusMapper;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use App\Services\WorkflowDefinitionService;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();

    $this->multichain = Mockery::mock(Manager::class);
    $this->documentPublisher = Mockery::mock(DocumentPublisher::class);
    $this->statusPublisher = Mockery::mock(StatusPublisher::class);
    $this->eventPublisher = Mockery::mock(EventPublisher::class);
    $this->procurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->documentRepository = Mockery::mock(DocumentRepository::class);
    $this->workflowDefinitionService = Mockery::mock(WorkflowDefinitionService::class);
    $this->stageStatusMapper = Mockery::mock(StageStatusMapper::class);

    $this->service = new ProcurementSupportService(
        $this->multichain,
        $this->documentPublisher,
        $this->statusPublisher,
        $this->eventPublisher,
        $this->procurementDataService,
        $this->documentRepository,
        $this->workflowDefinitionService,
        $this->stageStatusMapper,
    );
});

// Helper to mock procurement repository for getProcurementMode
function mockProcurementRepo(ProcurementModeEnums $mode, string $prNumber = 'PR-2025-001'): void
{
    $procurement = ProcurementData::fromBlockchainArray([
        'pr_number' => $prNumber,
        'title' => 'Test',
        'description' => 'Test',
        'abc_amount' => '500000',
        'funding_source' => 'GAA',
        'category' => 'goods',
        'procurement_mode' => $mode->value,
        'office' => 'Test Office',
        'status' => 'procurement_submitted',
        'user_id' => '1',
        'created_at' => now()->toIso8601String(),
    ]);

    $repo = Mockery::mock(ProcurementRepository::class);
    $repo->shouldReceive('findByProcurement')
        ->with($prNumber)
        ->andReturn($procurement);

    app()->instance(ProcurementRepository::class, $repo);
}

describe('ProcurementSupportService', function () {
    describe('getInitialStatusForStage', function () {
        it('returns correct status for procurement initiation', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->stageStatusMapper
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::PROCUREMENT_INITIATION, ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(StatusEnums::PROCUREMENT_INITIATED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBe(StatusEnums::PROCUREMENT_INITIATED);
        });

        it('returns mode-aware status for BAC resolution with SVP', function () {
            mockProcurementRepo(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

            $this->stageStatusMapper
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::BAC_RESOLUTION, ProcurementModeEnums::SMALL_VALUE_PROCUREMENT)
                ->once()
                ->andReturn(StatusEnums::ABSTRACT_PREPARED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001', StageEnums::BAC_RESOLUTION);

            expect($result)->toBe(StatusEnums::ABSTRACT_PREPARED);
        });

        it('returns mode-aware status for BAC resolution with competitive bidding', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->stageStatusMapper
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::BAC_RESOLUTION, ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(StatusEnums::POST_QUALIFICATION_VERIFIED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001', StageEnums::BAC_RESOLUTION);

            expect($result)->toBe(StatusEnums::POST_QUALIFICATION_VERIFIED);
        });
    });

    describe('getNextStageForProcurement', function () {
        it('returns correct next stage from workflow service', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('getStagesForMode')
                ->with(ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([
                    StageEnums::PROCUREMENT_INITIATION,
                    StageEnums::PRE_PROCUREMENT_CONFERENCE,
                ]);
            $this->workflowDefinitionService
                ->shouldReceive('getOptionalStagesForMode')
                ->with(ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([]);

            $result = $this->service->getNextStageForProcurement('PR-2025-001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE);
        });

        it('returns null when at end of workflow', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('getStagesForMode')
                ->with(ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([
                    StageEnums::PROCUREMENT_INITIATION,
                    StageEnums::COMPLETED,
                ]);
            $this->workflowDefinitionService
                ->shouldReceive('getOptionalStagesForMode')
                ->with(ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([]);

            $result = $this->service->getNextStageForProcurement('PR-2025-001', StageEnums::COMPLETED);

            expect($result)->toBeNull();
        });

        it('falls back to default getNextStage when mode not found', function () {
            // Mock procurement repo returning null
            $repo = Mockery::mock(ProcurementRepository::class);
            $repo->shouldReceive('findByProcurement')
                ->andReturn(null);
            app()->instance(ProcurementRepository::class, $repo);

            $result = $this->service->getNextStageForProcurement('PR-2025-990-0001', StageEnums::PROCUREMENT_INITIATION);

            // Should use the enum's getNextStage method as fallback
            expect($result)->toBeInstanceOf(StageEnums::class);
        });
    });

    describe('stageExistsInWorkflow', function () {
        it('returns true when stage exists in workflow', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageInWorkflow')
                ->with(StageEnums::BID_OPENING, ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(true);

            $result = $this->service->stageExistsInWorkflow('PR-2025-001', StageEnums::BID_OPENING);

            expect($result)->toBeTrue();
        });

        it('returns false when stage does not exist in workflow', function () {
            mockProcurementRepo(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

            $this->workflowDefinitionService
                ->shouldReceive('isStageInWorkflow')
                ->with(StageEnums::PRE_BID_CONFERENCE, ProcurementModeEnums::SMALL_VALUE_PROCUREMENT)
                ->once()
                ->andReturn(false);

            $result = $this->service->stageExistsInWorkflow('PR-2025-001', StageEnums::PRE_BID_CONFERENCE);

            expect($result)->toBeFalse();
        });

        it('returns true for all stages when mode not found', function () {
            $repo = Mockery::mock(ProcurementRepository::class);
            $repo->shouldReceive('findByProcurement')
                ->andReturn(null);
            app()->instance(ProcurementRepository::class, $repo);

            $result = $this->service->stageExistsInWorkflow('PR-2025-990-0001', StageEnums::BID_OPENING);

            expect($result)->toBeTrue();
        });
    });

    describe('isStageOptional', function () {
        it('returns true for optional stages', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageOptional')
                ->with(StageEnums::PRE_BID_CONFERENCE, ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(true);

            $result = $this->service->isStageOptional('PR-2025-001', StageEnums::PRE_BID_CONFERENCE);

            expect($result)->toBeTrue();
        });

        it('returns false for required stages', function () {
            mockProcurementRepo(ProcurementModeEnums::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageOptional')
                ->with(StageEnums::PROCUREMENT_INITIATION, ProcurementModeEnums::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(false);

            $result = $this->service->isStageOptional('PR-2025-001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBeFalse();
        });

        it('falls back to enum canSkip when mode not found', function () {
            $repo = Mockery::mock(ProcurementRepository::class);
            $repo->shouldReceive('findByProcurement')
                ->andReturn(null);
            app()->instance(ProcurementRepository::class, $repo);

            // PROCUREMENT_INITIATION should not be skippable
            $result = $this->service->isStageOptional('PR-2025-990-0001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBeFalse();
        });
    });
});
