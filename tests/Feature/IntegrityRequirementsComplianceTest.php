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

it('detects unauthorized records with tampered pr_number via txid lookup', function () {
    Procurement::create([
        'pr_number' => 'PR-TAMPERED-VALUE',
        'title' => 'Modified Title',
        'description' => 'Original Description',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'end_user' => 'BAC Office',
        'abc_amount' => 500000,
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-tampered-txid',
    ]);

    $originalChainData = [
        'pr_number' => 'PR-ORIGINAL-VALUE',
        'title' => 'Original Title',
        'description' => 'Original Description',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'end_user' => 'BAC Office',
        'funding_source' => 'General Fund',
        'abc_amount' => '500000',
        'status' => 'draft',
    ];

    $this->mock(Manager::class, function ($mock) use ($originalChainData) {
        $mock->shouldReceive('liststreamitems')
            ->andReturnUsing(function (string $stream) use ($originalChainData) {
                if ($stream === 'procurement.metadata') {
                    return [
                        [
                            'txid' => 'metadata-tampered-txid',
                            'data' => ['json' => $originalChainData],
                        ],
                    ];
                }

                return [];
            });
        $mock->shouldReceive('getrawtransaction')
            ->with('metadata-tampered-txid', 1)
            ->andReturn([
                'data' => [
                    [
                        'json' => $originalChainData,
                    ],
                ],
            ]);
        $mock->shouldReceive('getrawtransaction')
            ->andReturn([]);
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'tamper-test');

    $log = IntegrityAuditLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachTypeEnums::UNAUTHORIZED_RECORD->value)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->field_differences)->toContain([
        'field' => 'pr_number',
        'old_value' => 'PR-ORIGINAL-VALUE',
        'new_value' => 'PR-TAMPERED-VALUE',
    ]);
});

it('detects pr_number changed to another valid blockchain PR by comparing txid payload', function () {
    Procurement::create([
        'pr_number' => 'PR-VALID-B',
        'title' => 'PR A Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-txid-a',
    ]);

    $chainA = [
        'pr_number' => 'PR-VALID-A',
        'title' => 'PR A Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'status' => 'draft',
    ];
    $chainB = [
        'pr_number' => 'PR-VALID-B',
        'title' => 'PR B Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'status' => 'draft',
    ];

    $this->mock(Manager::class, function ($mock) use ($chainA, $chainB) {
        $mock->shouldReceive('liststreamitems')
            ->andReturnUsing(function (string $stream) use ($chainA, $chainB) {
                if ($stream === 'procurement.metadata') {
                    return [
                        ['txid' => 'metadata-txid-a', 'data' => ['json' => $chainA]],
                        ['txid' => 'metadata-txid-b', 'data' => ['json' => $chainB]],
                    ];
                }

                return [];
            });
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'valid-pr-swap-test');

    $matchingDiff = IntegrityAuditLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachTypeEnums::CONTENT_MISMATCH->value)
        ->get()
        ->flatMap(fn (IntegrityAuditLog $log): array => $log->field_differences ?? [])
        ->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'pr_number'
            && ($diff['old_value'] ?? null) === 'PR-VALID-A'
            && ($diff['new_value'] ?? null) === 'PR-VALID-B');

    expect($matchingDiff)->toBeTrue();
});

it('detects pr_number tampering even when the local hash is recomputed', function () {
    $procurement = Procurement::create([
        'pr_number' => 'PR-REHASHED-TAMPER',
        'title' => 'Original Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-rehashed-txid',
    ]);

    $procurement->refresh();

    $hashData = [];
    foreach (Procurement::getHashableFields() as $field) {
        $value = $procurement->{$field} ?? null;
        $hashData[$field] = is_string($value) && is_numeric($value) ? (float) $value : $value;
    }
    $currentHash = hash('sha256', json_encode($hashData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $procurement->forceFill(['data_hash' => $currentHash, 'blockchain_hash' => $currentHash])->save();

    $chainData = [
        'pr_number' => 'PR-ORIGINAL-REHASHED',
        'title' => 'Original Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'status' => 'draft',
    ];

    $this->mock(Manager::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')
            ->andReturnUsing(function (string $stream) use ($chainData) {
                if ($stream === 'procurement.metadata') {
                    return [['txid' => 'metadata-rehashed-txid', 'data' => ['json' => $chainData]]];
                }

                return [];
            });
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'rehashed-pr-tamper-test');

    $contentLog = IntegrityAuditLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachTypeEnums::CONTENT_MISMATCH->value)
        ->first();

    expect(IntegrityAuditLog::where('violation_type', BreachTypeEnums::HASH_MISMATCH->value)->count())->toBe(0);
    expect($contentLog)->not->toBeNull();
    expect($contentLog->field_differences)->toContain([
        'field' => 'pr_number',
        'old_value' => 'PR-ORIGINAL-REHASHED',
        'new_value' => 'PR-REHASHED-TAMPER',
    ]);
});

it('auto repairs a tampered pr_number back to the trusted blockchain PR', function () {
    Procurement::create([
        'pr_number' => 'PR-AUTO-REPAIR-TAMPERED',
        'title' => 'Original Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-auto-repair-txid',
    ]);

    $chainData = [
        'pr_number' => 'PR-AUTO-REPAIR-ORIGINAL',
        'title' => 'Original Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'status' => 'draft',
    ];

    $this->mock(Manager::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')
            ->andReturnUsing(function (string $stream) use ($chainData) {
                if ($stream === 'procurement.metadata') {
                    return [['txid' => 'metadata-auto-repair-txid', 'data' => ['json' => $chainData]]];
                }

                return [];
            });
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyAndRepair(true, 'auto-repair-pr-tamper-test');

    expect($result['restored'])->toBeGreaterThanOrEqual(1);
    expect(Procurement::where('pr_number', 'PR-AUTO-REPAIR-TAMPERED')->exists())->toBeFalse();
    expect(Procurement::where('pr_number', 'PR-AUTO-REPAIR-ORIGINAL')->exists())->toBeTrue();
    expect(IntegrityAuditLog::where('source', 'auto-repair-pr-tamper-test')->where('recovery_status', 'restored')->count())
        ->toBeGreaterThanOrEqual(1);
});
