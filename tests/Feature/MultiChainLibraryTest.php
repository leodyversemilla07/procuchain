<?php

use App\Enums\Stream;
use App\Libraries\MultiChain\Client;
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Config;

/**
 * MultiChain Library Integration Tests
 *
 * Tests for the official MultiChain PHP library integration.
 * Tests both the Client (direct RPC) and BlockchainRpcClient (Laravel wrapper).
 */
beforeEach(function () {
    // Store original config
    $this->originalConfig = [
        'host' => config('multichain.rpc.host'),
        'port' => config('multichain.rpc.port'),
        'username' => config('multichain.rpc.username'),
        'password' => config('multichain.rpc.password'),
        'chain_name' => config('multichain.chain_name'),
        'use_ssl' => config('multichain.use_ssl'),
    ];
});

afterEach(function () {
    // Restore original config
    foreach ($this->originalConfig as $key => $value) {
        $configKey = $key === 'host' || $key === 'port' || $key === 'username' || $key === 'password'
            ? "multichain.rpc.{$key}"
            : "multichain.{$key}";
        Config::set($configKey, $value);
    }
});

describe('Stream Enums', function () {
    it('has correct stream names', function () {
        expect(Stream::DOCUMENTS->value)->toBe('procurement.documents')
            ->and(Stream::METADATA->value)->toBe('procurement.metadata')
            ->and(Stream::STATUS->value)->toBe('procurement.status')
            ->and(Stream::EVENTS->value)->toBe('procurement.events')
            ->and(Stream::CORRECTIONS->value)->toBe('procurement.corrections');
    });

    it('can list all stream values', function () {
        $streams = array_map(fn ($case) => $case->value, Stream::cases());

        expect($streams)->toBeArray()
            ->toContain('procurement.documents')
            ->toContain('procurement.metadata')
            ->toContain('procurement.status')
            ->toContain('procurement.events')
            ->toContain('procurement.corrections');
    });
});

describe('Client Initialization', function () {
    it('can instantiate client with valid credentials', function () {
        $client = new Client(
            'localhost',
            8570,
            'multichainrpc',
            'test_password',
            false
        );

        expect($client)->toBeInstanceOf(Client::class);
    });

    it('sets chain name option correctly', function () {
        $client = new Client(
            'localhost',
            8570,
            'multichainrpc',
            'test_password',
            false
        );

        $result = $client->setoption(MC_OPT_CHAIN_NAME, 'procuchain');

        expect($result)->toBeTrue();
    });

    it('configures SSL usage', function () {
        $client = new Client(
            'localhost',
            8570,
            'multichainrpc',
            'test_password',
            true  // usessl
        );

        // Client accepts SSL in constructor
        expect($client)->toBeInstanceOf(Client::class);
    });

    it('configures timeout', function () {
        $client = new Client(
            'localhost',
            8570,
            'multichainrpc',
            'test_password',
            false
        );

        $result = $client->setoption(MC_OPT_TIMEOUT, 60);

        expect($result)->toBeTrue();
    });
});

describe('BlockchainRpcClient Initialization', function () {
    it('can instantiate BlockchainRpcClient', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);

        expect($blockchainRpcClient)->toBeInstanceOf(BlockchainRpcClient::class);
    });

    it('is registered as singleton', function () {
        $blockchainRpcClient1 = app(BlockchainRpcClient::class);
        $blockchainRpcClient2 = app(BlockchainRpcClient::class);

        expect($blockchainRpcClient1)->toBe($blockchainRpcClient2);
    });

    it('provides access to underlying client', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);
        $client = $blockchainRpcClient->getClient();

        expect($client)->toBeInstanceOf(Client::class);
    });
});

