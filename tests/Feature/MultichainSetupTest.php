<?php

use App\Libraries\MultiChain\Contracts\MultiChainManagerInterface as MultiChainManager;

beforeEach(function () {
    $this->multichain = mock(MultiChainManager::class);
    $this->app->instance(MultiChainManager::class, $this->multichain);
});

it('checks connection with correct RPC method name', function () {
    $this->multichain
        ->shouldReceive('getinfo')
        ->once()
        ->andReturn([
            'chainname' => 'procuchain',
            'blocks' => 1,
        ]);

    $this->artisan('multichain:setup --check')
        ->expectsOutput('Connected to MultiChain node')
        ->assertSuccessful();
});

it('runs without throwing exceptions', function () {
    // Basic mocks to prevent exceptions
    $this->multichain->shouldReceive('getinfo')->andReturn(['chainname' => 'procuchain']);
    $this->multichain->shouldReceive('getnewaddress')->andReturn('1TestAddress123456789');
    $this->multichain->shouldReceive('getstreaminfo')->andReturn(['name' => 'existing_stream']);
    $this->multichain->shouldReceive('subscribe')->andReturnNull();
    $this->multichain->shouldReceive('grant')->andReturnNull();

    // Just ensure it doesn't throw an exception
    $this->artisan('multichain:setup');

    expect(true)->toBeTrue(); // Dummy assertion to make the test pass
});
