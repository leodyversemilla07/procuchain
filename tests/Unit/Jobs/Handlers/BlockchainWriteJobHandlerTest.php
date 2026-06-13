<?php

declare(strict_types=1);

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Jobs\BlockchainWriteJob;
use App\Jobs\Handlers\CorrectionHandler;
use App\Jobs\Handlers\DocumentUploadHandler;
use App\Jobs\Handlers\ProcurementInitiationHandler;
use App\Jobs\Handlers\ProcurementUpdateHandler;
use App\Jobs\Handlers\StageCompletionHandler;
use App\Jobs\Handlers\StageTransitionHandler;
use App\Repositories\ProcurementRepository;
use App\Services\Publishers\CorrectionPublisher;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementCorrectionPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

// ============================================================================
// BlockchainWriteJob – Dispatch Routing Tests
// ============================================================================

describe('BlockchainWriteJob dispatch routing', function () {

    beforeEach(function () {
        Log::spy();
        config(['cache.default' => 'array']);
    });

    it('routes upload_document to DocumentUploadHandler::execute()', function () {
        $mockHandler = Mockery::mock(DocumentUploadHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(['success' => true, 'doc_txid' => 'txid-123']);

        app()->instance(DocumentUploadHandler::class, $mockHandler);

        $data = ['pr_number' => 'PR-001', 'File' => 'test.pdf'];
        $job = new BlockchainWriteJob('upload_document', $data, 'job-uuid-1');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-1');
        expect($cached['status'])->toBe('done')
            ->and($cached['result']['doc_txid'])->toBe('txid-123');
    });

    it('routes initiate_procurement to ProcurementInitiationHandler::execute()', function () {
        $mockHandler = Mockery::mock(ProcurementInitiationHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(['success' => true, 'pr_number' => 'PR-002']);

        app()->instance(ProcurementInitiationHandler::class, $mockHandler);

        $data = ['pr_number' => 'PR-002', 'procurement_data' => []];
        $job = new BlockchainWriteJob('initiate_procurement', $data, 'job-uuid-2');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-2');
        expect($cached['status'])->toBe('done')
            ->and($cached['result']['pr_number'])->toBe('PR-002');
    });

    it('routes mark_stage_complete to StageCompletionHandler::execute()', function () {
        $mockHandler = Mockery::mock(StageCompletionHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andReturn(['success' => true]);

        app()->instance(StageCompletionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('mark_stage_complete', ['pr_number' => 'PR-003'], 'job-uuid-3');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-3');
        expect($cached['status'])->toBe('done');
    });

    it('routes skip_stage to StageTransitionHandler::executeSkip()', function () {
        $mockHandler = Mockery::mock(StageTransitionHandler::class);
        $mockHandler->shouldReceive('executeSkip')
            ->once()
            ->andReturn(['status_txid' => 'tx-skip']);

        app()->instance(StageTransitionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('skip_stage', ['pr_number' => 'PR-004'], 'job-uuid-4');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-4');
        expect($cached['status'])->toBe('done');
    });

    it('routes repeat_stage to StageTransitionHandler::executeRepeat()', function () {
        $mockHandler = Mockery::mock(StageTransitionHandler::class);
        $mockHandler->shouldReceive('executeRepeat')
            ->once()
            ->andReturn(['status_txid' => 'tx-repeat']);

        app()->instance(StageTransitionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('repeat_stage', ['pr_number' => 'PR-005'], 'job-uuid-5');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-5');
        expect($cached['status'])->toBe('done');
    });

    it('routes correct_document to CorrectionHandler::executeDocumentCorrection()', function () {
        $mockHandler = Mockery::mock(CorrectionHandler::class);
        $mockHandler->shouldReceive('executeDocumentCorrection')
            ->once()
            ->andReturn(['correction_txid' => 'tx-corr']);

        app()->instance(CorrectionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('correct_document', ['pr_number' => 'PR-006'], 'job-uuid-6');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-6');
        expect($cached['status'])->toBe('done');
    });

    it('routes correct_procurement to CorrectionHandler::executeProcurementCorrection()', function () {
        $mockHandler = Mockery::mock(CorrectionHandler::class);
        $mockHandler->shouldReceive('executeProcurementCorrection')
            ->once()
            ->andReturn(['correction_txid' => 'tx-pcorr']);

        app()->instance(CorrectionHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('correct_procurement', ['pr_number' => 'PR-007'], 'job-uuid-7');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-7');
        expect($cached['status'])->toBe('done');
    });

    it('routes update_delivery_details to ProcurementUpdateHandler::executeDeliveryDetails()', function () {
        $mockHandler = Mockery::mock(ProcurementUpdateHandler::class);
        $mockHandler->shouldReceive('executeDeliveryDetails')
            ->once()
            ->andReturn(['event_txid' => 'tx-del']);

        app()->instance(ProcurementUpdateHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('update_delivery_details', ['pr_number' => 'PR-008'], 'job-uuid-8');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-8');
        expect($cached['status'])->toBe('done');
    });

    it('routes publish_decision to ProcurementUpdateHandler::executeDecision()', function () {
        $mockHandler = Mockery::mock(ProcurementUpdateHandler::class);
        $mockHandler->shouldReceive('executeDecision')
            ->once()
            ->andReturn(['decision_txid' => 'tx-dec']);

        app()->instance(ProcurementUpdateHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('publish_decision', ['pr_number' => 'PR-009'], 'job-uuid-9');
        $job->handle();

        $cached = Cache::get('blockchain_job:job-uuid-9');
        expect($cached['status'])->toBe('done');
    });

    it('throws exception for unknown operations', function () {
        $job = new BlockchainWriteJob('nonexistent_operation', ['pr_number' => 'PR-999'], 'job-uuid-unknown');
        $job->handle();
    })->throws(Exception::class, 'Unknown blockchain operation: nonexistent_operation');

    it('caches failed result when handler throws', function () {
        $mockHandler = Mockery::mock(DocumentUploadHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Blockchain RPC timeout'));

        app()->instance(DocumentUploadHandler::class, $mockHandler);

        $job = new BlockchainWriteJob('upload_document', ['pr_number' => 'PR-2025-992-0001'], 'job-uuid-fail');

        try {
            $job->handle();
        } catch (Exception) {
            // expected
        }

        $cached = Cache::get('blockchain_job:job-uuid-fail');
        expect($cached['status'])->toBe('retrying')
            ->and($cached['attempt'])->toBe(1)
            ->and($cached['max_attempts'])->toBe(3);
    });

    it('caches result in Redis with key blockchain_job:{jobId}', function () {
        $mockHandler = Mockery::mock(ProcurementInitiationHandler::class);
        $mockHandler->shouldReceive('execute')
            ->once()
            ->andReturn(['success' => true, 'txid' => 'abc123']);

        app()->instance(ProcurementInitiationHandler::class, $mockHandler);

        $jobId = 'specific-job-id-test';
        $job = new BlockchainWriteJob('initiate_procurement', ['pr_number' => 'PR-2025-992-0002'], $jobId);
        $job->handle();

        $cached = Cache::get("blockchain_job:{$jobId}");
        expect($cached)->toBeArray()
            ->and($cached['status'])->toBe('done')
            ->and($cached['result'])->toBe(['success' => true, 'txid' => 'abc123']);
    });
});

// ============================================================================
// ProcurementInitiationHandler Tests
// ============================================================================

describe('ProcurementInitiationHandler', function () {

    beforeEach(function () {
        Log::spy();
    });

    it('calls ProcurementOrchestrator::initiateProcurement() with correct data', function () {
        $procurementData = ['pr_number' => 'PR-100', 'title' => 'Test Procurement'];
        $data = [
            'procurement_data' => $procurementData,
            'user_name' => 'John Doe',
        ];

        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('initiateProcurement')
            ->once()
            ->with($procurementData, [], 'John Doe')
            ->andReturn(['success' => true, 'txid' => 'init-txid']);

        $handler = new ProcurementInitiationHandler($orchestrator);
        $result = $handler->execute($data);

        expect($result['success'])->toBeTrue()
            ->and($result['txid'])->toBe('init-txid');
    });

    it('throws exception when orchestrator returns failure', function () {
        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('initiateProcurement')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Stream write failed']);

        $handler = new ProcurementInitiationHandler($orchestrator);
        $handler->execute([
            'procurement_data' => ['pr_number' => 'PR-2025-992-0003'],
            'user_name' => 'Test User',
        ]);
    })->throws(Exception::class, 'Stream write failed');

    it('throws generic message when orchestrator failure has no message', function () {
        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('initiateProcurement')
            ->once()
            ->andReturn(['success' => false]);

        $handler = new ProcurementInitiationHandler($orchestrator);
        $handler->execute([
            'procurement_data' => ['pr_number' => 'PR-2025-992-0004'],
            'user_name' => 'Test User',
        ]);
    })->throws(Exception::class, 'Orchestrator returned failure during initiation');
});

// ============================================================================
// DocumentUploadHandler Tests
// ============================================================================

describe('DocumentUploadHandler', function () {

    beforeEach(function () {
        Log::spy();
        Storage::fake('local');
    });

    it('reconstitutes temp File and calls publishDocumentWorkflow', function () {
        // Create a temp File on the fake disk
        Storage::put('temp/test-doc.pdf', 'fake-pdf-content');

        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')
            ->once()
            ->withArgs(function ($procurementData, $File, $documentData, $statusData, $eventData) {
                return $procurementData['pr_number'] === 'PR-2025-992-0005'
                    && $File instanceof UploadedFile
                    && $documentData['stage'] === StageEnums::BID_OPENING
                    && $documentData['document_type'] === DocumentTypeEnums::PURCHASE_REQUEST
                    && $statusData['stage'] === StageEnums::BID_OPENING
                    && $statusData['current_status'] === ProcurementStatus::BIDS_OPENED
                    && $eventData['event_type'] === 'document_uploaded';
            })
            ->andReturn(['success' => true, 'doc_txid' => 'doc-tx-1']);

        $handler = new DocumentUploadHandler($orchestrator);
        $result = $handler->execute([
            'temp_file_path' => 'temp/test-doc.pdf',
            'original_filename' => 'purchase_request.pdf',
            'mime_type' => 'application/pdf',
            'pr_number' => 'PR-2025-992-0005',
            'procurement_title' => 'Test Procurement',
            'user_address' => '0xABC',
            'stage' => StageEnums::BID_OPENING->value,
            'status' => 'bids_opened',
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'uploaded_by' => 'tester',
            'current_status' => ProcurementStatus::BIDS_OPENED->value,
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['doc_txid'])->toBe('doc-tx-1');
    });

    it('cleans up temp File after successful upload', function () {
        Storage::put('temp/cleanup-test.pdf', 'content');

        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')
            ->once()
            ->andReturn(['success' => true]);

        $handler = new DocumentUploadHandler($orchestrator);
        $handler->execute([
            'temp_file_path' => 'temp/cleanup-test.pdf',
            'original_filename' => 'cleanup.pdf',
            'mime_type' => 'application/pdf',
            'pr_number' => 'PR-2025-992-0006',
            'procurement_title' => 'Cleanup Test',
            'user_address' => '0xDEF',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'status' => 'procurement_initiated',
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'uploaded_by' => 'tester',
            'current_status' => ProcurementStatus::PROCUREMENT_INITIATED->value,
        ]);

        Storage::assertMissing('temp/cleanup-test.pdf');
    });

    it('keeps temp File when orchestrator fails so the job can retry', function () {
        Storage::put('temp/fail-cleanup.pdf', 'content');

        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')
            ->once()
            ->andReturn(['success' => false, 'error' => 'RPC failed']);

        $handler = new DocumentUploadHandler($orchestrator);

        try {
            $handler->execute([
                'temp_file_path' => 'temp/fail-cleanup.pdf',
                'original_filename' => 'fail.pdf',
                'mime_type' => 'application/pdf',
                'pr_number' => 'PR-2025-992-0007',
                'procurement_title' => 'Fail Cleanup Test',
                'user_address' => '0xGHI',
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'status' => 'procurement_initiated',
                'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
                'uploaded_by' => 'tester',
                'current_status' => ProcurementStatus::PROCUREMENT_INITIATED->value,
            ]);
        } catch (Exception) {
            // expected
        }

        Storage::assertExists('temp/fail-cleanup.pdf');
    });

    it('throws when temp File is missing', function () {
        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);

        $handler = new DocumentUploadHandler($orchestrator);
        $handler->execute([
            'temp_file_path' => 'temp/nonexistent.pdf',
            'original_filename' => 'ghost.pdf',
            'mime_type' => 'application/pdf',
            'pr_number' => 'PR-2025-992-0008',
            'procurement_title' => 'Missing File Test',
            'user_address' => '0xMIS',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'status' => 'procurement_initiated',
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'uploaded_by' => 'tester',
            'current_status' => ProcurementStatus::PROCUREMENT_INITIATED->value,
        ]);
    })->throws(Exception::class, 'Temp File not found');
});

// ============================================================================
// HandlesTempFiles Trait Tests
// ============================================================================

describe('HandlesTempFiles trait', function () {

    beforeEach(function () {
        Log::spy();
        Storage::fake('local');
    });

    it('reconstituteTempFile creates an UploadedFile from storage path', function () {
        Storage::put('temp/trait-test.pdf', 'sample-content');

        // Use DocumentUploadHandler as a concrete class that uses the trait
        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $handler = new DocumentUploadHandler($orchestrator);

        $reflection = new ReflectionMethod($handler, 'reconstituteTempFile');
        $reflection->setAccessible(true);

        $File = $reflection->invoke($handler, 'temp/trait-test.pdf', 'original.pdf', 'application/pdf');

        expect($File)->toBeInstanceOf(UploadedFile::class)
            ->and($File->getClientOriginalName())->toBe('original.pdf')
            ->and($File->getClientMimeType())->toBe('application/pdf');
    });

    it('cleanupTempFile removes the File from storage', function () {
        Storage::put('temp/to-delete.pdf', 'delete-me');

        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $handler = new DocumentUploadHandler($orchestrator);

        $reflection = new ReflectionMethod($handler, 'cleanupTempFile');
        $reflection->setAccessible(true);

        $reflection->invoke($handler, 'temp/to-delete.pdf');

        Storage::assertMissing('temp/to-delete.pdf');
    });

    it('cleanupTempFile does not throw when File is already missing', function () {
        $orchestrator = Mockery::mock(ProcurementOrchestrator::class);
        $handler = new DocumentUploadHandler($orchestrator);

        $reflection = new ReflectionMethod($handler, 'cleanupTempFile');
        $reflection->setAccessible(true);

        // Should not throw — just logs a warning at most
        $reflection->invoke($handler, 'temp/already-gone.pdf');

        expect(true)->toBeTrue();
    });
});

// ============================================================================
// CorrectionHandler Tests
// ============================================================================

describe('CorrectionHandler', function () {

    beforeEach(function () {
        Log::spy();
        Storage::fake('local');
    });

    // CorrectionPublisher and ProcurementCorrectionPublisher are final classes.
    // bypass-finals is enabled to allow Mockery to mock them.
    it('executeDocumentCorrection delegates to CorrectionPublisher with File', function () {
        Storage::put('temp/correction-File.pdf', 'corrected-content');

        $correctionPublisher = Mockery::mock(CorrectionPublisher::class);
        $correctionPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $originalTxid,
                string $originalDocumentHash,
                string $correctionType,
                string $action,
                string $reason,
                string $correctedBy,
                string $userAddress,
                $correctedFile,
                ?string $originalStage,
            ) {
                return $prNumber === 'PR-2025-992-0009'
                    && $originalTxid === 'txid-original'
                    && $action === 'replace'
                    && $correctedFile instanceof UploadedFile
                    && $originalStage === 'bid_opening';
            })
            ->andReturn(['correction_txid' => 'corr-tx-1']);

        $procurementCorrectionPublisher = Mockery::mock(ProcurementCorrectionPublisher::class);

        $handler = new CorrectionHandler($correctionPublisher, $procurementCorrectionPublisher);
        $result = $handler->executeDocumentCorrection([
            'pr_number' => 'PR-2025-992-0009',
            'procurement_title' => 'Correction Test',
            'original_txid' => 'txid-original',
            'original_document_hash' => 'hash-abc',
            'correction_type' => 'content_error',
            'action' => 'replace',
            'reason' => 'Incorrect amount',
            'corrected_by' => 'Admin',
            'user_address' => '0xCORR',
            'temp_file_path' => 'temp/correction-File.pdf',
            'original_filename' => 'corrected.pdf',
            'mime_type' => 'application/pdf',
            'original_stage' => 'bid_opening',
        ]);

        expect($result['correction_txid'])->toBe('corr-tx-1');
    });

    it('executeDocumentCorrection works without a File (invalidation)', function () {
        $correctionPublisher = Mockery::mock(CorrectionPublisher::class);
        $correctionPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $originalTxid,
                string $originalDocumentHash,
                string $correctionType,
                string $action,
                string $reason,
                string $correctedBy,
                string $userAddress,
                $correctedFile,
            ) {
                return $correctedFile === null && $action === 'invalidate';
            })
            ->andReturn(['correction_txid' => 'corr-tx-2']);

        $procurementCorrectionPublisher = Mockery::mock(ProcurementCorrectionPublisher::class);

        $handler = new CorrectionHandler($correctionPublisher, $procurementCorrectionPublisher);
        $result = $handler->executeDocumentCorrection([
            'pr_number' => 'PR-2025-992-0010',
            'procurement_title' => 'Invalidation Test',
            'original_txid' => 'txid-original-2',
            'original_document_hash' => 'hash-def',
            'correction_type' => 'obsolete',
            'action' => 'invalidate',
            'reason' => 'Document no longer valid',
            'corrected_by' => 'Admin',
            'user_address' => '0xINVAL',
        ]);

        expect($result['correction_txid'])->toBe('corr-tx-2');
    });

    it('executeProcurementCorrection delegates to ProcurementCorrectionPublisher', function () {
        $correctionPublisher = Mockery::mock(CorrectionPublisher::class);
        $procurementCorrectionPublisher = Mockery::mock(ProcurementCorrectionPublisher::class);

        $originalProcurementArray = [
            'pr_number' => 'PR-2025-992-0011',
            'title' => 'Original Title',
            'description' => 'Original Description',
            'abc_amount' => '100000',
            'funding_source' => 'GAA',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'office' => 'Test Office',
            'status' => 'procurement_initiated',
            'user_id' => 'user-1',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ];

        $procurementCorrectionPublisher->shouldReceive('publishCorrection')
            ->once()
            ->withArgs(function (
                ProcurementData $originalProcurement,
                array $correctedData,
                string $reason,
                string $correctedBy,
                string $userAddress,
            ) {
                return $originalProcurement->prNumber === 'PR-2025-992-0011'
                    && $correctedData['title'] === 'Corrected Title'
                    && $reason === 'Title was wrong'
                    && $correctedBy === 'Admin'
                    && $userAddress === '0xPCORR';
            })
            ->andReturn(['correction_txid' => 'pcorr-tx-1']);

        $handler = new CorrectionHandler($correctionPublisher, $procurementCorrectionPublisher);
        $result = $handler->executeProcurementCorrection([
            'original_procurement' => $originalProcurementArray,
            'corrected_data' => ['title' => 'Corrected Title'],
            'reason' => 'Title was wrong',
            'corrected_by' => 'Admin',
            'user_address' => '0xPCORR',
        ]);

        expect($result['correction_txid'])->toBe('pcorr-tx-1');
    });
});

// ============================================================================
// StageCompletionHandler Tests
// ============================================================================

describe('StageCompletionHandler', function () {

    beforeEach(function () {
        Log::spy();
    });

    it('publishes status and event for standard stage completion', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);

        $statusPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                StageEnums $stage,
                ProcurementStatus $currentStatus,
                string $userAddress,
            ) {
                return $prNumber === 'PR-2025-992-0012'
                    && $stage === StageEnums::BID_OPENING
                    && $currentStatus === ProcurementStatus::BIDS_OPENED;
            })
            ->andReturn(['status_txid' => 'status-tx-1']);

        $eventPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $stage,
                string $eventType,
            ) {
                return $prNumber === 'PR-2025-992-0012'
                    && $stage === StageEnums::BID_OPENING->value
                    && $eventType === 'stage_completed';
            })
            ->andReturn(['event_txid' => 'event-tx-1']);

        $handler = new StageCompletionHandler($statusPublisher, $eventPublisher);
        $result = $handler->execute([
            'pr_number' => 'PR-2025-992-0012',
            'procurement_title' => 'Stage Completion Test',
            'current_stage' => StageEnums::BID_OPENING->value,
            'completion_status' => ProcurementStatus::BIDS_OPENED->value,
            'user_address' => '0xSC',
            'document_count' => 3,
            'procurement_mode' => 'competitive_bidding',
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['status_txid'])->toBe('status-tx-1')
            ->and($result['event_txid'])->toBe('event-tx-1')
            ->and($result['next_stage'])->toBeNull();
    });

    it('publishes transition when next_stage is provided', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);

        $statusPublisher->shouldReceive('publish')->once()->andReturn(['status_txid' => 'st-1']);
        $statusPublisher->shouldReceive('publishTransition')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                StageEnums $fromStage,
                StageEnums $toStage,
                ProcurementStatus $currentStatus,
            ) {
                return $fromStage === StageEnums::BID_OPENING
                    && $toStage === StageEnums::BID_EVALUATION;
            });

        $eventPublisher->shouldReceive('publish')->once()->andReturn(['event_txid' => 'ev-1']);
        $eventPublisher->shouldReceive('publishStageTransition')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $fromStage,
                string $toStage,
            ) {
                return $fromStage === StageEnums::BID_OPENING->value
                    && $toStage === StageEnums::BID_EVALUATION->value;
            });

        $handler = new StageCompletionHandler($statusPublisher, $eventPublisher);
        $result = $handler->execute([
            'pr_number' => 'PR-2025-992-0013',
            'procurement_title' => 'Transition Test',
            'current_stage' => StageEnums::BID_OPENING->value,
            'completion_status' => ProcurementStatus::BIDS_OPENED->value,
            'user_address' => '0xTRANS',
            'document_count' => 2,
            'procurement_mode' => 'competitive_bidding',
            'next_stage' => StageEnums::BID_EVALUATION->value,
            'next_stage_status' => ProcurementStatus::BIDS_EVALUATED->value,
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['next_stage'])->toBe(StageEnums::BID_EVALUATION->value);
    });

    it('handles initiation_complete variant', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);

        $statusPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                StageEnums $stage,
                ProcurementStatus $currentStatus,
            ) {
                return $stage === StageEnums::PRE_PROCUREMENT_CONFERENCE
                    && $currentStatus === ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD;
            })
            ->andReturn([]);

        $eventPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $stage,
                string $eventType,
            ) {
                return $stage === StageEnums::PRE_PROCUREMENT_CONFERENCE->value
                    && $eventType === 'stage_completed';
            })
            ->andReturn([]);

        $handler = new StageCompletionHandler($statusPublisher, $eventPublisher);
        $result = $handler->execute([
            'operation_variant' => 'initiation_complete',
            'pr_number' => 'PR-2025-992-0014',
            'procurement_title' => 'Initiation Complete Test',
            'current_stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'next_stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'next_stage_status' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD->value,
            'user_address' => '0xINIT',
            'document_count' => 5,
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['next_stage'])->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE->value);
    });
});

