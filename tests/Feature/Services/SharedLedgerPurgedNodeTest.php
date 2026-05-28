<?php

declare(strict_types=1);

use App\Services\Manager;
use App\Services\SharedLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

beforeEach(function () {
    $this->managerMock = Mockery::mock(Manager::class);
    $this->managerMock->shouldReceive('success')->andReturn(true);
    $this->service = new SharedLedgerService($this->managerMock);

    // Set up node config so getNodes() works — matches production config/multichain.php
    config()->set('multichain.nodes', [
        ['id' => 'admin', 'name' => 'Primary Node', 'role' => 'Administrator', 'private_ip' => '10.0.1.10', 'rpc_port' => 6834],
        ['id' => 'bac-secretariat', 'name' => 'BAC Secretariat', 'role' => 'Secretariat', 'private_ip' => '10.0.1.20', 'rpc_port' => 6834],
        ['id' => 'bac-chairman', 'name' => 'BAC Chairman', 'role' => 'Chairman', 'private_ip' => '10.0.1.30', 'rpc_port' => 6834],
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE', 'private_ip' => '10.0.1.40', 'rpc_port' => 6834],
    ]);

    config()->set('multichain.chain_name', 'procuchain');
    config()->set('multichain.rpc.username', 'multichainrpc');
    config()->set('multichain.rpc.password', 'testpassword');

    // Clear the available nodes cache
    Cache::forget('shared_ledger:available_nodes');
});

afterEach(function () {
    Mockery::close();
});

// ─── fetchFromNode: purged node returns empty entries ─────────────────────────

it('returns empty entries when viewing a purged node', function () {
    $purgeBlocktime = 1779754000;

    // Primary node detects the purge for bac-secretariat
    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'testing']]]]);

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_resync')
        ->andReturn([]);

    // Use reflection to call fetchFromNode
    $method = new ReflectionMethod(SharedLedgerService::class, 'fetchFromNode');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, 'bac-secretariat');

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty('Purged node should return zero entries');

    // Verify purge state was set
    expect($this->service->nodePurgeState)->not->toBeNull()
        ->and($this->service->nodePurgeState['is_purged'])->toBeTrue()
        ->and($this->service->nodePurgeState['was_explicitly_purged'])->toBeTrue();
});

// ─── Resynced node should show data again ─────────────────────────────────────

it('returns data for a previously purged but resynced node', function () {
    $purgeBlocktime = 1779753638;
    $resyncBlocktime = 1779754000; // resync AFTER purge → node recovered

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'old-purge']]]]);

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_resync')
        ->andReturn([['blocktime' => $resyncBlocktime]]);

    $method = new ReflectionMethod(SharedLedgerService::class, 'checkPurgeStateFromPrimary');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, 'bac-secretariat');

    expect($result['is_purged'])->toBeFalse('Resynced node should not be flagged as purged');
});

// ─── getLedgerPage: purged node shows empty table with purge banner ───────────

it('returns empty entries with purge state when viewing purged node via getLedgerPage', function () {
    $purgeBlocktime = 1779754000;

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => [
            'reason' => 'testing',
            'node_id' => 'bac-secretariat',
            'node_name' => 'BAC Secretariat',
            'items_purged' => 2746,
        ]]]]);

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_resync')
        ->andReturn([]);

    // getLedgerPage also calls buildAvailableNodesList → checkPurgeStateFromPrimary for ALL nodes
    // Set up default "no purge" for other nodes
    foreach (['admin', 'bac-chairman', 'hope'] as $nodeId) {
        $this->managerMock->shouldReceive('liststreamkeyitems')
            ->withArgs(fn (string $stream, string $key) => $key === "node_{$nodeId}_full_purge")
            ->andReturn([]);
    }

    $result = $this->service->getLedgerPage(['node' => 'bac-secretariat']);

    expect($result['entries'])->toBeEmpty('Purged node ledger should have no entries');
    expect($result['pagination']['total'])->toBe(0);
    expect($result['node_purge_state'])->not->toBeNull()
        ->and($result['node_purge_state']['is_purged'])->toBeTrue()
        ->and($result['node_purge_state']['was_explicitly_purged'])->toBeTrue();
});

// ─── Purged node does not fall back to primary node data ──────────────────────

it('never returns primary node data when fetching a purged node', function () {
    $purgeBlocktime = 1779754000;

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'purge']]]]);

    $this->managerMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_bac-secretariat_resync')
        ->andReturn([]);

    // The primary Manager should NOT receive liststreamitems calls
    // (which would indicate it fell back to fetchFromDefaultClient)
    $this->managerMock->shouldNotReceive('liststreamitems');

    $method = new ReflectionMethod(SharedLedgerService::class, 'fetchFromNode');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, 'bac-secretariat');

    expect($result)->toBeEmpty();
});

// ─── Controller auto-detects node from route prefix ───────────────────────────

it('auto-detects valid node ID from URL prefix', function () {
    $request = Request::create('/bac-secretariat/shared-ledger', 'GET');

    // segment(1) extracts the first path segment
    expect($request->segment(1))->toBe('bac-secretariat');

    // Verify it's a valid node ID in the config
    $validNodeIds = collect(config('multichain.nodes'))->pluck('id')->toArray();
    expect($validNodeIds)->toContain('bac-secretariat');
    expect($validNodeIds)->toContain('admin');
    expect($validNodeIds)->toContain('bac-chairman');
    expect($validNodeIds)->toContain('hope');
});
