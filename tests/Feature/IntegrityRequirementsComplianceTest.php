<?php

use App\Enums\BreachType;
use App\Models\File;
use App\Models\IntegrityViolationLog;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Services\BlockchainAuditTrailService;
use App\Services\BlockchainRpcClient;
use App\Services\IntegrityVerificationService;
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($procurement, $chainItem) {
        $mock->shouldReceive('liststreamkeyitems')
            ->with('procurement.metadata', $procurement->pr_number)
            ->andReturn([$chainItem]);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyPr($procurement->pr_number);
    $log = IntegrityViolationLog::where('stream_key', $procurement->pr_number)->firstOrFail();
    $report = app(IntegrityVerificationService::class)->generateReport($result['run_id']);

    expect($result['violations'])->toHaveKey(BreachType::CONTENT_MISMATCH->value)
        ->and($log->violation_type)->toBe(BreachType::CONTENT_MISMATCH->value)
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($metadataItem, $deletedEventItem) {
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
    $log = IntegrityViolationLog::where('stream', 'procurement.events')->firstOrFail();

    expect($result['violations'])->toHaveKey(BreachType::ROW_DELETED->value)
        ->and($log->violation_type)->toBe(BreachType::ROW_DELETED->value)
        ->and($log->stream_key)->toBe($procurement->pr_number)
        ->and($log->txid)->toBe('event-deleted-on-db-txid')
        ->and(ProcurementEvent::where('txid', 'event-deleted-on-db-txid')->exists())->toBeFalse();
});

it('restores deleted records from trusted blockchain data and verifies repair before closing', function () {
    $auditLog = IntegrityViolationLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-PANEL-003',
        violationType: BreachType::ROW_DELETED->value,
        txid: 'metadata-panel-txid-003',
        publishToChain: false,
    );

    $this->mock(BlockchainRpcClient::class, function ($mock) {
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
    $auditLog = IntegrityViolationLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-PANEL-004',
        violationType: BreachType::CONTENT_MISMATCH->value,
        fieldDifferences: [['field' => 'title', 'old_value' => 'Chain', 'new_value' => 'DB']],
        publishToChain: false,
    );

    $published = [];
    $this->mock(BlockchainRpcClient::class, function ($mock) use (&$published) {
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
    $first = IntegrityViolationLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachType::CONTENT_MISMATCH->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    // Second identical violation should return the same record
    $second = IntegrityViolationLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachType::CONTENT_MISMATCH->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    expect($first->id)->toBe($second->id)
        ->and(IntegrityViolationLog::where('stream_key', 'PR-DEDUP-001')->count())->toBe(1);

    // Different violation type should create a new record
    $third = IntegrityViolationLog::recordViolation(
        stream: 'procurement.metadata',
        streamKey: 'PR-DEDUP-001',
        violationType: BreachType::ROW_DELETED->value,
        txid: 'dedup-txid-001',
        publishToChain: false,
    );

    expect($third->id)->not->toBe($first->id)
        ->and(IntegrityViolationLog::where('stream_key', 'PR-DEDUP-001')->count())->toBe(2);
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($originalChainData) {
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

    $log = IntegrityViolationLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachType::UNAUTHORIZED_RECORD->value)
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainA, $chainB) {
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

    $matchingDiff = IntegrityViolationLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachType::CONTENT_MISMATCH->value)
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? [])
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
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

    $contentLog = IntegrityViolationLog::where('stream', 'procurement.metadata')
        ->where('violation_type', BreachType::CONTENT_MISMATCH->value)
        ->first();

    expect(IntegrityViolationLog::where('violation_type', BreachType::HASH_MISMATCH->value)->count())->toBe(0);
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

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
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
    expect(IntegrityViolationLog::where('source', 'auto-repair-pr-tamper-test')->where('recovery_status', 'restored')->count())
        ->toBeGreaterThanOrEqual(1);
});

