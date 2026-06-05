<?php

use App\Services\Manager;
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
    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('isConnectionError');
    $method->setAccessible(true);

    expect($method->invoke($manager, 'Connection refused'))->toBeTrue();
    expect($method->invoke($manager, 'Connection timed out'))->toBeTrue();
    expect($method->invoke($manager, 'Could not connect to host'))->toBeTrue();
    expect($method->invoke($manager, 'Failed to connect'))->toBeTrue();
    expect($method->invoke($manager, 'Network is unreachable'))->toBeTrue();
    expect($method->invoke($manager, 'Operation timed out'))->toBeTrue();
    expect($method->invoke($manager, 'Connection reset by peer'))->toBeTrue();
    expect($method->invoke($manager, 'Unable to connect'))->toBeTrue();
});

it('does not treat non-connection errors as connection errors', function () {
    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('isConnectionError');
    $method->setAccessible(true);

    expect($method->invoke($manager, 'Invalid parameter'))->toBeFalse();
    expect($method->invoke($manager, 'Stream not found'))->toBeFalse();
    expect($method->invoke($manager, 'Permission denied'))->toBeFalse();
});

it('detects RPC -703 as failover-eligible (node purged/unsubscribed)', function () {
    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('isFailoverEligibleError');
    $method->setAccessible(true);

    expect($method->invoke($manager, -703, 'Not subscribed'))->toBeTrue();
    expect($method->invoke($manager, -701, 'Invalid parameter'))->toBeTrue();
    expect($method->invoke($manager, -1, 'Connection refused'))->toBeTrue();
    expect($method->invoke($manager, -32600, 'Parse error'))->toBeFalse();
});

// ─── Fallback Node Selection ────────────────────────────────────────

it('excludes the active node from fallback candidates', function () {
    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    // The primary node (admin at 10.0.1.10:6834) should be excluded
    // since the Manager connects to it on construction
    $fallbacks = $method->invoke($manager);

    $fallbackIps = array_map(fn ($n) => $n['private_ip'], $fallbacks);

    expect($fallbackIps)->not->toContain('10.0.1.10');
    expect($fallbackIps)->toContain('10.0.1.20');
    expect($fallbackIps)->toContain('10.0.1.30');
    expect($fallbackIps)->toContain('10.0.1.40');
});

it('returns empty array when no fallback nodes are configured', function () {
    config(['multichain.nodes' => []]);

    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    $fallbacks = $method->invoke($manager);

    expect($fallbacks)->toBeEmpty();
});

// ─── Active Node Tracking ───────────────────────────────────────────

it('starts with primary as the active node', function () {
    $manager = new Manager;

    expect($manager->getActiveNodeId())->toBe('primary');
    expect($manager->isFailedOver())->toBeFalse();
});

it('reports failed-over state after switching to a peer', function () {
    $manager = new Manager;

    $ref = new ReflectionClass($manager);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($manager, 'bac-secretariat');

    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($manager, true);

    expect($manager->getActiveNodeId())->toBe('bac-secretariat');
    expect($manager->isFailedOver())->toBeTrue();
});

// ─── Primary Recheck ────────────────────────────────────────────────

it('skips primary recheck when not failed over', function () {
    $manager = new Manager;

    $ref = new ReflectionClass($manager);
    $method = $ref->getMethod('tryPromotePrimaryBack');
    $method->setAccessible(true);

    // Should not throw or change state
    $method->invoke($manager);

    expect($manager->getActiveNodeId())->toBe('primary');
    expect($manager->isFailedOver())->toBeFalse();
});

it('throttles primary recheck based on interval', function () {
    $manager = new Manager;

    $ref = new ReflectionClass($manager);

    // Simulate failed-over state
    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($manager, true);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($manager, 'bac-secretariat');

    // Set verifiedAt to "just now" so recheck is throttled
    $verifiedAtProp = $ref->getProperty('activeNodeVerifiedAt');
    $verifiedAtProp->setAccessible(true);
    $verifiedAtProp->setValue($manager, time());

    $method = $ref->getMethod('tryPromotePrimaryBack');
    $method->setAccessible(true);

    // Should not switch back because interval hasn't elapsed
    $method->invoke($manager);

    expect($manager->getActiveNodeId())->toBe('bac-secretariat');
    expect($manager->isFailedOver())->toBeTrue();
});

// ─── End-to-End Failover Scenarios ──────────────────────────────────

it('throws exception when all nodes are down', function () {
    // Configure nodes with unreachable IPs (all same as primary)
    config(['multichain.nodes' => [
        [
            'id' => 'admin',
            'private_ip' => '10.0.1.10',
            'rpc_port' => 6834,
        ],
    ]]);

    $manager = new Manager;

    // The Manager tries to call getinfo on the primary,
    // which will fail in test env (no real MultiChain node)
    $this->expectException(Exception::class);

    $manager->getinfo();
});

it('excludes the currently-active node when building fallback list after failover', function () {
    $manager = new Manager;

    $ref = new ReflectionClass($manager);

    // Simulate that we've failed over to bac-secretariat
    $failedOverProp = $ref->getProperty('failedOver');
    $failedOverProp->setAccessible(true);
    $failedOverProp->setValue($manager, true);

    $nodeIdProp = $ref->getProperty('activeNodeId');
    $nodeIdProp->setAccessible(true);
    $nodeIdProp->setValue($manager, 'bac-secretariat');

    // getFallbackNodes uses getActiveHost/getActivePort which look up from
    // config based on activeNodeId, so they'll resolve to 10.0.1.20
    $method = $ref->getMethod('getFallbackNodes');
    $method->setAccessible(true);

    $fallbacks = $method->invoke($manager);
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

    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $prop = $ref->getProperty('primaryRecheckInterval');
    $prop->setAccessible(true);

    expect($prop->getValue($manager))->toBe(120);
});

it('defaults primary_recheck_interval to 60 seconds when config is null', function () {
    config(['multichain.primary_recheck_interval' => null]);

    $manager = new Manager;
    $ref = new ReflectionClass($manager);
    $prop = $ref->getProperty('primaryRecheckInterval');
    $prop->setAccessible(true);

    // null ?? 60 = 60, then (int)60 = 60
    expect($prop->getValue($manager))->toBe(60);
});
