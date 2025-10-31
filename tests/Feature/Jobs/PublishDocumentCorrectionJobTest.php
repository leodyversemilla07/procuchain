<?php

use App\Jobs\PublishDocumentCorrectionJob;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-CORR-001';
    $this->procurementTitle = 'Test Procurement for Correction';
    $this->originalTxid = 'original-txid-123abc';
    $this->originalDocumentHash = 'hash-original-456def';
    $this->correctionReason = 'Incorrect document uploaded';
    $this->correctedMetadata = [
        'document_type' => 'bidding_documents',
        'hash' => 'hash-corrected-789ghi',
        'file_key' => 'corrected-file-key',
    ];
    $this->correctedBy = 'admin@example.com';
    $this->userAddress = '1ABC123XYZ';
    $this->streamKey = 'stream-key-correction-123';

    $this->multichainService = mock(MultichainService::class);
    $this->streamKeyService = mock(StreamKeyService::class);
    $this->eventLoggerService = mock(BlockchainEventLoggerService::class);
});

describe('PublishDocumentCorrectionJob', function () {
    describe('job configuration', function () {
        it('can be dispatched', function () {
            Queue::fake();

            PublishDocumentCorrectionJob::dispatch(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            Queue::assertPushed(PublishDocumentCorrectionJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });

        it('has correct retry configuration', function () {
            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect($job->tries)->toBe(5);
            expect($job->timeout)->toBe(120);
            expect($job->backoff)->toBeArray();
            expect($job->backoff)->toHaveCount(5);
        });
    });

    describe('handle method - successful correction with metadata', function () {
        // NOTE: These tests expose a bug in PublishDocumentCorrectionJob line 133-140
        // The job calls logEvent() with named parameters, but the method signature requires positional parameters
        // This needs to be fixed in the production code: app/Jobs/PublishDocumentCorrectionJob.php
        it('publishes document correction with corrected metadata successfully', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->with($this->procurementId, $this->procurementTitle)
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->with(
                    $this->userAddress,
                    'procurement.corrections',
                    $this->streamKey,
                    \Mockery::on(function ($data) {
                        return isset($data['json'])
                            && $data['json']['procurement_id'] === 'PROC-CORR-001'
                            && $data['json']['correction_type'] === 'document_correction'
                            && $data['json']['original_txid'] === 'original-txid-123abc'
                            && $data['json']['reason'] === 'Incorrect document uploaded'
                            && isset($data['json']['corrected_metadata'])
                            && $data['json']['action'] === 'replace';
                    })
                )
                ->andReturn('correction-txid-abc123')
                ->once();

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->withAnyArgs()
                ->once();

            Log::shouldReceive('info')->atLeast()->twice();
            Log::shouldReceive('error')->never();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            );
        });
    });

    describe('handle method - invalidation without metadata', function () {
        it('publishes document correction without corrected metadata (invalidation)', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->with($this->procurementId, $this->procurementTitle)
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->with(
                    $this->userAddress,
                    'procurement.corrections',
                    $this->streamKey,
                    \Mockery::on(function ($data) {
                        return isset($data['json'])
                            && $data['json']['action'] === 'invalidate'
                            && ! isset($data['json']['corrected_metadata']);
                    })
                )
                ->andReturn('correction-txid-xyz789')
                ->once();

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->withAnyArgs()
                ->once();

            Log::shouldReceive('info')->atLeast()->twice();
            Log::shouldReceive('error')->never();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                null, // No corrected metadata = invalidation
                $this->correctedBy,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            );
        });
    });

    describe('handle method - validation failures', function () {
        it('throws exception when procurement ID is empty', function () {
            $job = new PublishDocumentCorrectionJob(
                '', // Empty procurement ID
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Procurement ID and title are required');
        });

        it('throws exception when procurement title is empty', function () {
            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                '', // Empty title
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Procurement ID and title are required');
        });

        it('throws exception when original txid is empty', function () {
            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                '', // Empty original txid
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Original transaction ID is required for correction');
        });

        it('throws exception when correction reason is empty', function () {
            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                '', // Empty correction reason
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Correction reason is required');
        });

        it('throws exception when blockchain address is invalid', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with('invalid-address')
                ->andReturn(false);

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                'invalid-address'
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Invalid blockchain address: invalid-address');
        });
    });

    describe('handle method - blockchain failures', function () {
        it('throws exception when publishFrom fails', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->andThrow(new Exception('Blockchain connection failed'));

            Log::shouldReceive('info')->once();
            Log::shouldReceive('error')->once();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            ))->toThrow(Exception::class, 'Blockchain connection failed');
        });

        it('logs error details when exception occurs', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->andThrow(new Exception('Test error'));

            Log::shouldReceive('info')->once();
            Log::shouldReceive('error')
                ->with(
                    'Failed to publish document correction',
                    \Mockery::on(function ($context) {
                        return $context['procurement_id'] === 'PROC-CORR-001'
                            && $context['original_txid'] === 'original-txid-123abc'
                            && $context['error'] === 'Test error';
                    })
                )
                ->once();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            try {
                $job->handle(
                    $this->multichainService,
                    $this->streamKeyService,
                    $this->eventLoggerService
                );
            } catch (Exception $e) {
                // Expected exception
            }
        });
    });

    describe('failed method', function () {
        it('logs permanent failure details', function () {
            Log::shouldReceive('error')
                ->with(
                    'PublishDocumentCorrectionJob permanently failed',
                    \Mockery::on(function ($context) {
                        return $context['procurement_id'] === 'PROC-CORR-001'
                            && $context['original_txid'] === 'original-txid-123abc'
                            && $context['correction_reason'] === 'Incorrect document uploaded'
                            && isset($context['exception'])
                            && isset($context['trace']);
                    })
                )
                ->once();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            $exception = new Exception('Test failure');
            $job->failed($exception);
        });
    });

    describe('correction data structure', function () {
        it('includes all required fields in correction record', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->with(
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::on(function ($data) {
                        $json = $data['json'];

                        return isset($json['procurement_id'])
                            && isset($json['procurement_title'])
                            && isset($json['correction_type'])
                            && isset($json['original_txid'])
                            && isset($json['original_document_hash'])
                            && isset($json['reason'])
                            && isset($json['corrected_by'])
                            && isset($json['user_address'])
                            && isset($json['timestamp'])
                            && isset($json['action']);
                    })
                )
                ->andReturn('txid-123')
                ->once();

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->withAnyArgs()
                ->once();

            Log::shouldReceive('info')->atLeast()->twice();
            Log::shouldReceive('error')->never();

            $job = new PublishDocumentCorrectionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->originalTxid,
                $this->originalDocumentHash,
                $this->correctionReason,
                $this->correctedMetadata,
                $this->correctedBy,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->eventLoggerService
            );
        });
    });
});
