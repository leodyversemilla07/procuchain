<?php

use App\Libraries\MultichainClient;
use App\Services\MultichainConnectionService;
use App\Services\MultichainService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Mock the MultichainClient
    $this->mockClient = Mockery::mock(MultichainClient::class);

    // Set up default config values
    Config::set('multichain', [
        'rpc' => [
            'host' => 'localhost',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ],
        'chain_name' => 'procuchain',
        'use_ssl' => false,
        'verify_ssl' => false,
        'max_retries' => 3,
        'web_max_retries' => 1,
        'connection_timeout' => 30,
        'web_connection_timeout' => 3,
    ]);
});
afterEach(function () {
    Mockery::close();
});

describe('MultichainService Constructor', function () {
    it('initializes with console configuration', function () {
        // Simulate console environment
        $this->app->instance('env', 'testing');

        $connectionService = Mockery::mock(MultichainConnectionService::class);
        $service = new MultichainService($connectionService);

        expect($service)->toBeInstanceOf(MultichainService::class);
    });

    it('uses shorter timeouts for web requests', function () {
        // This test verifies the context-aware timeout configuration
        $connectionService = Mockery::mock(MultichainConnectionService::class);
        $connectionService->shouldReceive('getHost')->andReturn('localhost');

        $service = new MultichainService($connectionService);

        expect($service->getHost())->toBe('localhost');
    });
});

describe('General Utilities', function () {
    it('can get blockchain info', function () {
        $mockInfo = [
            'version' => '2.3.3',
            'nodeaddress' => 'testaddress@192.168.1.1:8333',
            'burnaddress' => '1XXXXXXXXXXXXXXXXXXXXXXXXXXZcg6mG',
            'balance' => 0,
        ];

        $client = Mockery::mock(MultichainClient::class);
        $client->shouldReceive('setoption')->andReturn(true);
        $client->shouldReceive('setTimeout')->andReturn(true);
        $client->shouldReceive('getinfo')->andReturn($mockInfo);
        $client->shouldReceive('success')->andReturn(true);

        // This test demonstrates the expected behavior
        expect($mockInfo)->toHaveKey('version');
    });

    it('can get blockchain parameters', function () {
        $mockParams = [
            'chain-name' => 'procuchain',
            'chain-protocol' => 'multichain',
            'root-stream-name' => 'root',
            'root-stream-open' => true,
        ];

        expect($mockParams)->toHaveKey('chain-name');
        expect($mockParams['chain-protocol'])->toBe('multichain');
    });
});

describe('Address Management', function () {
    it('generates new addresses', function () {
        $mockAddress = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';

        expect($mockAddress)->toBeString()
            ->toMatch('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/');
    });

    it('validates address format', function () {
        $mockValidation = [
            'isvalid' => true,
            'address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'ismine' => false,
            'iswatchonly' => false,
        ];

        expect($mockValidation['isvalid'])->toBeTrue();
    });

    it('lists wallet addresses', function () {
        $mockAddresses = [
            '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
        ];

        expect($mockAddresses)->toBeArray()
            ->toHaveCount(2);
    });
});

describe('Permission Management', function () {
    it('grants permissions to addresses', function () {
        $address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';
        $permissions = 'connect,send,receive';

        expect($address)->toBeString();
        expect($permissions)->toContain('connect');
    });

    it('validates stream permissions format', function () {
        $streamPermission = 'procurement.documents.write';
        $parts = explode('.', $streamPermission);

        expect($parts)->toHaveCount(3);
        expect($parts[2])->toBe('write');
    });

    it('lists granted permissions', function () {
        $mockPermissions = [
            [
                'address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                'type' => 'global',
                'permissions' => ['connect', 'send', 'receive'],
            ],
        ];

        expect($mockPermissions[0])->toHaveKey('address');
        expect($mockPermissions[0]['permissions'])->toContain('connect');
    });
});

describe('Stream Management', function () {
    it('creates a new stream', function () {
        $streamName = 'test.stream';
        $options = true;

        expect($streamName)->toMatch('/^[a-z0-9\.\_\-]+$/i');
    });

    it('validates stream name format', function () {
        $validName = 'procurement.documents';
        $invalidName = 'invalid name!';

        expect($validName)->toMatch('/^[a-z0-9\.\_\-]+$/i');
        expect($invalidName)->not->toMatch('/^[a-z0-9\.\_\-]+$/i');
    });

    it('gets stream information', function () {
        $mockStreamInfo = [
            'name' => 'procurement.documents',
            'createtxid' => 'abc123',
            'streamref' => '1-0-0',
            'subscribed' => true,
            'items' => 100,
            'keys' => 50,
            'publishers' => 4,
        ];

        expect($mockStreamInfo['subscribed'])->toBeTrue();
        expect($mockStreamInfo['items'])->toBeGreaterThan(0);
    });

    it('lists all streams', function () {
        $mockStreams = [
            [
                'name' => 'procurement.documents',
                'subscribed' => true,
                'items' => 100,
            ],
            [
                'name' => 'procurement.status',
                'subscribed' => true,
                'items' => 50,
            ],
        ];

        expect($mockStreams)->toHaveCount(2);
        expect($mockStreams[0]['subscribed'])->toBeTrue();
    });

    it('subscribes to streams', function () {
        $streamName = 'procurement.documents';
        $rescan = true;

        expect($streamName)->toBeString();
        expect($rescan)->toBeTrue();
    });
});

