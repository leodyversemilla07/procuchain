<?php

use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Log::spy();

    // Set up test config values
    config([
        'multichain.rpc.host' => '10.0.1.10',
        'multichain.rpc.port' => 6834,
        'multichain.rpc.username' => 'testuser',
        'multichain.rpc.password' => 'testpass',
        'multichain.chain_name' => 'procuchain',
        'multichain.use_ssl' => false,
        'multichain.verify_ssl' => false,
        'multichain.web_max_retries' => 1,
        'multichain.max_retries' => 1,
        'multichain.retry_delay' => 0,
        'multichain.web_connection_timeout' => 1,
        'multichain.connection_timeout' => 1,
        'multichain.primary_recheck_interval' => 60,
        'multichain.nodes' => [
            [
                'id' => 'admin',
                'name' => 'Primary Node',
                'role' => 'Administrator',
                'private_ip' => '10.0.1.10',
                'rpc_port' => 6834,
            ],
            [
                'id' => 'bac-secretariat',
                'name' => 'BAC Secretariat',
                'role' => 'Secretariat',
                'private_ip' => '10.0.1.20',
                'rpc_port' => 6834,
            ],
            [
                'id' => 'bac-chairman',
                'name' => 'BAC Chairman',
                'role' => 'Chairman',
                'private_ip' => '10.0.1.30',
                'rpc_port' => 6834,
            ],
            [
                'id' => 'hope',
                'name' => 'HOPE',
                'role' => 'HOPE',
                'private_ip' => '10.0.1.40',
                'rpc_port' => 6834,
            ],
        ],
    ]);
});

// ─── Failover Detection ─────────────────────────────────────────────

it('detects connection errors as failover-eligible', function () {
    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('isConnectionError');
    $method->setAccessible(true);

    expect($method->invoke($blockchainRpcClient, 'Connection refused'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Connection timed out'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Could not connect to host'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Failed to connect'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Network is unreachable'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Operation timed out'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Connection reset by peer'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, 'Unable to connect'))->toBeTrue();
});

it('does not treat non-connection errors as connection errors', function () {
    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('isConnectionError');
    $method->setAccessible(true);

    expect($method->invoke($blockchainRpcClient, 'Invalid parameter'))->toBeFalse();
    expect($method->invoke($blockchainRpcClient, 'Stream not found'))->toBeFalse();
    expect($method->invoke($blockchainRpcClient, 'Permission denied'))->toBeFalse();
});

it('detects RPC -703 as failover-eligible (node purged/unsubscribed)', function () {
    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('isFailoverEligibleError');
    $method->setAccessible(true);

    expect($method->invoke($blockchainRpcClient, -703, 'Not subscribed'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, -701, 'Invalid parameter'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, -1, 'Connection refused'))->toBeTrue();
    expect($method->invoke($blockchainRpcClient, -32600, 'Parse error'))->toBeFalse();
});

// ─── Fallback Node Selection ────────────────────────────────────────

it('excludes the active node from fallback candidates', function () {
    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    // The primary node (admin at 10.0.1.10:6834) should be excluded
    // since the BlockchainRpcClient connects to it on construction
    $fallbacks = $method->invoke($blockchainRpcClient);

    $fallbackIps = array_map(fn ($n) => $n['private_ip'], $fallbacks);

    expect($fallbackIps)->not->toContain('10.0.1.10');
    expect($fallbackIps)->toContain('10.0.1.20');
    expect($fallbackIps)->toContain('10.0.1.30');
    expect($fallbackIps)->toContain('10.0.1.40');
});

it('returns empty array when no fallback nodes are configured', function () {
    config(['multichain.nodes' => []]);

    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    $fallbacks = $method->invoke($blockchainRpcClient);

    expect($fallbacks)->toBeEmpty();
});

// ─── Active Node Tracking ───────────────────────────────────────────

it('starts with primary as the active node', function () {
    $blockchainRpcClient = new BlockchainRpcClient;

    expect($blockchainRpcClient->getActiveNodeId())->toBe('primary');
    expect($blockchainRpcClient->isFailedOver())->toBeFalse();
});

it('reports failed-over state after switching to a peer', function () {
    $blockchainRpcClient = new BlockchainRpcClient;

    $ref = new ReflectionClass($blockchainRpcClient);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($blockchainRpcClient, 'bac-secretariat');

    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($blockchainRpcClient, true);

    expect($blockchainRpcClient->getActiveNodeId())->toBe('bac-secretariat');
    expect($blockchainRpcClient->isFailedOver())->toBeTrue();
});

