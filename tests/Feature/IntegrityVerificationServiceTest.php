<?php

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Services\BlockchainAuditTrailService;
use App\Services\BlockchainRecordSyncService;
use App\Services\IntegrityVerificationService;
use App\Services\Manager;
use App\Services\NormalizedTableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Log::spy();

    // Mock BlockchainAuditTrailService
    $this->mock(BlockchainAuditTrailService::class, function ($mock) {
        $mock->shouldReceive('publishViolation')->andReturn('mock-txid');
        $mock->shouldReceive('publishRecovery')->andReturn('mock-recovery-txid');
    });

    // Mock Manager
    $this->mock(Manager::class, function ($mock) {
        $mock->shouldReceive('liststreamitems')->andReturn([]);
        $mock->shouldReceive('liststreamkeyitems')->andReturn([]);
        $mock->shouldReceive('publish')->andReturn('mock-txid');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// IntegrityAuditLog Model Tests
// ═══════════════════════════════════════════════════════════════════════

describe('IntegrityAuditLog Model', function () {
    it('generates unique run IDs', function () {
        $id1 = IntegrityAuditLog::newRunId();
        $id2 = IntegrityAuditLog::newRunId();

        expect($id1)->not->toBe($id2);
        expect(strlen($id1))->toBe(36);
    });

    it('determines severity from violation type', function () {
        expect(IntegrityAuditLog::severityForType('hash_mismatch'))->toBe('critical');
        expect(IntegrityAuditLog::severityForType('content_mismatch'))->toBe('critical');
        expect(IntegrityAuditLog::severityForType('user_address_tampered'))->toBe('high');
        expect(IntegrityAuditLog::severityForType('unauthorized_publisher'))->toBe('medium');
        expect(IntegrityAuditLog::severityForType('row_deleted'))->toBe('low');
    });

    it('records a violation with all fields', function () {
        $runId = IntegrityAuditLog::newRunId();

        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'abc123txid',
            fieldDifferences: [
                ['field' => 'title', 'old_value' => 'Original', 'new_value' => 'Tampered'],
            ],
            mirrorSnapshot: ['title' => 'Tampered'],
            chainSnapshot: ['title' => 'Original'],
            runId: $runId,
        );

        expect($log)->toBeInstanceOf(IntegrityAuditLog::class);
        expect($log->stream)->toBe('procurement.metadata');
        expect($log->stream_key)->toBe('PR-2024-001');
        expect($log->violation_type)->toBe('hash_mismatch');
        expect($log->severity)->toBe('critical');
        expect($log->field_differences)->toHaveCount(1);
        expect($log->recovery_status)->toBe('pending');
        expect($log->verification_run_id)->toBe($runId);
    });

    it('marks violation as restored', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-002',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );

        $log->markRestored(['items_restored' => 1, 'restored_by' => 'admin']);

        expect($log->recovery_status)->toBe('restored');
        expect($log->recovered_at)->not->toBeNull();
        expect($log->recovery_result)->toHaveKey('restored_by');
    });

    it('marks violation as failed', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-003',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $log->markFailed('Blockchain unreachable');

        expect($log->recovery_status)->toBe('failed');
        expect($log->recovery_result)->toBe(['error' => 'Blockchain unreachable']);
    });

    it('marks violation as skipped', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-004',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $log->markSkipped('Cannot restore user records');

        expect($log->recovery_status)->toBe('skipped');
        expect($log->recovery_result)->toHaveKey('reason');
    });

    it('can filter by recovery status', function () {
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-1',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        )->markRestored();

        $pending = IntegrityAuditLog::where('recovery_status', 'pending')->count();
        $restored = IntegrityAuditLog::where('recovery_status', 'restored')->count();

        expect($pending)->toBe(1);
        expect($restored)->toBe(1);
    });

    it('can filter by violation type', function () {
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-1',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $hash = IntegrityAuditLog::where('violation_type', 'hash_mismatch')->count();
        $deleted = IntegrityAuditLog::where('violation_type', 'row_deleted')->count();

        expect($hash)->toBe(1);
        expect($deleted)->toBe(1);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// IntegrityVerificationService Tests
// ═══════════════════════════════════════════════════════════════════════

describe('IntegrityVerificationService', function () {
    it('verifies clean record', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-TEST-001',
            'title' => 'Test PR',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'procurement_initiation',
            'current_status' => 'draft',
            'txid' => 'test-txid-clean',
            'data_hash' => 'valid-hash',
            'blockchain_hash' => 'valid-hash',
        ]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyAndRepair(false, 'test');

        expect($result['verified'])->toBeGreaterThan(0);
    });

    it('detects deleted records', function () {
        // Create a procurement that exists
        Procurement::create([
            'pr_number' => 'PR-TEST-002',
            'title' => 'Test PR',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'procurement_initiation',
            'current_status' => 'draft',
        ]);

        // Mock blockchain to return this PR
        $this->mock(Manager::class, function ($mock) {
            $mock->shouldReceive('liststreamitems')->andReturn([
                ['data' => ['json' => ['pr_number' => 'PR-TEST-DELETED', 'title' => 'Deleted PR']]],
            ]);
            $mock->shouldReceive('liststreamkeyitems')->andReturn([]);
        });

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyAndRepair(false, 'test');

        expect($result['violations'])->toHaveKey('row_deleted');
    });

    it('generates violation report', function () {
        $runId = IntegrityAuditLog::newRunId();

        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-1',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            runId: $runId,
        );
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2',
            violationType: BreachTypeEnums::ROW_DELETED->value,
            runId: $runId,
        );

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        expect($report['summary']['total_violations'])->toBe(2);
        expect($report['summary']['critical'])->toBe(1);
        expect($report['summary']['low'])->toBe(1);
    });

    it('restores a pending violation via sync service', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-TEST-RESTORE',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'restore-txid',
        );

        $syncMock = $this->mock(NormalizedTableSyncService::class);
        $syncMock->shouldReceive('syncAll')->once()->andReturn([
            'procurements' => 1,
            'stages' => 0,
            'documents' => 0,
            'events' => 0,
        ]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeTrue();
        expect($result['items_restored'])->toBe(1);

        $auditLog->refresh();
        expect($auditLog->recovery_status)->toBe('restored');
        expect($auditLog->recovery_result)->toHaveKey('restored_by');
    });

    it('handles restoration failure', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-TEST-FAIL',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $syncMock = $this->mock(NormalizedTableSyncService::class);
        $syncMock->shouldReceive('syncAll')->andThrow(new Exception('Blockchain unreachable'));

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Blockchain unreachable');

        $auditLog->refresh();
        expect($auditLog->recovery_status)->toBe('failed');
    });

    it('refuses to re-process a non-pending violation', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-TEST-ALREADY',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );
        $auditLog->markRestored(['items_restored' => 1]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Already processed');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Normalized Table Sync Tests
// ═══════════════════════════════════════════════════════════════════════

describe('NormalizedTableSyncService', function () {
    it('syncs procurement metadata to procurements table', function () {
        // Mock blockchain to return metadata
        $this->mock(Manager::class, function ($mock) {
            $mock->shouldReceive('liststreamitems')->with('procurement.metadata', false, 10000)->andReturn([
                [
                    'txid' => 'test-txid-meta',
                    'blocktime' => now()->timestamp,
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-SYNC-001',
                            'title' => 'Sync Test PR',
                            'category' => 'goods',
                            'procurement_mode' => 'competitive_bidding',
                            'status' => 'draft',
                        ],
                    ],
                ],
            ]);
            $mock->shouldReceive('liststreamitems')->andReturn([]);
        });

        $service = app(NormalizedTableSyncService::class);
        $counts = $service->syncAll();

        expect($counts['procurements'])->toBe(1);

        $procurement = Procurement::where('pr_number', 'PR-SYNC-001')->first();
        expect($procurement)->not->toBeNull();
        expect($procurement->title)->toBe('Sync Test PR');
        expect($procurement->txid)->toBe('test-txid-meta');
        expect($procurement->data_hash)->not->toBeNull();
        expect($procurement->blockchain_hash)->not->toBeNull();
    });

    it('skips system events', function () {
        $this->mock(Manager::class, function ($mock) {
            $mock->shouldReceive('liststreamitems')->with('procurement.events', false, 10000)->andReturn([
                [
                    'txid' => 'test-txid-event',
                    'blocktime' => now()->timestamp,
                    'data' => [
                        'json' => [
                            'pr_number' => 'system',
                            'event_type' => 'auth.logout',
                        ],
                    ],
                ],
            ]);
            $mock->shouldReceive('liststreamitems')->andReturn([]);
        });

        $service = app(NormalizedTableSyncService::class);
        $counts = $service->syncAll();

        expect($counts['events'])->toBe(0);
    });
});
