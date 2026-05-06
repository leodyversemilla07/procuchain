<?php

use App\DataTransferObjects\DocumentData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\DocumentRepository;
use App\Services\BlockchainStorageService;
use App\Services\DocumentValidationService;
use App\Services\StageDocumentRequirements;
use App\Services\Verification\DocumentCompletenessVerifier;
use App\Services\Verification\DocumentComplianceVerifier;
use App\Services\Verification\DocumentCrossReferenceVerifier;
use App\Services\Verification\DocumentIntegrityVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

function makeDocumentData(array $overrides = []): DocumentData
{
    $defaults = [
        'prNumber' => 'PR-2024-001',
        'procurementTitle' => 'Test Procurement',
        'userAddress' => '0xTestAddress',
        'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        'status' => StatusEnums::PROCUREMENT_INITIATED->value,
        'documentType' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
        'fileKey' => 'test-file-key-'.uniqid(),
        'fileName' => 'test-document.pdf',
        'fileSize' => 1024,
        'mimeType' => 'application/pdf',
        'hash' => hash('sha256', 'test-content'),
        'dataTxid' => 'txid-'.uniqid(),
        'metadataTxid' => 'meta-txid-'.uniqid(),
        'uploadedBy' => 'test-user',
        'timestamp' => Carbon::now(),
        'description' => null,
        'stageMetadata' => null,
    ];

    $merged = array_merge($defaults, $overrides);

    return new DocumentData(
        prNumber: $merged['prNumber'],
        procurementTitle: $merged['procurementTitle'],
        userAddress: $merged['userAddress'],
        stage: $merged['stage'],
        status: $merged['status'],
        documentType: $merged['documentType'],
        fileKey: $merged['fileKey'],
        fileName: $merged['fileName'],
        fileSize: $merged['fileSize'],
        mimeType: $merged['mimeType'],
        hash: $merged['hash'],
        dataTxid: $merged['dataTxid'],
        metadataTxid: $merged['metadataTxid'],
        uploadedBy: $merged['uploadedBy'],
        timestamp: $merged['timestamp'],
        description: $merged['description'],
        stageMetadata: $merged['stageMetadata'],
    );
}

// =============================================================================
// DocumentIntegrityVerifier
// =============================================================================
describe('DocumentIntegrityVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->blockchainStorage = Mockery::mock(BlockchainStorageService::class);
        $this->documentRepository = Mockery::mock(DocumentRepository::class);
        $this->verifier = new DocumentIntegrityVerifier(
            $this->blockchainStorage,
            $this->documentRepository,
        );
    });

    describe('verify', function () {
        it('returns success when hashes match', function () {
            $content = 'test-file-content';
            $hash = hash('sha256', $content);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('file-key-1', 'txid-1')
                ->andReturn(['hash' => $hash, 'content' => $content]);

            $result = $this->verifier->verify('file-key-1', 'txid-1');

            expect($result->isValid)->toBeTrue();
            expect($result->errors)->toBeEmpty();
            expect($result->verificationType)->toBe('integrity');
        });

        it('returns failure when hashes do not match', function () {
            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('file-key-1', 'txid-1')
                ->andReturn(['hash' => 'wrong_hash', 'content' => 'content']);

            $result = $this->verifier->verify('file-key-1', 'txid-1');

            expect($result->isValid)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('hash mismatch');
        });

        it('returns failure on exception', function () {
            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->andThrow(new Exception('Blockchain unavailable'));

            $result = $this->verifier->verify('file-key-1', 'txid-1');

            expect($result->isValid)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('Blockchain unavailable');
        });
    });

    describe('verifySingle', function () {
        it('delegates to verify when document found', function () {
            $content = 'file-content';
            $hash = hash('sha256', $content);
            $doc = makeDocumentData(['fileKey' => 'fk-1', 'dataTxid' => 'txid-1']);

            $this->documentRepository
                ->shouldReceive('findByFileKey')
                ->once()
                ->with('fk-1')
                ->andReturn($doc);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->once()
                ->with('fk-1', 'txid-1')
                ->andReturn(['hash' => $hash, 'content' => $content]);

            $result = $this->verifier->verifySingle('fk-1');

            expect($result->isValid)->toBeTrue();
        });

        it('returns failure when document not found', function () {
            $this->documentRepository
                ->shouldReceive('findByFileKey')
                ->once()
                ->with('nonexistent-key')
                ->andReturn(null);

            $result = $this->verifier->verifySingle('nonexistent-key');

            expect($result->isValid)->toBeFalse();
            expect($result->errors[0])->toContain('Document not found');
        });
    });

    describe('batchVerify', function () {
        it('verifies all documents for a procurement', function () {
            $content1 = 'content-1';
            $content2 = 'content-2';
            $hash1 = hash('sha256', $content1);
            $hash2 = hash('sha256', $content2);

            $doc1 = makeDocumentData([
                'fileKey' => 'fk-1',
                'dataTxid' => 'txid-1',
                'documentType' => DocumentTypeEnums::PURCHASE_REQUEST->value,
                'timestamp' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'fileKey' => 'fk-2',
                'dataTxid' => 'txid-2',
                'documentType' => DocumentTypeEnums::PPMP->value,
                'timestamp' => Carbon::now(),
            ]);

            $this->documentRepository
                ->shouldReceive('findByProcurement')
                ->once()
                ->with('PR-2024-001')
                ->andReturn(collect([$doc1, $doc2]));

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->with('fk-1', 'txid-1')
                ->andReturn(['hash' => $hash1, 'content' => $content1]);

            $this->blockchainStorage
                ->shouldReceive('retrieveFile')
                ->with('fk-2', 'txid-2')
                ->andReturn(['hash' => $hash2, 'content' => $content2]);

            $results = $this->verifier->batchVerify('PR-2024-001');

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
        $this->documentRepository = Mockery::mock(DocumentRepository::class);
        $this->validationService = Mockery::mock(DocumentValidationService::class);
        $this->requirements = Mockery::mock(StageDocumentRequirements::class);
        $this->verifier = new DocumentCompletenessVerifier(
            $this->documentRepository,
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

            $result = $this->verifier->verify('PR-2024-001', $stage, [$doc]);

            expect($result->isComplete)->toBeTrue();
            expect($result->completionPercentage)->toBe(100.0);
            expect($result->errors)->toBeEmpty();
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

            $result = $this->verifier->verify('PR-2024-001', $stage, []);

            expect($result->isComplete)->toBeFalse();
            expect($result->missingDocuments)->not->toBeEmpty();
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

            $result = $this->verifier->verify('PR-2024-001', $stage, [$doc]);

            expect($result->warnings)->not->toBeEmpty();
            expect($result->warnings[0])->toContain('No optional documents uploaded');
        });

        it('handles exception gracefully', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;

            $this->documentRepository
                ->shouldReceive('findByProcurement')
                ->once()
                ->andThrow(new Exception('Repository error'));

            $result = $this->verifier->verify('PR-2024-001', $stage);

            expect($result->isComplete)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('Repository error');
        });
    });
});

