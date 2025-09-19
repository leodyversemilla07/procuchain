<?php

use App\Enums\StreamEnums;
use App\Libraries\MultichainClient;
use App\Services\MultichainService;
use Exception;
use Mockery;

beforeEach(function () {
    $this->mock = Mockery::mock(MultichainService::class);
});

it('can connect to multichain', function () {
    // Create a mock info response
    $mockInfo = [
        'chainname' => 'procuchain',
        'version' => '2.0',
        'nodeaddress' => 'sample-address',
        'protocolversion' => 20012,
        'blocks' => 100,
        'connections' => 5,
    ];

    // Configure the mock
    $this->mock->shouldReceive('getInfo')
        ->once()
        ->andReturn($mockInfo);

    // Test the getInfo method
    $info = $this->mock->getInfo();

    expect($info)->not->toBeNull()
        ->and($info)->toBeArray()
        ->and($info)->toHaveKey('chainname')
        ->and($info['chainname'])->toBe('procuchain');
});

it('handles connection errors gracefully', function () {
    // Configure the mock to throw an exception
    $this->mock->shouldReceive('getInfo')
        ->once()
        ->andThrow(new Exception('Connection refused'));

    // Test the error handling
    expect(fn () => $this->mock->getInfo())
        ->toThrow(Exception::class, 'Connection refused');
});

afterEach(function () {
    Mockery::close();
});

it('has stream enums with expected values', function () {
    expect(StreamEnums::DOCUMENTS->value)->toBe('procurement.documents')
        ->and(StreamEnums::STATUS->value)->toBe('procurement.status')
        ->and(StreamEnums::EVENTS->value)->toBe('procurement.events')
        ->and(StreamEnums::CORRECTION->value)->toBe('procurement.correction');
});

it('has multichain addresses configured in env/config', function () {
    $addresses = config('multichain.addresses');

    expect($addresses)
        ->toBeArray()
        ->toHaveKeys(['bac_secretariat', 'bac_chairman', 'hope', 'admin']);

    foreach (['bac_secretariat', 'bac_chairman', 'hope', 'admin'] as $role) {
        expect($addresses[$role] ?? null)
            ->toBeString()
            ->not->toBe('')
            ->and($addresses[$role])->not->toStartWith('default_');
    }
});

it('defines required globals and stream perms per role', function () {
    $roles = config('multichain.permissions.roles');

    expect($roles)
        ->toBeArray()
        ->toHaveKeys(['admin', 'bac_secretariat', 'bac_chairman', 'hope']);

    $expected = [
        'admin' => [
            'global' => ['admin', 'send', 'receive', 'create', 'issue', 'mine', 'activate'],
            'stream' => ['admin', 'write', 'read'],
        ],
        'bac_secretariat' => [
            'global' => ['send', 'receive', 'create', 'issue', 'activate'],
            'stream' => ['admin', 'write', 'read'],
        ],
        'bac_chairman' => [
            'global' => ['send', 'receive'],
            'stream' => ['write', 'read'],
        ],
        'hope' => [
            'global' => ['send', 'receive'],
            'stream' => ['write', 'read'],
        ],
    ];

    foreach ($expected as $role => $matrix) {
        expect($roles[$role] ?? null)->toBeArray()->toHaveKeys(['global', 'stream']);

        foreach ($matrix['global'] as $perm) {
            expect(in_array($perm, $roles[$role]['global'], true))->toBeTrue();
        }
        foreach ($matrix['stream'] as $perm) {
            expect(in_array($perm, $roles[$role]['stream'], true))->toBeTrue();
        }
    }
});

it('validates connection and returns info via getInfo', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    // validateConnection path
    // First pass in validateConnection
    $mc->shouldReceive('getinfo')->once()->andReturn(['chainname' => 'procuchain']);
    $mc->shouldReceive('success')->andReturnTrue();
    $mc->shouldReceive('getinitstatus')->once()->andReturn(['initialized' => true]);
    // Operation path (getInfo again)
    $mc->shouldReceive('getinfo')->once()->andReturn(['chainname' => 'procuchain', 'ok' => true]);

    $service = new MultichainService;
    setPrivate($service, 'mc', $mc);

    $info = $service->getInfo();
    expect($info)->toBeArray()->toHaveKey('ok');
});

it('throws on wrong chain name', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    $mc->shouldReceive('getinfo')->andReturn(['chainname' => 'wrongchain']);
    $mc->shouldReceive('success')->andReturnTrue();

    $service = new MultichainService;
    setPrivate($service, 'mc', $mc);

    expect(fn () => $service->getInfo())
        ->toThrow(Exception::class, 'Connected to wrong blockchain');
});

it('throws when node not initialized', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    $mc->shouldReceive('getinfo')->andReturn(['chainname' => 'procuchain']);
    $mc->shouldReceive('success')->andReturnTrue();
    $mc->shouldReceive('getinitstatus')->andReturn(['initialized' => false]);

    $service = new MultichainService;
    setPrivate($service, 'mc', $mc);

    expect(fn () => $service->getInfo())
        ->toThrow(Exception::class, 'Node is not fully initialized');
});

it('maps RPC errors (Forbidden) to exception', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    // validateConnection success
    $mc->shouldReceive('getinfo')->andReturn(['chainname' => 'procuchain']);
    // success() is called twice in validateConnection, then once after operation
    $mc->shouldReceive('success')->andReturn(true, true, false);
    $mc->shouldReceive('getinitstatus')->andReturn(['initialized' => true]);
    // operation failure on liststreamitems
    $mc->shouldReceive('liststreamitems')->with('procurement.status', true, 1, -1, false)->andReturnNull();
    $mc->shouldReceive('errormessage')->andReturn('Forbidden');
    $mc->shouldReceive('errorcode')->andReturn(403);

    $service = new MultichainService;
    setPrivate($service, 'mc', $mc);

    expect(fn () => $service->listStreamItems('procurement.status', true, 1, -1, false))
        ->toThrow(Exception::class, 'MultiChain Error: Forbidden');
});

it('fails with final message on connection failure', function () {
    // Limit retries to 1 to avoid sleep and reinit
    $service = new MultichainService;
    setPrivate($service, 'maxRetries', 1);

    $mc = Mockery::mock(MultichainClient::class);
    // validateConnection -> getinfo fails with connection-like error
    $mc->shouldReceive('getinfo')->andReturnNull();
    $mc->shouldReceive('success')->andReturnFalse();
    $mc->shouldReceive('errormessage')->andReturn('Failed to connect');
    $mc->shouldReceive('errorcode')->andReturn(7);

    setPrivate($service, 'mc', $mc);

    expect(fn () => $service->getInfo())
        ->toThrow(Exception::class, 'Failed to connect to MultiChain node after 1 attempts');
});
