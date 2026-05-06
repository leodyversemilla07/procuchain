<?php

use App\Services\Manager;

describe('SmartContractSetup Command', function () {
    beforeEach(function () {
        $this->multichainManager = mock(Manager::class);
        $this->app->instance(Manager::class, $this->multichainManager);
    });

    it('checks multichain connection before any operation', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->andReturn('txid-123');

        $this->artisan('smartcontract:setup')
            ->expectsOutput('Connected to: procuchain (block: 12345)')
            ->assertSuccessful();
    });

    it('deploys by default when no option is provided', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', Mockery::type('string'), false, Mockery::type('string'))
            ->andReturn('txid-123');

        $this->multichainManager
            ->shouldReceive('create')
            ->with('txfilter', Mockery::type('string'), false, Mockery::type('string'))
            ->andReturn('txid-456');

        $this->artisan('smartcontract:setup')
            ->expectsOutput('Deploying smart contracts...')
            ->assertSuccessful();
    });

    it('checks deployment status with --check flag', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('liststreamfilters')
            ->once()
            ->andReturn([
                ['name' => 'sf_document_validation', 'compiled' => true],
                ['name' => 'sf_status_validation', 'compiled' => true],
            ]);

        $this->artisan('smartcontract:setup --check')
            ->expectsOutput('Stream Filters:')
            ->assertSuccessful();
    });

    it('shows warning when no filters deployed on --check', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('liststreamfilters')
            ->once()
            ->andReturn([]);

        $this->artisan('smartcontract:setup --check')
            ->assertSuccessful();
    });

    it('deploys all stream and transaction filters', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', Mockery::type('string'), false, Mockery::type('string'))
            ->andReturn('txid-123');

        $this->multichainManager
            ->shouldReceive('create')
            ->with('txfilter', Mockery::type('string'), false, Mockery::type('string'))
            ->andReturn('txid-456');

        $this->artisan('smartcontract:setup')
            ->expectsOutput('Deploying smart contracts...')
            ->expectsOutput('Stream Filters:')
            ->expectsOutput('Transaction Filters:')
            ->assertSuccessful();
    });

    it('handles already existing filters gracefully during deploy', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->andThrow(new Exception('Entity with this name already exists'));

        $this->artisan('smartcontract:setup')
            ->assertSuccessful();
    });

    it('activates filters with --activate flag', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('getaddresses')
            ->once()
            ->andReturn(['1abc123def456']);

        $this->multichainManager
            ->shouldReceive('liststreamfilters')
            ->once()
            ->andReturn([
                ['name' => 'sf_document_validation'],
                ['name' => 'sf_status_validation'],
            ]);

        $this->multichainManager
            ->shouldReceive('listtxfilters')
            ->once()
            ->andReturn([]);

        $this->multichainManager
            ->shouldReceive('approvefrom')
            ->andReturn(true);

        $this->artisan('smartcontract:setup --activate')
            ->expectsOutput('Activating filters...')
            ->assertSuccessful();
    });

    it('deactivates filters with --deactivate flag', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('getaddresses')
            ->once()
            ->andReturn(['1abc123def456']);

        $this->multichainManager
            ->shouldReceive('liststreamfilters')
            ->once()
            ->andReturn([
                ['name' => 'sf_document_validation'],
            ]);

        $this->multichainManager
            ->shouldReceive('listtxfilters')
            ->once()
            ->andReturn([]);

        $this->multichainManager
            ->shouldReceive('approvefrom')
            ->andReturn(true);

        $this->artisan('smartcontract:setup --deactivate')
            ->expectsOutput('Deactivating filters...')
            ->assertSuccessful();
    });

    it('fails when no admin address available', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('getaddresses')
            ->once()
            ->andReturn([]);

        $this->artisan('smartcontract:setup --activate')
            ->expectsOutput('No admin address available')
            ->assertFailed();
    });

    it('handles connection errors gracefully', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andThrow(new Exception('Connection refused'));

        $this->artisan('smartcontract:setup')
            ->assertFailed();
    });
});

describe('Smart Filter JavaScript Files', function () {
    it('has documents filter file', function () {
        expect(file_exists(resource_path('blockchain/filters/stream_document_validation.js')))->toBeTrue();
    });

    it('has status filter file', function () {
        expect(file_exists(resource_path('blockchain/filters/stream_status_validation.js')))->toBeTrue();
    });

    it('has event filter file', function () {
        expect(file_exists(resource_path('blockchain/filters/stream_event_validation.js')))->toBeTrue();
    });

    it('has file metadata filter file', function () {
        expect(file_exists(resource_path('blockchain/filters/stream_file_metadata_validation.js')))->toBeTrue();
    });

    it('documents filter validates hash format', function () {
        $content = file_get_contents(resource_path('blockchain/filters/stream_document_validation.js'));
        expect($content)->toContain('hash');
    });

    it('documents filter checks required fields', function () {
        $content = file_get_contents(resource_path('blockchain/filters/stream_document_validation.js'));
        expect($content)->toContain('pr_number');
        expect($content)->toContain('file_name');
    });

    it('status filter validates status enums', function () {
        $content = file_get_contents(resource_path('blockchain/filters/stream_status_validation.js'));
        expect($content)->toContain('status');
    });

    it('event filter validates event structure', function () {
        $content = file_get_contents(resource_path('blockchain/filters/stream_event_validation.js'));
        expect($content)->toContain('event_type');
    });
});
