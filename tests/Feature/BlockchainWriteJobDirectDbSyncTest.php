<?php

declare(strict_types=1);

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Jobs\BlockchainWriteJob;
use App\Jobs\Handlers\ProcurementInitiationHandler;
use App\Jobs\Handlers\ProcurementUpdateHandler;
use App\Jobs\Handlers\StageCompletionHandler;
use App\Jobs\Handlers\StageTransitionHandler;
use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Services\BlockchainRecordSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

describe('BlockchainWriteJob direct DB sync', function () {

    beforeEach(function () {
        Log::spy();
        config(['cache.default' => 'array']);

        // Mock the BlockchainRecordSyncService to prevent real blockchain connections
        // during the transaction-based sync phase
        $mockSync = Mockery::mock(BlockchainRecordSyncService::class);
        $mockSync->shouldReceive('upstream')->byDefault();
        app()->instance(BlockchainRecordSyncService::class, $mockSync);
    });

    it('updates procurement current_stage and current_status in DB after publish_decision (bulletin needed)', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2025-DDB-001',
            'title' => 'Direct DB Sync Test',
            'current_stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
            'current_status' => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED->value,
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
        ]);

        $mockHandler = Mockery::mock(ProcurementUpdateHandler::class);
        $mockHandler->shouldReceive('executeDecision')
            ->once()
            ->andReturn([
                'success' => true,
                'held' => true,
                'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                'status' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING->value,
                'status_txid' => 'tx-status-held-001',
                'event_txid' => 'tx-event-held-001',
            ]);

        app()->instance(ProcurementUpdateHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'publish_decision',
            [
                'pr_number' => 'PR-2025-DDB-001',
                'user_address' => '0xTEST',
                'decision_type' => 'supplemental_bid_bulletin',
                'procurement_title' => 'Direct DB Sync Test',
                'was_held' => true,
            ],
            'job-direct-sync-1',
            1,
        );
        $this->app->call([$job, 'handle']);

        $procurement->refresh();
        expect($procurement->current_stage)->toBe(StageEnums::SUPPLEMENTAL_BID_BULLETIN->value)
            ->and($procurement->current_status)->toBe(ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING->value);

        $stageRecord = ProcurementStage::where('procurement_id', $procurement->id)
            ->where('txid', 'tx-status-held-001')
            ->first();
        expect($stageRecord)->not->toBeNull()
            ->and($stageRecord->stage)->toBe(StageEnums::SUPPLEMENTAL_BID_BULLETIN->value)
            ->and($stageRecord->status)->toBe(ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING->value)
            ->and($stageRecord->data_hash)->toBeString()
            ->and(strlen($stageRecord->data_hash))->toBe(64)
            ->and($stageRecord->blockchain_hash)->toBe($stageRecord->data_hash);

        $cached = Cache::get('blockchain_job:job-direct-sync-1');
        expect($cached['status'])->toBe('done');
    });

    it('updates procurement current_stage and current_status in DB after publish_decision (bulletin skipped)', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2025-DDB-002',
            'title' => 'Skip DB Sync Test',
            'current_stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
            'current_status' => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED->value,
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
        ]);

        $mockHandler = Mockery::mock(ProcurementUpdateHandler::class);
        $mockHandler->shouldReceive('executeDecision')
            ->once()
            ->andReturn([
                'success' => true,
                'held' => false,
                'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                'status' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED->value,
                'next_stage' => StageEnums::BID_OPENING->value,
                'status_txid' => 'tx-status-skip-001',
                'event_txid' => 'tx-event-skip-001',
                'transition_txid' => 'tx-trans-skip-001',
            ]);

        app()->instance(ProcurementUpdateHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'publish_decision',
            [
                'pr_number' => 'PR-2025-DDB-002',
                'user_address' => '0xTEST',
                'decision_type' => 'supplemental_bid_bulletin',
                'procurement_title' => 'Skip DB Sync Test',
                'was_held' => false,
            ],
            'job-direct-sync-2',
            1,
        );
        $this->app->call([$job, 'handle']);

        $procurement->refresh();
        expect($procurement->current_stage)->toBe(StageEnums::BID_OPENING->value)
            ->and($procurement->current_status)->toBe(ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED->value);

        $statusStageRecord = ProcurementStage::where('txid', 'tx-status-skip-001')->first();
        expect($statusStageRecord)->not->toBeNull()
            ->and($statusStageRecord->stage)->toBe(StageEnums::SUPPLEMENTAL_BID_BULLETIN->value)
            ->and($statusStageRecord->status)->toBe(ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED->value)
            ->and($statusStageRecord->blockchain_hash)->toBe($statusStageRecord->data_hash);

        $transitionStageRecord = ProcurementStage::where('txid', 'tx-trans-skip-001')->first();
        expect($transitionStageRecord)->not->toBeNull()
            ->and($transitionStageRecord->stage)->toBe(StageEnums::BID_OPENING->value)
            ->and($transitionStageRecord->blockchain_hash)->toBe($transitionStageRecord->data_hash);

        $cached = Cache::get('blockchain_job:job-direct-sync-2');
        expect($cached['status'])->toBe('done');
    });

    it('creates procurement in DB after initiate_procurement', function () {
        // Ensure no procurement exists yet
        expect(Procurement::where('pr_number', 'PR-2025-DDB-005')->exists())->toBeFalse();

        $mockHandler = Mockery::mock(ProcurementInitiationHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andReturn([
                'success' => true,
                'pr_number' => 'PR-2025-DDB-005',
                'transactions' => [
                    'metadata' => ['txid' => 'tx-meta-init-001', 'step' => 'metadata'],
                    'status' => ['status_txid' => 'tx-status-init-001', 'stage' => 'procurement_initiation', 'current_status' => 'procurement_initiated'],
                    'event' => ['event_txid' => 'tx-event-init-001'],
                ],
            ]);

        app()->instance(ProcurementInitiationHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'initiate_procurement',
            [
                'pr_number' => 'PR-2025-DDB-005',
                'procurement_data' => [
                    'pr_number' => 'PR-2025-DDB-005',
                    'app_reference' => 'APP-001',
                    'title' => 'Initiation DB Sync Test',
                    'description' => 'Testing initiation direct sync',
                    'abc_amount' => '50000',
                    'funding_source' => 'GAA',
                    'category' => 'goods',
                    'procurement_mode' => 'competitive_bidding',
                    'office' => 'Test Office',
                    'user_address' => '0xTEST',
                    'user_id' => '1',
                    'status' => 'draft',
                    'created_at' => now()->toIso8601String(),
                ],
                'user_name' => 'Test User',
            ],
            'job-init-sync',
            1,
        );
        $this->app->call([$job, 'handle']);

        // Verify procurement was created in DB
        $procurement = Procurement::where('pr_number', 'PR-2025-DDB-005')->first();
        expect($procurement)->not->toBeNull()
            ->and($procurement->title)->toBe('Initiation DB Sync Test')
            ->and($procurement->current_stage)->toBe('procurement_initiation')
            ->and($procurement->current_status)->toBe('procurement_initiated')
            ->and($procurement->data_hash)->toBeString()
            ->and(strlen($procurement->data_hash))->toBe(64)
            ->and($procurement->blockchain_hash)->toBe($procurement->data_hash);

        // Verify procurement_stage record
        $stageRecord = ProcurementStage::where('txid', 'tx-status-init-001')->first();
        expect($stageRecord)->not->toBeNull()
            ->and($stageRecord->stage)->toBe('procurement_initiation')
            ->and($stageRecord->status)->toBe('procurement_initiated')
            ->and($stageRecord->data_hash)->toBeString()
            ->and(strlen($stageRecord->data_hash))->toBe(64)
            ->and($stageRecord->blockchain_hash)->toBe($stageRecord->data_hash);

        $cached = Cache::get('blockchain_job:job-init-sync');
        expect($cached['status'])->toBe('done');
    });

    it('does not fail when procurement does not exist in DB for stage operations', function () {
        $mockHandler = Mockery::mock(ProcurementUpdateHandler::class);
        $mockHandler->shouldReceive('executeDecision')
            ->once()
            ->andReturn([
                'success' => true,
                'held' => true,
                'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                'status' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING->value,
                'status_txid' => 'tx-no-proc-001',
                'event_txid' => 'tx-no-proc-ev-001',
            ]);

        app()->instance(ProcurementUpdateHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'publish_decision',
            [
                'pr_number' => 'PR-NONEXISTENT',
                'user_address' => '0xTEST',
                'decision_type' => 'supplemental_bid_bulletin',
                'procurement_title' => 'Nonexistent Test',
                'was_held' => true,
            ],
            'job-no-proc',
            1,
        );
        $this->app->call([$job, 'handle']);

        $cached = Cache::get('blockchain_job:job-no-proc');
        expect($cached['status'])->toBe('done');
    });

    it('handles direct DB sync for skip_stage operation', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2025-DDB-004',
            'title' => 'Skip Stage Test',
            'current_stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'current_status' => ProcurementStatus::PROCUREMENT_SUBMITTED->value,
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
        ]);

        $mockHandler = Mockery::mock(StageTransitionHandler::class);
        $mockHandler->shouldReceive('executeSkip')
            ->once()
            ->andReturn([
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'status' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_SKIPPED->value,
                'next_stage' => StageEnums::BIDDING_DOCUMENTS->value,
                'status_txid' => 'tx-skip-stage-001',
                'event_txid' => 'tx-skip-stage-ev-001',
                'transition_txid' => 'tx-skip-trans-001',
            ]);

        app()->instance(StageTransitionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'skip_stage',
            [
                'pr_number' => 'PR-2025-DDB-004',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'reason' => 'Not required',
                'user_address' => '0xTEST',
            ],
            'job-skip-stage',
            1,
        );
        $this->app->call([$job, 'handle']);

        $procurement->refresh();
        expect($procurement->current_stage)->toBe(StageEnums::BIDDING_DOCUMENTS->value)
            ->and($procurement->current_status)->toBe(ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_SKIPPED->value);

        $cached = Cache::get('blockchain_job:job-skip-stage');
        expect($cached['status'])->toBe('done');
    });

    it('updates DB mirror after procurement initiation is marked complete', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2025-DDB-006',
            'title' => 'Initiation Completion DB Sync Test',
            'current_stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'current_status' => ProcurementStatus::PROCUREMENT_INITIATED->value,
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
        ]);

        $mockHandler = Mockery::mock(StageCompletionHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andReturn([
                'success' => true,
                'status_txid' => 'tx-init-complete-001',
                'event_txid' => 'tx-init-complete-ev-001',
                'next_stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'next_stage_name' => 'Pre-Procurement Conference',
                'next_stage_url' => '/bac-secretariat/pre-procurement/PR-2025-DDB-006/pre_procurement_conference',
            ]);

        app()->instance(StageCompletionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'mark_stage_complete',
            [
                'operation_variant' => 'initiation_complete',
                'pr_number' => 'PR-2025-DDB-006',
                'procurement_title' => 'Initiation Completion DB Sync Test',
                'user_address' => '0xTEST',
                'current_stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'next_stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'next_stage_status' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD->value,
                'document_count' => 3,
            ],
            'job-init-complete-sync',
            1,
        );
        $this->app->call([$job, 'handle']);

        $procurement->refresh();
        expect($procurement->current_stage)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE->value)
            ->and($procurement->current_status)->toBe(ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD->value);

        $stageRecord = ProcurementStage::where('txid', 'tx-init-complete-001')->first();
        expect($stageRecord)->not->toBeNull()
            ->and($stageRecord->stage)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE->value)
            ->and($stageRecord->status)->toBe(ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD->value)
            ->and($stageRecord->blockchain_hash)->toBe($stageRecord->data_hash);
    });

    it('updates DB mirror to next stage status after stage completion transition', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2025-DDB-007',
            'title' => 'Stage Completion Transition DB Sync Test',
            'current_stage' => StageEnums::BID_OPENING->value,
            'current_status' => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED->value,
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
        ]);

        $mockHandler = Mockery::mock(StageCompletionHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andReturn([
                'success' => true,
                'status_txid' => 'tx-bid-opened-001',
                'event_txid' => 'tx-bid-opened-ev-001',
                'next_stage' => StageEnums::BID_EVALUATION->value,
                'transition_txid' => 'tx-bid-evaluation-001',
            ]);

        app()->instance(StageCompletionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob(
            'mark_stage_complete',
            [
                'pr_number' => 'PR-2025-DDB-007',
                'procurement_title' => 'Stage Completion Transition DB Sync Test',
                'user_address' => '0xTEST',
                'current_stage' => StageEnums::BID_OPENING->value,
                'completion_status' => ProcurementStatus::BIDS_OPENED->value,
                'next_stage' => StageEnums::BID_EVALUATION->value,
                'next_stage_status' => ProcurementStatus::BIDS_EVALUATED->value,
                'document_count' => 2,
            ],
            'job-stage-complete-sync',
            1,
        );
        $this->app->call([$job, 'handle']);

        $procurement->refresh();
        expect($procurement->current_stage)->toBe(StageEnums::BID_EVALUATION->value)
            ->and($procurement->current_status)->toBe(ProcurementStatus::BIDS_EVALUATED->value);

        $completionRecord = ProcurementStage::where('txid', 'tx-bid-opened-001')->first();
        expect($completionRecord)->not->toBeNull()
            ->and($completionRecord->stage)->toBe(StageEnums::BID_OPENING->value)
            ->and($completionRecord->status)->toBe(ProcurementStatus::BIDS_OPENED->value);

        $transitionRecord = ProcurementStage::where('txid', 'tx-bid-evaluation-001')->first();
        expect($transitionRecord)->not->toBeNull()
            ->and($transitionRecord->stage)->toBe(StageEnums::BID_EVALUATION->value)
            ->and($transitionRecord->status)->toBe(ProcurementStatus::BIDS_EVALUATED->value);
    });
});