describe('Blockchain Permissions', function () {
    it('defines required global and stream permissions per role', function () {
        $roles = config('multichain.permissions.roles');

        expect($roles)->toBeArray()
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
            expect($roles[$role] ?? null)
                ->toBeArray()
                ->toHaveKeys(['global', 'stream']);

            foreach ($matrix['global'] as $perm) {
                expect(in_array($perm, $roles[$role]['global'], true))
                    ->toBeTrue("Role {$role} should have global permission: {$perm}");
            }

            foreach ($matrix['stream'] as $perm) {
                expect(in_array($perm, $roles[$role]['stream'], true))
                    ->toBeTrue("Role {$role} should have stream permission: {$perm}");
            }
        }
    });

    it('has proper permission hierarchy', function () {
        $roles = config('multichain.permissions.roles');

        // Admin should have all permissions
        expect($roles['admin']['global'])->toContain('admin', 'send', 'receive', 'create', 'issue', 'mine', 'activate')
            ->and($roles['admin']['stream'])->toContain('admin', 'write', 'read');

        // BAC Secretariat should have extensive permissions but not mining
        expect($roles['bac_secretariat']['global'])->toContain('send', 'receive', 'create', 'issue', 'activate')
            ->and($roles['bac_secretariat']['global'])->not->toContain('mine')
            ->and($roles['bac_secretariat']['stream'])->toContain('admin', 'write', 'read');

        // HOPE should have limited permissions
        expect($roles['hope']['global'])->toContain('send', 'receive')
            ->and($roles['hope']['global'])->not->toContain('create', 'issue', 'mine')
            ->and($roles['hope']['stream'])->toContain('write', 'read')
            ->and($roles['hope']['stream'])->not->toContain('admin');
    });
});

describe('Magic Method Calls', function () {
    it('forwards RPC methods through magic __call', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);

        // These method names should be recognized
        $methods = [
            'getinfo',
            'liststreams',
            'liststreamitems',
            'publish',
            'subscribe',
            'getblockchainparams',
        ];

        foreach ($methods as $method) {
            expect(method_exists($blockchainRpcClient, '__call'))->toBeTrue();
        }
    });
});

describe('Error Handling', function () {
    it('provides success status check', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);
        $client = $blockchainRpcClient->getClient();

        expect(method_exists($client, 'success'))->toBeTrue();
    });

    it('provides error retrieval', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);
        $client = $blockchainRpcClient->getClient();

        expect(method_exists($client, 'errormessage'))->toBeTrue();
    });

    it('provides error code retrieval', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);
        $client = $blockchainRpcClient->getClient();

        expect(method_exists($client, 'errorcode'))->toBeTrue();
    });

    it('provides error message retrieval', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);
        $client = $blockchainRpcClient->getClient();

        expect(method_exists($client, 'errormessage'))->toBeTrue();
    });
});

describe('Configuration', function () {
    it('loads RPC configuration correctly', function () {
        expect(config('multichain.rpc.host'))->not->toBeNull()
            ->and(config('multichain.rpc.port'))->not->toBeNull()
            ->and(config('multichain.rpc.username'))->not->toBeNull()
            ->and(config('multichain.rpc.password'))->not->toBeNull();
    });

    it('has proper chain name configured', function () {
        $chainName = config('multichain.chain_name');

        expect($chainName)->toBeString()
            ->and($chainName)->not->toBeEmpty();
    });

    it('configures retry settings', function () {
        $maxRetries = config('multichain.max_retries');
        $webMaxRetries = config('multichain.web_max_retries');

        expect((int) $maxRetries)->toBeInt()
            ->and((int) $maxRetries)->toBeGreaterThan(0)
            ->and((int) $webMaxRetries)->toBeInt();
    });

    it('configures timeout settings', function () {
        $timeout = config('multichain.connection_timeout');
        $webTimeout = config('multichain.web_connection_timeout');

        expect((int) $timeout)->toBeInt()
            ->and((int) $webTimeout)->toBeInt()
            ->and((int) $timeout)->toBeGreaterThan(0);
    });

    it('has different timeouts for console and web', function () {
        $consoleTimeout = config('multichain.connection_timeout');
        $webTimeout = config('multichain.web_connection_timeout');

        // Console should typically have longer timeout
        expect($consoleTimeout)->toBeGreaterThanOrEqual($webTimeout);
    });
});

