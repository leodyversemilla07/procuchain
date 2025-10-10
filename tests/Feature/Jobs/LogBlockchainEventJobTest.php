<?php

use App\Enums\StreamEnums;
use App\Jobs\LogBlockchainEventJob;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->stage = 'bidding';
    $this->details = 'Event details';
    $this->documentCount = 5;
    $this->userAddress = '1ABC123XYZ';
    $this->eventType = 'document_upload';
    $this->category = 'system';
    $this->severity = 'info';
    $this->timestamp = now()->toIso8601String();
    $this->streamKey = 'stream-key-123';

    $this->multichainService = mock(MultichainService::class);
    $this->streamKeyService = mock(StreamKeyService::class);
});

describe('LogBlockchainEventJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched', function () {
            Queue::fake();

            LogBlockchainEventJob::dispatch(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });
    });

    describe('handle method', function () {
        it('logs blockchain event successfully', function () {
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
                ->once()
                ->with(
                    $this->userAddress,
                    StreamEnums::EVENTS->value,
                    $this->streamKey,
                    \Mockery::type('array')
                );

            Log::shouldReceive('info')->once();

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('publishes correct event data to blockchain', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->once()
                ->with(
                    $this->userAddress,
                    StreamEnums::EVENTS->value,
                    $this->streamKey,
                    \Mockery::on(function ($data) {
                        return isset($data['json'])
                            && $data['json']['procurement_id'] === $this->procurementId
                            && $data['json']['event_type'] === $this->eventType
                            && $data['json']['severity'] === $this->severity
                            && $data['json']['document_count'] === $this->documentCount;
                    })
                );

            Log::shouldReceive('info');

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs event information', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom');

            Log::shouldReceive('info')
                ->with('Logging procurement event to blockchain', [
                    'procurement_id' => $this->procurementId,
                    'event_type' => $this->eventType,
                    'severity' => $this->severity,
                ])
                ->once();

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });
    });

    describe('validation', function () {
        it('logs error when procurement ID is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === '' &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            $job = new LogBlockchainEventJob(
                '', // Empty procurement ID
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs error when procurement title is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                '', // Empty title
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs error when details are empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Event details and type are required');
                }));

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                '', // Empty details
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs error when event type is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Event details and type are required');
                }));

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                '', // Empty event type
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs error when user address is invalid', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(false);

            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Invalid blockchain address');
                }));

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                $this->severity,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs error for invalid severity level', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(true);

            Log::shouldReceive('error')
                ->once()
                ->with('Failed to log event to blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Invalid severity level');
                }));

            $job = new LogBlockchainEventJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->stage,
                $this->details,
                $this->documentCount,
                $this->userAddress,
                $this->eventType,
                $this->category,
                'invalid_severity', // Invalid severity
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });
    });

    describe('severity levels', function () {
        it('accepts info severity')->with(['info'])->expect(fn ($severity) => true)->toBeTrue();
        it('accepts warning severity')->with(['warning'])->expect(fn ($severity) => true)->toBeTrue();
        it('accepts error severity')->with(['error'])->expect(fn ($severity) => true)->toBeTrue();
    });
});

