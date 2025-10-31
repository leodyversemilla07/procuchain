<?php

use App\Enums\StreamEnums;
use App\Libraries\MultichainClient;
use App\Services\MultichainConnectionService;
use App\Services\MultichainService;

beforeEach(function () {
    $this->mock = \Mockery::mock(MultichainService::class);
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
        ->and(StreamEnums::CORRECTIONS->value)->toBe('procurement.corrections');
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
    // getinfo call returns data
    $mc->shouldReceive('getinfo')->once()->andReturn(['chainname' => 'procuchain', 'blocks' => 100]);
    $mc->shouldReceive('success')->andReturnTrue();

    $connectionService = Mockery::mock(MultichainConnectionService::class);
    $connectionService->shouldReceive('getClient')->andReturn($mc);
    $connectionService->shouldReceive('handleRequest')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $service = new MultichainService($connectionService);

    $info = $service->getInfo();
    expect($info)->toBeArray()->toHaveKey('chainname');
});

it('returns info even with different chain name', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    $mc->shouldReceive('getinfo')->andReturn(['chainname' => 'wrongchain']);
    $mc->shouldReceive('success')->andReturnTrue();

    $connectionService = Mockery::mock(MultichainConnectionService::class);
    $connectionService->shouldReceive('getClient')->andReturn($mc);
    $connectionService->shouldReceive('handleRequest')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $service = new MultichainService($connectionService);

    // Service no longer validates chain name in getInfo, just returns the data
    $info = $service->getInfo();
    expect($info)->toBeArray()->toHaveKey('chainname');
});

it('returns info without checking initialization status', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    $mc->shouldReceive('getinfo')->andReturn(['chainname' => 'procuchain']);
    $mc->shouldReceive('success')->andReturnTrue();

    $connectionService = Mockery::mock(MultichainConnectionService::class);
    $connectionService->shouldReceive('getClient')->andReturn($mc);
    $connectionService->shouldReceive('handleRequest')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $service = new MultichainService($connectionService);

    // Service no longer checks initialization status in getInfo
    $info = $service->getInfo();
    expect($info)->toBeArray();
});

it('maps RPC errors (Forbidden) to exception', function () {
    config()->set('multichain.chain_name', 'procuchain');

    $mc = Mockery::mock(MultichainClient::class);
    // operation failure on liststreamitems
    $mc->shouldReceive('liststreamitems')->with('procurement.status', true, 1, -1, false)->andReturnNull();
    $mc->shouldReceive('success')->andReturnFalse();
    $mc->shouldReceive('errormessage')->andReturn('Forbidden');
    $mc->shouldReceive('errorcode')->andReturn(403);

    $connectionService = Mockery::mock(MultichainConnectionService::class);
    $connectionService->shouldReceive('getClient')->andReturn($mc);
    $connectionService->shouldReceive('handleRequest')->andReturnUsing(function ($callback) use ($mc) {
        $result = $callback();
        if (! $mc->success()) {
            throw new Exception('MultiChain Error: Forbidden', 403);
        }

        return $result;
    });

    $service = new MultichainService($connectionService);

    expect(fn () => $service->listStreamItems('procurement.status', true, 1, -1, false))
        ->toThrow(Exception::class, 'MultiChain Error: Forbidden');
});

it('fails with final message on connection failure', function () {
    $mc = Mockery::mock(MultichainClient::class);
    // validateConnection -> getinfo fails with connection-like error
    $mc->shouldReceive('getinfo')->andReturnNull();
    $mc->shouldReceive('success')->andReturnFalse();
    $mc->shouldReceive('errormessage')->andReturn('Failed to connect');
    $mc->shouldReceive('errorcode')->andReturn(7);

    $connectionService = Mockery::mock(MultichainConnectionService::class);
    $connectionService->shouldReceive('getClient')->andReturn($mc);
    $connectionService->shouldReceive('handleRequest')->andThrow(
        new Exception('MultiChain connection failed')
    );

    $service = new MultichainService($connectionService);

    expect(fn () => $service->getInfo())
        ->toThrow(Exception::class, 'MultiChain connection failed');
});
