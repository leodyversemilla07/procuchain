<?php

use App\Enums\Stream;
use App\Models\AuditLog;
use App\Models\DocumentViewLog;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\BlockchainRpcClient;
use App\Services\BlockchainSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
});

// ═══════════════════════════════════════════════════════════════════════
// Stream — New Audit Streams
// ═══════════════════════════════════════════════════════════════════════

describe('Stream — New Audit Streams', function () {
    it('has AUDIT_TRAIL stream', function () {
        expect(Stream::AUDIT_TRAIL->value)->toBe('audit.trail');
        expect(Stream::AUDIT_TRAIL->getDisplayName())->toBe('Audit Trail');
    });

    it('has DOCUMENT_ACCESS stream', function () {
        expect(Stream::DOCUMENT_ACCESS->value)->toBe('document.access');
        expect(Stream::DOCUMENT_ACCESS->getDisplayName())->toBe('Document Access');
    });

    it('has CONFIG_WORKFLOWS stream', function () {
        expect(Stream::CONFIG_WORKFLOWS->value)->toBe('config.workflows');
        expect(Stream::CONFIG_WORKFLOWS->getDisplayName())->toBe('Workflow Configurations');
    });

    it('has CONFIG_STAGE_DOCS stream', function () {
        expect(Stream::CONFIG_STAGE_DOCS->value)->toBe('config.stage_docs');
        expect(Stream::CONFIG_STAGE_DOCS->getDisplayName())->toBe('Stage Document Configurations');
    });

    it('has USER_LOGIN_SESSIONS stream', function () {
        expect(Stream::USER_LOGIN_SESSIONS->value)->toBe('user.login_sessions');
        expect(Stream::USER_LOGIN_SESSIONS->getDisplayName())->toBe('User Login Sessions');
    });

    it('returns all audit streams', function () {
        $streams = Stream::auditStreams();

        expect($streams)->toHaveCount(5);
        expect($streams)->toContain(Stream::AUDIT_TRAIL);
        expect($streams)->toContain(Stream::DOCUMENT_ACCESS);
        expect($streams)->toContain(Stream::CONFIG_WORKFLOWS);
        expect($streams)->toContain(Stream::CONFIG_STAGE_DOCS);
        expect($streams)->toContain(Stream::USER_LOGIN_SESSIONS);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainSyncService — Publish
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainSyncService — Publish', function () {
    it('publishes model data to blockchain and updates blockchain columns', function () {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'test.action',
            'subject_type' => 'user',
            'subject_id' => '1',
        ]);

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('publish')
            ->once()
            ->with(
                Stream::AUDIT_TRAIL->value,
                (string) $auditLog->id,
                Mockery::on(fn ($data) => isset($data['json']['action']) && $data['json']['action'] === 'test.action')
            )
            ->andReturn('test-chain-txid-123');

        $txid = app(BlockchainSyncService::class)->publish($auditLog, Stream::AUDIT_TRAIL);

        expect($txid)->toBe('test-chain-txid-123');

        $auditLog->refresh();
        expect($auditLog->txid)->toBe('test-chain-txid-123');
        expect($auditLog->data_hash)->not->toBeNull();
        expect($auditLog->blockchain_synced_at)->not->toBeNull();
    });

    it('returns null and does not throw when blockchain publish fails', function () {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'test.fail',
        ]);

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andReturn(null);

        $txid = app(BlockchainSyncService::class)->publish($auditLog, Stream::AUDIT_TRAIL);

        expect($txid)->toBeNull();
    });

    it('excludes blockchain metadata from payload', function () {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'test.payload',
            'txid' => 'existing-txid',
            'data_hash' => 'existing-hash',
        ]);

        $capturedPayload = null;

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('publish')
            ->once()
            ->with(
                Stream::AUDIT_TRAIL->value,
                Mockery::type('string'),
                Mockery::on(function ($data) use (&$capturedPayload) {
                    $capturedPayload = $data['json'];

                    return true;
                })
            )
            ->andReturn('payload-txid');

        app(BlockchainSyncService::class)->publish($auditLog, Stream::AUDIT_TRAIL);

        expect($capturedPayload)->not->toBeNull();
        expect($capturedPayload)->not->toHaveKey('txid');
        expect($capturedPayload)->not->toHaveKey('data_hash');
        expect($capturedPayload)->not->toHaveKey('blockchain_synced_at');
        expect($capturedPayload)->toHaveKey('action');
    });

    it('uses custom stream key when provided', function () {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'test.custom_key',
        ]);

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('publish')
            ->once()
            ->with(
                Stream::AUDIT_TRAIL->value,
                'custom-key-123',
                Mockery::type('array')
            )
            ->andReturn('custom-txid');

        $txid = app(BlockchainSyncService::class)->publish($auditLog, Stream::AUDIT_TRAIL, 'custom-key-123');

        expect($txid)->toBe('custom-txid');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainSyncService — Restore
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainSyncService — Restore', function () {
    it('restores table from blockchain', function () {
        $chainItems = [
            [
                'txid' => 'restore-tx-1',
                'data' => ['json' => [
                    'user_id' => null,
                    'action' => 'restored.action',
                    'subject_type' => 'user',
                    'subject_id' => '1',
                ]],
            ],
            [
                'txid' => 'restore-tx-2',
                'data' => ['json' => [
                    'user_id' => null,
                    'action' => 'restored.action2',
                ]],
            ],
        ];

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('liststreamitems')
            ->once()
            ->with(Stream::AUDIT_TRAIL->value, true, 100000)
            ->andReturn($chainItems);

        $result = app(BlockchainSyncService::class)->restoreTable(
            'audit_logs',
            Stream::AUDIT_TRAIL,
            AuditLog::class,
        );

        expect($result['imported'])->toBe(2);
        expect($result['errors'])->toBe(0);

        $restored1 = DB::table('audit_logs')->where('txid', 'restore-tx-1')->first();
        expect($restored1)->not->toBeNull();
        expect($restored1->action)->toBe('restored.action');

        $restored2 = DB::table('audit_logs')->where('txid', 'restore-tx-2')->first();
        expect($restored2)->not->toBeNull();
        expect($restored2->action)->toBe('restored.action2');
    });

    it('skips duplicates during restore', function () {
        AuditLog::create([
            'user_id' => null,
            'action' => 'existing.action',
            'txid' => 'existing-txid',
            'data_hash' => 'existing-hash',
            'blockchain_synced_at' => now(),
        ]);

        $chainItems = [
            [
                'txid' => 'existing-txid',
                'data' => ['json' => ['action' => 'should.skip']],
            ],
        ];

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldReceive('liststreamitems')
            ->once()
            ->andReturn($chainItems);

        $result = app(BlockchainSyncService::class)->restoreTable(
            'audit_logs',
            Stream::AUDIT_TRAIL,
            AuditLog::class,
        );

        expect($result['skipped'])->toBe(1);
        expect($result['imported'])->toBe(0);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Observer Integration — AuditLog
// ═══════════════════════════════════════════════════════════════════════

describe('Observer Integration — AuditLog', function () {
    it('skips automatic publish during unit tests when AuditLog is created', function () {
        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldNotReceive('publish');

        $log = AuditLog::create([
            'user_id' => null,
            'action' => 'observer.test',
        ]);

        expect($log->txid)->toBeNull();
    });

    it('skips publish if txid already set', function () {
        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldNotReceive('publish');

        AuditLog::create([
            'user_id' => null,
            'action' => 'observer.skip',
            'txid' => 'already-set',
            'data_hash' => 'existing-hash',
            'blockchain_synced_at' => now(),
        ]);
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Observer Integration — DocumentViewLog
// ═══════════════════════════════════════════════════════════════════════

describe('Observer Integration — DocumentViewLog', function () {
    it('skips automatic publish during unit tests when DocumentViewLog is created', function () {
        $user = User::factory()->create();

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldNotReceive('publish');

        $view = DocumentViewLog::create([
            'user_id' => $user->id,
            'file_key' => 'test-File-key',
            'pr_number' => 'PR-TEST-001',
            'ip_address' => '127.0.0.1',
            'viewed_at' => now(),
        ]);

        expect($view->txid)->toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Observer Integration — UserLoginLog
// ═══════════════════════════════════════════════════════════════════════

describe('Observer Integration — UserLoginLog', function () {
    it('skips automatic publish during unit tests when UserLoginLog is created', function () {
        $user = User::factory()->create();

        $mock = $this->mock(BlockchainRpcClient::class);
        $mock->shouldNotReceive('publish');

        $log = UserLoginLog::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'login_at' => now(),
        ]);

        expect($log->txid)->toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════
// BlockchainSyncService — Compute Hash
// ═══════════════════════════════════════════════════════════════════════

describe('BlockchainSyncService — Compute Hash', function () {
    it('computes consistent SHA-256 hash', function () {
        $service = app(BlockchainSyncService::class);

        $data = ['action' => 'test', 'user_id' => 1];
        $hash1 = $service->computeHash($data);
        $hash2 = $service->computeHash($data);

        expect($hash1)->toBe($hash2);
        expect(strlen($hash1))->toBe(64);
    });
});