it('detects procurement txid removed from a blockchain-backed row', function () {
    Procurement::create([
        'pr_number' => 'PR-TXID-NULL',
        'title' => 'Original Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => null,
    ]);

    $chainData = ['pr_number' => 'PR-TXID-NULL', 'title' => 'Original Title', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [['txid' => 'metadata-null-original-txid', 'data' => ['json' => $chainData]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'missing-txid-test');

    expect(IntegrityViolationLog::where('source', 'missing-txid-test')
        ->where('stream', 'procurement.metadata')
        ->where('violation_type', BreachType::UNAUTHORIZED_RECORD->value)
        ->exists())->toBeTrue();
});

it('detects procurement txid swapped to another valid PR txid', function () {
    Procurement::create([
        'pr_number' => 'PR-TXID-SWAP-A',
        'title' => 'A Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'metadata-txid-b',
    ]);

    $chainA = ['pr_number' => 'PR-TXID-SWAP-A', 'title' => 'A Title', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];
    $chainB = ['pr_number' => 'PR-TXID-SWAP-B', 'title' => 'B Title', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainA, $chainB) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [
                ['txid' => 'metadata-txid-a', 'data' => ['json' => $chainA]],
                ['txid' => 'metadata-txid-b', 'data' => ['json' => $chainB]],
            ]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'txid-swap-test');

    $matchingDiff = IntegrityViolationLog::where('source', 'txid-swap-test')
        ->where('stream', 'procurement.metadata')
        ->where('violation_type', BreachType::CONTENT_MISMATCH->value)
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? [])
        ->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'pr_number'
            && ($diff['old_value'] ?? null) === 'PR-TXID-SWAP-B'
            && ($diff['new_value'] ?? null) === 'PR-TXID-SWAP-A');

    expect($matchingDiff)->toBeTrue();
});

