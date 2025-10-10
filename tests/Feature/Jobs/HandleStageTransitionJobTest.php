<?php

use App\Enums\StreamEnums;
use App\Jobs\HandleStageTransitionJob;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->fromStatus = 'draft';
    $this->toStatus = 'published';
    $this->fromStage = 'preparation';
    $this->toStage = 'bidding';
    $this->userAddress = '1ABC123XYZ';
    $this->details = 'Stage transition details';
    $this->streamKey = 'stream-key-123';

    $this->multichainService = mock(MultichainService::class);
    $this->streamKeyService = mock(StreamKeyService::class);
    $this->eventLoggerService = mock(BlockchainEventLoggerService::class);
});

describe('HandleStageTransitionJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched', function () {
            Queue::fake();

            HandleStageTransitionJob::dispatch(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            Queue::assertPushed(HandleStageTransitionJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });
    });

    describe('handle method', function () {
        it('processes stage transition successfully', function () {
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
                    StreamEnums::STATUS->value,
                    $this->streamKey,
                    \Mockery::type('array')
                );

            $this->eventLoggerService
                ->shouldReceive('logEvent')
                ->once();

            Log::shouldReceive('info')->once();

            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService);
        });

        it('publishes correct status data to blockchain', function () {
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
                    StreamEnums::STATUS->value,
                    $this->streamKey,
                    \Mockery::on(function ($data) {
                        return isset($data['json']['procurement_id'])
                            && $data['json']['procurement_id'] === $this->procurementId
                            && $data['json']['previous_stage'] === $this->fromStage
                            && $data['json']['stage'] === $this->toStage
                            && $data['json']['previous_status'] === $this->fromStatus
                            && $data['json']['current_status'] === $this->toStatus;
                    })
                );

            $this->eventLoggerService
                ->shouldReceive('logEvent');

            Log::shouldReceive('info');

            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService);
        });

        it('logs stage transition information', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom');

            $this->eventLoggerService
                ->shouldReceive('logEvent');

            Log::shouldReceive('info')
                ->with('Processing stage transition (job)', [
                    'procurement_id' => $this->procurementId,
                    'from_stage' => $this->fromStage,
                    'to_stage' => $this->toStage,
                ])
                ->once();

            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService);
        });
    });

    describe('validation', function () {
        it('throws exception when procurement ID is empty', function () {
            $job = new HandleStageTransitionJob(
                '', // Empty procurement ID
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService))
                ->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('throws exception when procurement title is empty', function () {
            $job = new HandleStageTransitionJob(
                $this->procurementId,
                '', // Empty title
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService))
                ->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('throws exception when fromStage is empty', function () {
            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                '', // Empty fromStage
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService))
                ->toThrow(\Exception::class, 'From and to stages are required');
        });

        it('throws exception when toStage is empty', function () {
            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                '', // Empty toStage
                $this->userAddress,
                $this->details
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService))
                ->toThrow(\Exception::class, 'From and to stages are required');
        });

        it('throws exception when user address is invalid', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(false);

            $job = new HandleStageTransitionJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->fromStatus,
                $this->toStatus,
                $this->fromStage,
                $this->toStage,
                $this->userAddress,
                $this->details
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService, $this->eventLoggerService))
                ->toThrow(\Exception::class, "Invalid blockchain address: {$this->userAddress}");
        });
    });
});

