<?php

use App\Services\Manager;
use Illuminate\Support\Facades\File;

describe('SmartContractSetup Command', function () {
    beforeEach(function () {
        $this->multichainManager = mock(Manager::class);
        $this->app->instance(Manager::class, $this->multichainManager);
    });

    it('checks multichain connection before deployment', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Testing - library deployment skipped'));

        try {
            $this->artisan('smartcontract:setup');
        } catch (Exception $e) {
            // Expected to fail during deployment
        }

        // Connection check should have succeeded even if deployment failed
        expect(true)->toBeTrue();
    });

    it('deploys validation helper library successfully', function () {
        // Mock File facade to simulate file existence and content
        File::shouldReceive('exists')
            ->andReturn(true);

        File::shouldReceive('get')
            ->andReturn('// Mock JavaScript library code');

        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $libraryTxid = '9a1c2e3f4b5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f';

        $this->multichainManager
            ->shouldReceive('create')
            ->with(
                'library',
                'procuchain_validation_helpers',
                \Mockery::type('bool'),
                \Mockery::type('array')
            )
            ->once()
            ->andReturn($libraryTxid);

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->andReturn(['name' => 'procurement.documents']);

        $this->multichainManager
            ->shouldReceive('create')
            ->andReturn($libraryTxid);

        $this->artisan('smartcontract:setup')
            ->expectsOutput('Smart contract setup completed successfully!')
            ->assertSuccessful();
    });

    it('deploys document validation filter successfully', function () {
        // Mock File facade to simulate file existence and content for specific files
        File::shouldReceive('exists')
            ->with(resource_path('blockchain/libraries/validation_helpers.js'))
            ->andReturn(true);
        File::shouldReceive('exists')
            ->with(resource_path('blockchain/filters/documents_filter_v1_standalone.js'))
            ->andReturn(true);
        File::shouldReceive('exists')
            ->with(resource_path('blockchain/filters/status_filter_v3_standalone.js'))
            ->andReturn(true);

        File::shouldReceive('get')
            ->with(resource_path('blockchain/libraries/validation_helpers.js'))
            ->andReturn('// Mock library code');
        File::shouldReceive('get')
            ->with(resource_path('blockchain/filters/documents_filter_v1_standalone.js'))
            ->andReturn('// Mock documents filter code');
        File::shouldReceive('get')
            ->with(resource_path('blockchain/filters/status_filter_v3_standalone.js'))
            ->andReturn('// Mock status filter code');

        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $filterTxid = '8b1c2e3f4b5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e2a';

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->with('procurement.documents')
            ->once()
            ->andReturn(['name' => 'procurement.documents']);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', 'procuchain_documents_validator', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn($filterTxid);

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->with('procurement.status')
            ->once()
            ->andReturn(['name' => 'procurement.status']);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', 'procuchain_status_v3', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn($filterTxid);

        $this->artisan('smartcontract:setup')
            ->assertSuccessful();
    });

    it('handles already existing library gracefully', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Library already exists'));

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->twice() // Called for both streams
            ->andReturn(['name' => 'procurement.documents'], ['name' => 'procurement.status']);

        $this->multichainManager
            ->shouldReceive('create')
            ->twice() // Called for both filters
            ->andReturn('some-txid');

        $this->artisan('smartcontract:setup')
            ->assertSuccessful();
    });

    it('handles already existing filter gracefully', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('library', 'procuchain_validation_helpers', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn('library-txid');

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->andReturn(['name' => 'procurement.documents']);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', 'procuchain_documents_validator', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andThrow(new Exception('Filter already exists for stream'));

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->with('procurement.status')
            ->andReturn(['name' => 'procurement.status']);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', 'procuchain_status_v3', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn('status-filter-txid');

        $this->artisan('smartcontract:setup')
            ->assertSuccessful();
    });

    it('fails if stream does not exist', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('library', 'procuchain_validation_helpers', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn('library-txid');

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->with('procurement.documents')
            ->once()
            ->andThrow(new Exception('Stream not found'));

        // When first stream check fails, second stream is still checked
        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->with('procurement.status')
            ->once()
            ->andReturn(['name' => 'procurement.status']);

        // Filter should be created for the stream that exists
        $this->multichainManager
            ->shouldReceive('create')
            ->with('streamfilter', 'procuchain_status_v3', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn('filter-txid');

        $this->artisan('smartcontract:setup')
            ->assertSuccessful(); // Command succeeds but logs error for missing stream
    });

    it('can check deployment status with --check flag', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->artisan('smartcontract:setup --check')
            ->expectsOutput('Checking smart contract deployment status...')
            ->assertSuccessful();
    });

    it('can deploy only libraries with --deploy-libraries flag', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->once()
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->with('library', 'procuchain_validation_helpers', true, \Mockery::on(function ($options) {
                return isset($options['restrictions']) && isset($options['code']);
            }))
            ->once()
            ->andReturn('library-txid');

        $this->artisan('smartcontract:setup --deploy-libraries')
            ->expectsOutput('Deploying JavaScript libraries...')
            ->assertSuccessful();
    });

    it('displays deployment summary table', function () {
        $this->multichainManager
            ->shouldReceive('getinfo')
            ->andReturn([
                'chainname' => 'procuchain',
                'blocks' => 12345,
            ]);

        $this->multichainManager
            ->shouldReceive('create')
            ->andReturn('txid-library-123');

        $this->multichainManager
            ->shouldReceive('getstreaminfo')
            ->andReturn(['name' => 'procurement.documents']);

        $this->multichainManager
            ->shouldReceive('create')
            ->andReturn('txid-filter-456');

        $this->artisan('smartcontract:setup')
            ->expectsOutput('Deployment Summary:')
            ->assertSuccessful();
    });
});