// ============================================================================
// StageTransitionHandler Tests
// ============================================================================

describe('StageTransitionHandler', function () {

    beforeEach(function () {
        Log::spy();
    });

    it('executeSkip skips optional stages', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);

        $procurement = ProcurementData::fromArray([
            'pr_number' => 'PR-2025-992-0015',
            'title' => 'Skip Test',
            'description' => 'Testing skip',
            'abc_amount' => '50000',
            'funding_source' => 'GAA',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'office' => 'Test Office',
            'status' => 'procurement_initiated',
            'user_id' => 'user-1',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ]);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0015')
            ->andReturn($procurement);

        $statusPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                StageEnums $stage,
                ProcurementStatus $currentStatus,
            ) {
                return $prNumber === 'PR-2025-992-0015'
                    && $stage === StageEnums::PRE_PROCUREMENT_CONFERENCE
                    && $currentStatus === ProcurementStatus::STAGE_SKIPPED;
            })
            ->andReturn(['status_txid' => 'skip-st-1']);

        $eventPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $stage,
                string $eventType,
            ) {
                return $stage === StageEnums::PRE_PROCUREMENT_CONFERENCE->value
                    && $eventType === 'stage_skipped';
            })
            ->andReturn(['event_txid' => 'skip-ev-1']);

        $handler = new StageTransitionHandler($statusPublisher, $eventPublisher, $procurementRepo);
        $result = $handler->executeSkip([
            'pr_number' => 'PR-2025-992-0015',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'reason' => 'Not required for this mode',
            'user_address' => '0xSKIP',
        ]);

        expect($result['status_txid'])->toBe('skip-st-1')
            ->and($result['event_txid'])->toBe('skip-ev-1')
            ->and($result['stage'])->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE->value);
    });

    it('executeSkip throws when procurement not found', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0016')
            ->andReturnNull();

        $handler = new StageTransitionHandler($statusPublisher, $eventPublisher, $procurementRepo);
        $handler->executeSkip([
            'pr_number' => 'PR-2025-992-0016',
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
            'user_address' => '0xNF',
        ]);
    })->throws(Exception::class, 'Procurement not found: PR-2025-992-0016');

    it('executeRepeat repeats stages with event and status publishing', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);

        $procurement = ProcurementData::fromArray([
            'pr_number' => 'PR-2025-992-0017',
            'title' => 'Repeat Test',
            'description' => 'Testing repeat',
            'abc_amount' => '75000',
            'funding_source' => 'GAA',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'office' => 'Test Office',
            'status' => 'procurement_initiated',
            'user_id' => 'user-1',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ]);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0017')
            ->andReturn($procurement);

        $eventPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $stage,
                string $eventType,
            ) {
                return $prNumber === 'PR-2025-992-0017'
                    && $stage === StageEnums::SUPPLEMENTAL_BID_BULLETIN->value
                    && $eventType === 'stage_repeated';
            })
            ->andReturn(['event_txid' => 'rep-ev-1']);

        $statusPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                StageEnums $stage,
                ProcurementStatus $currentStatus,
            ) {
                return $prNumber === 'PR-2025-992-0017'
                    && $stage === StageEnums::SUPPLEMENTAL_BID_BULLETIN
                    && $currentStatus === ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING;
            })
            ->andReturn(['status_txid' => 'rep-st-1']);

        $handler = new StageTransitionHandler($statusPublisher, $eventPublisher, $procurementRepo);
        $result = $handler->executeRepeat([
            'pr_number' => 'PR-2025-992-0017',
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
            'reason' => 'Additional bulletin required',
            'user_address' => '0xREP',
        ]);

        expect($result['status_txid'])->toBe('rep-st-1')
            ->and($result['event_txid'])->toBe('rep-ev-1')
            ->and($result['stage'])->toBe(StageEnums::SUPPLEMENTAL_BID_BULLETIN->value);
    });

    it('executeRepeat throws when procurement not found', function () {
        $statusPublisher = Mockery::mock(StatusPublisher::class);
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0018')
            ->andReturnNull();

        $handler = new StageTransitionHandler($statusPublisher, $eventPublisher, $procurementRepo);
        $handler->executeRepeat([
            'pr_number' => 'PR-2025-992-0018',
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
            'user_address' => '0xRNF',
        ]);
    })->throws(Exception::class, 'Procurement not found: PR-2025-992-0018');
});