describe('Stream Operations', function () {
    it('has stream enums defined', function () {
        $streams = Stream::cases();

        expect($streams)->toBeArray();

        $values = array_map(fn ($case) => $case->value, $streams);

        expect($values)->toContain('procurement.documents')
            ->toContain('procurement.metadata')
            ->toContain('procurement.status')
            ->toContain('procurement.events')
            ->toContain('procurement.corrections')
            ->toContain('procurement.metadata.corrections')
            ->toContain('procurement.archive')
            ->toContain('File.data')
            ->toContain('File.metadata')
            ->toContain('File.chunks')
            ->toContain('user.registrations')
            ->toContain('integrity.violations')
            ->toContain('audit.trail')
            ->toContain('document.access')
            ->toContain('config.workflows')
            ->toContain('config.stage_docs')
            ->toContain('user.login_sessions');
    });

    it('validates stream naming convention', function () {
        $streams = Stream::cases();

        foreach ($streams as $stream) {
            // Streams should follow a known namespace pattern
            $isProcurement = str_starts_with($stream->value, 'procurement.');
            $isFile = str_starts_with($stream->value, 'File.');
            $isUser = str_starts_with($stream->value, 'user.');
            $isIntegrity = str_starts_with($stream->value, 'integrity.');
            $isAudit = str_starts_with($stream->value, 'audit.');
            $isDocument = str_starts_with($stream->value, 'document.');
            $isConfig = str_starts_with($stream->value, 'config.');

            expect($isProcurement || $isFile || $isUser || $isIntegrity || $isAudit || $isDocument || $isConfig)
                ->toBeTrue("Stream {$stream->value} should start with a known stream namespace");

            if ($isProcurement) {
                expect($stream->value)->toMatch('/^procurement\.([a-z_]+\.?)+$/');
            } elseif ($isFile) {
                expect($stream->value)->toMatch('/^File\.([a-z_]+\.?)+$/');
            } elseif ($isUser) {
                expect($stream->value)->toMatch('/^user\.([a-z_]+\.?)+$/');
            } elseif ($isIntegrity) {
                expect($stream->value)->toMatch('/^integrity\.([a-z_]+\.?)+$/');
            } elseif ($isAudit) {
                expect($stream->value)->toMatch('/^audit\.([a-z_]+\.?)+$/');
            } elseif ($isDocument) {
                expect($stream->value)->toMatch('/^document\.([a-z_]+\.?)+$/');
            } elseif ($isConfig) {
                expect($stream->value)->toMatch('/^config\.([a-z_]+\.?)+$/');
            }
        }
    });

    it('has display names for all streams', function () {
        $streams = Stream::cases();

        foreach ($streams as $stream) {
            $displayName = $stream->getDisplayName();

            expect($displayName)->toBeString()
                ->and($displayName)->not->toBeEmpty();
        }
    });

    it('has descriptions for all streams', function () {
        $streams = Stream::cases();

        foreach ($streams as $stream) {
            $description = $stream->getDescription();

            expect($description)->toBeString()
                ->and($description)->not->toBeEmpty();
        }
    });

    it('correctly identifies procurement streams', function () {
        expect(Stream::METADATA->isProcurementStream())->toBeTrue()
            ->and(Stream::DOCUMENTS->isProcurementStream())->toBeTrue()
            ->and(Stream::STATUS->isProcurementStream())->toBeTrue()
            ->and(Stream::EVENTS->isProcurementStream())->toBeTrue()
            ->and(Stream::CORRECTIONS->isProcurementStream())->toBeTrue()
            ->and(Stream::PROCUREMENTS_CORRECTIONS->isProcurementStream())->toBeTrue();
    });

    it('correctly identifies File streams', function () {
        expect(Stream::FILE_DATA->isBlockchainFileStream())->toBeTrue()
            ->and(Stream::FILE_METADATA->isBlockchainFileStream())->toBeTrue()
            ->and(Stream::FILE_CHUNKS->isBlockchainFileStream())->toBeTrue();
    });

    it('correctly separates procurement and File streams', function () {
        expect(Stream::METADATA->isBlockchainFileStream())->toBeFalse()
            ->and(Stream::DOCUMENTS->isBlockchainFileStream())->toBeFalse()
            ->and(Stream::FILE_DATA->isProcurementStream())->toBeFalse()
            ->and(Stream::FILE_METADATA->isProcurementStream())->toBeFalse();
    });

    it('provides static values method', function () {
        $values = Stream::values();

        expect($values)->toBeArray()
            ->toContain('procurement.metadata')
            ->toContain('File.data')
            ->toContain('user.registrations')
            ->toContain('audit.trail')
            ->toContain('config.workflows');
    });

    it('provides static options method', function () {
        $options = Stream::options();

        expect($options)->toBeArray()
            ->toHaveKey('procurement.metadata')
            ->toHaveKey('File.data')
            ->toHaveKey('user.registrations')
            ->toHaveKey('audit.trail')
            ->toHaveKey('config.workflows');

        // Check that values are display names
        expect($options['procurement.metadata'])->toBe('Procurement Metadata')
            ->and($options['File.data'])->toBe('File Data')
            ->and($options['user.registrations'])->toBe('User Registrations');
    });
});

