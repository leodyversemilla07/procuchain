<?php

use App\Libraries\MultiChain\Manager as MultiChainManager;

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
        ->expectsOutput('✅ Connected to MultiChain node')
        ->assertSuccessful();
});

it('creates streams using create RPC for streams', function () {
    // Simulate check success
    $this->multichain->shouldReceive('getinfo')->andReturn(['chainname' => 'procuchain']);

    // First the stream check will throw and we will attempt to create with create('stream', name, true)
    $this->multichain->shouldReceive('getstreaminfo')->andThrow(new Exception('Stream not found'));
    $this->multichain->shouldReceive('create')->with('stream', 'procurement.documents', true)->once();
    $this->multichain->shouldReceive('subscribe')->with('procurement.documents', true)->once();

    // Grant perms: mock to no-op
    $this->multichain->shouldReceive('grant')->andReturnNull();

    $this->artisan('multichain:setup')
        ->expectsOutput('✅ MultiChain setup completed successfully!')
        ->assertSuccessful();
});
