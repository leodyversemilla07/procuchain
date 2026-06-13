<?php

use App\Enums\Stream;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
use App\Services\BlockchainRecordSyncService;
use App\Services\BlockchainRpcClient;
use App\Services\NormalizedTableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRepairTestProcurement(string $prNumber): Procurement
{
    return Procurement::create([
        'pr_number' => $prNumber,
        'title' => "Repair test {$prNumber}",
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
    ]);
}

it('repairs only the requested PR and preserves unrelated normalized records', function () {
    $target = createRepairTestProcurement('PR-2026-100-0001');
    $unrelated = createRepairTestProcurement('PR-2026-200-0001');

    ProcurementStage::create([
        'procurement_id' => $target->id,
        'stage' => 'procurement_initiation',
        'status' => 'draft',
        'entered_at' => now(),
        'txid' => 'target-stage-stale',
    ]);
    ProcurementDocument::create([
        'procurement_id' => $target->id,
        'document_type' => 'purchase_request',
        'stage' => 'procurement_initiation',
        'filename' => 'target.pdf',
        'file_key' => 'target-File-key',
        'hash' => 'target-hash',
        'uploaded_by' => 'tester',
        'uploaded_at' => now(),
        'txid' => 'target-document-stale',
    ]);
    ProcurementEvent::create([
        'procurement_id' => $target->id,
        'event_type' => 'created',
        'category' => 'procurement',
        'severity' => 'info',
        'details' => 'Target event',
        'stage' => 'procurement_initiation',
        'occurred_at' => now(),
        'txid' => 'target-event-stale',
    ]);

    ProcurementStage::create([
        'procurement_id' => $unrelated->id,
        'stage' => 'procurement_initiation',
        'status' => 'draft',
        'entered_at' => now(),
        'txid' => 'unrelated-stage-stale',
    ]);
    ProcurementDocument::create([
        'procurement_id' => $unrelated->id,
        'document_type' => 'purchase_request',
        'stage' => 'procurement_initiation',
        'filename' => 'unrelated.pdf',
        'file_key' => 'unrelated-File-key',
        'hash' => 'unrelated-hash',
        'uploaded_by' => 'tester',
        'uploaded_at' => now(),
        'txid' => 'unrelated-document-stale',
    ]);
    ProcurementEvent::create([
        'procurement_id' => $unrelated->id,
        'event_type' => 'created',
        'category' => 'procurement',
        'severity' => 'info',
        'details' => 'Unrelated event',
        'stage' => 'procurement_initiation',
        'occurred_at' => now(),
        'txid' => 'unrelated-event-stale',
    ]);

    $BlockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::METADATA->value, 'PR-2026-100-0001', false, 10000)
        ->once()
        ->andReturn([
            ['txid' => 'target-metadata', 'data' => ['json' => ['pr_number' => 'PR-2026-100-0001']]],
        ]);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::STATUS->value, 'PR-2026-100-0001', false, 10000)
        ->once()
        ->andReturn([]);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::DOCUMENTS->value, 'PR-2026-100-0001', false, 10000)
        ->once()
        ->andReturn([]);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::EVENTS->value, 'PR-2026-100-0001', false, 10000)
        ->once()
        ->andReturn([]);
    app()->instance(BlockchainRpcClient::class, $BlockchainRpcClient);

    $sync = Mockery::mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->once()
        ->with('PR-2026-100-0001')
        ->andReturn(['metadata' => 0, 'stages' => 0, 'documents' => 0, 'events' => 0]);

    $service = new BlockchainRecordSyncService($sync);

    expect($service->repairFromChain('PR-2026-100-0001'))->toBe(3);

    $this->assertDatabaseMissing('procurement_stages', ['txid' => 'target-stage-stale']);
    $this->assertDatabaseMissing('procurement_documents', ['txid' => 'target-document-stale']);
    $this->assertDatabaseMissing('procurement_events', ['txid' => 'target-event-stale']);

    $this->assertDatabaseHas('procurements', ['pr_number' => 'PR-2026-200-0001']);
    $this->assertDatabaseHas('procurement_stages', ['txid' => 'unrelated-stage-stale']);
    $this->assertDatabaseHas('procurement_documents', ['txid' => 'unrelated-document-stale']);
    $this->assertDatabaseHas('procurement_events', ['txid' => 'unrelated-event-stale']);
});

it('removes only the requested procurement when that PR is absent from chain', function () {
    createRepairTestProcurement('PR-2026-300-0001');
    createRepairTestProcurement('PR-2026-400-0001');

    $BlockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::METADATA->value, 'PR-2026-300-0001', false, 10000)
        ->once()
        ->andReturn([]);
    app()->instance(BlockchainRpcClient::class, $BlockchainRpcClient);

    $sync = Mockery::mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->once()
        ->with('PR-2026-300-0001')
        ->andReturn(['metadata' => 0, 'stages' => 0, 'documents' => 0, 'events' => 0]);

    $service = new BlockchainRecordSyncService($sync);

    expect($service->repairFromChain('PR-2026-300-0001'))->toBe(1);

    $this->assertDatabaseMissing('procurements', ['pr_number' => 'PR-2026-300-0001']);
    $this->assertDatabaseHas('procurements', ['pr_number' => 'PR-2026-400-0001']);
});

it('does not delete records when blockchain metadata cannot be read', function () {
    createRepairTestProcurement('PR-2026-500-0001');

    $BlockchainRpcClient = Mockery::mock(BlockchainRpcClient::class);
    $BlockchainRpcClient->shouldReceive('liststreamkeyitems')
        ->with(Stream::METADATA->value, 'PR-2026-500-0001', false, 10000)
        ->once()
        ->andThrow(new RuntimeException('RPC unavailable'));
    app()->instance(BlockchainRpcClient::class, $BlockchainRpcClient);

    $sync = Mockery::mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->once()
        ->with('PR-2026-500-0001')
        ->andReturn(['metadata' => 0, 'stages' => 0, 'documents' => 0, 'events' => 0]);

    $service = new BlockchainRecordSyncService($sync);

    expect($service->repairFromChain('PR-2026-500-0001'))->toBe(0);

    $this->assertDatabaseHas('procurements', ['pr_number' => 'PR-2026-500-0001']);
});
