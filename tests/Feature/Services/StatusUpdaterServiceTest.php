<?php

use App\Jobs\UpdateProcurementStatusJob;
use App\Services\StatusUpdaterService;
use Illuminate\Support\Facades\Queue;

describe('StatusUpdaterService', function () {
    beforeEach(function () {
        $this->service = new StatusUpdaterService;
    });

    describe('updateStatus', function () {
        it('dispatches UpdateProcurementStatusJob', function () {
            Queue::fake();

            $procurementId = 'PROC-2024-001';
            $procurementTitle = 'Road Construction';
            $status = 'published';
            $stage = 'bidding';
            $userAddress = '0x123abc';
            $timestamp = now()->toIso8601String();

            $this->service->updateStatus(
                $procurementId,
                $procurementTitle,
                $status,
                $stage,
                $userAddress,
                $timestamp
            );

            Queue::assertPushed(UpdateProcurementStatusJob::class);
        });

        it('dispatches job once per call', function () {
            Queue::fake();

            $this->service->updateStatus(
                'PROC-001',
                'Project',
                'published',
                'bidding',
                '0x123',
                now()->toIso8601String()
            );

            Queue::assertPushed(UpdateProcurementStatusJob::class, 1);
        });

        it('returns void', function () {
            Queue::fake();

            $result = $this->service->updateStatus(
                'PROC-001',
                'Project',
                'published',
                'bidding',
                '0x123',
                now()->toIso8601String()
            );

            expect($result)->toBeNull();
        });

        it('dispatches job with different statuses', function () {
            Queue::fake();

            $statuses = ['draft', 'published', 'awarded', 'completed'];

            foreach ($statuses as $status) {
                $this->service->updateStatus(
                    'PROC-001',
                    'Project',
                    $status,
                    'bidding',
                    '0x123',
                    now()->toIso8601String()
                );
            }

            Queue::assertPushed(UpdateProcurementStatusJob::class, 4);
        });

        it('dispatches job with different stages', function () {
            Queue::fake();

            $stages = ['preparation', 'bidding', 'evaluation', 'award'];

            foreach ($stages as $stage) {
                $this->service->updateStatus(
                    'PROC-001',
                    'Project',
                    'published',
                    $stage,
                    '0x123',
                    now()->toIso8601String()
                );
            }

            Queue::assertPushed(UpdateProcurementStatusJob::class, 4);
        });

        it('handles empty procurement ID', function () {
            Queue::fake();

            $this->service->updateStatus(
                '',
                'Project',
                'published',
                'bidding',
                '0x123',
                now()->toIso8601String()
            );

            Queue::assertPushed(UpdateProcurementStatusJob::class);
        });

        it('handles empty procurement title', function () {
            Queue::fake();

            $this->service->updateStatus(
                'PROC-001',
                '',
                'published',
                'bidding',
                '0x123',
                now()->toIso8601String()
            );

            Queue::assertPushed(UpdateProcurementStatusJob::class);
        });

        it('handles different timestamp formats', function () {
            Queue::fake();

            $timestamps = [
                now()->toIso8601String(),
                now()->format('Y-m-d H:i:s'),
                '2024-01-01T00:00:00Z',
            ];

            foreach ($timestamps as $timestamp) {
                $this->service->updateStatus(
                    'PROC-001',
                    'Project',
                    'published',
                    'bidding',
                    '0x123',
                    $timestamp
                );
            }

            Queue::assertPushed(UpdateProcurementStatusJob::class, 3);
        });
    });
});

