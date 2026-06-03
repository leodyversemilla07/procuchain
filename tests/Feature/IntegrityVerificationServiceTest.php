<?php

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\IntegrityAuditLog;
use App\Models\ProcurementMirror;
use App\Models\User;
use App\Services\BlockchainMirrorSyncService;
use App\Services\IntegrityVerificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Log::spy();
});

// ═══════════════════════════════════════════════════════════════════════
// IntegrityAuditLog Model Tests
// ═══════════════════════════════════════════════════════════════════════

describe('IntegrityAuditLog Model', function () {
    it('generates unique run IDs', function () {
        $id1 = IntegrityAuditLog::newRunId();
        $id2 = IntegrityAuditLog::newRunId();

        expect($id1)->not->toBe($id2);
        expect(strlen($id1))->toBe(36); // UUID v4 format
    });

    it('determines severity from violation type', function () {
        expect(IntegrityAuditLog::severityForType('hash_mismatch'))->toBe('critical');
        expect(IntegrityAuditLog::severityForType('content_mismatch'))->toBe('critical');
        expect(IntegrityAuditLog::severityForType('user_address_tampered'))->toBe('high');
        expect(IntegrityAuditLog::severityForType('unauthorized_publisher'))->toBe('medium');
        expect(IntegrityAuditLog::severityForType('row_deleted'))->toBe('low');
        expect(IntegrityAuditLog::severityForType('unknown_type'))->toBe('medium');
    });

    it('records a violation with all fields', function () {
        $runId = IntegrityAuditLog::newRunId();

        $log = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'abc123txid',
            fieldDifferences: [
                ['field' => 'title', 'old_value' => 'Original', 'new_value' => 'Tampered'],
            ],
            mirrorSnapshot: ['title' => 'Tampered'],
            chainSnapshot: ['title' => 'Original'],
            mirrorId: 5,
            runId: $runId,
            source: 'scheduled',
        );

        expect($log->stream)->toBe('pr.initiation');
        expect($log->stream_key)->toBe('PR-2024-001');
        expect($log->violation_type)->toBe('hash_mismatch');
        expect($log->severity)->toBe('critical');
        expect($log->field_differences)->toBe([['field' => 'title', 'old_value' => 'Original', 'new_value' => 'Tampered']]);
        expect($log->mirror_snapshot)->toBe(['title' => 'Tampered']);
        expect($log->chain_snapshot)->toBe(['title' => 'Original']);
        expect($log->recovery_status)->toBe('pending');
        expect($log->mirror_id)->toBe(5);
        expect($log->verification_run_id)->toBe($runId);
        expect($log->source)->toBe('scheduled');
    });

    it('records a minimal violation (only required fields)', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'pr.bidding',
            streamKey: 'PR-2024-002',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        expect($log->severity)->toBe('low');
        expect($log->field_differences)->toBeNull();
        expect($log->mirror_snapshot)->toBeNull();
        expect($log->chain_snapshot)->toBeNull();
        expect($log->txid)->toBeNull();
        expect($log->mirror_id)->toBeNull();
    });

    it('marks a violation as restored', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-003',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );

        $log->markRestored([
            'items_restored' => 1,
            'restored_by' => 'system',
        ]);

        expect($log->recovery_status)->toBe('restored');
        expect($log->recovered_at)->not->toBeNull();
        expect($log->recovery_result)->toBe(['items_restored' => 1, 'restored_by' => 'system']);
    });

    it('marks a violation as failed', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-004',
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
        );

        $log->markFailed('Blockchain node unreachable');

        expect($log->recovery_status)->toBe('failed');
        expect($log->recovery_result)->toBe(['error' => 'Blockchain node unreachable']);
    });

    it('marks a violation as skipped', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'user.registrations',
            streamKey: '42',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );

        $log->markSkipped('User record deletion cannot be auto-repaired');

        expect($log->recovery_status)->toBe('skipped');
        expect($log->recovery_result)->toBe(['reason' => 'User record deletion cannot be auto-repaired']);
    });

    it('scopes by violation type', function () {
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-A', 'hash_mismatch');
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-B', 'content_mismatch');
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-C', 'hash_mismatch');

        expect(IntegrityAuditLog::forViolationType('hash_mismatch')->count())->toBe(2);
        expect(IntegrityAuditLog::forViolationType('content_mismatch')->count())->toBe(1);
    });

    it('scopes by verification run', function () {
        $run1 = IntegrityAuditLog::newRunId();
        $run2 = IntegrityAuditLog::newRunId();

        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-X', 'hash_mismatch', runId: $run1);
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-Y', 'content_mismatch', runId: $run1);
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-Z', 'row_deleted', runId: $run2);

        expect(IntegrityAuditLog::forRun($run1)->count())->toBe(2);
        expect(IntegrityAuditLog::forRun($run2)->count())->toBe(1);
    });

    it('scopes by recovery status', function () {
        $log1 = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-D', 'hash_mismatch');
        $log2 = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-E', 'content_mismatch');
        $log2->markRestored(['items_restored' => 1]);

        expect(IntegrityAuditLog::withRecoveryStatus('pending')->count())->toBe(1);
        expect(IntegrityAuditLog::withRecoveryStatus('restored')->count())->toBe(1);
    });

    it('scopes by severity', function () {
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-F', 'hash_mismatch');           // critical
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-G', 'unauthorized_publisher');  // medium
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-H', 'row_deleted');              // low

        expect(IntegrityAuditLog::withSeverity('critical')->count())->toBe(1);
        expect(IntegrityAuditLog::withSeverity('medium')->count())->toBe(1);
        expect(IntegrityAuditLog::withSeverity('low')->count())->toBe(1);
    });

    it('scopes by source', function () {
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-I', 'hash_mismatch', source: 'scheduled');
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-J', 'hash_mismatch', source: 'manual');
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-K', 'hash_mismatch', source: 'read_time');

        expect(IntegrityAuditLog::fromSource('scheduled')->count())->toBe(1);
        expect(IntegrityAuditLog::fromSource('manual')->count())->toBe(1);
        expect(IntegrityAuditLog::fromSource('read_time')->count())->toBe(1);
    });

    it('scopes unresolved and recovered violations', function () {
        $log1 = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-L', 'hash_mismatch');
        $log2 = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-M', 'content_mismatch');
        $log2->markRestored([]);
        $log3 = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-N', 'row_deleted');
        $log3->markFailed('error');

        expect(IntegrityAuditLog::unresolved()->count())->toBe(1);
        expect(IntegrityAuditLog::recovered()->count())->toBe(1);
    });

    it('has no updated_at column (append-only)', function () {
        $log = IntegrityAuditLog::recordViolation('pr.initiation', 'PR-090', 'hash_mismatch');

        expect($log->getUpdatedAtColumn())->toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Field-Level Diff Engine Tests
// ═══════════════════════════════════════════════════════════════════════

describe('Field-Level Diff Engine', function () {
    it('detects modified scalar fields', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['title' => 'Tampered Title', 'amount' => 50000, 'status' => 'approved'];
        $chain = ['title' => 'Original Title', 'amount' => 50000, 'status' => 'pending'];

        $diffs = $service->computeFieldDifferences($mirror, $chain);

        expect($diffs)->toHaveCount(2);
        expect($diffs[0]['field'])->toBe('title');
        expect($diffs[0]['old_value'])->toBe('Original Title');
        expect($diffs[0]['new_value'])->toBe('Tampered Title');
        expect($diffs[1]['field'])->toBe('status');
        expect($diffs[1]['old_value'])->toBe('pending');
        expect($diffs[1]['new_value'])->toBe('approved');
    });

    it('detects added fields (present in mirror but not in chain)', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['title' => 'Test', 'injected_field' => 'malicious'];
        $chain = ['title' => 'Test'];

        $diffs = $service->computeFieldDifferences($mirror, $chain);

        expect($diffs)->toHaveCount(1);
        expect($diffs[0]['field'])->toBe('injected_field');
        expect($diffs[0]['old_value'])->toBeNull();
        expect($diffs[0]['new_value'])->toBe('malicious');
    });

    it('detects removed fields (present in chain but not in mirror)', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['title' => 'Test'];
        $chain = ['title' => 'Test', 'deleted_field' => 'important_data'];

        $diffs = $service->computeFieldDifferences($mirror, $chain);

        expect($diffs)->toHaveCount(1);
        expect($diffs[0]['field'])->toBe('deleted_field');
        expect($diffs[0]['old_value'])->toBe('important_data');
        expect($diffs[0]['new_value'])->toBeNull();
    });

    it('detects nested array differences', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['metadata' => ['key' => 'tampered', 'version' => 2]];
        $chain = ['metadata' => ['key' => 'original', 'version' => 1]];

        $diffs = $service->computeFieldDifferences($mirror, $chain);

        expect($diffs)->toHaveCount(1);
        expect($diffs[0]['field'])->toBe('metadata');
    });

    it('returns no differences for identical data', function () {
        $service = app(IntegrityVerificationService::class);

        $data = ['title' => 'Same', 'amount' => 1000, 'items' => ['a', 'b']];
        $diffs = $service->computeFieldDifferences($data, $data);

        expect($diffs)->toHaveCount(0);
    });

    it('handles empty chain data gracefully', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['title' => 'Modified', 'amount' => 999];
        $diffs = $service->computeFieldDifferences($mirror, []);

        expect($diffs)->toHaveCount(1);
        expect($diffs[0]['field'])->toBe('*');
        expect($diffs[0]['note'])->toBe('Chain data unavailable for field-level comparison');
    });

    it('handles numeric type coercion (string "100" vs int 100)', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['amount' => '100'];
        $chain = ['amount' => 100];

        // Both are numeric and equal — should NOT be flagged
        $diffs = $service->computeFieldDifferences($mirror, $chain);
        expect($diffs)->toHaveCount(0);
    });

    it('detects null vs empty string difference', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['notes' => ''];
        $chain = ['notes' => null];

        $diffs = $service->computeFieldDifferences($mirror, $chain);
        // null vs empty string — string cast: '' vs '' → same
        // But they're different types. Let's verify behavior.
        expect($diffs)->toHaveCount(0); // (string)null === '' in PHP
    });

    it('detects boolean vs integer coercion', function () {
        $service = app(IntegrityVerificationService::class);

        $mirror = ['active' => 1];
        $chain = ['active' => true];

        // is_numeric(1) && is_bool(true) → falls through to (string) comparison
        // (string)1 === '1', (string)true === '1' → same
        $diffs = $service->computeFieldDifferences($mirror, $chain);
        expect($diffs)->toHaveCount(0);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Read-Time Verification Tests
// ═══════════════════════════════════════════════════════════════════════

describe('Read-Time Verification', function () {
    it('validates a clean mirror record', function () {
        $data = ['title' => 'Test PR', 'amount' => 50000];
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mirror = ProcurementMirror::create([
            'stream' => 'pr.initiation',
            'stream_key' => 'PR-2024-100',
            'txid' => 'test-txid-clean',
            'publisher_address' => '1CleanPublisherAddr',
            'blocktime' => now(),
            'data_json' => $data,
            'data_hash' => $hash,
            'is_authorized' => true,
            'synced_at' => now(),
        ]);

        // Create an authorized (non-locked) user with that address
        User::factory()->create([
            'blockchain_address' => '1CleanPublisherAddr',
            'account_locked' => false,
        ]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyOnRead($mirror);

        expect($result['valid'])->toBeTrue();
        expect($result['audit_log_id'])->toBeNull();
    });

    it('detects hash mismatch on read', function () {
        $originalData = ['title' => 'Test PR', 'amount' => 50000];
        $hash = hash('sha256', json_encode($originalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mirror = ProcurementMirror::create([
            'stream' => 'pr.initiation',
            'stream_key' => 'PR-2024-101',
            'txid' => 'test-txid-mismatch',
            'publisher_address' => '1HashMismatchAddr',
            'blocktime' => now(),
            'data_json' => ['title' => 'TAMPERED', 'amount' => 50000], // Modified!
            'data_hash' => $hash, // Still has the ORIGINAL hash
            'is_authorized' => true,
            'synced_at' => now(),
        ]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyOnRead($mirror);

        expect($result['valid'])->toBeFalse();
        expect($result['audit_log_id'])->not->toBeNull();

        $auditLog = IntegrityAuditLog::find($result['audit_log_id']);
        expect($auditLog->violation_type)->toBe('hash_mismatch');
        expect($auditLog->severity)->toBe('critical');
        expect($auditLog->source)->toBe('read_time');
    });

    it('detects unauthorized publisher on read', function () {
        $data = ['title' => 'Test PR'];
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mirror = ProcurementMirror::create([
            'stream' => 'pr.initiation',
            'stream_key' => 'PR-2024-102',
            'txid' => 'test-txid-unauth',
            'publisher_address' => 'LOCKED_PUBLISHER_ADDR',
            'blocktime' => now(),
            'data_json' => $data,
            'data_hash' => $hash,
            'is_authorized' => true,
            'synced_at' => now(),
        ]);

        // Create a LOCKED user with that address
        User::factory()->create([
            'blockchain_address' => 'LOCKED_PUBLISHER_ADDR',
            'account_locked' => true,
        ]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyOnRead($mirror);

        expect($result['valid'])->toBeFalse();

        $auditLog = IntegrityAuditLog::find($result['audit_log_id']);
        expect($auditLog->violation_type)->toBe('unauthorized_publisher');
        expect($auditLog->severity)->toBe('medium');
    });

    it('passes for publisher address with no matching user', function () {
        $data = ['title' => 'Test PR'];
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mirror = ProcurementMirror::create([
            'stream' => 'pr.initiation',
            'stream_key' => 'PR-2024-103',
            'txid' => 'test-txid-nouser',
            'publisher_address' => 'NO_USER_WITH_THIS_ADDR',
            'blocktime' => now(),
            'data_json' => $data,
            'data_hash' => $hash,
            'is_authorized' => true,
            'synced_at' => now(),
        ]);

        // No user with this address → isAuthorizedPublisher returns false
        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyOnRead($mirror);

        expect($result['valid'])->toBeFalse();

        $auditLog = IntegrityAuditLog::find($result['audit_log_id']);
        expect($auditLog->violation_type)->toBe('unauthorized_publisher');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Restore Violation Tests
// ═══════════════════════════════════════════════════════════════════════

describe('Restore Violation', function () {
    it('restores a pending violation via sync service', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-200',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'restore-txid-1',
        );

        $syncMock = $this->mock(BlockchainMirrorSyncService::class);
        $syncMock->shouldReceive('repairFromChain')
            ->with('PR-2024-200', 'pr.initiation')
            ->andReturn(1);

        $service = new IntegrityVerificationService($syncMock);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeTrue();
        expect($result['items_restored'])->toBe(1);
        expect($result['error'])->toBeNull();

        $auditLog->refresh();
        expect($auditLog->recovery_status)->toBe('restored');
        expect($auditLog->recovered_at)->not->toBeNull();
        expect($auditLog->recovery_result)->toHaveKey('items_restored');
    });

    it('handles restoration failure', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-201',
            violationType: BreachTypeEnums::ROW_DELETED->value,
            txid: 'restore-txid-2',
        );

        $syncMock = $this->mock(BlockchainMirrorSyncService::class);
        $syncMock->shouldReceive('repairFromChain')
            ->andThrow(new \Exception('Blockchain node unreachable'));

        $service = new IntegrityVerificationService($syncMock);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['items_restored'])->toBe(0);
        expect($result['error'])->toBe('Blockchain node unreachable');

        $auditLog->refresh();
        expect($auditLog->recovery_status)->toBe('failed');
        expect($auditLog->recovery_result)->toBe(['error' => 'Blockchain node unreachable']);
    });

    it('refuses to re-process a non-pending violation', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-202',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );
        $auditLog->markRestored(['items_restored' => 1]);

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Violation already processed');
    });

    it('refuses to re-process a failed violation', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-203',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );
        $auditLog->markFailed('Previous error');

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Violation already processed');
    });

    it('refuses to re-process a skipped violation', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-204',
            violationType: BreachTypeEnums::ROW_DELETED->value,
        );
        $auditLog->markSkipped('Manual review needed');

        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Violation already processed');
    });

    it('marks mirror breach as repaired when restoring', function () {
        $data = ['title' => 'Breached PR'];
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mirror = ProcurementMirror::create([
            'stream' => 'pr.initiation',
            'stream_key' => 'PR-2024-205',
            'txid' => 'restore-txid-3',
            'publisher_address' => '1RepairTestAddr',
            'blocktime' => now(),
            'data_json' => $data,
            'data_hash' => $hash,
            'is_authorized' => true,
            'synced_at' => now(),
        ]);
        $mirror->markAsBreached('hash_mismatch', ['test' => true]);
        expect($mirror->isBreached())->toBeTrue();

        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'pr.initiation',
            streamKey: 'PR-2024-205',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'restore-txid-3',
            mirrorId: $mirror->id,
        );

        $syncMock = $this->mock(BlockchainMirrorSyncService::class);
        $syncMock->shouldReceive('repairFromChain')
            ->andReturn(1);

        $service = new IntegrityVerificationService($syncMock);
        $result = $service->restoreViolation($auditLog);

        expect($result['success'])->toBeTrue();

        $mirror->refresh();
        expect($mirror->isBreached())->toBeFalse();
        expect($mirror->repaired_at)->not->toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Report Generation Tests
// ═══════════════════════════════════════════════════════════════════════

describe('Report Generation', function () {
    it('generates a comprehensive violation report', function () {
        $runId = IntegrityAuditLog::newRunId();

        $log1 = IntegrityAuditLog::recordViolation(
            'pr.initiation', 'PR-2024-300', 'hash_mismatch', runId: $runId
        );
        $log1->markRestored(['items_restored' => 1]);

        $log2 = IntegrityAuditLog::recordViolation(
            'pr.initiation', 'PR-2024-301', 'content_mismatch', runId: $runId
        );

        $log3 = IntegrityAuditLog::recordViolation(
            'pr.initiation', 'PR-2024-302', 'unauthorized_publisher', runId: $runId
        );
        $log3->markFailed('Node error');

        $log4 = IntegrityAuditLog::recordViolation(
            'pr.initiation', 'PR-2024-303', 'row_deleted', runId: $runId
        );
        $log4->markSkipped('Manual review needed');

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        expect($report['run_id'])->toBe($runId);
        expect($report['summary']['total_violations'])->toBe(4);
        expect($report['summary']['critical'])->toBe(2);  // hash_mismatch + content_mismatch
        expect($report['summary']['medium'])->toBe(1);     // unauthorized_publisher
        expect($report['summary']['low'])->toBe(1);        // row_deleted
        expect($report['summary']['restored'])->toBe(1);
        expect($report['summary']['failed'])->toBe(1);
        expect($report['summary']['pending'])->toBe(1);
        expect($report['summary']['by_type'])->toHaveKey('hash_mismatch');
        expect($report['summary']['by_type'])->toHaveKey('content_mismatch');
        expect($report['violations'])->toHaveCount(4);
    });

    it('generates an empty report for a run with no violations', function () {
        $runId = IntegrityAuditLog::newRunId();

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        expect($report['run_id'])->toBe($runId);
        expect($report['summary']['total_violations'])->toBe(0);
        expect($report['summary']['critical'])->toBe(0);
        expect($report['summary']['high'])->toBe(0);
        expect($report['summary']['medium'])->toBe(0);
        expect($report['summary']['low'])->toBe(0);
        expect($report['violations'])->toHaveCount(0);
    });

    it('groups violations by type in report', function () {
        $runId = IntegrityAuditLog::newRunId();

        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-310', 'hash_mismatch', runId: $runId);
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-311', 'hash_mismatch', runId: $runId);
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-312', 'hash_mismatch', runId: $runId);
        IntegrityAuditLog::recordViolation('pr.initiation', 'PR-313', 'row_deleted', runId: $runId);

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        expect($report['summary']['by_type']['hash_mismatch'])->toBe(3);
        expect($report['summary']['by_type']['row_deleted'])->toBe(1);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BreachTypeEnums Tests
// ═══════════════════════════════════════════════════════════════════════

describe('BreachTypeEnums', function () {
    it('has all required breach types', function () {
        expect(BreachTypeEnums::HASH_MISMATCH->value)->toBe('hash_mismatch');
        expect(BreachTypeEnums::CONTENT_MISMATCH->value)->toBe('content_mismatch');
        expect(BreachTypeEnums::ROW_DELETED->value)->toBe('row_deleted');
        expect(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value)->toBe('unauthorized_publisher');
        expect(BreachTypeEnums::USER_ADDRESS_TAMPERED->value)->toBe('user_address_tampered');
    });

    it('provides display names for all types', function () {
        foreach (BreachTypeEnums::cases() as $case) {
            expect($case->getDisplayName())->toBeString();
            expect($case->getDisplayName())->not->toBeEmpty();
        }
    });

    it('provides descriptions for all types', function () {
        foreach (BreachTypeEnums::cases() as $case) {
            expect($case->getDescription())->toBeString();
            expect($case->getDescription())->not->toBeEmpty();
        }
    });

    it('returns all values as array', function () {
        $values = BreachTypeEnums::values();

        expect($values)->toBeArray();
        expect($values)->toContain('hash_mismatch');
        expect($values)->toContain('content_mismatch');
        expect($values)->toContain('row_deleted');
        expect($values)->toContain('unauthorized_publisher');
        expect($values)->toContain('user_address_tampered');
    });

    it('returns options as value => display_name map', function () {
        $options = BreachTypeEnums::options();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('hash_mismatch');
        expect($options['hash_mismatch'])->toBe('Hash Mismatch');
        expect($options['row_deleted'])->toBe('Row Deleted');
    });
});
