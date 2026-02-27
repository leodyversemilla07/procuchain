<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;
use App\Services\Procurement\ProcurementCorrectionService;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper: build a ProcurementCorrectionService with Manager-backed final repos.
 */
function buildCorrectionService(
    ?object $procurementRepository = null,
    ?Manager $correctionManager = null,
    ?Manager $procCorrectionManager = null,
    ?object $documentRepository = null,
    ?object $procurementDataService = null,
): ProcurementCorrectionService {
    return new ProcurementCorrectionService(
        $procurementRepository ?? Mockery::mock(ProcurementRepository::class),
        new ProcurementCorrectionRepository($procCorrectionManager ?? Mockery::mock(Manager::class)),
        new CorrectionRepository($correctionManager ?? Mockery::mock(Manager::class)),
        $documentRepository ?? Mockery::mock(DocumentRepository::class),
        $procurementDataService ?? Mockery::mock(ProcurementDataService::class),
    );
}

beforeEach(function () {
    Log::spy();

    $this->procurementRepository = Mockery::mock(ProcurementRepository::class);
    $this->documentRepository = Mockery::mock(DocumentRepository::class);
    $this->procurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->procCorrectionManager = Mockery::mock(Manager::class);
    $this->correctionManager = Mockery::mock(Manager::class);

    $this->service = buildCorrectionService(
        procurementRepository: $this->procurementRepository,
        correctionManager: $this->correctionManager,
        procCorrectionManager: $this->procCorrectionManager,
        documentRepository: $this->documentRepository,
        procurementDataService: $this->procurementDataService,
    );
});

