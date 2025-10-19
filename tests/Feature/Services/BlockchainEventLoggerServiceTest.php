<?php

use App\Jobs\LogBlockchainEventJob;
use App\Services\BlockchainEventLoggerService;
use Illuminate\Support\Facades\Queue;

describe('BlockchainEventLoggerService', function () {
    beforeEach(function () {
        $this->service = new BlockchainEventLoggerService;
    });

    describe('logEvent', function () {
        it('dispatches LogBlockchainEventJob', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-2024-001',
                'Road Construction',
                'bidding',
                'Documents published',
                3,
                '0x123abc',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });

        it('dispatches job once per call', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-001',
                'Project',
                'bidding',
                'Event details',
                1,
                '0x123',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class, 1);
        });

        it('returns void', function () {
            Queue::fake();

            $result = $this->service->logEvent(
                'PROC-001',
                'Project',
                'bidding',
                'Details',
                1,
                '0x123',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            expect($result)->toBeNull();
        });

        it('handles zero document count', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-001',
                'Project',
                'preparation',
                'Stage transition',
                0,
                '0x123',
                'phase_transition',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });

        it('handles multiple document count', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-001',
                'Project',
                'bidding',
                'Documents published',
                100,
                '0x123',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });

        it('handles different event types', function () {
            Queue::fake();

            $eventTypes = ['publication', 'phase_transition', 'decision', 'document_upload'];

            foreach ($eventTypes as $eventType) {
                $this->service->logEvent(
                    'PROC-001',
                    'Project',
                    'bidding',
                    'Details',
                    1,
                    '0x123',
                    $eventType,
                    'procurement',
                    'info',
                    now()->toIso8601String()
                );
            }

            Queue::assertPushed(LogBlockchainEventJob::class, 4);
        });

        it('handles different severity levels', function () {
            Queue::fake();

            $severities = ['info', 'warning', 'error', 'critical'];

            foreach ($severities as $severity) {
                $this->service->logEvent(
                    'PROC-001',
                    'Project',
                    'bidding',
                    'Details',
                    1,
                    '0x123',
                    'publication',
                    'procurement',
                    $severity,
                    now()->toIso8601String()
                );
            }

            Queue::assertPushed(LogBlockchainEventJob::class, 4);
        });

        it('handles different categories', function () {
            Queue::fake();

            $categories = ['procurement', 'bidding', 'evaluation', 'award'];

            foreach ($categories as $category) {
                $this->service->logEvent(
                    'PROC-001',
                    'Project',
                    'bidding',
                    'Details',
                    1,
                    '0x123',
                    'publication',
                    $category,
                    'info',
                    now()->toIso8601String()
                );
            }

            Queue::assertPushed(LogBlockchainEventJob::class, 4);
        });

        it('handles empty details', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-001',
                'Project',
                'bidding',
                '',
                1,
                '0x123',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });

        it('handles long details', function () {
            Queue::fake();

            $this->service->logEvent(
                'PROC-001',
                'Project',
                'bidding',
                str_repeat('Very long details. ', 100),
                1,
                '0x123',
                'publication',
                'procurement',
                'info',
                now()->toIso8601String()
            );

            Queue::assertPushed(LogBlockchainEventJob::class);
        });
    });
});
