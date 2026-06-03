<?php

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\ProcurementMirrorRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Dashboard\ModeAnalyzer;
use App\Services\Dashboard\StatisticsCalculator;
use App\Services\DashboardService;
use App\Services\Manager;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();

    $this->manager = mock(Manager::class);
    $this->mirrorRepository = mock(ProcurementMirrorRepository::class);
    $this->eventRepository = new EventRepository($this->manager);
    $this->documentRepository = new DocumentRepository($this->manager);
    $this->userService = mock(UserService::class)->makePartial(); // Allow real calls
    $this->procurementRepository = mock(ProcurementRepository::class);

    // Default mock: return null for any batch procurement lookup (no mode info)
    $this->mirrorRepository
        ->shouldReceive('findManyByProcurement')
        ->andReturn([])
        ->byDefault();

    // Default mock: return empty for events and documents
    $this->mirrorRepository
        ->shouldReceive('findRecentEvents')
        ->andReturn([])
        ->byDefault();

    $this->mirrorRepository
        ->shouldReceive('getAllDocuments')
        ->andReturn([])
        ->byDefault();

    $this->service = new DashboardService(
        $this->manager,
        $this->mirrorRepository,
        $this->eventRepository,
        $this->documentRepository,
        $this->userService,
        $this->procurementRepository,
        new StatisticsCalculator,
        new ModeAnalyzer
    );
});
describe('DashboardService', function () {
    describe('getUserName', function () {
        test('it retrieves user name from database', function () {
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1ABC123XYZ')
                ->andReturn('John Doe');

            $result = $this->service->getUserName('1ABC123XYZ');

            expect($result)->toBe('John Doe');
        });

        test('it returns Unknown for non-existent address', function () {
            $result = $this->service->getUserName('NONEXISTENT_ADDRESS');

            expect($result)->toBe('System');
        });

        test('it caches user names for performance', function () {
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1DEF456ABC')
                ->andReturn('Jane Smith');

            // First call
            $result1 = $this->service->getUserName('1DEF456ABC');

            // Second call - should use cached value
            $result2 = $this->service->getUserName('1DEF456ABC');

            expect($result1)->toBe('Jane Smith');
            expect($result2)->toBe('Jane Smith');
        });

        test('it handles database exceptions gracefully', function () {
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1ABC123XYZ')
                ->andThrow(new Exception('Database connection lost'));

            expect(fn () => $this->service->getUserName('1ABC123XYZ'))->toThrow(Exception::class);
        });

        test('it caches multiple different addresses', function () {
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1ALICE123')
                ->andReturn('Alice');
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1BOB456')
                ->andReturn('Bob');

            $result1 = $this->service->getUserName('1ALICE123');
            $result2 = $this->service->getUserName('1BOB456');
            $result3 = $this->service->getUserName('1ALICE123'); // Cache hit

            expect($result1)->toBe('Alice');
            expect($result2)->toBe('Bob');
            expect($result3)->toBe('Alice');
        });
    });

    describe('getProcurementsByKey', function () {
        test('it transforms stream data to procurement collection', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Test Procurement',
                            'stage' => 'Pre-Procurement',
                            'current_status' => 'Active',
                            'user_address' => '1ABC123XYZ',
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
            ];

            User::factory()->create([
                'name' => 'John Doe',
                'blockchain_address' => '1ABC123XYZ',
            ]);

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result)->toHaveCount(1);
            expect($result->has('PR-001'))->toBeTrue();
            expect($result->get('PR-001')['title'])->toBe('Test Procurement');
            expect($result->get('PR-001')['stage'])->toBe('Pre-Procurement');
            expect($result->get('PR-001')['user'])->toBe('John Doe');
        });

        test('it enriches procurements with batched mode metadata', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Mode A Procurement',
                            'stage' => 'Pre-Procurement',
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-002',
                            'procurement_title' => 'Mode B Procurement',
                            'stage' => 'Bidding',
                            'timestamp' => '2024-01-16T10:00:00Z',
                        ],
                    ],
                ],
            ];

            $procurementOne = ProcurementData::fromBlockchainArray([
                'pr_number' => 'PR-001',
                'title' => 'Mode A Procurement',
                'category' => 'goods',
                'procurement_mode' => 'small_value_procurement',
            ]);
            $procurementTwo = ProcurementData::fromBlockchainArray([
                'pr_number' => 'PR-002',
                'title' => 'Mode B Procurement',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
            ]);

            $this->mirrorRepository
                ->shouldReceive('findManyByProcurement')
                ->with(['PR-001', 'PR-002'])
                ->andReturn([
                    'PR-001' => $procurementOne,
                    'PR-002' => $procurementTwo,
                ])
                ->once();

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result->get('PR-001')['procurement_mode'])->toBe('small_value_procurement')
                ->and($result->get('PR-001')['is_alternative_mode'])->toBeTrue()
                ->and($result->get('PR-002')['procurement_mode'])->toBe('competitive_bidding')
                ->and($result->get('PR-002')['is_alternative_mode'])->toBeFalse();
        });

        test('it groups multiple entries by procurement ID and returns latest', function () {
            $streamData = [
                [
                    'time' => 1705312800, // 2024-01-15T10:00:00Z
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Test Procurement',
                            'stage' => 'Pre-Procurement',
                            'current_status' => 'Active',
                            'user_address' => '1ABC123XYZ',
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
                [
                    'time' => 1705413600, // 2024-01-16T14:00:00Z
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Test Procurement',
                            'stage' => 'Bidding',
                            'current_status' => 'In Progress',
                            'user_address' => '1ABC123XYZ',
                            'timestamp' => '2024-01-16T14:00:00Z', // Later timestamp
                        ],
                    ],
                ],
            ];

            User::factory()->create([
                'blockchain_address' => '1ABC123XYZ',
            ]);

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result)->toHaveCount(1);
            expect($result->get('PR-001')['stage'])->toBe('Bidding');
            expect($result->get('PR-001')['timestamp'])->toBe('2024-01-16T14:00:00Z');
        });

        test('it filters out items with missing required fields', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Valid Procurement',
                            'stage' => 'Pre-Procurement',
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-002',
                            // Missing procurement_title
                            'stage' => 'Pre-Procurement',
                        ],
                    ],
                ],
                [
                    'data' => [
                        'json' => [
                            // Missing pr_number
                            'procurement_title' => 'Invalid Procurement',
                            'stage' => 'Pre-Procurement',
                        ],
                    ],
                ],
            ];

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result)->toHaveCount(1);
            expect($result->has('PR-001'))->toBeTrue();
            expect($result->has('PR-002'))->toBeFalse();
        });

        test('it logs warnings for invalid data structures', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'procurement_title' => 'Invalid - No ID',
                        ],
                    ],
                ],
            ];

            $this->service->getProcurementsByKey($streamData);

            Log::shouldHaveReceived('warning')
                ->with('Invalid procurement data structure', Mockery::type('array'))
                ->once();
        });

        test('it handles empty stream data', function () {
            $result = $this->service->getProcurementsByKey([]);

            expect($result)->toBeEmpty();
        });

        test('it uses status field when current_status is missing', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'Test Procurement',
                            'stage' => 'Pre-Procurement',
                            // No current_status field
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
            ];

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result->get('PR-001')['status'])->toBe('Pre-Procurement');
        });

        test('it handles multiple procurements with different IDs', function () {
            $streamData = [
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-001',
                            'procurement_title' => 'First Procurement',
                            'stage' => 'Pre-Procurement',
                            'timestamp' => '2024-01-15T10:00:00Z',
                        ],
                    ],
                ],
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-002',
                            'procurement_title' => 'Second Procurement',
                            'stage' => 'Bidding',
                            'timestamp' => '2024-01-16T14:00:00Z',
                        ],
                    ],
                ],
                [
                    'data' => [
                        'json' => [
                            'pr_number' => 'PR-003',
                            'procurement_title' => 'Third Procurement',
                            'stage' => 'Post-Qualification',
                            'timestamp' => '2024-01-17T09:00:00Z',
                        ],
                    ],
                ],
            ];

            $result = $this->service->getProcurementsByKey($streamData);

            expect($result)->toHaveCount(3);
            expect($result->has('PR-001'))->toBeTrue();
            expect($result->has('PR-002'))->toBeTrue();
            expect($result->has('PR-003'))->toBeTrue();
        });

        test('it handles null json data gracefully', function () {
            // Create malformed data with null json
            $streamData = [
                [
                    'data' => [
                        'json' => null, // Null json
                    ],
                ],
            ];

            // Should filter out null items and return empty collection
            $result = $this->service->getProcurementsByKey($streamData);

            expect($result)->toBeEmpty();
        });
    });

    describe('getRecentProcurements', function () {
        test('it returns recent procurements sorted by timestamp', function () {
            $procurements = collect([
                'PR-001' => [
                    'id' => 'PR-001',
                    'title' => 'Oldest',
                    'stage' => 'Pre-Procurement',
                    'status' => 'Active',
                    'timestamp' => '2024-01-10T10:00:00Z',
                ],
                'PR-002' => [
                    'id' => 'PR-002',
                    'title' => 'Middle',
                    'stage' => 'Bidding',
                    'status' => 'Active',
                    'timestamp' => '2024-01-15T14:00:00Z',
                ],
                'PR-003' => [
                    'id' => 'PR-003',
                    'title' => 'Newest',
                    'stage' => 'Post-Qualification',
                    'status' => 'Active',
                    'timestamp' => '2024-01-20T09:00:00Z',
                ],
            ]);

            $result = $this->service->getRecentProcurements($procurements);

            expect($result)->toHaveCount(3);
            expect($result[0]['id'])->toBe('PR-003'); // Newest first
            expect($result[1]['id'])->toBe('PR-002');
            expect($result[2]['id'])->toBe('PR-001'); // Oldest last
        });

        test('it limits results to configured display limit', function () {
            $procurements = collect();
            for ($i = 1; $i <= 10; $i++) {
                $procurements->put("PR-{$i}", [
                    'id' => "PR-{$i}",
                    'title' => "Procurement {$i}",
                    'stage' => 'Pre-Procurement',
                    'status' => 'Active',
                    'timestamp' => "2024-01-{$i}T10:00:00Z",
                ]);
            }

            $result = $this->service->getRecentProcurements($procurements);

            // Config default is 5
            expect($result)->toHaveCount(5);
        });

        test('it returns only required fields', function () {
            $procurements = collect([
                'PR-001' => [
                    'id' => 'PR-001',
                    'title' => 'Test Procurement',
                    'stage' => 'Pre-Procurement',
                    'status' => 'Active',
                    'timestamp' => '2024-01-15T10:00:00Z',
                    'user' => 'John Doe', // Extra field
                    'user_address' => '1ABC123XYZ', // Extra field
                ],
            ]);

            $result = $this->service->getRecentProcurements($procurements);

            expect($result[0])->toHaveKeys(['id', 'title', 'stage', 'status']);
            expect($result[0])->not->toHaveKey('user');
            expect($result[0])->not->toHaveKey('user_address');
            expect($result[0])->not->toHaveKey('timestamp');
        });

        test('it handles empty collection', function () {
            $result = $this->service->getRecentProcurements(collect());

            expect($result)->toBeEmpty();
        });
    });

    describe('getProcurementDistributionData', function () {
        test('it returns all procurements sorted by timestamp', function () {
            $procurements = collect([
                'PR-001' => [
                    'id' => 'PR-001',
                    'title' => 'First',
                    'stage' => 'Pre-Procurement',
                    'status' => 'Active',
                    'timestamp' => '2024-01-10T10:00:00Z',
                ],
                'PR-002' => [
                    'id' => 'PR-002',
                    'title' => 'Second',
                    'stage' => 'Bidding',
                    'status' => 'Active',
                    'timestamp' => '2024-01-15T14:00:00Z',
                ],
            ]);

            $result = $this->service->getProcurementDistributionData($procurements);

            expect($result)->toHaveCount(2);
            expect($result[0]['id'])->toBe('PR-002'); // Newest first
        });

        test('it returns required fields for distribution visualization', function () {
            $procurements = collect([
                'PR-001' => [
                    'id' => 'PR-001',
                    'title' => 'Test',
                    'stage' => 'Pre-Procurement',
                    'status' => 'Active',
                    'timestamp' => '2024-01-15T10:00:00Z',
                    'user' => 'John Doe',
                ],
            ]);

            $result = $this->service->getProcurementDistributionData($procurements);

            expect($result[0])->toHaveKeys(['id', 'title', 'stage', 'status']);
            expect($result[0])->not->toHaveKey('timestamp');
            expect($result[0])->not->toHaveKey('user');
        });
    });

    describe('getRecentActivities', function () {
        test('it retrieves and formats recent activities from blockchain', function () {
            $this->userService
                ->shouldReceive('getUserNameByAddress')
                ->with('1ABC123XYZ')
                ->andReturn('Jane Doe');

            $eventData = new EventData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement',
                stage: 'Contract And PO',
                eventType: 'document_uploaded',
                category: 'document',
                severity: 'info',
                details: 'Contract signed',
                documentCount: 1,
                userAddress: '1ABC123XYZ',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );

            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andReturn([$eventData]);

            $result = $this->service->getRecentActivities();

            expect($result)->toHaveCount(1);
            expect($result[0]['id'])->toBe('PR-001');
            expect($result[0]['title'])->toBe('Test Procurement');
            expect($result[0]['action'])->toBe('Document Uploaded');
            expect($result[0]['user'])->toBe('Jane Doe');
        });

        test('it filters out invalid activity items', function () {
            $validEvent = new EventData(
                prNumber: 'PR-001',
                procurementTitle: 'Valid',
                stage: 'Pre-Procurement',
                eventType: 'test',
                category: 'test',
                severity: 'info',
                details: 'test',
                documentCount: 0,
                userAddress: '1ABC123XYZ',
                timestamp: Carbon::now(),
            );

            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andReturn([$validEvent]);

            $result = $this->service->getRecentActivities();

            expect($result)->toHaveCount(1);
        });

        test('it returns empty array when no events found', function () {
            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andReturn([]);

            $result = $this->service->getRecentActivities();

            expect($result)->toBeEmpty();

            Log::shouldHaveReceived('warning')
                ->with('No events found in repository')
                ->once();
        });

        test('it sorts activities by timestamp descending', function () {
            $event1 = new EventData(
                prNumber: 'PR-001',
                procurementTitle: 'Oldest',
                stage: 'Pre-Procurement',
                eventType: 'test',
                category: 'test',
                severity: 'info',
                details: 'test',
                documentCount: 0,
                userAddress: '1ABC123XYZ',
                timestamp: Carbon::parse('2024-01-10T10:00:00Z'),
            );
            $event2 = new EventData(
                prNumber: 'PR-002',
                procurementTitle: 'Newest',
                stage: 'Bidding',
                eventType: 'test',
                category: 'test',
                severity: 'info',
                details: 'test',
                documentCount: 0,
                userAddress: '1ABC123XYZ',
                timestamp: Carbon::parse('2024-01-20T10:00:00Z'),
            );
            $event3 = new EventData(
                prNumber: 'PR-003',
                procurementTitle: 'Middle',
                stage: 'Post-Qualification',
                eventType: 'test',
                category: 'test',
                severity: 'info',
                details: 'test',
                documentCount: 0,
                userAddress: '1ABC123XYZ',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );

            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andReturn([$event1, $event2, $event3]);

            $result = $this->service->getRecentActivities();

            expect($result[0]['id'])->toBe('PR-002'); // Newest
            expect($result[1]['id'])->toBe('PR-003'); // Middle
            expect($result[2]['id'])->toBe('PR-001'); // Oldest
        });

        test('it limits results to configured display limit', function () {
            // Generate more activities than display limit (8)
            $events = [];
            for ($i = 1; $i <= 15; $i++) {
                $events[] = new EventData(
                    prNumber: "PR-{$i}",
                    procurementTitle: "Procurement {$i}",
                    stage: 'Pre-Procurement',
                    eventType: 'test',
                    category: 'test',
                    severity: 'info',
                    details: 'test',
                    documentCount: 0,
                    userAddress: '1ABC123XYZ',
                    timestamp: Carbon::parse("2024-01-{$i}T10:00:00Z"),
                );
            }

            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andReturn($events);

            $result = $this->service->getRecentActivities();

            expect($result)->toHaveCount(8); // Config default
        });

        test('it handles blockchain service exceptions', function () {
            $this->mirrorRepository
                ->shouldReceive('findRecentEvents')
                ->with(16)
                ->andThrow(new Exception('Repository failed'));

            $result = $this->service->getRecentActivities();

            expect($result)->toBeEmpty();

            Log::shouldHaveReceived('error')
                ->with('Failed to retrieve recent activities', Mockery::type('array'))
                ->once();
        });
    });

    describe('getTotalDocuments', function () {
        test('it calculates total documents for dashboard procurements', function () {
            $procurements = collect([
                'PR-001' => ['prNumber' => 'PR-001'],
                'PR-002' => ['prNumber' => 'PR-002'],
            ]);

            $doc1 = new DocumentData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement 1',
                userAddress: '1ABC123XYZ',
                stage: 'Pre-Procurement',
                status: 'Active',
                documentType: 'Contract',
                fileKey: 'key1',
                fileName: 'doc1.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'hash1',
                dataTxid: 'tx1',
                metadataTxid: 'mtx1',
                uploadedBy: 'user1',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );
            $doc2 = new DocumentData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement 1',
                userAddress: '1ABC123XYZ',
                stage: 'Pre-Procurement',
                status: 'Active',
                documentType: 'Contract',
                fileKey: 'key2',
                fileName: 'doc2.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'hash2',
                dataTxid: 'tx2',
                metadataTxid: 'mtx2',
                uploadedBy: 'user1',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );
            $doc3 = new DocumentData(
                prNumber: 'PR-002',
                procurementTitle: 'Test Procurement 2',
                userAddress: '1DEF456ABC',
                stage: 'Bidding',
                status: 'Active',
                documentType: 'Bid',
                fileKey: 'key3',
                fileName: 'doc3.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'hash3',
                dataTxid: 'tx3',
                metadataTxid: 'mtx3',
                uploadedBy: 'user2',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );

            $this->mirrorRepository->shouldReceive('getAllDocuments')
                ->with(500)
                ->andReturn([$doc1, $doc2, $doc3]);

            $result = $this->service->getTotalDocuments($procurements);

            // 2 unique hashes from PR-001, 1 unique hash from PR-002 = 3 total
            expect($result)->toBe(3);

            Log::shouldHaveReceived('info')
                ->with('Dashboard document count calculated', Mockery::on(function ($data) {
                    return isset($data['total_documents']) && $data['total_documents'] === 3;
                }))
                ->once();
        });

        test('it handles duplicate document hashes correctly', function () {
            $procurements = collect([
                'PR-001' => ['prNumber' => 'PR-001'],
            ]);

            $doc1 = new DocumentData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement',
                userAddress: '1ABC123XYZ',
                stage: 'Pre-Procurement',
                status: 'Active',
                documentType: 'Contract',
                fileKey: 'key1',
                fileName: 'doc1.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'duplicate_hash',
                dataTxid: 'tx1',
                metadataTxid: 'mtx1',
                uploadedBy: 'user1',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );
            $doc2 = new DocumentData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement',
                userAddress: '1ABC123XYZ',
                stage: 'Pre-Procurement',
                status: 'Active',
                documentType: 'Contract',
                fileKey: 'key2',
                fileName: 'doc2.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'duplicate_hash', // Same hash
                dataTxid: 'tx2',
                metadataTxid: 'mtx2',
                uploadedBy: 'user1',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );

            $this->mirrorRepository->shouldReceive('getAllDocuments')
                ->with(500)
                ->andReturn([$doc1, $doc2]);

            $result = $this->service->getTotalDocuments($procurements);

            expect($result)->toBe(1); // Duplicates counted once
        });

        test('it returns zero when document stream is empty', function () {
            $procurements = collect([
                'PR-001' => ['prNumber' => 'PR-001'],
            ]);

            $this->mirrorRepository->shouldReceive('getAllDocuments')
                ->with(500)
                ->andReturn([]);

            $result = $this->service->getTotalDocuments($procurements);

            expect($result)->toBe(0);

            Log::shouldHaveReceived('warning')
                ->with('Failed to retrieve document stream items for dashboard stats.')
                ->once();
        });

        test('it filters documents missing required fields', function () {
            $procurements = collect([
                'PR-001' => ['prNumber' => 'PR-001'],
            ]);

            $doc1 = new DocumentData(
                prNumber: 'PR-001',
                procurementTitle: 'Test Procurement',
                userAddress: '1ABC123XYZ',
                stage: 'Pre-Procurement',
                status: 'Active',
                documentType: 'Contract',
                fileKey: 'key1',
                fileName: 'doc1.pdf',
                fileSize: 1000,
                mimeType: 'application/pdf',
                hash: 'valid_hash',
                dataTxid: 'tx1',
                metadataTxid: 'mtx1',
                uploadedBy: 'user1',
                timestamp: Carbon::parse('2024-01-15T10:00:00Z'),
            );

            $this->mirrorRepository->shouldReceive('getAllDocuments')
                ->with(500)
                ->andReturn([$doc1]);

            $result = $this->service->getTotalDocuments($procurements);
            expect($result)->toBe(1); // Only valid document counted
        });

        test('it handles exceptions gracefully', function () {
            $procurements = collect([
                'PR-001' => ['prNumber' => 'PR-001'],
            ]);

            $this->mirrorRepository->shouldReceive('getAllDocuments')
                ->with(500)
                ->andThrow(new Exception('Repository error'));

            $result = $this->service->getTotalDocuments($procurements);

            expect($result)->toBe(0);

            Log::shouldHaveReceived('error')
                ->with('Failed to calculate total documents for dashboard', Mockery::type('array'))
                ->once();
        });
    });

    describe('countOngoingProjects', function () {
        test('it excludes completed monitoring projects', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'Pre-Procurement'],
                'PR-002' => ['stage' => 'Bidding'],
                'PR-003' => ['stage' => 'Notice Of Award'],
                'PR-004' => ['stage' => 'completed'], // This should be excluded
            ]);

            $result = $this->service->countOngoingProjects($procurements);

            expect($result)->toBe(3); // PR-001, PR-002, PR-003
        });

        test('it handles empty collection', function () {
            $result = $this->service->countOngoingProjects(collect());

            expect($result)->toBe(0);
        });
    });

    describe('countCompletedBiddings', function () {
        test('it counts procurements in post-award stages', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'completed'],
                'PR-002' => ['stage' => 'completion'],
                'PR-003' => ['stage' => 'Pre-Procurement'],
            ]);

            $result = $this->service->countCompletedBiddings($procurements);

            expect($result)->toBe(2); // PR-001 and PR-002
        });

        test('it includes all configured completed bidding stages', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'completion'],
                'PR-002' => ['stage' => 'completed'],
                'PR-003' => ['stage' => 'Completion'],
                'PR-004' => ['stage' => 'Completed'],
                'PR-005' => ['stage' => 'Monitoring'],
                'PR-006' => ['stage' => 'Bidding'],
            ]);

            $result = $this->service->countCompletedBiddings($procurements);

            expect($result)->toBe(4); // completion, completed, Completion, Completed
        });

        test('it excludes pre-award stages', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'Pre-Procurement'],
                'PR-002' => ['stage' => 'Bidding'],
                'PR-003' => ['stage' => 'Bid Evaluation'],
                'PR-004' => ['stage' => 'completed'], // Only this counts
            ]);

            $result = $this->service->countCompletedBiddings($procurements);

            expect($result)->toBe(1);
        });

        test('it handles empty collection', function () {
            $result = $this->service->countCompletedBiddings(collect());

            expect($result)->toBe(0);
        });
    });

    describe('getEmptyStats', function () {
        test('it returns empty stats structure', function () {
            $result = $this->service->getEmptyStats();

            expect($result)->toBe([
                'ongoingProjects' => 0,
                'completedBiddings' => 0,
                'totalDocuments' => 0,
            ]);
        });
    });

    describe('calculateStats', function () {
        test('it calculates all dashboard statistics correctly', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'Pre-Procurement', 'status' => 'Active'],
                'PR-002' => ['stage' => 'Bidding', 'status' => 'Active'],
                'PR-003' => ['stage' => 'completed', 'status' => 'Active'],
                'PR-004' => ['stage' => 'completed', 'status' => 'Completed'],
            ]);

            $result = $this->service->calculateStats($procurements, 15);

            // Ongoing: All except completed stages (PR-003, PR-004)
            expect($result['ongoingProjects'])->toBe(2); // PR-001, PR-002
            expect($result['completedBiddings'])->toBe(2); // PR-003, PR-004
            expect($result['totalDocuments'])->toBe(15);
        });

        test('it handles empty procurements collection', function () {
            $result = $this->service->calculateStats(collect(), 0);

            expect($result)->toBe([
                'ongoingProjects' => 0,
                'completedBiddings' => 0,
                'totalDocuments' => 0,
            ]);
        });

        test('it uses provided total documents count', function () {
            $procurements = collect([
                'PR-001' => ['stage' => 'Pre-Procurement', 'status' => 'Active'],
            ]);

            $result = $this->service->calculateStats($procurements, 42);

            expect($result['totalDocuments'])->toBe(42);
        });
    });
});