// ─── Primary Recheck ────────────────────────────────────────────────

it('skips primary recheck when not failed over', function () {
    $blockchainRpcClient = new BlockchainRpcClient;

    $ref = new ReflectionClass($blockchainRpcClient);
    $method = $ref->getMethod('tryPromotePrimaryBack');
    $method->setAccessible(true);

    // Should not throw or change state
    $method->invoke($blockchainRpcClient);

    expect($blockchainRpcClient->getActiveNodeId())->toBe('primary');
    expect($blockchainRpcClient->isFailedOver())->toBeFalse();
});

it('throttles primary recheck based on interval', function () {
    $blockchainRpcClient = new BlockchainRpcClient;

    $ref = new ReflectionClass($blockchainRpcClient);

    // Simulate failed-over state
    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($blockchainRpcClient, true);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($blockchainRpcClient, 'bac-secretariat');

    // Set verifiedAt to "just now" so recheck is throttled
    $verifiedAtProp = $ref->getProperty('activeNodeVerifiedAt');
    $verifiedAtProp->setAccessible(true);
    $verifiedAtProp->setValue($blockchainRpcClient, time());

    $method = $ref->getMethod('tryPromotePrimaryBack');
    $method->setAccessible(true);

    // Should not switch back because interval hasn't elapsed
    $method->invoke($blockchainRpcClient);

    expect($blockchainRpcClient->getActiveNodeId())->toBe('bac-secretariat');
    expect($blockchainRpcClient->isFailedOver())->toBeTrue();
});

// ─── End-to-End Failover Scenarios ──────────────────────────────────

it('throws exception when all nodes are down', function () {
    // This test makes real network calls to unreachable IPs which hang on Windows.
    // Skip in CI or when blockchain node is unavailable.
    if (env('CI') || ! env('MULTICHAIN_RPC_HOST')) {
        $this->markTestSkipped('Requires a real MultiChain node connection');
    }

    config(['multichain.nodes' => [
        [
            'id' => 'admin',
            'private_ip' => '10.0.1.10',
            'rpc_port' => 6834,
        ],
    ]]);

    $blockchainRpcClient = new BlockchainRpcClient;

    $this->expectException(Exception::class);

    $blockchainRpcClient->getinfo();
});

it('excludes the currently-active node when building fallback list after failover', function () {
    $blockchainRpcClient = new BlockchainRpcClient;

    $ref = new ReflectionClass($blockchainRpcClient);

    // Simulate that we've failed over to bac-secretariat
    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($blockchainRpcClient, true);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($blockchainRpcClient, 'bac-secretariat');

    // getFallbackNodes uses getActiveHost/getActivePort which look up from
    // config based on activeNodeId, so they'll resolve to 10.0.1.20
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    $fallbacks = $method->invoke($blockchainRpcClient);
    $fallbackIps = array_map(fn ($n) => $n['private_ip'] ?? '', $fallbacks);

    // bac-secretariat (10.0.1.20) should be excluded since it's the active node
    expect($fallbackIps)->not->toContain('10.0.1.20');
    expect($fallbackIps)->toContain('10.0.1.10'); // admin is back as a candidate
    expect($fallbackIps)->toContain('10.0.1.30'); // bac-chairman
    expect($fallbackIps)->toContain('10.0.1.40'); // hope
});

// ─── Config Integration ─────────────────────────────────────────────

it('reads primary_recheck_interval from config', function () {
    config(['multichain.primary_recheck_interval' => 120]);

    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $prop = $ref->getProperty('primaryRecheckInterval');
    $prop->setAccessible(true);

    expect($prop->getValue($blockchainRpcClient))->toBe(120);
});

it('defaults primary_recheck_interval to 60 seconds when config is null', function () {
    config(['multichain.primary_recheck_interval' => null]);

    $blockchainRpcClient = new BlockchainRpcClient;
    $ref = new ReflectionClass($blockchainRpcClient);
    $prop = $ref->getProperty('primaryRecheckInterval');
    $prop->setAccessible(true);

    // null ?? 60 = 60, then (int)60 = 60
    expect($prop->getValue($blockchainRpcClient))->toBe(60);
});
