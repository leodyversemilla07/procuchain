<?php

use App\Enums\StreamEnums;
use App\Services\DocumentBlockchainService;
use App\Services\MultichainService;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Mock MultichainService
    $this->mockMultichainService = Mockery::mock(MultichainService::class);
    $this->service = new DocumentBlockchainService($this->mockMultichainService);
});

afterEach(function () {
    Mockery::close();
});

describe('DocumentBlockchainService - Get Document Data', function () {
    it('retrieves document data from blockchain by file key', function () {
        $fileKey = 'document_123.pdf';
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'document_123.pdf',
                        'procurement_id' => 'PROC-2024-001',
                        'procurement_title' => 'Office Supplies Procurement',
                        'document_type' => 'contract',
                        'stage' => 'award',
                        'file_size' => 1024000,
                        'timestamp' => '2024-10-31T10:00:00Z',
                        'hash' => 'abc123hash456',
                        'user_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(StreamEnums::DOCUMENTS->value, true, 10000, 0, false)
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeArray()
            ->toHaveKeys(['procurement_id', 'procurement_title', 'document_type', 'hash'])
            ->and($result['procurement_id'])->toBe('PROC-2024-001')
            ->and($result['hash'])->toBe('abc123hash456');
    });

    it('returns null when document not found', function () {
        $fileKey = 'nonexistent.pdf';
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'different.pdf',
                        'procurement_id' => 'PROC-2024-002',
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeNull();
    });

    it('handles blockchain query errors gracefully', function () {
        $fileKey = 'error_document.pdf';

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting to get blockchain data', ['file_key' => $fileKey]);

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(StreamEnums::DOCUMENTS->value, true, 10000, 0, false)
            ->andThrow(new Exception('Blockchain connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get document data from blockchain', Mockery::type('array'));

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeNull();
    });

    it('returns default values for missing fields', function () {
        $fileKey = 'partial_data.pdf';
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'partial_data.pdf',
                        'procurement_id' => 'PROC-2024-003',
                        // Missing other fields
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeArray()
            ->and($result['procurement_title'])->toBe('Unknown Document')
            ->and($result['stage'])->toBe('Unknown')
            ->and($result['hash'])->toBe('');
    });

    it('logs debug information during search', function () {
        $fileKey = 'test.pdf';
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'test.pdf',
                        'procurement_id' => 'PROC-001',
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        Log::shouldReceive('info')
            ->atLeast()
            ->once();

        Log::shouldReceive('debug')
            ->atLeast()
            ->once();

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeArray();
    });
});

describe('DocumentBlockchainService - Get Procurement Status', function () {
    it('retrieves current procurement status', function () {
        $procurementId = 'PROC-2024-001';
        $mockStatusItems = [
            [
                'data' => [
                    'json' => [
                        'procurement_id' => 'PROC-2024-001',
                        'current_status' => 'Awarded',
                        'stage' => 'Award',
                        'timestamp' => '2024-10-31T12:00:00Z',
                        'procurement_title' => 'Test Procurement',
                        'user_address' => 'admin@procuchain.gov',
                    ],
                ],
                'confirmations' => 10,
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(StreamEnums::STATUS->value, true, 1000, 0, false)
            ->andReturn($mockStatusItems);

        $result = $this->service->getCurrentProcurementStatus($procurementId);

        expect($result)->toBeArray()
            ->toHaveKeys(['procurement_id', 'current_status', 'stage', 'timestamp', 'procurement_title', 'user_address']);
    });

    it('handles empty status stream', function () {
        $procurementId = 'PROC-2024-999';

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->getCurrentProcurementStatus($procurementId);

        expect($result)->toBeNull();
    });

    it('filters status by procurement ID', function () {
        $procurementId = 'PROC-2024-001';
        $mockStatusItems = [
            [
                'data' => ['json' => ['procurement_id' => 'PROC-2024-002']],
            ],
            [
                'data' => ['json' => ['procurement_id' => 'PROC-2024-001', 'status' => 'Active']],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockStatusItems);

        $result = $this->service->getCurrentProcurementStatus($procurementId);

        expect($result)->toBeArray()
            ->and($result['procurement_id'])->toBe('PROC-2024-001');
    });
});

describe('DocumentBlockchainService - Document Verification', function () {
    it('verifies document hash matches blockchain', function () {
        $fileKey = 'contract.pdf';
        $expectedHash = 'abc123hash456';

        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'contract.pdf',
                        'hash' => 'abc123hash456',
                        'procurement_id' => 'PROC-001',
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData($fileKey);

        expect($result['hash'])->toBe($expectedHash);
    });

    it('extracts all metadata fields correctly', function () {
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'document.pdf',
                        'procurement_id' => 'PROC-001',
                        'procurement_title' => 'Test Procurement',
                        'document_type' => 'contract',
                        'stage' => 'award',
                        'file_size' => 2048000,
                        'timestamp' => '2024-10-31T10:00:00Z',
                        'hash' => 'hashvalue',
                        'user_address' => 'user@example.com',
                        'stage_metadata' => ['key' => 'value'],
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData('document.pdf');

        expect($result)->toMatchArray([
            'procurement_id' => 'PROC-001',
            'procurement_title' => 'Test Procurement',
            'document_type' => 'contract',
            'stage' => 'award',
            'file_size' => 2048000,
            'hash' => 'hashvalue',
            'user_address' => 'user@example.com',
        ])
            ->and($result['stage_metadata'])->toBeArray();
    });
});

describe('DocumentBlockchainService - Error Scenarios', function () {
    it('logs errors with full context', function () {
        $fileKey = 'error.pdf';

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting to get blockchain data', ['file_key' => $fileKey]);

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(StreamEnums::DOCUMENTS->value, true, 10000, 0, false)
            ->andThrow(new Exception('RPC Error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get document data from blockchain', Mockery::on(function ($context) use ($fileKey) {
                return $context['file_key'] === $fileKey
                    && isset($context['error'])
                    && isset($context['trace']);
            }));

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeNull();
    });

    it('handles null blockchain response', function () {
        $fileKey = 'document.pdf';

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting to get blockchain data', ['file_key' => $fileKey]);

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(StreamEnums::DOCUMENTS->value, true, 10000, 0, false)
            ->andReturn(null);

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to retrieve document stream items.', ['file_key' => $fileKey]);

        $result = $this->service->getDocumentData($fileKey);

        expect($result)->toBeNull();
    });

    it('handles malformed blockchain data', function () {
        $mockItems = [
            [
                'data' => [
                    // Missing 'json' key
                    'text' => 'invalid data',
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData('test.pdf');

        expect($result)->toBeNull();
    });
});

describe('DocumentBlockchainService - Collection Operations', function () {
    it('correctly filters documents from large datasets', function () {
        $targetFileKey = 'target.pdf';
        $mockItems = [];

        // Create 100 mock items
        for ($i = 0; $i < 100; $i++) {
            $mockItems[] = [
                'data' => [
                    'json' => [
                        'file_key' => "document_{$i}.pdf",
                        'procurement_id' => "PROC-{$i}",
                    ],
                ],
            ];
        }

        // Add target in the middle
        $mockItems[50] = [
            'data' => [
                'json' => [
                    'file_key' => $targetFileKey,
                    'procurement_id' => 'PROC-TARGET',
                    'hash' => 'targethash',
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData($targetFileKey);

        expect($result)->toBeArray()
            ->and($result['procurement_id'])->toBe('PROC-TARGET')
            ->and($result['hash'])->toBe('targethash');
    });

    it('handles empty streams gracefully', function () {
        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->getDocumentData('any.pdf');

        expect($result)->toBeNull();
    });
});

describe('DocumentBlockchainService - Timestamp Handling', function () {
    it('preserves ISO timestamp format', function () {
        $timestamp = '2024-10-31T10:30:00Z';
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'doc.pdf',
                        'procurement_id' => 'PROC-001',
                        'timestamp' => $timestamp,
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData('doc.pdf');

        expect($result['timestamp'])->toBe($timestamp);
    });

    it('provides current timestamp when missing', function () {
        $mockItems = [
            [
                'data' => [
                    'json' => [
                        'file_key' => 'doc.pdf',
                        'procurement_id' => 'PROC-001',
                        // No timestamp
                    ],
                ],
            ],
        ];

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $result = $this->service->getDocumentData('doc.pdf');

        expect($result['timestamp'])->toBeString()
            ->toContain('T')
            ->toContain('Z');
    });
});

describe('DocumentBlockchainService - Performance', function () {
    it('limits stream query size appropriately', function () {
        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->with(
                StreamEnums::DOCUMENTS->value,
                true,
                10000, // count limit
                0,     // start
                false  // local ordering
            )
            ->andReturn([]);

        $this->service->getDocumentData('test.pdf');
    });

    it('uses efficient collection filtering', function () {
        // Test that the service uses Laravel collections efficiently
        $mockItems = array_map(fn ($i) => [
            'data' => ['json' => ['file_key' => "doc_{$i}.pdf", 'procurement_id' => "PROC-{$i}"]],
        ], range(1, 1000));

        $this->mockMultichainService
            ->shouldReceive('listStreamItems')
            ->once()
            ->andReturn($mockItems);

        $start = microtime(true);
        $this->service->getDocumentData('doc_500.pdf');
        $duration = microtime(true) - $start;

        // Should complete quickly even with 1000 items
        expect($duration)->toBeLessThan(1.0);
    });
});