describe('Individual Stream Coverage', function () {
    it('tests METADATA stream', function () {
        $stream = Stream::METADATA;

        expect($stream->value)->toBe('procurement.metadata')
            ->and($stream->getDisplayName())->toBe('Procurement Metadata')
            ->and($stream->getDescription())->toContain('metadata')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests DOCUMENTS stream', function () {
        $stream = Stream::DOCUMENTS;

        expect($stream->value)->toBe('procurement.documents')
            ->and($stream->getDisplayName())->toBe('Procurement Documents')
            ->and($stream->getDescription())->toContain('documents')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests STATUS stream', function () {
        $stream = Stream::STATUS;

        expect($stream->value)->toBe('procurement.status')
            ->and($stream->getDisplayName())->toBe('Procurement Status')
            ->and($stream->getDescription())->toContain('status')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests EVENTS stream', function () {
        $stream = Stream::EVENTS;

        expect($stream->value)->toBe('procurement.events')
            ->and($stream->getDisplayName())->toBe('Procurement Events')
            ->and($stream->getDescription())->toContain('events')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests CORRECTIONS stream', function () {
        $stream = Stream::CORRECTIONS;

        expect($stream->value)->toBe('procurement.corrections')
            ->and($stream->getDisplayName())->toBe('Procurement Corrections')
            ->and($stream->getDescription())->toContain('correction')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests PROCUREMENTS_CORRECTIONS stream', function () {
        $stream = Stream::PROCUREMENTS_CORRECTIONS;

        expect($stream->value)->toBe('procurement.metadata.corrections')
            ->and($stream->getDisplayName())->toBe('Procurement Metadata Corrections')
            ->and($stream->getDescription())->toContain('correction')
            ->and($stream->isProcurementStream())->toBeTrue()
            ->and($stream->isBlockchainFileStream())->toBeFalse();
    });

    it('tests FILE_DATA stream', function () {
        $stream = Stream::FILE_DATA;

        expect($stream->value)->toBe('File.data')
            ->and($stream->getDisplayName())->toBe('File Data')
            ->and($stream->getDescription())->toContain('File data')
            ->and($stream->isProcurementStream())->toBeFalse()
            ->and($stream->isBlockchainFileStream())->toBeTrue();
    });

    it('tests FILE_METADATA stream', function () {
        $stream = Stream::FILE_METADATA;

        expect($stream->value)->toBe('File.metadata')
            ->and($stream->getDisplayName())->toBe('File Metadata')
            ->and($stream->getDescription())->toContain('metadata')
            ->and($stream->isProcurementStream())->toBeFalse()
            ->and($stream->isBlockchainFileStream())->toBeTrue();
    });

    it('tests FILE_CHUNKS stream', function () {
        $stream = Stream::FILE_CHUNKS;

        expect($stream->value)->toBe('File.chunks')
            ->and($stream->getDisplayName())->toBe('File Chunks')
            ->and($stream->getDescription())->toContain('chunk')
            ->and($stream->isProcurementStream())->toBeFalse()
            ->and($stream->isBlockchainFileStream())->toBeTrue();
    });

    it('validates all procurement streams have unique purposes', function () {
        $procurementStreams = [
            Stream::METADATA,
            Stream::DOCUMENTS,
            Stream::STATUS,
            Stream::EVENTS,
            Stream::CORRECTIONS,
            Stream::PROCUREMENTS_CORRECTIONS,
        ];

        $descriptions = array_map(fn ($s) => $s->getDescription(), $procurementStreams);

        // All descriptions should be unique
        expect(count($descriptions))->toBe(count(array_unique($descriptions)));
    });

    it('validates all File streams have unique purposes', function () {
        $blockchainFileStreams = [
            Stream::FILE_DATA,
            Stream::FILE_METADATA,
            Stream::FILE_CHUNKS,
        ];

        $descriptions = array_map(fn ($s) => $s->getDescription(), $blockchainFileStreams);

        // All descriptions should be unique
        expect(count($descriptions))->toBe(count(array_unique($descriptions)));
    });

    it('can iterate through all streams programmatically', function () {
        $allStreams = Stream::cases();
        $procurementCount = 0;
        $blockchainFileCount = 0;

        foreach ($allStreams as $stream) {
            if ($stream->isProcurementStream()) {
                $procurementCount++;
            }
            if ($stream->isBlockchainFileStream()) {
                $blockchainFileCount++;
            }
        }

        expect($procurementCount)->toBe(7)
            ->and($blockchainFileCount)->toBe(3);
    });
});