// =============================================================================
// DocumentCrossReferenceVerifier
// =============================================================================
describe('DocumentCrossReferenceVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->documentRepository = Mockery::mock(DocumentRepository::class);
        $this->verifier = new DocumentCrossReferenceVerifier(
            $this->documentRepository,
        );
    });

    describe('verify', function () {
        it('returns consistent when all PR numbers match', function () {
            $doc1 = makeDocumentData([
                'prNumber' => 'PR-2024-001',
                'timestamp' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'prNumber' => 'PR-2024-001',
                'timestamp' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001', [$doc1, $doc2]);

            expect($result->isConsistent)->toBeTrue();
            expect($result->errors)->toBeEmpty();
        });

        it('returns inconsistent with PR mismatch', function () {
            $doc1 = makeDocumentData([
                'prNumber' => 'PR-2024-001',
                'timestamp' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'prNumber' => 'PR-WRONG-999',
                'timestamp' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001', [$doc1, $doc2]);

            expect($result->isConsistent)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('PR number mismatch');
        });

        it('warns about out-of-order documents', function () {
            $doc1 = makeDocumentData([
                'prNumber' => 'PR-2024-001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
                'documentType' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
                'timestamp' => Carbon::now(),
            ]);
            $doc2 = makeDocumentData([
                'prNumber' => 'PR-2024-001',
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'documentType' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
                'timestamp' => Carbon::now()->addMinute(),
            ]);

            $result = $this->verifier->verify('PR-2024-001', [$doc1, $doc2]);

            expect($result->warnings)->not->toBeEmpty();
            expect($result->warnings[0])->toContain('out of stage order');
        });

        it('handles exception gracefully', function () {
            $this->documentRepository
                ->shouldReceive('findByProcurement')
                ->once()
                ->andThrow(new Exception('Connection failed'));

            $result = $this->verifier->verify('PR-2024-001');

            expect($result->isConsistent)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('Connection failed');
        });
    });
});

// =============================================================================
// DocumentComplianceVerifier
// =============================================================================
describe('DocumentComplianceVerifier', function () {
    beforeEach(function () {
        Log::spy();
        $this->documentRepository = Mockery::mock(DocumentRepository::class);
        $this->requirements = Mockery::mock(StageDocumentRequirements::class);
        $this->verifier = new DocumentComplianceVerifier(
            $this->documentRepository,
            $this->requirements,
        );
    });

    describe('verify', function () {
        it('returns compliant when all docs are valid PDF', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mimeType' => 'application/pdf',
                'documentType' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
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

            $result = $this->verifier->verify('PR-2024-001', $stage, [$doc]);

            expect($result->isCompliant)->toBeTrue();
            expect($result->errors)->toBeEmpty();
        });

        it('returns non-compliant with non-PDF', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mimeType' => 'image/png',
                'documentType' => DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->value,
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

            $result = $this->verifier->verify('PR-2024-001', $stage, [$doc]);

            expect($result->isCompliant)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('invalid format');
        });

        it('warns about inappropriate document type', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;
            $doc = makeDocumentData([
                'stage' => $stage->value,
                'mimeType' => 'application/pdf',
                'documentType' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
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

            $result = $this->verifier->verify('PR-2024-001', $stage, [$doc]);

            expect($result->warnings)->not->toBeEmpty();
            expect($result->warnings[0])->toContain('may not be appropriate');
        });

        it('handles exception gracefully', function () {
            $stage = StageEnums::PROCUREMENT_INITIATION;

            $this->documentRepository
                ->shouldReceive('findByProcurement')
                ->once()
                ->andThrow(new Exception('Service unavailable'));

            $result = $this->verifier->verify('PR-2024-001', $stage);

            expect($result->isCompliant)->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
            expect($result->errors[0])->toContain('Service unavailable');
        });
    });
});
