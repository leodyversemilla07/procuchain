<?php

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Models\ProcurementEvent;
use App\Services\BlockchainAuditTrailService;
use App\Services\IntegrityVerificationService;
use App\Services\Manager;
use App\Services\NormalizedTableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

it('detects unauthorized database modifications and reports exact blockchain differences', function () {
    $procurement = Procurement::create([
        'pr_number' => 'PR-PANEL-001',
        'title' => 'Tampered DB Title',
        'description' => 'Original Description',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'end_user' => 'BAC Office',
        'fund_source' => 'General Fund',
        'abc_amount' => 500000,
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-panel-txid-001',
    ]);

    $chainItem = [
        'txid' => 'metadata-panel-txid-001',
        'data' => [
            'json' => [
                'pr_number' => $procurement->pr_number,
                'title' => 'Original Blockchain Title',
                'description' => 'Original Description',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'BAC Office',
                'end_user' => 'BAC Office',
                'funding_source' => 'General Fund',
                'abc_amount' => '500000',
                'status' => 'draft',
            ],
        ],
    ];

    $this->mock(Manager::class, function ($mock) use ($procurement, $chainItem) {
        $mock->shouldReceive('liststreamkeyitems')
            ->with('procurement.metadata', $procurement->pr_number)
            ->andReturn([$chainItem]);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyPr($procurement->pr_number);
    $log = IntegrityAuditLog::where('stream_key', $procurement->pr_number)->firstOrFail();
    $report = app(IntegrityVerificationService::class)->generateReport($result['run_id']);

    expect($result['violations'])->toHaveKey(BreachTypeEnums::CONTENT_MISMATCH->value)
        ->and($log->violation_type)->toBe(BreachTypeEnums::CONTENT_MISMATCH->value)
        ->and($log->field_differences)->toContain([
            'field' => 'title',
            'old_value' => 'Original Blockchain Title',
            'new_value' => 'Tampered DB Title',
        ])
        ->and($report['summary']['total_violations'])->toBe(1)
        ->and($report['summary']['critical'])->toBe(1);
});

it('detects deleted mirror records that still exist on blockchain', function () {
    $procurement = Procurement::create([
        'pr_number' => 'PR-PANEL-002',
        'title' => 'Panel PR',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-panel-txid-002',
    ]);

    $metadataItem = [
        'txid' => 'metadata-panel-txid-002',
        'data' => ['json' => ['pr_number' => $procurement->pr_number, 'title' => 'Panel PR']],
    ];

    $deletedEventItem = [
        'txid' => 'event-deleted-on-db-txid',
        'data' => ['json' => [
            'pr_number' => $procurement->pr_number,
            'event_type' => 'document_upload',
            'category' => 'document',
            'severity' => 'info',
            'details' => 'Document uploaded',
            'stage' => 'procurement_initiation',
            'timestamp' => now()->toIso8601String(),
        ]],
    ];

    $this->mock(Manager::class, function ($mock) use ($metadataItem, $deletedEventItem) {
        $mock->shouldReceive('liststreamitems')
            ->with('procurement.metadata', false, 10000)
            ->andReturn([$metadataItem]);
        $mock->shouldReceive('liststreamitems')
            ->with('procurement.events', false, 10000)
            ->andReturn([$deletedEventItem]);
        $mock->shouldReceive('liststreamitems')->andReturn([])->byDefault();
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyAndRepair(false, 'panel-test');
    $log = IntegrityAuditLog::where('stream', 'procurement.events')->firstOrFail();

    expect($result['violations'])->toHaveKey(BreachTypeEnums::ROW_DELETED->value)
        ->and($log->violation_type)->toBe(BreachTypeEnums::ROW_DELETED->value)
        ->and($log->stream_key)->toBe($procurement->pr_number)
        ->and($log->txid)->toBe('event-deleted-on-db-txid')
        ->and(ProcurementEvent::where('txid', 'event-deleted-on-db-txid')->exists())->toBeFalse();
});

it('restores deleted records from trusted blockchain data and verifies repair before closing', function () {
    $auditLog = IntegrityAuditLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-PANEL-003',
        violationType: BreachTypeEnums::ROW_DELETED->value,
        txid: 'metadata-panel-txid-003',
        publishToChain: false,
    );

    $this->mock(Manager::class, function ($mock) {
        $mock->shouldReceive('liststreamitems')
            ->with('procurement.metadata', false, 10000)
            ->andReturn([
                [
                    'txid' => 'metadata-panel-txid-003',
                    'data' => ['json' => ['pr_number' => 'PR-PANEL-003', 'title' => 'Restored from Chain']],
                ],
            ]);
        $mock->shouldReceive('publish')->andReturn('recovery-audit-txid')->byDefault();
    });

    $this->mock(NormalizedTableSyncService::class, function ($mock) {
        $mock->shouldReceive('syncAll')->once()->andReturnUsing(function () {
            Procurement::create([
                'pr_number' => 'PR-PANEL-003',
                'title' => 'Restored from Chain',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => 'procurement_initiation',
                'current_status' => 'draft',
                'txid' => 'metadata-panel-txid-003',
            ]);

            return ['procurements' => 1];
        });
    });

    $result = app(IntegrityVerificationService::class)->restoreViolation($auditLog);
    $auditLog->refresh();

    expect($result['success'])->toBeTrue()
        ->and(Procurement::where('pr_number', 'PR-PANEL-003')->exists())->toBeTrue()
        ->and($auditLog->recovery_status)->toBe('restored')
        ->and($auditLog->recovery_result)->toHaveKeys(['restored_by', 'restored_at', 'sync_counts']);
});

it('publishes violation and recovery operations to the permanent blockchain audit trail', function () {
    $auditLog = IntegrityAuditLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-PANEL-004',
        violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
        fieldDifferences: [['field' => 'title', 'old_value' => 'Chain', 'new_value' => 'DB']],
        publishToChain: false,
    );

    $published = [];
    $this->mock(Manager::class, function ($mock) use (&$published) {
        $mock->shouldReceive('publish')->twice()->andReturnUsing(function (string $stream, string $key, array $payload) use (&$published) {
            $published[] = compact('stream', 'key', 'payload');

            return 'audit-chain-txid-'.count($published);
        });
    });

    $service = app(BlockchainAuditTrailService::class);
    $violationTxid = $service->publishViolation($auditLog);
    $recoveryTxid = $service->publishRecovery($auditLog, ['items_restored' => 1]);

    expect($violationTxid)->toBe('audit-chain-txid-1')
        ->and($recoveryTxid)->toBe('audit-chain-txid-2')
        ->and($published[0]['stream'])->toBe('integrity.violations')
        ->and($published[0]['payload']['json']['type'])->toBe('violation')
        ->and($published[1]['stream'])->toBe('integrity.violations')
        ->and($published[1]['payload']['json']['type'])->toBe('recovery')
        ->and($published[1]['payload']['json']['violation_id'])->toBe($auditLog->id);
});

it('deduplicates identical pending violations in audit log', function () {
    $first = IntegrityAuditLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    // Second identical violation should return the same record
    $second = IntegrityAuditLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    expect($first->id)->toBe($second->id)
        ->and(IntegrityAuditLog::where('stream_key', 'PR-DEDUP-001')->count())->toBe(1);

    // Different violation type should create a new record
    $third = IntegrityAuditLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachTypeEnums::ROW_DELETED->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    expect($third->id)->not->toBe($first->id)
        ->and(IntegrityAuditLog::where('stream_key', 'PR-DEDUP-001')->count())->toBe(2);
});