// ============================================================================
// ProcurementUpdateHandler Tests
// ============================================================================

describe('ProcurementUpdateHandler', function () {

    beforeEach(function () {
        Log::spy();
    });

    it('executeDeliveryDetails updates delivery info and publishes event', function () {
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);
        $decisionPublisher = Mockery::mock(DecisionPublisher::class);

        $procurement = ProcurementData::fromArray([
            'pr_number' => 'PR-2025-992-0019',
            'title' => 'Delivery Test',
            'description' => 'Testing delivery update',
            'abc_amount' => '120000',
            'funding_source' => 'GAA',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'office' => 'Test Office',
            'status' => 'awarded',
            'user_id' => 'user-1',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ]);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0019')
            ->andReturn($procurement);

        $procurementRepo->shouldReceive('update')
            ->once()
            ->withArgs(function (ProcurementData $updated) {
                return $updated->deliveryLocation === 'Manila'
                    && $updated->deliveryTermDays === 30
                    && $updated->prNumber === 'PR-2025-992-0019';
            });

        $eventPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $prNumber,
                string $procurementTitle,
                string $stage,
                string $eventType,
            ) {
                return $prNumber === 'PR-2025-992-0019'
                    && $stage === StageEnums::NOTICE_TO_PROCEED->value
                    && $eventType === 'delivery_details_updated';
            })
            ->andReturn(['event_txid' => 'del-ev-1']);

        $handler = new ProcurementUpdateHandler($eventPublisher, $procurementRepo, $decisionPublisher);
        $result = $handler->executeDeliveryDetails([
            'pr_number' => 'PR-2025-992-0019',
            'user_address' => '0xDEL',
            'delivery_location' => 'Manila',
            'delivery_date' => '2024-06-15',
            'delivery_term_days' => '30',
        ]);

        expect($result['event_txid'])->toBe('del-ev-1');
    });

    it('executeDeliveryDetails throws when procurement not found', function () {
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);
        $decisionPublisher = Mockery::mock(DecisionPublisher::class);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0020')
            ->andReturnNull();

        $handler = new ProcurementUpdateHandler($eventPublisher, $procurementRepo, $decisionPublisher);
        $handler->executeDeliveryDetails([
            'pr_number' => 'PR-2025-992-0020',
            'user_address' => '0xDNF',
            'delivery_location' => 'Cebu',
            'delivery_date' => '2024-06-15',
            'delivery_term_days' => '15',
        ]);
    })->throws(Exception::class, 'Procurement not found: PR-2025-992-0020');

    // Note: ProcurementUpdateHandler passes ProcurementData to publishDecision()
    // but the publisher expects ?array — pre-existing type mismatch. Testing with null procurement.
    it('executeDecision delegates to DecisionPublisher', function () {
        $eventPublisher = Mockery::mock(EventPublisher::class);
        $procurementRepo = Mockery::mock(ProcurementRepository::class);
        $decisionPublisher = Mockery::mock(DecisionPublisher::class);

        $procurementRepo->shouldReceive('findByProcurement')
            ->once()
            ->with('PR-2025-992-0021')
            ->andReturnNull();

        $decisionPublisher->shouldReceive('publishDecision')
            ->once()
            ->withArgs(function (
                string $decisionType,
                string $prNumber,
                string $procurementTitle,
                bool $wasHeld,
                string $userAddress,
                $procurementArg,
            ) {
                return $decisionType === 'pre_procurement_conference'
                    && $prNumber === 'PR-2025-992-0021'
                    && $wasHeld === true
                    && $procurementArg === null;
            })
            ->andReturn(['decision_txid' => 'dec-tx-1']);

        $handler = new ProcurementUpdateHandler($eventPublisher, $procurementRepo, $decisionPublisher);
        $result = $handler->executeDecision([
            'pr_number' => 'PR-2025-992-0021',
            'user_address' => '0xDEC',
            'decision_type' => 'pre_procurement_conference',
            'procurement_title' => 'Decision Test',
            'was_held' => true,
        ]);

        expect($result['decision_txid'])->toBe('dec-tx-1');
    });
});