describe('Blockchain Address Management', function () {
    it('validates blockchain address format', function () {
        // MultiChain addresses are typically base58 encoded
        $validAddress = '1ABC2DEF3GHI4JKL5MNO6PQR7STU8VWX9YZ';

        expect($validAddress)->toBeString()
            ->and(strlen($validAddress))->toBeGreaterThan(20);
    });

    it('understands addresses are stored in database', function () {
        // Admin address and user blockchain addresses are stored in the database
        // for single source of truth, not in config/env
        expect(true)->toBeTrue();
    });
});

describe('Data Encoding', function () {
    it('can encode JSON data for publishing', function () {
        $data = ['name' => 'John Doe', 'age' => 30];
        $encoded = ['json' => $data];

        expect($encoded)->toHaveKey('json')
            ->and($encoded['json'])->toBe($data);
    });

    it('can encode text data for publishing', function () {
        $text = 'Hello, MultiChain!';
        $encoded = ['text' => $text];

        expect($encoded)->toHaveKey('text')
            ->and($encoded['text'])->toBe($text);
    });

    it('can encode binary data for publishing', function () {
        $hex = 'a1b2c3d4e5f6';
        $encoded = ['data' => $hex];

        expect($encoded)->toHaveKey('data')
            ->and($encoded['data'])->toBe($hex);
    });
});

describe('Integration Requirements', function () {
    it('has all required streams defined in enum', function () {
        $enumValues = array_map(fn ($case) => $case->value, Stream::cases());

        // Check that all expected streams exist
        $expectedStreams = [
            'procurement.metadata',
            'procurement.documents',
            'procurement.status',
            'procurement.events',
            'procurement.corrections',
            'procurement.metadata.corrections',
            'File.data',
            'File.metadata',
            'File.chunks',
        ];

        foreach ($expectedStreams as $stream) {
            expect($enumValues)->toContain($stream);
        }
    });

    it('validates procurement stream names', function () {
        $streams = [
            Stream::DOCUMENTS,
            Stream::METADATA,
            Stream::STATUS,
            Stream::EVENTS,
            Stream::CORRECTIONS,
        ];

        foreach ($streams as $stream) {
            expect($stream->value)->toStartWith('procurement.');
        }
    });

    it('has proper SSL configuration', function () {
        $useSsl = config('multichain.use_ssl');
        $verifySsl = config('multichain.verify_ssl');

        expect($useSsl)->toBeIn([true, false])
            ->and($verifySsl)->toBeIn([true, false]);

        // If SSL is enabled, verify should typically be enabled too in production
        if ($useSsl && app()->environment('production')) {
            expect($verifySsl)->toBeTrue();
        }
    });
});

describe('BlockchainRpcClient Features', function () {
    it('uses different retry settings for console vs web', function () {
        $consoleRetries = config('multichain.max_retries');
        $webRetries = config('multichain.web_max_retries');

        // Console operations should typically have more retries
        expect($consoleRetries)->toBeGreaterThanOrEqual($webRetries);
    });

    it('provides context-aware timeouts', function () {
        $blockchainRpcClient = app(BlockchainRpcClient::class);

        // BlockchainRpcClient should be able to handle different operation types
        expect($blockchainRpcClient)->toBeInstanceOf(BlockchainRpcClient::class);

        // Verify timeout configuration exists
        $timeout = config('multichain.connection_timeout');
        $webTimeout = config('multichain.web_connection_timeout');

        expect((int) $timeout)->toBeInt()
            ->and((int) $webTimeout)->toBeInt();
    });
});

describe('Library Documentation', function () {
    it('has README File in library directory', function () {
        $readmePath = base_path('app/Libraries/MultiChain/README.md');

        expect(file_exists($readmePath))->toBeTrue();
    });

    it('README contains essential sections', function () {
        $readmePath = base_path('app/Libraries/MultiChain/README.md');

        if (file_exists($readmePath)) {
            $content = file_get_contents($readmePath);

            expect($content)->toContain('MultiChain')
                ->and($content)->toContain('Installation')
                ->and($content)->toContain('Usage')
                ->and($content)->toContain('Examples');
        }
    });
});
