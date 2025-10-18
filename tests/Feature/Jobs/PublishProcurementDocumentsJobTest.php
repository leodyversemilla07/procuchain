<?php

use App\Jobs\PublishProcurementDocumentsJob;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StatusUpdaterService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->state = 'bidding';
    $this->status = 'published';
    $this->userAddress = '1ABC123XYZ';
    $this->streamKey = 'stream-key-123';

    $this->metadataArray = [
        [
            'document_type' => 'bid',
            'hash' => 'hash123',
            'file_key' => 'file123',
            'file_size' => 1024,
        ],
    ];

    $this->multichainService = mock(MultichainService::class);
    $this->streamKeyService = mock(StreamKeyService::class);
    $this->statusUpdaterService = mock(StatusUpdaterService::class);
    $this->eventLoggerService = mock(BlockchainEventLoggerService::class);
});

describe('PublishProcurementDocumentsJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched', function () {
            Queue::fake();

            PublishProcurementDocumentsJob::dispatch(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            Queue::assertPushed(PublishProcurementDocumentsJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });
    });

    describe('handle method', function () {
        it('publishes procurement documents successfully', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->with($this->procurementId, $this->procurementTitle)
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishMultiFrom')
                ->andReturn(true)
                ->once();

            $this->statusUpdaterService
                ->shouldReceive('updateStatus')
                ->andReturn(null)
                ->once();

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->andReturn(null)
                ->once();

            Log::shouldReceive('info')->atLeast()->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            );
        });

        it('processes multiple documents', function () {
            $multipleMetadata = [
                [
                    'document_type' => 'bid',
                    'hash' => 'hash1',
                    'file_key' => 'file1',
                    'file_size' => 1024,
                ],
                [
                    'document_type' => 'specification',
                    'hash' => 'hash2',
                    'file_key' => 'file2',
                    'file_size' => 2048,
                ],
            ];

            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishMultiFrom')
                ->andReturn(true)
                ->once();

            $this->statusUpdaterService
                ->shouldReceive('updateStatus')
                ->andReturn(null);

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->andReturn(null);

            Log::shouldReceive('info')->atLeast()->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $multipleMetadata,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            );
        });

        it('logs stream key generation', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishMultiFrom')
                ->andReturn(true)
                ->once();

            $this->statusUpdaterService
                ->shouldReceive('updateStatus')
                ->andReturn(null);

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->andReturn(null);

            Log::shouldReceive('info')
                ->with('Generated stream key', [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'stream_key' => $this->streamKey,
                ])
                ->once();

            Log::shouldReceive('info')->atLeast()->once(); // Other log calls

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            );
        });
    });

    describe('validation', function () {
        it('logs error when procurement ID is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to publish procurement documents asynchronously', Mockery::on(function ($context) {
                    return $context['procurementId'] === '' &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            // markDocumentsAsFailed() logs a warning
            Log::shouldReceive('warning')->once();

            $job = new PublishProcurementDocumentsJob(
                '', // Empty procurement ID
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('logs error when procurement title is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to publish procurement documents asynchronously', Mockery::on(function ($context) {
                    return $context['procurementId'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            // markDocumentsAsFailed() logs a warning
            Log::shouldReceive('warning')->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                '', // Empty title
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('logs error when metadata array is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to publish procurement documents asynchronously', Mockery::on(function ($context) {
                    return $context['procurementId'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Document metadata array cannot be empty');
                }));

            // markDocumentsAsFailed() logs a warning
            Log::shouldReceive('warning')->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                [], // Empty metadata array
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class, 'Document metadata array cannot be empty');
        });

        it('logs error when user address is invalid', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(false);

            Log::shouldReceive('error')
                ->once()
                ->with('Failed to publish procurement documents asynchronously', Mockery::on(function ($context) {
                    return $context['procurementId'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Invalid blockchain address');
                }));

            // markDocumentsAsFailed() logs a warning
            Log::shouldReceive('warning')->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $this->metadataArray,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class, 'Invalid blockchain address');
        });

        it('logs error when document metadata is missing required fields', function () {
            $invalidMetadata = [
                [
                    'document_type' => 'bid',
                    // Missing hash, file_key, file_size
                ],
            ];

            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            Log::shouldReceive('info')
                ->with('Generated stream key', Mockery::any())
                ->once();

            Log::shouldReceive('error')
                ->once()
                ->with('Failed to publish procurement documents asynchronously', Mockery::on(function ($context) {
                    return isset($context['error']) &&
                           str_contains($context['error'], 'Missing required metadata field');
                }));

            // markDocumentsAsFailed() logs a warning
            Log::shouldReceive('warning')->once();

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $invalidMetadata,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class, 'Missing required metadata field');
        });
    });

    describe('required metadata fields', function () {
        it('requires document_type field', function () {
            $metadata = [['hash' => 'h1', 'file_key' => 'f1', 'file_size' => 100]];

            $this->multichainService->shouldReceive('validateAddress')->andReturn(true);
            $this->streamKeyService->shouldReceive('generate')->andReturn($this->streamKey);
            Log::shouldReceive('info');

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $metadata,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class);
        });

        it('requires hash field', function () {
            $metadata = [['document_type' => 'bid', 'file_key' => 'f1', 'file_size' => 100]];

            $this->multichainService->shouldReceive('validateAddress')->andReturn(true);
            $this->streamKeyService->shouldReceive('generate')->andReturn($this->streamKey);
            Log::shouldReceive('info');

            $job = new PublishProcurementDocumentsJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                $this->status,
                $metadata,
                $this->userAddress
            );

            expect(fn () => $job->handle(
                $this->multichainService,
                $this->streamKeyService,
                $this->statusUpdaterService,
                $this->eventLoggerService
            ))->toThrow(\Exception::class);
        });
    });
});