it('detects child row procurement_id moved to another procurement', function () {
    $original = Procurement::create(['pr_number' => 'PR-CHILD-ORIGINAL', 'title' => 'Original PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'current_stage' => 'procurement_initiation', 'current_status' => 'draft']);
    $other = Procurement::create(['pr_number' => 'PR-CHILD-OTHER', 'title' => 'Other PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'current_stage' => 'procurement_initiation', 'current_status' => 'draft']);

    ProcurementEvent::create([
        'procurement_id' => $other->id,
        'event_type' => 'document_upload',
        'category' => 'document',
        'severity' => 'info',
        'details' => 'Document uploaded',
        'stage' => 'procurement_initiation',
        'txid' => 'event-original-txid',
        'occurred_at' => now(),
    ]);

    $chainEvent = ['pr_number' => $original->pr_number, 'event_type' => 'document_upload', 'category' => 'document', 'severity' => 'info', 'details' => 'Document uploaded', 'stage' => 'procurement_initiation', 'timestamp' => now()->toIso8601String()];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainEvent) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.events'
            ? [['txid' => 'event-original-txid', 'data' => ['json' => $chainEvent]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'child-procurement-id-test');

    $expectedDiff = IntegrityViolationLog::where('source', 'child-procurement-id-test')
        ->where('stream', 'procurement.events')
        ->where('violation_type', BreachType::CONTENT_MISMATCH->value)
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? [])
        ->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'procurement_id'
            && ($diff['old_value'] ?? null) === $original->id
            && ($diff['new_value'] ?? null) === $other->id);

    expect($expectedDiff)->toBeTrue();
});

it('detects tampered procurement document hash', function () {
    $procurement = Procurement::create(['pr_number' => 'PR-DOC-HASH', 'title' => 'Document Hash PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'current_stage' => 'procurement_initiation', 'current_status' => 'draft']);

    ProcurementDocument::create([
        'procurement_id' => $procurement->id,
        'document_type' => 'purchase_request',
        'stage' => 'procurement_initiation',
        'filename' => 'purchase-request.pdf',
        'file_key' => 'PR-DOC-HASH/purchase-request.pdf',
        'hash' => 'tampered-hash',
        'uploaded_by' => 'Alice',
        'txid' => 'document-hash-txid',
        'uploaded_at' => now(),
    ]);

    $chainDocument = ['pr_number' => $procurement->pr_number, 'document_type' => 'purchase_request', 'stage' => 'procurement_initiation', 'file_name' => 'purchase-request.pdf', 'file_key' => 'PR-DOC-HASH/purchase-request.pdf', 'hash' => 'original-hash', 'uploaded_by' => 'Alice', 'timestamp' => now()->toIso8601String()];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainDocument) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.documents'
            ? [['txid' => 'document-hash-txid', 'data' => ['json' => $chainDocument]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'document-hash-test');

    $hashDiff = IntegrityViolationLog::where('source', 'document-hash-test')
        ->where('stream', 'procurement.documents')
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? [])
        ->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'hash'
            && ($diff['old_value'] ?? null) === 'original-hash'
            && ($diff['new_value'] ?? null) === 'tampered-hash');

    expect($hashDiff)->toBeTrue();
});

it('restores a hard-deleted procurement from blockchain metadata', function () {
    $chainData = ['pr_number' => 'PR-HARD-DELETED', 'title' => 'Restored Hard Deleted PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [['txid' => 'hard-deleted-txid', 'data' => ['json' => $chainData]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyPr('PR-HARD-DELETED', true, 'hard-delete-restore-test');

    expect($result['violations'])->toHaveKey(BreachType::ROW_DELETED->value);
    expect($result['restored'])->toBeGreaterThanOrEqual(1);
    expect(Procurement::where('pr_number', 'PR-HARD-DELETED')->value('title'))->toBe('Restored Hard Deleted PR');
});

it('restores integrity audit logs from the blockchain audit trail stream', function () {
    $chainViolation = [
        'type' => 'violation',
        'violation_id' => 90001,
        'stream' => 'procurement.metadata',
        'stream_key' => 'PR-AUDIT-RESTORE',
        'txid' => 'audit-restore-txid',
        'violation_type' => BreachType::CONTENT_MISMATCH->value,
        'severity' => 'critical',
        'field_differences' => [['field' => 'title', 'old_value' => 'Chain', 'new_value' => 'DB']],
        'mirror_snapshot' => ['title' => 'DB'],
        'chain_snapshot' => ['title' => 'Chain'],
        'recovery_status' => 'pending',
        'verification_run_id' => 'audit-restore-run',
        'source' => 'audit_restore_test',
        'detected_at' => now()->toIso8601String(),
    ];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainViolation) {
        $mock->shouldReceive('liststreamitems')
            ->with('integrity.violations', true, 10000)
            ->andReturn([
                ['key' => '90001', 'txid' => 'integrity-violation-chain-txid', 'data' => ['json' => $chainViolation], 'blocktime' => now()->timestamp],
            ]);
    });

    $result = app(BlockchainAuditTrailService::class)->restoreAuditLogsToMySQL();
    $restored = IntegrityViolationLog::where('stream_key', 'PR-AUDIT-RESTORE')->first();

    expect($result['imported'])->toBe(1);
    expect($restored)->not->toBeNull();
    expect($restored->violation_type)->toBe(BreachType::CONTENT_MISMATCH->value);
    expect($restored->recovery_status)->toBe('superseded');
});

it('restores a soft-deleted procurement from blockchain metadata', function () {
    $procurement = Procurement::create([
        'pr_number' => 'PR-SOFT-DELETED',
        'title' => 'Soft Deleted PR',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'soft-deleted-txid',
    ]);
    $procurement->delete();

    $chainData = [
        'pr_number' => 'PR-SOFT-DELETED',
        'title' => 'Soft Deleted PR',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'status' => 'draft',
    ];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [['txid' => 'soft-deleted-txid', 'data' => ['json' => $chainData]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    $result = app(IntegrityVerificationService::class)->verifyPr('PR-SOFT-DELETED', true, 'soft-delete-restore-test');

    expect($result['violations'])->toHaveKey(BreachType::ROW_DELETED->value);
    expect($result['restored'])->toBeGreaterThanOrEqual(1);
    expect(Procurement::where('pr_number', 'PR-SOFT-DELETED')->exists())->toBeTrue();
    expect(Procurement::withTrashed()->where('pr_number', 'PR-SOFT-DELETED')->first()?->trashed())->toBeFalse();
});

it('detects tampered procurement document file_key', function () {
    $procurement = Procurement::create(['pr_number' => 'PR-DOC-fileKey', 'title' => 'Document File Key PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'current_stage' => 'procurement_initiation', 'current_status' => 'draft']);

    ProcurementDocument::create([
        'procurement_id' => $procurement->id,
        'document_type' => 'purchase_request',
        'stage' => 'procurement_initiation',
        'filename' => 'purchase-request.pdf',
        'file_key' => 'tampered/File-key.pdf',
        'hash' => 'original-hash',
        'uploaded_by' => 'Alice',
        'txid' => 'document-fileKey-txid',
        'uploaded_at' => now(),
    ]);

    $chainDocument = ['pr_number' => $procurement->pr_number, 'document_type' => 'purchase_request', 'stage' => 'procurement_initiation', 'file_name' => 'purchase-request.pdf', 'file_key' => 'original/File-key.pdf', 'hash' => 'original-hash', 'uploaded_by' => 'Alice', 'timestamp' => now()->toIso8601String()];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainDocument) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.documents'
            ? [['txid' => 'document-fileKey-txid', 'data' => ['json' => $chainDocument]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'document-fileKey-test');

    $fileKeyDiff = IntegrityViolationLog::where('source', 'document-fileKey-test')
        ->where('stream', 'procurement.documents')
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? [])
        ->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'file_key'
            && ($diff['old_value'] ?? null) === 'original/File-key.pdf'
            && ($diff['new_value'] ?? null) === 'tampered/File-key.pdf');

    expect($fileKeyDiff)->toBeTrue();
});

it('detects tampered File metadata hash and File key', function () {
    File::create([
        'file_key' => 'tampered/File.pdf',
        'filename' => 'File.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
        'hash' => 'tampered-hash',
        'storage_method' => 'direct',
        'pr_number' => 'PR-File-META',
        'stage' => 'procurement_initiation',
        'document_type' => 'purchase_request',
        'txid' => 'File-metadata-txid',
        'stored_at' => now(),
    ]);

    $chainFile = [
        'file_key' => 'original/File.pdf',
        'filename' => 'File.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
        'hash' => 'original-hash',
        'storage_method' => 'direct',
        'pr_number' => 'PR-File-META',
        'stage' => 'procurement_initiation',
        'document_type' => 'purchase_request',
    ];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainFile) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'File.metadata'
            ? [['txid' => 'File-metadata-txid', 'data' => ['json' => $chainFile]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'File-metadata-test');

    $diffs = IntegrityViolationLog::where('source', 'File-metadata-test')
        ->where('stream', 'File.metadata')
        ->get()
        ->flatMap(fn (IntegrityViolationLog $log): array => $log->field_differences ?? []);

    expect($diffs->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'hash'
        && ($diff['old_value'] ?? null) === 'original-hash'
        && ($diff['new_value'] ?? null) === 'tampered-hash'))->toBeTrue();
    expect($diffs->contains(fn (array $diff): bool => ($diff['field'] ?? null) === 'file_key'
        && ($diff['old_value'] ?? null) === 'original/File.pdf'
        && ($diff['new_value'] ?? null) === 'tampered/File.pdf'))->toBeTrue();
});

it('detects unauthorized DB-only child row with no blockchain txid', function () {
    $procurement = Procurement::create(['pr_number' => 'PR-INJECTED-CHILD', 'title' => 'Injected Child PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'current_stage' => 'procurement_initiation', 'current_status' => 'draft']);

    ProcurementEvent::create([
        'procurement_id' => $procurement->id,
        'event_type' => 'fake_event',
        'category' => 'security',
        'severity' => 'critical',
        'details' => 'Injected row',
        'stage' => 'procurement_initiation',
        'txid' => null,
        'occurred_at' => now(),
    ]);

    $chainMetadata = ['pr_number' => $procurement->pr_number, 'title' => 'Injected Child PR', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainMetadata) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [['txid' => 'injected-child-metadata-txid', 'data' => ['json' => $chainMetadata]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andReturn('integrity-violation-txid')->byDefault();
    });

    app(IntegrityVerificationService::class)->verifyAndRepair(false, 'unauthorized-child-test');

    expect(IntegrityViolationLog::where('source', 'unauthorized-child-test')
        ->where('stream', 'procurement.events')
        ->where('violation_type', BreachType::UNAUTHORIZED_RECORD->value)
        ->exists())->toBeTrue();
});

it('continues recording mysql violations when blockchain audit publish fails', function () {
    Procurement::create([
        'pr_number' => 'PR-AUDIT-PUBLISH-FAIL',
        'title' => 'Tampered Title',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'current_stage' => 'procurement_initiation',
        'current_status' => 'draft',
        'txid' => 'audit-publish-fail-txid',
    ]);

    $chainData = ['pr_number' => 'PR-AUDIT-PUBLISH-FAIL', 'title' => 'Original Title', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding', 'status' => 'draft'];

    $this->mock(BlockchainRpcClient::class, function ($mock) use ($chainData) {
        $mock->shouldReceive('liststreamitems')->andReturnUsing(fn (string $stream): array => $stream === 'procurement.metadata'
            ? [['txid' => 'audit-publish-fail-txid', 'data' => ['json' => $chainData]]]
            : []);
        $mock->shouldReceive('getrawtransaction')->andReturn([])->byDefault();
        $mock->shouldReceive('publish')->andThrow(new RuntimeException('audit stream unavailable'));
    });

    $result = app(IntegrityVerificationService::class)->verifyAndRepair(false, 'audit-publish-fail-test');

    expect($result['violations'])->not->toBeEmpty();
    expect(IntegrityViolationLog::where('source', 'audit-publish-fail-test')->exists())->toBeTrue();
});

it('skips a concurrent full audit when the verification lock is held', function () {
    $lock = cache()->lock('integrity:verification:lock', 300);
    expect($lock->get())->toBeTrue();

    try {
        $result = app(IntegrityVerificationService::class)->verifyAndRepair(false, 'concurrent-lock-test');

        expect($result['skipped'])->toBeTrue();
        expect($result['verified'])->toBe(0);
    } finally {
        $lock->release();
    }
});
