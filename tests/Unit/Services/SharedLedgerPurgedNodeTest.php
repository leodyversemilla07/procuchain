<?php

declare(strict_types=1);

use App\Services\BlockchainRpcClient;
use App\Services\NodePurgeDetector;
use App\Services\SharedLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->blockchainRpcClientMock = Mockery::mock(BlockchainRpcClient::class);
    $this->blockchainRpcClientMock->shouldReceive('success')->zeroOrMoreTimes()->andReturn(true);
    $this->service = new SharedLedgerService($this->blockchainRpcClientMock);

    // Set up node config so getNodes() works
    config()->set('multichain.nodes', [
        ['id' => 'admin', 'name' => 'Primary Node', 'role' => 'Administrator', 'private_ip' => '10.0.1.10', 'rpc_port' => 6834],
        ['id' => 'bac-sec', 'name' => 'BAC Secretariat', 'role' => 'Secretariat', 'private_ip' => '10.0.1.20', 'rpc_port' => 6834],
        ['id' => 'bac-chairman', 'name' => 'BAC Chairman', 'role' => 'Chairman', 'private_ip' => '10.0.1.30', 'rpc_port' => 6834],
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE', 'private_ip' => '10.0.1.40', 'rpc_port' => 6834],
    ]);

    config()->set('multichain.chain_name', 'procuchain');
    config()->set('multichain.rpc.username', 'multichainrpc');
    config()->set('multichain.rpc.password', 'testpassword');

    Cache::forget('shared_ledger:available_nodes');
});

afterEach(function () {
    Mockery::close();
});

// ─── fetchFromNode: purged node returns empty entries ─────────────────────────

it('returns empty entries when viewing a purged node', function () {
    $purgeBlocktime = 1779754000;

    // Primary node detects the purge
    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_full_purge')
        ->once()
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'Demo: full node purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_resync')
        ->once()
        ->andReturn([]);

    // Use reflection to call fetchFromNode
    $method = new ReflectionMethod(SharedLedgerService::class, 'fetchFromNode');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, 'bac-sec');

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty('Purged node should return zero entries');

    expect($this->service->nodePurgeState)->not->toBeNull()
        ->and($this->service->nodePurgeState['is_purged'])->toBeTrue()
        ->and($this->service->nodePurgeState['was_explicitly_purged'])->toBeTrue();
});

it('does not call fetchFromDefaultClient for purged nodes', function () {
    $purgeBlocktime = 1779754000;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_full_purge')
        ->once()
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_resync')
        ->once()
        ->andReturn([]);

    $this->blockchainRpcClientMock->shouldNotReceive('liststreamitems');

    $method = new ReflectionMethod(SharedLedgerService::class, 'fetchFromNode');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, 'bac-sec');

    expect($result)->toBeEmpty();
});

// ─── fetchFromAllNodes: skips purged nodes ────────────────────────────────────

it('skips purged nodes when fetching from all nodes', function () {
    $purgeBlocktime = 1779754000;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_full_purge')
        ->once()
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_resync')
        ->once()
        ->andReturn([]);

    $fetchFromNode = new ReflectionMethod(SharedLedgerService::class, 'fetchFromNode');
    $fetchFromNode->setAccessible(true);

    $result = $fetchFromNode->invoke($this->service, 'bac-sec');
    expect($result)->toBeEmpty('Purged node should not show any ledger data');
});

// ─── Resynced node should show data again ─────────────────────────────────────

it('returns data for a previously purged but resynced node', function () {
    $purgeBlocktime = 1779753638;
    $resyncBlocktime = 1779754000;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_full_purge')
        ->once()
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'old-purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_resync')
        ->once()
        ->andReturn([['blocktime' => $resyncBlocktime]]);

    $detector = new NodePurgeDetector($this->blockchainRpcClientMock);
    $result = $detector->checkPurgeStateFromPrimary('bac-sec');

    expect($result['is_purged'])->toBeFalse('Resynced node should not be flagged as purged');
});

// ─── getLedgerPage: purged node shows empty table with purge banner ───────────

it('returns empty entries with purge state when viewing purged node via getLedgerPage', function () {
    $purgeBlocktime = 1779754000;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_full_purge')
        ->zeroOrMoreTimes()
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => [
            'reason' => 'Demo: full node purge',
            'node_id' => 'bac-sec',
            'node_name' => 'BAC Secretariat',
            'items_purged' => 42,
        ]]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-sec_resync')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    foreach (['admin', 'bac-chairman', 'hope'] as $nodeId) {
        $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
            ->withArgs(fn (string $stream, string $key) => $key === "node_{$nodeId}_full_purge")
            ->zeroOrMoreTimes()
            ->andReturn([]);
    }

    $result = $this->service->getLedgerPage(['node' => 'bac-sec']);

    expect($result['entries'])->toBeEmpty('Purged node ledger should have no entries');
    expect($result['pagination']['total'])->toBe(0);
    expect($result['node_purge_state'])->not->toBeNull()
        ->and($result['node_purge_state']['is_purged'])->toBeTrue()
        ->and($result['node_purge_state']['was_explicitly_purged'])->toBeTrue();
});
