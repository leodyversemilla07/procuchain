<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Services\BlockchainStorageService;
use App\Services\DocumentValidationService;
use App\Services\StageDocumentRequirementsService;
use App\Services\Verification\DocumentCompletenessVerifier;
use App\Services\Verification\DocumentComplianceVerifier;
use App\Services\Verification\DocumentCrossReferenceVerifier;
use App\Services\Verification\DocumentIntegrityVerifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeDocumentData(array $overrides = []): object
{
    $merged = array_merge([
        'pr_number' => 'PR-2024-001-0001',
        'procurement_title' => 'Test Procurement',
        'user_address' => '0xTestAddress',
        'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        'status' => ProcurementStatus::PROCUREMENT_INITIATED->value,
        'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
        'file_key' => 'test-file-key-'.uniqid(),
        'filename' => 'test-document.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'hash' => hash('sha256', 'test-content'),
        'data_txid' => 'txid-'.uniqid(),
        'metadata_txid' => 'meta-txid-'.uniqid(),
        'uploaded_by' => 'test-user',
        'uploaded_at' => Carbon::now(),
        'description' => null,
        'stage_metadata' => null,
    ], $overrides);

    $merged['procurement'] = (object) ['pr_number' => $merged['pr_number']];

    return (object) $merged;
}

// =============================================================================
// DocumentIntegrityVerifier
// =============================================================================
describe('DocumentIntegrityVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->blockchainStorage = Mockery::mock(BlockchainStorageService::class);
        $this->verifier = new DocumentIntegrityVerifier(
            $this->blockchainStorage,
        );
    });

    describe('verify', function () {
        it('returns success when hashes match', function () {
            $content = 'test-File-content';
            $hash = hash('sha256', $content);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('File-key-1', 'txid-1')
                ->andReturn(['hash' => $hash, 'content' => $content]);

            $result = $this->verifier->verify('File-key-1', 'txid-1');

            expect($result['is_valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
            expect($result['verification_type'])->toBe('integrity');
        });

        it('returns failure when hashes do not match', function () {
            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('File-key-1', 'txid-1')
                ->andReturn(['hash' => 'wrong_hash', 'content' => 'content']);

            $result = $this->verifier->verify('File-key-1', 'txid-1');

            expect($result['is_valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('hash mismatch');
        });

        it('returns failure on exception', function () {
            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->andThrow(new Exception('Blockchain unavailable'));

            $result = $this->verifier->verify('File-key-1', 'txid-1');

            expect($result['is_valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('Blockchain unavailable');
        });
    });

    describe('verifySingle', function () {
        it('delegates to verify when document found', function () {
            $procurement = Procurement::create(['pr_number' => 'PR-TEST-001', 'title' => 'Test', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding']);
            ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'file_key' => 'fk-1',
                'txid' => 'txid-1',
                'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'filename' => 'test.pdf',
                'mime_type' => 'application/pdf',
                'hash' => hash('sha256', 'test-content'),
                'uploaded_by' => 'test-user',
                'uploaded_at' => Carbon::now(),
            ]);

            $content = 'File-content';
            $hash = hash('sha256', $content);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('fk-1', 'txid-1')
                ->andReturn(['hash' => $hash, 'content' => $content]);

            $result = $this->verifier->verifySingle('fk-1');

            expect($result['is_valid'])->toBeTrue();
        });

        it('returns failure when document not found', function () {
            $result = $this->verifier->verifySingle('nonexistent-key');

            expect($result['is_valid'])->toBeFalse();
        });
    });

    describe('batchVerify', function () {
        it('verifies all documents for a procurement', function () {
            $procurement = Procurement::create(['pr_number' => 'PR-2024-001-0001', 'title' => 'Test', 'category' => 'goods', 'procurement_mode' => 'competitive_bidding']);
            ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'file_key' => 'fk-1',
                'txid' => 'txid-1',
                'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'filename' => 'test-1.pdf',
                'mime_type' => 'application/pdf',
                'hash' => hash('sha256', 'content-1'),
                'uploaded_by' => 'test-user',
                'uploaded_at' => Carbon::now(),
            ]);
            ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'file_key' => 'fk-2',
                'txid' => 'txid-2',
                'document_type' => DocumentTypeEnums::PPMP->value,
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'filename' => 'test-2.pdf',
                'mime_type' => 'application/pdf',
                'hash' => hash('sha256', 'content-2'),
                'uploaded_by' => 'test-user',
                'uploaded_at' => Carbon::now(),
            ]);

            $content1 = 'content-1';
            $content2 = 'content-2';
            $hash1 = hash('sha256', $content1);
            $hash2 = hash('sha256', $content2);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->with('fk-1', 'txid-1')
                ->andReturn(['hash' => $hash1, 'content' => $content1]);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->with('fk-2', 'txid-2')
                ->andReturn(['hash' => $hash2, 'content' => $content2]);

            $results = $this->verifier->batchVerify('PR-2024-001-0001');

            expect($results)->toHaveCount(2);
            expect($results[0]['verification']['is_valid'])->toBeTrue();
            expect($results[1]['verification']['is_valid'])->toBeTrue();
        });
    });
});

