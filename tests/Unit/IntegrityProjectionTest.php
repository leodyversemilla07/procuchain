<?php

use App\Services\BlockchainRpcClient;
use App\Services\Integrity\BlockchainPayloadProjector;
use App\Services\Integrity\BlockchainVerificationIndex;
use App\Services\Integrity\IntegrityComparator;

it('projects procurement metadata blockchain payloads to database mirror fields', function () {
    $projected = app(BlockchainPayloadProjector::class)->projectForTable([
        'funding_source' => 'General Fund',
        'status' => 'procurement_initiated',
        'created_at' => '2026-05-19T21:37:15+08:00',
    ], 'procurements');

    expect($projected['fund_source'])->toBe('General Fund')
        ->and($projected)->not->toHaveKey('current_status')
        ->and($projected)->not->toHaveKey('status')
        ->and($projected['initiated_at'])->toBe('2026-05-19 21:37:15');
});

it('projects document blockchain payloads to database mirror fields', function () {
    $projected = app(BlockchainPayloadProjector::class)->projectForTable([
        'file_name' => 'DOA.pdf',
        'timestamp' => '2026-05-19T21:38:21+08:00',
    ], 'procurement_documents');

    expect($projected['filename'])->toBe('DOA.pdf')
        ->and($projected['uploaded_at'])->toBe('2026-05-19 21:38:21');
});

it('projects status and event timestamps to their database mirror fields', function () {
    $projector = app(BlockchainPayloadProjector::class);

    $stage = $projector->projectForTable([
        'current_status' => 'procurement_submitted',
        'timestamp' => '2026-05-27T23:00:56+08:00',
    ], 'procurement_stages');

    $event = $projector->projectForTable([
        'timestamp' => '2026-05-27T23:00:56+08:00',
    ], 'procurement_events');

    expect($stage['status'])->toBe('procurement_submitted')
        ->and($stage['entered_at'])->toBe('2026-05-27 23:00:56')
        ->and($event['occurred_at'])->toBe('2026-05-27 23:00:56');
});

it('indexes blockchain stream items by txid and pr number', function () {
    $blockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $blockchainRpcClient->shouldReceive('liststreamitems')
        ->once()
        ->with('procurement.events', false, 10000)
        ->andReturn([
            [
                'txid' => 'tx-1',
                'data' => ['json' => ['pr_number' => 'PR-1', 'timestamp' => '2026-05-27T23:00:56+08:00']],
                'keys' => ['PR-1'],
            ],
            [
                'txid' => 'tx-2',
                'data' => ['json' => ['pr_number' => 'PR-1', 'timestamp' => '2026-05-27T23:01:56+08:00']],
                'keys' => ['PR-1'],
            ],
        ]);

    $index = new BlockchainVerificationIndex($blockchainRpcClient);
    $index->loadStream('procurement.events');

    expect($index->txids('procurement.events'))->toBe(['tx-1', 'tx-2'])
        ->and($index->jsonByTxid('procurement.events', 'tx-1')['timestamp'])->toBe('2026-05-27T23:00:56+08:00')
        ->and($index->latestJsonByPrNumber('procurement.events', 'PR-1')['timestamp'])->toBe('2026-05-27T23:01:56+08:00');
});

it('compares only projected blockchain counterparts and ignores db-only mirror fields', function () {
    $diffs = app(IntegrityComparator::class)->diff(
        [
            'title' => 'Modified',
            'status' => 'approved',
            'procurement_id' => 9,
            'db_only_field' => 'local',
        ],
        [
            'title' => 'Original',
            'status' => 'pending',
            'procurement_id' => 9,
        ],
    );

    expect($diffs)->toHaveCount(2)
        ->and($diffs[0])->toBe(['field' => 'title', 'old_value' => 'Original', 'new_value' => 'Modified'])
        ->and($diffs[1])->toBe(['field' => 'status', 'old_value' => 'pending', 'new_value' => 'approved']);
});
