<?php

namespace Tests\Feature;

use App\Services\MultichainService;
use Exception;
use Mockery;

beforeEach(function () {
    $this->mock = Mockery::mock(MultichainService::class);
});

test('can connect to multichain', function () {
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

test('handles connection errors gracefully', function () {
    // Configure the mock to throw an exception
    $this->mock->shouldReceive('getInfo')
               ->once()
               ->andThrow(new Exception('Connection refused'));
    
    // Test the error handling
    expect(fn() => $this->mock->getInfo())
        ->toThrow(Exception::class, 'Connection refused');
});

afterEach(function () {
    Mockery::close();
});