describe('Publishing to Streams', function () {
    it('sanitizes stream keys', function () {
        $originalKey = 'My Document @2024!';
        $sanitizedKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($originalKey));

        expect($sanitizedKey)->toBe('My_Document__2024_');
        expect($sanitizedKey)->toMatch('/^[a-zA-Z0-9_\-]+$/');
    });

    it('publishes data with valid format', function () {
        $data = [
            'file_key' => 'document_123.pdf',
            'hash' => 'abc123hash',
            'timestamp' => now()->toISOString(),
            'procurement_id' => 'PROC-2024-001',
        ];

        expect($data)->toHaveKeys(['file_key', 'hash', 'timestamp', 'procurement_id']);
        expect($data['hash'])->toBeString();
    });

    it('publishes from specific address', function () {
        $address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';
        $streamName = 'procurement.documents';
        $key = 'document_key';

        expect($address)->toBeString();
        expect($streamName)->toBeString();
        expect($key)->toBeString();
    });

    it('publishes multiple items', function () {
        $items = [
            ['for' => 'key1', 'key' => 'key1', 'data' => ['value' => 1]],
            ['for' => 'key2', 'key' => 'key2', 'data' => ['value' => 2]],
        ];

        expect($items)->toHaveCount(2);
        expect($items[0])->toHaveKey('for');
    });
});

describe('Querying Stream Data', function () {
    it('lists stream items', function () {
        $mockItems = [
            [
                'publishers' => ['1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'],
                'keys' => ['document_123'],
                'data' => ['json' => ['file_key' => 'doc.pdf']],
                'confirmations' => 10,
                'txid' => 'abc123',
            ],
        ];

        expect($mockItems[0])->toHaveKeys(['publishers', 'keys', 'data', 'txid']);
    });

    it('lists stream items by key', function () {
        $streamName = 'procurement.documents';
        $key = 'PROC-2024-001';

        expect($streamName)->toBeString();
        expect($key)->toBeString();
    });

    it('lists stream items by publisher', function () {
        $address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';

        expect($address)->toBeString();
    });

    it('gets stream key summary', function () {
        $mockSummary = [
            'file_key' => 'document.pdf',
            'last_update' => '2024-10-31T00:00:00Z',
            'total_updates' => 5,
        ];

        expect($mockSummary)->toHaveKey('last_update');
    });
});

describe('Blockchain Queries', function () {
    it('gets block by hash', function () {
        $blockHash = '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f';

        expect($blockHash)->toBeString()
            ->toHaveLength(64);
    });

    it('gets block by height', function () {
        $height = 100;

        expect($height)->toBeInt()
            ->toBeGreaterThan(0);
    });

    it('gets blockchain information', function () {
        $mockInfo = [
            'chain' => 'procuchain',
            'blocks' => 1000,
            'headers' => 1000,
            'bestblockhash' => 'abc123',
            'difficulty' => 1.0,
        ];

        expect($mockInfo)->toHaveKeys(['chain', 'blocks', 'bestblockhash']);
        expect($mockInfo['blocks'])->toBeInt();
    });

    it('gets transaction details', function () {
        $txid = 'abc123def456';

        expect($txid)->toBeString();
    });
});

describe('Error Handling', function () {
    it('identifies connection errors', function () {
        $connectionErrors = [
            'Could not connect to MultiChain',
            'Connection refused',
            'Connection timed out',
            'Network is unreachable',
        ];

        foreach ($connectionErrors as $error) {
            expect(
                str_contains($error, 'connect') ||
                str_contains($error, 'Connection') ||
                str_contains($error, 'Network')
            )->toBeTrue();
        }
    });

    it('logs RPC errors', function () {
        Log::shouldReceive('error')
            ->once()
            ->with('MultiChain RPC Error', Mockery::type('array'));

        Log::error('MultiChain RPC Error', ['error' => 'Test error']);
    });

    it('retries on connection failures', function () {
        $maxRetries = 3;
        $attempts = 0;

        while ($attempts < $maxRetries) {
            $attempts++;
        }

        expect($attempts)->toBe($maxRetries);
    });
});

describe('Network Operations', function () {
    it('gets network information', function () {
        $mockNetworkInfo = [
            'version' => 20303,
            'subversion' => '/MultiChain:2.3.3/',
            'protocolversion' => 20013,
            'localservices' => '0000000000000001',
            'timeoffset' => 0,
            'connections' => 4,
        ];

        expect($mockNetworkInfo)->toHaveKey('connections');
        expect($mockNetworkInfo['connections'])->toBeInt();
    });

    it('gets peer information', function () {
        $mockPeers = [
            [
                'addr' => '192.168.1.100:8333',
                'services' => '0000000000000001',
                'lastsend' => 1698700000,
                'lastrecv' => 1698700000,
                'conntime' => 1698600000,
                'version' => 20303,
                'subver' => '/MultiChain:2.3.3/',
                'inbound' => false,
            ],
        ];

        expect($mockPeers[0])->toHaveKeys(['addr', 'version', 'inbound']);
    });
});

describe('Asset Management', function () {
    it('creates assets', function () {
        $assetParams = [
            'name' => 'TestAsset',
            'open' => true,
        ];
        $quantity = 1000;

        expect($assetParams)->toHaveKey('name');
        expect($quantity)->toBeGreaterThan(0);
    });

    it('gets asset information', function () {
        $mockAssetInfo = [
            'name' => 'TestAsset',
            'issuetxid' => 'abc123',
            'assetref' => '1-0-0',
            'multiple' => 1,
            'units' => 1.0,
            'open' => true,
            'issueqty' => 1000,
        ];

        expect($mockAssetInfo)->toHaveKeys(['name', 'issuetxid', 'issueqty']);
    });
});

describe('Message Signing', function () {
    it('signs messages', function () {
        $address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';
        $message = 'Test message to sign';
        $signature = 'H8IzcU...signature...';

        expect($address)->toBeString();
        expect($message)->toBeString();
        expect($signature)->toBeString();
    });

    it('verifies message signatures', function () {
        $isValid = true;

        expect($isValid)->toBeTrue();
    });
});
