<?php

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Services\Manager;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

it('publishes status and event in single atomic batch transaction', function () {
    // Mock the Manager service
    $mockManager = mock(Manager::class);

    // Expect publishmulti to be called once with correct parameters
    $mockManager->shouldReceive('publishmulti')
        ->once()
        ->withArgs(function ($stream, $items) {
            // Verify stream is correct
            expect($stream)->toBe('procurement.status');

            // Verify we have 2 items (status + event)
            expect($items)->toHaveCount(2);

            // Verify status item structure
            expect($items[0])->toHaveKeys(['key', 'data', 'for']);
            expect($items[0]['key'])->toBe('PR-2024-001-0001');
            expect($items[0]['for'])->toBe('procurement.status');
            expect($items[0]['data']['json'])->toHaveKeys([
                'pr_number',
                'procurement_title',
                'stage',
                'current_status',
                'user_address',
                'timestamp',
            ]);

            // Verify event item structure
            expect($items[1])->toHaveKeys(['key', 'data', 'for']);
            expect($items[1]['for'])->toBe('procurement.events');
            expect($items[1]['data']['json'])->toHaveKeys([
                'pr_number',
                'procurement_title',
                'event_type',
                'category',
                'details',
            ]);

            return true;
        })
        ->andReturn('mock-txid-12345');

    // Create orchestrator with mocked dependencies
    $orchestrator = new ProcurementOrchestrator(
        documentPublisher: mock(DocumentPublisher::class),
        statusPublisher: mock(StatusPublisher::class),
        eventPublisher: mock(EventPublisher::class),
    );

    // Execute batch publish
    $result = $orchestrator->publishStatusWithEventBatch(
        prNumber: 'PR-2024-001-0001',
        procurementTitle: 'Test Procurement',
        stage: StageEnums::PROCUREMENT_INITIATION,
        currentStatus: StatusEnums::PROCUREMENT_INITIATED,
        userAddress: '1ABC123xyz',
        eventData: [
            'event_type' => 'status_change',
            'category' => 'workflow',
            'details' => 'Status updated to INITIATED',
        ],
    );

    // Verify result structure
    expect($result)->toHaveKeys([
        'success',
        'pr_number',
        'txid',
        'items_published',
        'duration_ms',
        'performance_improvement',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['pr_number'])->toBe('PR-2024-001-0001');
    expect($result['txid'])->toBe('mock-txid-12345');
    expect($result['items_published'])->toBe(2);
});

it('publishes only status when no event data provided', function () {
    $mockManager = mock(Manager::class);

    // Expect publishmulti with only 1 item (status, no event)
    $mockManager->shouldReceive('publishmulti')
        ->once()
        ->withArgs(function ($stream, $items) {
            expect($items)->toHaveCount(1);
            expect($items[0]['for'])->toBe('procurement.status');

            return true;
        })
        ->andReturn('mock-txid-67890');

    $orchestrator = new ProcurementOrchestrator(
        mock(DocumentPublisher::class),
        mock(StatusPublisher::class),
        mock(EventPublisher::class)
    );

    $result = $orchestrator->publishStatusWithEventBatch(
        prNumber: 'PR-2024-002-0001',
        procurementTitle: 'Another Test',
        stage: StageEnums::BIDDING_DOCUMENTS,
        currentStatus: StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        userAddress: '1XYZ789abc',
        eventData: null, // No event
    );

    expect($result['success'])->toBeTrue();
    expect($result['items_published'])->toBe(1);
});

it('includes previous status when provided', function () {
    $mockManager = mock(Manager::class);

    $mockManager->shouldReceive('publishmulti')
        ->once()
        ->withArgs(function ($stream, $items) {
            // Verify previous_status is included
            expect($items[0]['data']['json'])->toHaveKey('previous_status');
            expect($items[0]['data']['json']['previous_status'])->toBe(StatusEnums::BIDS_OPENED->value);

            return true;
        })
        ->andReturn('mock-txid-11111');

    $orchestrator = new ProcurementOrchestrator(
        mock(DocumentPublisher::class),
        mock(StatusPublisher::class),
        mock(EventPublisher::class)
    );

    $result = $orchestrator->publishStatusWithEventBatch(
        prNumber: 'PR-2024-003-0001',
        procurementTitle: 'Status Transition Test',
        stage: StageEnums::BID_EVALUATION,
        currentStatus: StatusEnums::BIDS_EVALUATED,
        userAddress: '1TEST123',
        previousStatus: StatusEnums::BIDS_OPENED,
    );

    expect($result['success'])->toBeTrue();
});

it('logs performance metrics for batch operations', function () {
    Log::spy();

    $mockManager = mock(Manager::class);
    $mockManager->shouldReceive('publishmulti')
        ->once()
        ->andReturn('mock-txid-metrics');

    $orchestrator = new ProcurementOrchestrator(
        mock(DocumentPublisher::class),
        mock(StatusPublisher::class),
        mock(EventPublisher::class)
    );

    $orchestrator->publishStatusWithEventBatch(
        prNumber: 'PR-2024-004-0001',
        procurementTitle: 'Performance Test',
        stage: StageEnums::PROCUREMENT_INITIATION,
        currentStatus: StatusEnums::PROCUREMENT_INITIATED,
        userAddress: '1PERF123',
    );

    // Verify performance logging
    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'Orchestrator: Batch publish successful'
                && isset($context['duration_ms'])
                && isset($context['performance_improvement']);
        });
});
