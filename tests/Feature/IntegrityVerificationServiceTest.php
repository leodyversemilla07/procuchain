<?php

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementEvent;
use App\Services\BlockchainAuditTrailService;
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
            streamKey: 'PR-2024-001-0001',
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
        expect($log->stream_key)->toBe('PR-2024-001-0001');
        expect($log->violation_type)->toBe('hash_mismatch');
        expect($log->severity)->toBe('critical');
        expect($log->field_differences)->toHaveCount(1);
        expect($log->recovery_status)->toBe('pending');
        expect($log->verification_run_id)->toBe($runId);
    });

    it('marks violation as restored', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-002-0001',
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
            streamKey: 'PR-2024-003-0001',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $log->markFailed('Blockchain unreachable');

        expect($log->recovery_status)->toBe('failed');
        expect($log->recovery_result)->toBe(['error' => 'Blockchain unreachable']);
    });

    it('marks violation as skipped', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2024-004-0001',
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

    it('does not flag procurement metadata aliases as content mismatches', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-001-0001',
            'title' => 'Procurement of Water System Repair',
            'description' => 'Water System Installation/Repair',
            'category' => 'infrastructure_projects',
            'procurement_mode' => 'limited_source_bidding',
            'office' => "MO - Mayor's Office",
            'end_user' => "MO - Mayor's Office",
            'fund_source' => 'General Fund',
            'prepared_by' => 'Bryle Maamo',
            'abc_amount' => 1000000,
            'current_stage' => 'procurement_initiation',
            'current_status' => 'procurement_initiated',
            'initiated_at' => '2026-05-19 21:37:15',
            'txid' => 'metadata-txid-aliases',
        ]);

        $this->mock(Manager::class, function ($mock) use ($procurement) {
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.metadata', $procurement->pr_number)
                ->andReturn([
                    [
                        'txid' => 'metadata-txid-aliases',
                        'data' => [
                            'json' => [
                                'pr_number' => $procurement->pr_number,
                                'title' => 'Procurement of Water System Repair',
                                'description' => 'Water System Installation/Repair',
                                'category' => 'infrastructure_projects',
                                'procurement_mode' => 'limited_source_bidding',
                                'office' => "MO - Mayor's Office",
                                'end_user' => "MO - Mayor's Office",
                                'funding_source' => 'General Fund',
                                'prepared_by' => 'Bryle Maamo',
                                'abc_amount' => '1000000',
                                'status' => 'procurement_initiated',
                                'created_at' => '2026-05-19T21:37:15+08:00',
                            ],
                        ],
                    ],
                ]);
            $mock->shouldReceive('getrawtransaction')->andReturn([]);
        });

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyPr($procurement->pr_number);

        expect($result['violations'])->not->toHaveKey(BreachTypeEnums::CONTENT_MISMATCH->value);
        expect(IntegrityAuditLog::where('stream', 'procurement.metadata')->count())->toBe(0);
    });

    it('detects real database tampering after canonical blockchain mapping', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-001-0002',
            'title' => 'Tampered Title',
            'description' => 'Original Description',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'office' => 'Office',
            'end_user' => 'Office',
            'fund_source' => 'General Fund',
            'abc_amount' => 1000,
            'current_stage' => 'procurement_initiation',
            'current_status' => 'draft',
            'txid' => 'metadata-txid-tampered',
        ]);

        $this->mock(Manager::class, function ($mock) use ($procurement) {
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.metadata', $procurement->pr_number)
                ->andReturn([
                    [
                        'txid' => 'metadata-txid-tampered',
                        'data' => [
                            'json' => [
                                'pr_number' => $procurement->pr_number,
                                'title' => 'Original Title',
                                'description' => 'Original Description',
                                'category' => 'goods',
                                'procurement_mode' => 'competitive_bidding',
                                'office' => 'Office',
                                'end_user' => 'Office',
                                'funding_source' => 'General Fund',
                                'abc_amount' => '1000',
                                'status' => 'draft',
                            ],
                        ],
                    ],
                ]);
            $mock->shouldReceive('getrawtransaction')->andReturn([]);
        });

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyPr($procurement->pr_number);

        expect($result['violations'])->toHaveKey(BreachTypeEnums::CONTENT_MISMATCH->value);
        expect(IntegrityAuditLog::where('stream', 'procurement.metadata')->first()?->field_differences)
            ->toContain(['field' => 'title', 'old_value' => 'Original Title', 'new_value' => 'Tampered Title']);
    });

    it('does not flag normalized event fields as content mismatches', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-030-0300',
            'title' => 'Test PR',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'pre_procurement_conference',
            'current_status' => 'procurement_submitted',
        ]);

        ProcurementEvent::create([
            'procurement_id' => $procurement->id,
            'event_type' => 'stage_completed',
            'category' => 'stage_transition',
            'severity' => 'info',
            'details' => 'Stage Procurement Initiation completed. Transitioned to Pre-Procurement Conference with status Procurement Submitted.',
            'stage' => 'pre_procurement_conference',
            'document_count' => 0,
            'txid' => 'event-txid-normalized-fields',
            'occurred_at' => '2026-05-27 23:00:56',
        ]);

        $this->mock(Manager::class, function ($mock) use ($procurement) {
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.metadata', $procurement->pr_number)
                ->andReturn([]);
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.events', $procurement->pr_number)
                ->andReturn([
                    [
                        'txid' => 'event-txid-normalized-fields',
                        'data' => [
                            'json' => [
                                'pr_number' => $procurement->pr_number,
                                'event_type' => 'stage_completed',
                                'category' => 'stage_transition',
                                'severity' => 'info',
                                'details' => 'Stage Procurement Initiation completed. Transitioned to Pre-Procurement Conference with status Procurement Submitted.',
                                'stage' => 'pre_procurement_conference',
                                'timestamp' => '2026-05-27T23:00:56+00:00',
                            ],
                        ],
                    ],
                ]);
            $mock->shouldReceive('getrawtransaction')->andReturn([]);
        });

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyPr($procurement->pr_number);

        expect($result['violations'])->not->toHaveKey(BreachTypeEnums::CONTENT_MISMATCH->value);
        expect(IntegrityAuditLog::where('stream', 'procurement.events')->count())->toBe(0);
    });

    it('refreshes stale local hashes when canonical chain content matches', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-001-0001',
            'title' => 'Procurement of Water System Repair',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'procurement_initiation',
            'current_status' => 'draft',
        ]);

        $correction = ProcurementCorrection::create([
            'procurement_id' => $procurement->id,
            'correction_type' => 'document_correction',
            'action' => 'replace',
            'reason' => 'not accurate',
            'original_txid' => 'original-txid',
            'original_document_hash' => 'original-document-hash',
            'corrected_by' => 'Bryle Maamo',
            'txid' => 'correction-txid-stale-hash',
            'data_hash' => 'stale-hash',
            'blockchain_hash' => 'stale-hash',
            'corrected_at' => '2026-05-27 22:55:17',
            'has_breach' => true,
        ]);

        $staleLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.corrections',
            streamKey: $procurement->pr_number,
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: $correction->txid,
            fieldDifferences: [['field' => 'hash', 'old_value' => 'stale-hash', 'new_value' => 'current-hash']],
            mirrorSnapshot: $correction->toArray(),
            chainSnapshot: ['pr_number' => $procurement->pr_number],
            recordId: $correction->id,
        );

        $this->mock(Manager::class, function ($mock) use ($procurement) {
            $mock->shouldReceive('liststreamitems')->andReturn([]);
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.metadata', $procurement->pr_number)
                ->andReturn([]);
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.corrections', $procurement->pr_number)
                ->andReturn([
                    [
                        'txid' => 'correction-txid-stale-hash',
                        'data' => [
                            'json' => [
                                'pr_number' => $procurement->pr_number,
                                'correction_type' => 'document_correction',
                                'action' => 'replace',
                                'reason' => 'not accurate',
                                'original_txid' => 'original-txid',
                                'original_document_hash' => 'original-document-hash',
                                'corrected_by' => 'Bryle Maamo',
                                'timestamp' => '2026-05-27T22:55:17+08:00',
                            ],
                        ],
                    ],
                ]);
            $mock->shouldReceive('getrawtransaction')->andReturn([]);
        });

        $result = app(IntegrityVerificationService::class)->verifyAndRepair(false, 'test');

        $correction->refresh();
        $staleLog->refresh();

        expect($result['violations'])->not->toHaveKey(BreachTypeEnums::HASH_MISMATCH->value)
            ->and($correction->data_hash)->not->toBe('stale-hash')
            ->and($correction->blockchain_hash)->toBe($correction->data_hash)
            ->and($correction->has_breach)->toBeFalse()
            ->and($staleLog->recovery_status)->toBe('skipped');
    });

    it('marks stale pending projection mismatches as skipped after the record verifies clean', function () {
        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-030-0301',
            'title' => 'Test PR',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'pre_procurement_conference',
            'current_status' => 'procurement_submitted',
        ]);

        $event = ProcurementEvent::create([
            'procurement_id' => $procurement->id,
            'event_type' => 'stage_completed',
            'category' => 'stage_transition',
            'severity' => 'info',
            'details' => 'Stage completed.',
            'stage' => 'pre_procurement_conference',
            'document_count' => 0,
            'txid' => 'event-txid-stale-false-positive',
            'occurred_at' => '2026-05-27 23:00:56',
            'has_breach' => true,
        ]);

        $staleLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.events',
            streamKey: $procurement->pr_number,
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
            txid: $event->txid,
            fieldDifferences: [
                ['field' => 'procurement_id', 'old_value' => null, 'new_value' => $procurement->id],
                ['field' => 'occurred_at', 'old_value' => null, 'new_value' => '2026-05-27 23:00:56'],
            ],
            mirrorSnapshot: $event->toArray(),
            chainSnapshot: [
                'pr_number' => $procurement->pr_number,
                'timestamp' => '2026-05-27T23:00:56+00:00',
            ],
            recordId: $event->id,
        );

        $this->mock(Manager::class, function ($mock) use ($procurement) {
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.metadata', $procurement->pr_number)
                ->andReturn([]);
            $mock->shouldReceive('liststreamkeyitems')
                ->with('procurement.events', $procurement->pr_number)
                ->andReturn([
                    [
                        'txid' => 'event-txid-stale-false-positive',
                        'data' => [
                            'json' => [
                                'pr_number' => $procurement->pr_number,
                                'event_type' => 'stage_completed',
                                'category' => 'stage_transition',
                                'severity' => 'info',
                                'details' => 'Stage completed.',
                                'stage' => 'pre_procurement_conference',
                                'document_count' => 0,
                                'timestamp' => '2026-05-27T23:00:56+00:00',
                            ],
                        ],
                    ],
                ]);
            $mock->shouldReceive('getrawtransaction')->andReturn([]);
        });

        $result = app(IntegrityVerificationService::class)->verifyPr($procurement->pr_number);

        expect($result['violations'])->not->toHaveKey(BreachTypeEnums::CONTENT_MISMATCH->value);

        $staleLog->refresh();
        $event->refresh();

        expect($staleLog->recovery_status)->toBe('skipped')
            ->and($staleLog->recovery_result['reason'])->toContain('found no remaining DB/blockchain mismatch')
            ->and($event->has_breach)->toBeFalse();
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
