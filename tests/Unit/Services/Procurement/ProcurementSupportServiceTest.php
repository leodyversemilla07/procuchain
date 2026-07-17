<?php

use App\Enums\ProcurementMode;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Services\BlockchainRpcClient;
use App\Services\Procurement\ProcurementSupportService;
use App\Services\Procurement\StageStatusMappingService;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use App\Services\WorkflowDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Log::spy();

    $this->multichain = Mockery::mock(BlockchainRpcClient::class);
    $this->documentPublisher = Mockery::mock(DocumentPublisher::class);
    $this->statusPublisher = Mockery::mock(StatusPublisher::class);
    $this->eventPublisher = Mockery::mock(EventPublisher::class);
    $this->procurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->workflowDefinitionService = Mockery::mock(WorkflowDefinitionService::class);
    $this->stageStatusMappingService = Mockery::mock(StageStatusMappingService::class);

    $this->service = new ProcurementSupportService(
        $this->multichain,
        $this->documentPublisher,
        $this->statusPublisher,
        $this->eventPublisher,
        $this->procurementDataService,
        $this->workflowDefinitionService,
        $this->stageStatusMappingService,
    );
});

// Helper to seed procurement in database for getProcurementMode
function mockProcurementRepo(ProcurementMode $mode, string $prNumber = 'PR-2025-001-0001'): void
{
    Procurement::create([
        'pr_number' => $prNumber,
        'title' => 'Test',
        'description' => 'Test',
        'abc_amount' => 500000,
        'fund_source' => 'GAA',
        'category' => 'goods',
        'procurement_mode' => $mode->value,
        'office' => 'Test Office',
        'current_status' => 'procurement_submitted',
        'current_stage' => 'procurement_initiation',
        'user_id' => '1',
        'initiated_at' => now(),
    ]);
}

describe('ProcurementSupportService', function () {
    describe('getInitialStatusForStage', function () {
        it('returns correct status for procurement initiation', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->stageStatusMappingService
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::PROCUREMENT_INITIATION, ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(ProcurementStatus::PROCUREMENT_INITIATED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBe(ProcurementStatus::PROCUREMENT_INITIATED);
        });

        it('returns mode-aware status for BAC resolution with SVP', function () {
            mockProcurementRepo(ProcurementMode::SMALL_VALUE_PROCUREMENT);

            $this->stageStatusMappingService
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::BAC_RESOLUTION, ProcurementMode::SMALL_VALUE_PROCUREMENT)
                ->once()
                ->andReturn(ProcurementStatus::ABSTRACT_PREPARED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001-0001', StageEnums::BAC_RESOLUTION);

            expect($result)->toBe(ProcurementStatus::ABSTRACT_PREPARED);
        });

        it('returns mode-aware status for BAC resolution with competitive bidding', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->stageStatusMappingService
                ->shouldReceive('getInitialStatus')
                ->with(StageEnums::BAC_RESOLUTION, ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(ProcurementStatus::POST_QUALIFICATION_VERIFIED);

            $result = $this->service->getInitialStatusForStage('PR-2025-001-0001', StageEnums::BAC_RESOLUTION);

            expect($result)->toBe(ProcurementStatus::POST_QUALIFICATION_VERIFIED);
        });
    });

    describe('getNextStageForProcurement', function () {
        it('returns correct next stage from workflow service', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('getStagesForMode')
                ->with(ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([
                    StageEnums::PROCUREMENT_INITIATION,
                    StageEnums::PRE_PROCUREMENT_CONFERENCE,
                ]);
            $this->workflowDefinitionService
                ->shouldReceive('getOptionalStagesForMode')
                ->with(ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([]);

            $result = $this->service->getNextStageForProcurement('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE);
        });

        it('returns null when at end of workflow', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('getStagesForMode')
                ->with(ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([
                    StageEnums::PROCUREMENT_INITIATION,
                    StageEnums::COMPLETED,
                ]);
            $this->workflowDefinitionService
                ->shouldReceive('getOptionalStagesForMode')
                ->with(ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn([]);

            $result = $this->service->getNextStageForProcurement('PR-2025-001-0001', StageEnums::COMPLETED);

            expect($result)->toBeNull();
        });

        it('falls back to default getNextStage when mode not found', function () {
            $result = $this->service->getNextStageForProcurement('PR-2025-990-0001', StageEnums::PROCUREMENT_INITIATION);

            // Should use the enum's getNextStage method as fallback
            expect($result)->toBeInstanceOf(StageEnums::class);
        });
    });

    describe('stageExistsInWorkflow', function () {
        it('returns true when stage exists in workflow', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageInWorkflow')
                ->with(StageEnums::BID_OPENING, ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(true);

            $result = $this->service->stageExistsInWorkflow('PR-2025-001-0001', StageEnums::BID_OPENING);

            expect($result)->toBeTrue();
        });

        it('returns false when stage does not exist in workflow', function () {
            mockProcurementRepo(ProcurementMode::SMALL_VALUE_PROCUREMENT);

            $this->workflowDefinitionService
                ->shouldReceive('isStageInWorkflow')
                ->with(StageEnums::PRE_BID_CONFERENCE, ProcurementMode::SMALL_VALUE_PROCUREMENT)
                ->once()
                ->andReturn(false);

            $result = $this->service->stageExistsInWorkflow('PR-2025-001-0001', StageEnums::PRE_BID_CONFERENCE);

            expect($result)->toBeFalse();
        });

        it('returns true for all stages when mode not found', function () {
            $result = $this->service->stageExistsInWorkflow('PR-2025-990-0001', StageEnums::BID_OPENING);

            expect($result)->toBeTrue();
        });
    });

    describe('isStageOptional', function () {
        it('returns true for optional stages', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageOptional')
                ->with(StageEnums::PRE_BID_CONFERENCE, ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(true);

            $result = $this->service->isStageOptional('PR-2025-001-0001', StageEnums::PRE_BID_CONFERENCE);

            expect($result)->toBeTrue();
        });

        it('returns false for required stages', function () {
            mockProcurementRepo(ProcurementMode::COMPETITIVE_BIDDING);

            $this->workflowDefinitionService
                ->shouldReceive('isStageOptional')
                ->with(StageEnums::PROCUREMENT_INITIATION, ProcurementMode::COMPETITIVE_BIDDING)
                ->once()
                ->andReturn(false);

            $result = $this->service->isStageOptional('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBeFalse();
        });

        it('falls back to enum canSkip when mode not found', function () {
            // PROCUREMENT_INITIATION should not be skippable
            $result = $this->service->isStageOptional('PR-2025-990-0001', StageEnums::PROCUREMENT_INITIATION);

            expect($result)->toBeFalse();
        });
    });
});