describe('ProcurementCorrectionService', function () {
    describe('extractCorrectedData', function () {
        it('extracts basic information fields', function () {
            $validated = [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'abc_amount' => '500000.50',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveKeys(['title', 'description', 'abc_amount'])
                ->and($result['title'])->toBe('Updated Title')
                ->and($result['description'])->toBe('Updated Description')
                ->and($result['abc_amount'])->toBe(500000.50);
        });

        it('extracts office and organizational fields', function () {
            $validated = [
                'office' => 'Engineering Office',
                'end_user' => 'IT Department',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveKeys(['office', 'end_user'])
                ->and($result['office'])->toBe('Engineering Office')
                ->and($result['end_user'])->toBe('IT Department');
        });

        it('extracts BAC resolution fields', function () {
            $validated = [
                'bac_resolution_number' => 'BAC-2025-001',
                'bac_resolution_date' => '2025-01-15',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveKeys(['bac_resolution_number', 'bac_resolution_date'])
                ->and($result['bac_resolution_number'])->toBe('BAC-2025-001');
        });

        it('extracts PhilGEPS fields', function () {
            $validated = [
                'philgeps_reference' => 'PG-2025-001',
                'philgeps_posting_date' => '2025-01-20',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveKeys(['philgeps_reference', 'philgeps_posting_date']);
        });

        it('extracts approval fields', function () {
            $validated = [
                'approved_by' => 'Juan Dela Cruz',
                'approval_date' => '2025-02-01',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveKeys(['approved_by', 'approval_date']);
        });

        it('returns empty array when no recognized fields are present', function () {
            $validated = [
                'unrelated_field' => 'value',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toBeEmpty();
        });

        it('extracts all fields when all are present', function () {
            $validated = [
                'title' => 'Title',
                'description' => 'Desc',
                'abc_amount' => '100000',
                'funding_source' => 'GAA',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Office',
                'end_user' => 'End User',
                'bac_resolution_number' => 'BAC-001',
                'bac_resolution_date' => '2025-01-01',
                'philgeps_reference' => 'PG-001',
                'philgeps_posting_date' => '2025-01-10',
                'approved_by' => 'Approver',
                'approval_date' => '2025-02-01',
            ];

            $result = $this->service->extractCorrectedData($validated);

            expect($result)->toHaveCount(14);
        });

        it('casts abc_amount to float', function () {
            $result = $this->service->extractCorrectedData(['abc_amount' => '123456']);

            expect($result['abc_amount'])->toBeFloat()->toBe(123456.0);
        });
    });

    describe('findProcurementForCorrection', function () {
        it('returns procurement from primary repository when found', function () {
            $procurement = ProcurementData::fromBlockchainArray([
                'pr_number' => 'PR-2025-001',
                'title' => 'Test',
                'description' => 'Test',
                'abc_amount' => '500000',
                'funding_source' => 'GAA',
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Office',
                'status' => 'draft',
                'user_id' => '1',
                'created_at' => now()->toIso8601String(),
            ]);

            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->with('PR-2025-001')
                ->once()
                ->andReturn($procurement);

            $result = $this->service->findProcurementForCorrection('PR-2025-001');

            expect($result)->toBe($procurement);
        });

        it('falls back to STATUS stream when not found in primary repo', function () {
            // NOTE: The service's fallback path has a bug - it calls ProcurementData constructor
            // with unknown parameters (stage, procurementMode, timestamp, userAddress).
            // This test documents the bug by catching the expected error.
            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(null);

            $statusCollection = collect([
                [
                    'procurement_title' => 'Test Procurement',
                    'current_status' => 'procurement_submitted',
                    'stage' => 'procurement_initiation',
                    'timestamp' => now()->toIso8601String(),
                    'user_address' => '1abc123',
                ],
            ]);

            $this->procurementDataService
                ->shouldReceive('fetchStatusItems')
                ->with('PR-2025-001')
                ->once()
                ->andReturn($statusCollection);

            $this->actingAs(createUserWithRole('bac_secretariat'));

            // The service's fallback ProcurementData construction uses invalid named params
            expect(fn () => $this->service->findProcurementForCorrection('PR-2025-001'))
                ->toThrow(\Error::class, 'Unknown named parameter $stage');
        })->note('Documents pre-existing bug in service fallback path');

        it('throws RuntimeException when not found in any stream', function () {
            $this->procurementRepository
                ->shouldReceive('findByProcurement')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(null);

            $this->procurementDataService
                ->shouldReceive('fetchStatusItems')
                ->with('PR-2025-001')
                ->once()
                ->andReturn(collect());

            $this->actingAs(createUserWithRole('bac_secretariat'));

            expect(fn () => $this->service->findProcurementForCorrection('PR-2025-001'))
                ->toThrow(\RuntimeException::class, 'Procurement not found in blockchain.');
        });
    });

    describe('checkCorrections', function () {
        it('returns has_corrections true when corrections exist in blockchain', function () {
            $this->procCorrectionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([
                    [
                        'txid' => 'tx_correction_1',
                        'data' => [
                            'json' => [
                                'pr_number' => 'PR-2025-001',
                                'procurement_title' => 'Test',
                                'correction_type' => 'metadata',
                                'reason' => 'Typo fix',
                                'corrected_by' => 'user@test.com',
                                'user_address' => '1abc',
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            $result = $this->service->checkCorrections('PR-2025-001');

            expect($result['has_corrections'])->toBeTrue()
                ->and($result['latest_correction'])->not->toBeNull();
        });

        it('returns has_corrections false when no corrections exist', function () {
            $this->procCorrectionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([]);

            $result = $this->service->checkCorrections('PR-2025-001');

            expect($result['has_corrections'])->toBeFalse()
                ->and($result['latest_correction'])->toBeNull();
        });
    });

    describe('formatStage', function () {
        it('returns display name for valid stage enum value', function () {
            $result = $this->service->formatStage('procurement_initiation');

            expect($result)->toBe('Procurement Initiation');
        });

        it('returns the raw value for invalid stage', function () {
            $result = $this->service->formatStage('non_existent_stage');

            expect($result)->toBe('non_existent_stage');
        });

        it('returns Unknown for null input', function () {
            $result = $this->service->formatStage(null);

            expect($result)->toBe('Unknown');
        });

        it('returns Unknown for "Unknown" string input', function () {
            $result = $this->service->formatStage('Unknown');

            expect($result)->toBe('Unknown');
        });
    });

    describe('formatDocumentType', function () {
        it('returns display name for valid document type', function () {
            $result = $this->service->formatDocumentType('purchase_request');

            expect($result)->toBe(DocumentTypeEnums::PURCHASE_REQUEST->getDisplayName());
        });

        it('returns Unknown Document for null input', function () {
            $result = $this->service->formatDocumentType(null);

            expect($result)->toBe('Unknown Document');
        });

        it('returns Unknown Document for "Unknown" string input', function () {
            $result = $this->service->formatDocumentType('Unknown');

            expect($result)->toBe('Unknown Document');
        });

        it('converts snake_case to Title Case for unrecognized types', function () {
            $result = $this->service->formatDocumentType('custom_document_type');

            expect($result)->toBe('Custom Document Type');
        });
    });

    describe('getCorrectionHistory', function () {
        it('combines and sorts corrections from both repositories', function () {
            $this->actingAs(createUserWithRole('bac_secretariat'));

            // Mock procurement corrections manager
            $this->procCorrectionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([
                    [
                        'txid' => 'tx_proc_1',
                        'data' => [
                            'json' => [
                                'pr_number' => 'PR-2025-001',
                                'procurement_title' => 'Test',
                                'correction_type' => 'metadata',
                                'reason' => 'Title correction',
                                'corrected_by' => 'user@test.com',
                                'user_address' => '1abc',
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            // Mock document corrections manager
            $this->correctionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([
                    [
                        'txid' => 'tx_doc_1',
                        'data' => [
                            'json' => [
                                'pr_number' => 'PR-2025-001',
                                'procurement_title' => 'Test',
                                'original_txid' => 'otx1',
                                'original_document_hash' => 'hash1',
                                'correction_type' => 'document_replacement',
                                'action' => 'replace',
                                'reason' => 'Document replacement',
                                'corrected_by' => 'user@test.com',
                                'user_address' => '1abc',
                                'timestamp' => now()->subMinute()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            $result = $this->service->getCorrectionHistory('PR-2025-001');

            expect($result)->toHaveCount(2)
                ->and($result[0]['reason'])->toBe('Title correction')
                ->and($result[1]['reason'])->toBe('Document replacement');
        });

        it('returns empty array when no corrections exist', function () {
            $this->actingAs(createUserWithRole('bac_secretariat'));

            $this->procCorrectionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([]);

            $this->correctionManager
                ->shouldReceive('liststreamitems')
                ->andReturn([]);

            $result = $this->service->getCorrectionHistory('PR-2025-001');

            expect($result)->toBeEmpty();
        });
    });
});