// =============================================================================
// DocumentCompletenessVerifier
// =============================================================================
describe('DocumentCompletenessVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->validationService = Mockery::mock(DocumentValidationService::class);
        $this->requirements = Mockery::mock(StageDocumentRequirementsService::class);
        $this->verifier = new DocumentCompletenessVerifier(
            $this->validationService,
            $this->requirements,
        );
    });

    describe('verify', function () {
        it('returns complete when all required docs uploaded', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData(['stage' => $stage->value]);

            $this->validationService
                ->shouldReceive('validateStageCompletion')
                ->once()
                ->andReturn([
                    'can_complete' => true,
                    'completion_percentage' => 100.0,
                    'required_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                    'uploaded_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                    'missing_documents' => [],
                ]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->with($stage)
                ->andReturn([]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, [$doc]);

            expect($result['is_complete'])->toBeTrue();
            expect($result['completion_percentage'])->toBe(100.0);
            expect($result['errors'])->toBeEmpty();
        });

        it('returns incomplete when docs missing', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;

            $this->validationService
                ->shouldReceive('validateStageCompletion')
                ->once()
                ->andReturn([
                    'can_complete' => false,
                    'completion_percentage' => 0.0,
                    'required_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                    'uploaded_documents' => [],
                    'missing_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                ]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->andReturn([]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, []);

            expect($result['is_complete'])->toBeFalse();
            expect($result['missing_documents'])->not->toBeEmpty();
        });

        it('warns when no optional docs uploaded', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData(['stage' => $stage->value]);

            $this->validationService
                ->shouldReceive('validateStageCompletion')
                ->once()
                ->andReturn([
                    'can_complete' => true,
                    'completion_percentage' => 100.0,
                    'required_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                    'uploaded_documents' => [DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value],
                    'missing_documents' => [],
                ]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->with($stage)
                ->andReturn([
                    DocumentTypeEnums::MARKET_RESEARCH,
                    DocumentTypeEnums::TECHNICAL_SPECIFICATIONS,
                ]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, [$doc]);

            expect($result['warnings'])->not->toBeEmpty();
            expect($result['warnings'][0])->toContain('No optional documents uploaded');
        });

        it('handles exception gracefully', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;

            $this->validationService
                ->shouldReceive('validateStageCompletion')
                ->once()
                ->andThrow(new Exception('Validation error'));

            $result = $this->verifier->verify('PR-2024-001-0001', $stage);

            expect($result['is_complete'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('Validation error');
        });
    });
});

// =============================================================================
// DocumentCrossReferenceVerifier
// =============================================================================
describe('DocumentCrossReferenceVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->verifier = new DocumentCrossReferenceVerifier;
    });

    describe('verify', function () {
        it('returns consistent when all PR numbers match', function () {
            $doc1 = makeDocumentData([
                'pr_number' => 'PR-2024-001-0001',
                'uploaded_at' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'pr_number' => 'PR-2024-001-0001',
                'uploaded_at' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001-0001', [$doc1, $doc2]);

            expect($result['is_consistent'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('returns inconsistent with PR mismatch', function () {
            $doc1 = makeDocumentData([
                'pr_number' => 'PR-2024-001-0001',
                'uploaded_at' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'pr_number' => 'PR-2025-989-0001',
                'uploaded_at' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001-0001', [$doc1, $doc2]);

            expect($result['is_consistent'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('PR number mismatch');
        });

        it('warns about out-of-order documents', function () {
            $doc1 = makeDocumentData([
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
                'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
                'uploaded_at' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
                'uploaded_at' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001-0001', [$doc1, $doc2]);

            expect($result['warnings'])->not->toBeEmpty();
            expect($result['warnings'][0])->toContain('out of stage order');
        });

        it('returns consistent when no documents to cross-reference', function () {
            $result = $this->verifier->verify('PR-2024-001-0001');

            expect($result['is_consistent'])->toBeTrue();
        });
    });
});

// =============================================================================
// DocumentComplianceVerifier
// =============================================================================
describe('DocumentComplianceVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->requirements = Mockery::mock(StageDocumentRequirementsService::class);
        $this->verifier = new DocumentComplianceVerifier(
            $this->requirements,
        );
    });

    describe('verify', function () {
        it('returns compliant when all docs are valid PDF', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mime_type' => 'application/pdf',
                'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
            ]);

            $this->requirements
                ->shouldReceive('getRequiredDocuments')
                ->once()
                ->with($stage)
                ->andReturn([DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->with($stage)
                ->andReturn([]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, [$doc]);

            expect($result['is_compliant'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('returns non-compliant with non-PDF', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mime_type' => 'image/png',
                'document_type' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
            ]);

            $this->requirements
                ->shouldReceive('getRequiredDocuments')
                ->once()
                ->with($stage)
                ->andReturn([DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->with($stage)
                ->andReturn([]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, [$doc]);

            expect($result['is_compliant'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('invalid format');
        });

        it('warns about inappropriate document type', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mime_type' => 'application/pdf',
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
            ]);

            $this->requirements
                ->shouldReceive('getRequiredDocuments')
                ->once()
                ->with($stage)
                ->andReturn([DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT]);

            $this->requirements
                ->shouldReceive('getOptionalDocuments')
                ->once()
                ->with($stage)
                ->andReturn([]);

            $result = $this->verifier->verify('PR-2024-001-0001', $stage, [$doc]);

            expect($result['warnings'])->not->toBeEmpty();
            expect($result['warnings'][0])->toContain('may not be appropriate');
        });

        it('handles exception gracefully', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;

            $this->requirements
                ->shouldReceive('getRequiredDocuments')
                ->once()
                ->andThrow(new Exception('Service unavailable'));

            $result = $this->verifier->verify('PR-2024-001-0001', $stage);

            expect($result['is_compliant'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('Service unavailable');
        });
    });
});
