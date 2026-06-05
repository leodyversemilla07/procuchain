<?php

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\IntegrityAuditLog;
use App\Services\BlockchainAuditTrailService;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainAuditTrailService — Publish Violation
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainAuditTrailService — Publish Violation', function () {
    it('publishes a violation to the blockchain and attaches txid', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-001-0001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'chain-txid-001',
            fieldDifferences: [['field' => 'amount', 'old_value' => 100, 'new_value' => 999]],
            publishToChain: false, // Don't publish yet — we'll test publishViolation directly
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->with(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                (string) $auditLog->id,
                Mockery::on(fn ($data) => isset($data['json']['violation_type']) && $data['json']['violation_type'] === 'hash_mismatch')
            )
            ->andReturn('blockchain-txid-abc123');

        $service = app(BlockchainAuditTrailService::class);
        $txid = $service->publishViolation($auditLog);

        expect($txid)->toBe('blockchain-txid-abc123');

        // Verify txid was attached to the audit log
        $auditLog->refresh();
        $lineage = $auditLog->revision_lineage ?? [];
        expect($lineage['_blockchain_txid'])->toBe('blockchain-txid-abc123');
    });

    it('returns null and logs error when blockchain publish fails', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-002-0001',
            violationType: BreachTypeEnums::ROW_DELETED->value,
            publishToChain: false,
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->andReturn(null);

        $service = app(BlockchainAuditTrailService::class);
        $txid = $service->publishViolation($auditLog);

        expect($txid)->toBeNull();
    });

    it('returns null when blockchain throws an exception', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-003-0001',
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
            publishToChain: false,
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->andThrow(new Exception('Connection refused'));

        $service = app(BlockchainAuditTrailService::class);
        $txid = $service->publishViolation($auditLog);

        expect($txid)->toBeNull();
    });

    it('builds correct chain payload with all violation fields', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-004-0001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            txid: 'payload-test-txid',
            fieldDifferences: [['field' => 'title', 'old_value' => 'A', 'new_value' => 'B']],
            mirrorSnapshot: ['title' => 'B'],
            chainSnapshot: ['title' => 'A'],
            recordId: 42,
            runId: 'test-run-id',
            source: 'manual',
            revisionNumber: 3,
            publishToChain: false,
        );

        $capturedPayload = null;

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->with(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                (string) $auditLog->id,
                Mockery::on(function ($data) use (&$capturedPayload) {
                    $capturedPayload = $data['json'];

                    return true;
                })
            )
            ->andReturn('payload-test-chain-txid');

        $service = app(BlockchainAuditTrailService::class);
        $service->publishViolation($auditLog);

        expect($capturedPayload)->not->toBeNull();
        expect($capturedPayload['type'])->toBe('violation');
        expect($capturedPayload['violation_type'])->toBe('hash_mismatch');
        expect($capturedPayload['severity'])->toBe('critical');
        expect($capturedPayload['stream_key'])->toBe('PR-2026-004-0001');
        expect($capturedPayload['txid'])->toBe('payload-test-txid');
        expect($capturedPayload['record_id'])->toBe(42);
        expect($capturedPayload['field_differences'])->toHaveCount(1);
        expect($capturedPayload['mirror_snapshot'])->toBe(['title' => 'B']);
        expect($capturedPayload['chain_snapshot'])->toBe(['title' => 'A']);
        expect($capturedPayload['source'])->toBe('manual');
        expect($capturedPayload['revision_number'])->toBe(3);
        expect($capturedPayload['chain_hash'])->toBeString();
        expect($capturedPayload['detected_at'])->toBeString();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainAuditTrailService — Publish Recovery
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainAuditTrailService — Publish Recovery', function () {
    it('publishes a recovery event to the blockchain', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-010-0001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: false,
        );
        $auditLog->markRestored(['items_restored' => 1], publishToChain: false);

        $capturedPayload = null;

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->with(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                'recovery-'.$auditLog->id,
                Mockery::on(function ($data) use (&$capturedPayload) {
                    $capturedPayload = $data['json'];

                    return true;
                })
            )
            ->andReturn('recovery-chain-txid');

        $service = app(BlockchainAuditTrailService::class);
        $txid = $service->publishRecovery($auditLog, ['items_restored' => 1]);

        expect($txid)->toBe('recovery-chain-txid');
        expect($capturedPayload['type'])->toBe('recovery');
        expect($capturedPayload['violation_id'])->toBe($auditLog->id);
        expect($capturedPayload['recovery_status'])->toBe('restored');
        expect($capturedPayload['recovery_result'])->toBe(['items_restored' => 1]);
        expect($capturedPayload['recovered_at'])->toBeString();
    });

    it('returns null when recovery publish fails', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-011-0001',
            violationType: BreachTypeEnums::ROW_DELETED->value,
            publishToChain: false,
        );
        $auditLog->markRestored([], publishToChain: false);

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->andReturn(null);

        $service = app(BlockchainAuditTrailService::class);
        $txid = $service->publishRecovery($auditLog);

        expect($txid)->toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainAuditTrailService — Recover Audit Trail
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainAuditTrailService — Recover Audit Trail', function () {
    it('recovers audit trail entries from blockchain', function () {
        $chainEntries = [
            [
                'key' => '1',
                'txid' => 'violation-txid-1',
                'data' => ['json' => [
                    'type' => 'violation',
                    'violation_id' => 1,
                    'violation_type' => 'hash_mismatch',
                    'stream_key' => 'PR-2026-020-0001',
                    'severity' => 'critical',
                ]],
                'blocktime' => 1700000000,
                'publishers' => ['1PublisherAddr'],
            ],
            [
                'key' => 'recovery-1',
                'txid' => 'recovery-txid-1',
                'data' => ['json' => [
                    'type' => 'recovery',
                    'violation_id' => 1,
                    'violation_type' => 'hash_mismatch',
                    'stream_key' => 'PR-2026-020-0001',
                    'recovery_status' => 'restored',
                ]],
                'blocktime' => 1700000100,
                'publishers' => ['1PublisherAddr'],
            ],
        ];

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->with(StreamEnums::INTEGRITY_VIOLATIONS->value, true, 10000)
            ->andReturn($chainEntries);

        $service = app(BlockchainAuditTrailService::class);
        $entries = $service->recoverAuditTrail();

        expect($entries)->toHaveCount(2);
        expect($entries[0]['data']['type'])->toBe('violation');
        expect($entries[0]['txid'])->toBe('violation-txid-1');
        expect($entries[1]['data']['type'])->toBe('recovery');
        expect($entries[1]['txid'])->toBe('recovery-txid-1');
    });

    it('returns empty array when no entries exist on chain', function () {
        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn([]);

        $service = app(BlockchainAuditTrailService::class);
        $entries = $service->recoverAuditTrail();

        expect($entries)->toHaveCount(0);
    });

    it('returns empty array when blockchain throws exception', function () {
        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andThrow(new Exception('Stream not found'));

        $service = app(BlockchainAuditTrailService::class);
        $entries = $service->recoverAuditTrail();

        expect($entries)->toHaveCount(0);
    });

    it('recovers audit trail filtered by stream key', function () {
        $chainEntries = [
            [
                'key' => '1',
                'txid' => 'tx-1',
                'data' => ['json' => ['type' => 'violation', 'stream_key' => 'PR-2026-030-0001']],
                'blocktime' => null,
                'publishers' => [],
            ],
            [
                'key' => '2',
                'txid' => 'tx-2',
                'data' => ['json' => ['type' => 'violation', 'stream_key' => 'PR-2026-031-0001']],
                'blocktime' => null,
                'publishers' => [],
            ],
            [
                'key' => '3',
                'txid' => 'tx-3',
                'data' => ['json' => ['type' => 'violation', 'stream_key' => 'PR-2026-030-0001']],
                'blocktime' => null,
                'publishers' => [],
            ],
        ];

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn($chainEntries);

        $service = app(BlockchainAuditTrailService::class);
        $entries = $service->recoverAuditTrailForKey('PR-2026-030-0001');

        expect($entries)->toHaveCount(2);
        expect($entries[0]['data']['stream_key'])->toBe('PR-2026-030-0001');
        expect($entries[1]['data']['stream_key'])->toBe('PR-2026-030-0001');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainAuditTrailService — Restore to MySQL
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainAuditTrailService — Restore to MySQL', function () {
    it('imports violations from blockchain to MySQL', function () {
        $chainEntries = [
            [
                'key' => '100',
                'txid' => 'import-tx-1',
                'data' => ['json' => [
                    'type' => 'violation',
                    'violation_id' => 99901, // Use high IDs to avoid collision
                    'stream' => 'procurement.metadata',
                    'stream_key' => 'PR-IMPORT-001',
                    'txid' => 'original-txid',
                    'violation_type' => 'hash_mismatch',
                    'severity' => 'critical',
                    'field_differences' => null,
                    'mirror_snapshot' => null,
                    'chain_snapshot' => ['title' => 'Original'],
                    'recovery_status' => 'pending',
                    'mirror_id' => null,
                    'verification_run_id' => 'import-run-1',
                    'source' => 'scheduled',
                    'detected_at' => '2026-01-15T10:00:00+00:00',
                ]],
                'blocktime' => null,
                'publishers' => [],
            ],
        ];

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn($chainEntries);

        $service = app(BlockchainAuditTrailService::class);
        $result = $service->restoreAuditLogsToMySQL();

        expect($result['imported'])->toBeGreaterThanOrEqual(1);
        expect($result['errors'])->toBe(0);

        // Verify the imported record exists in MySQL
        $imported = IntegrityAuditLog::where('stream_key', 'PR-IMPORT-001')->first();
        expect($imported)->not->toBeNull();
        expect($imported->violation_type)->toBe('hash_mismatch');
        expect($imported->severity)->toBe('critical');
        expect($imported->chain_snapshot)->toBe(['title' => 'Original']);
        expect($imported->source)->toBe('scheduled'); // Preserved from chain data
    });

    it('skips duplicate records during restore', function () {
        // Create an existing record
        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-DUP-001',
            violationType: 'hash_mismatch',
            publishToChain: false,
        );

        $existingCount = IntegrityAuditLog::count();

        $chainEntries = [
            [
                'key' => 'dup-1',
                'txid' => 'dup-tx-1',
                'data' => ['json' => [
                    'type' => 'violation',
                    'violation_id' => IntegrityAuditLog::where('stream_key', 'PR-DUP-001')->first()->id,
                    'stream' => 'procurement.metadata',
                    'stream_key' => 'PR-DUP-001',
                    'violation_type' => 'hash_mismatch',
                    'severity' => 'critical',
                    'source' => 'scheduled',
                ]],
                'blocktime' => null,
                'publishers' => [],
            ],
        ];

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn($chainEntries);

        $service = app(BlockchainAuditTrailService::class);
        $result = $service->restoreAuditLogsToMySQL();

        expect($result['skipped'])->toBeGreaterThanOrEqual(1);
        expect(IntegrityAuditLog::count())->toBe($existingCount);
    });

    it('skips recovery entries during restore (they update existing violations)', function () {
        $chainEntries = [
            [
                'key' => 'recovery-999',
                'txid' => 'recovery-tx-1',
                'data' => ['json' => [
                    'type' => 'recovery',
                    'violation_id' => 99999,
                    'recovery_status' => 'restored',
                ]],
                'blocktime' => null,
                'publishers' => [],
            ],
        ];

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn($chainEntries);

        $service = app(BlockchainAuditTrailService::class);
        $result = $service->restoreAuditLogsToMySQL();

        // Recovery entries are counted as skipped (they don't create new records)
        expect($result['skipped'])->toBeGreaterThanOrEqual(1);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// IntegrityAuditLog — Blockchain Publishing Integration
// ═══════════════════════════════════════════════════════════════════════

describe('IntegrityAuditLog — Blockchain Publishing', function () {
    it('skips automatic blockchain publish during unit tests', function () {
        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldNotReceive('publish');

        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-CHAIN-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: true,
        );

        expect($log->exists)->toBeTrue();
    });

    it('does NOT publish to blockchain when publishToChain=false', function () {
        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldNotReceive('publish');

        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-NOCHAIN-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: false,
        );

        expect($log->exists)->toBeTrue();
    });

    it('skips automatic recovery publish during unit tests', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-RECOVER-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: false,
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldNotReceive('publish');

        $auditLog->markRestored(['items_restored' => 1], publishToChain: true);

        expect($auditLog->recovery_status)->toBe('restored');
    });

    it('does NOT publish recovery when publishToChain=false', function () {
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-NORECOVER-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: false,
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldNotReceive('publish');

        $auditLog->markRestored([], publishToChain: false);

        expect($auditLog->recovery_status)->toBe('restored');
    });

    it('handles blockchain publish failure gracefully (does not throw)', function () {
        $log = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-GRACEFUL-001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            publishToChain: false,
        );

        $managerMock = $this->mock(Manager::class);
        $managerMock->shouldReceive('publish')
            ->once()
            ->andThrow(new Exception('Connection refused'));

        $txid = $log->publishToBlockchain();

        expect($txid)->toBeNull();
        expect($log->exists)->toBeTrue();
        expect($log->violation_type)->toBe('hash_mismatch');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// StreamEnums — INTEGRITY_VIOLATIONS
// ═══════════════════════════════════════════════════════════════════════

describe('StreamEnums — INTEGRITY_VIOLATIONS', function () {
    it('has the INTEGRITY_VIOLATIONS case', function () {
        expect(StreamEnums::INTEGRITY_VIOLATIONS->value)->toBe('integrity.violations');
    });

    it('provides display name for INTEGRITY_VIOLATIONS', function () {
        expect(StreamEnums::INTEGRITY_VIOLATIONS->getDisplayName())->toBe('Integrity Violations');
    });

    it('provides description for INTEGRITY_VIOLATIONS', function () {
        $desc = StreamEnums::INTEGRITY_VIOLATIONS->getDescription();
        expect($desc)->toBeString();
        expect($desc)->toContain('audit trail');
    });

    it('returns INTEGRITY_VIOLATIONS in integrityStreams()', function () {
        $streams = StreamEnums::integrityStreams();
        expect($streams)->toContain(StreamEnums::INTEGRITY_VIOLATIONS);
    });

    it('is NOT classified as a procurement stream', function () {
        expect(StreamEnums::INTEGRITY_VIOLATIONS->isProcurementStream())->toBeFalse();
    });

    it('is NOT classified as a user stream', function () {
        expect(StreamEnums::INTEGRITY_VIOLATIONS->isUserStream())->toBeFalse();
    });

    it('is NOT classified as a file stream', function () {
        expect(StreamEnums::INTEGRITY_VIOLATIONS->isFileStream())->toBeFalse();
    });

    it('includes INTEGRITY_VIOLATIONS in all values', function () {
        expect(StreamEnums::values())->toContain('integrity.violations');
    });

    it('includes INTEGRITY_VIOLATIONS in options', function () {
        $options = StreamEnums::options();
        expect($options)->toHaveKey('integrity.violations');
        expect($options['integrity.violations'])->toBe('Integrity Violations');
    });
});
