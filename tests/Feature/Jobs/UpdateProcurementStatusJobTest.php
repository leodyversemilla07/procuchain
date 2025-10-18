<?php

use App\Enums\StreamEnums;
use App\Jobs\UpdateProcurementStatusJob;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->status = 'published';
    $this->stage = 'bidding';
    $this->userAddress = '1ABC123XYZ';
    $this->timestamp = now()->toIso8601String();
    $this->streamKey = 'stream-key-123';

    $this->multichainService = mock(MultichainService::class);
    $this->streamKeyService = mock(StreamKeyService::class);
});

describe('UpdateProcurementStatusJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched', function () {
            Queue::fake();

            UpdateProcurementStatusJob::dispatch(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            Queue::assertPushed(UpdateProcurementStatusJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });
    });

    describe('handle method', function () {
        it('updates procurement status successfully', function () {
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

            Log::shouldReceive('info')->twice(); // Before and after publish

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
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
                        return isset($data['json'])
                            && $data['json']['procurement_id'] === $this->procurementId
                            && $data['json']['current_status'] === $this->status
                            && $data['json']['stage'] === $this->stage
                            && $data['json']['timestamp'] === $this->timestamp;
                    })
                );

            Log::shouldReceive('info');

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });

        it('logs status update information', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom');

            Log::shouldReceive('info')
                ->with('Updating procurement status on blockchain', [
                    'procurement_id' => $this->procurementId,
                    'status' => $this->status,
                    'stage' => $this->stage,
                ])
                ->once();

            Log::shouldReceive('info')
                ->with('Procurement status updated successfully', \Mockery::type('array'))
                ->once();

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            $job->handle($this->multichainService, $this->streamKeyService);
        });
    });

    describe('validation', function () {
        it('logs error when procurement ID is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to update status on blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === '' &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            $job = new UpdateProcurementStatusJob(
                '', // Empty procurement ID
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('logs error when procurement title is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to update status on blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Procurement ID and title are required');
                }));

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                '', // Empty title
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Procurement ID and title are required');
        });

        it('logs error when status is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to update status on blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Status and stage are required');
                }));

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                '', // Empty status
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Status and stage are required');
        });

        it('logs error when stage is empty', function () {
            Log::shouldReceive('error')
                ->once()
                ->with('Failed to update status on blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Status and stage are required');
                }));

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                '', // Empty stage
                $this->userAddress,
                $this->timestamp
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Status and stage are required');
        });

        it('logs error when user address is invalid', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->with($this->userAddress)
                ->andReturn(false);

            Log::shouldReceive('error')
                ->once()
                ->with('Failed to update status on blockchain', Mockery::on(function ($context) {
                    return $context['procurement_id'] === $this->procurementId &&
                           isset($context['error']) &&
                           str_contains($context['error'], 'Invalid blockchain address');
                }));

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Invalid blockchain address');
        });
    });

    describe('error handling', function () {
        it('logs error when blockchain publish fails', function () {
            $this->multichainService
                ->shouldReceive('validateAddress')
                ->andReturn(true);

            $this->streamKeyService
                ->shouldReceive('generate')
                ->andReturn($this->streamKey);

            $this->multichainService
                ->shouldReceive('publishFrom')
                ->andThrow(new \Exception('Blockchain connection failed'));

            Log::shouldReceive('info')->once(); // Initial log
            Log::shouldReceive('error')
                ->with('Failed to update status on blockchain', [
                    'procurement_id' => $this->procurementId,
                    'error' => 'Blockchain connection failed',
                ])
                ->once();

            $job = new UpdateProcurementStatusJob(
                $this->procurementId,
                $this->procurementTitle,
                $this->status,
                $this->stage,
                $this->userAddress,
                $this->timestamp
            );

            // Job catches exception, logs it, then rethrows
            expect(fn () => $job->handle($this->multichainService, $this->streamKeyService))
                ->toThrow(\Exception::class, 'Blockchain connection failed');
        });
    });
});