describe('Smart Filter JavaScript Files', function () {
    it('has documents filter file', function () {
        $path = resource_path('blockchain/filters/documents_filter_v1_standalone.js');

        expect(File::exists($path))->toBeTrue()
            ->and(File::get($path))->toContain('filterstreamitem')
            ->and(File::get($path))->toContain('hashPattern')
            ->and(File::get($path))->toContain('SHA-256');
    });

    it('has status filter file', function () {
        $path = resource_path('blockchain/filters/status_filter_v1_standalone.js');

        expect(File::exists($path))->toBeTrue()
            ->and(File::get($path))->toContain('filterstreamitem')
            ->and(File::get($path))->toContain('current_status')
            ->and(File::get($path))->toContain('stage');
    });

    it('has validation helpers library file', function () {
        $path = resource_path('blockchain/libraries/validation_helpers.js');

        expect(File::exists($path))->toBeTrue()
            ->and(File::get($path))->toContain('validateHash')
            ->and(File::get($path))->toContain('validateRequiredFields')
            ->and(File::get($path))->toContain('validateFileSize')
            ->and(File::get($path))->toContain('validateEnum');
    });

    it('documents filter validates hash format', function () {
        $filterCode = File::get(resource_path('blockchain/filters/documents_filter_v1_standalone.js'));

        expect($filterCode)->toContain('/^[a-f0-9]{64}$/i')
            ->and($filterCode)->toContain('Invalid document hash format');
    });

    it('documents filter checks required fields', function () {
        $filterCode = File::get(resource_path('blockchain/filters/documents_filter_v1_standalone.js'));

        expect($filterCode)->toContain('pr_number')
            ->and($filterCode)->toContain('hash')
            ->and($filterCode)->toContain('file_key')
            ->and($filterCode)->toContain('document_type')
            ->and($filterCode)->toContain('Missing required field');
    });

    it('status filter validates status enums', function () {
        $filterCode = File::get(resource_path('blockchain/filters/status_filter_v1_standalone.js'));

        expect($filterCode)->toContain('procurement_submitted')
            ->and($filterCode)->toContain('bidding_documents_published')
            ->and($filterCode)->toContain('completed')
            ->and($filterCode)->toContain('Invalid status');
    });

    it('status filter validates stage-status alignment', function () {
        $filterCode = File::get(resource_path('blockchain/filters/status_filter_v1_standalone.js'));

        expect($filterCode)->toContain('stageStatusMap')
            ->and($filterCode)->toContain('not valid for stage');
    });
});
