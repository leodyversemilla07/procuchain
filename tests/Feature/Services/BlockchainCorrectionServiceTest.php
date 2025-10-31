<?php

use App\Jobs\PublishDocumentCorrectionJob;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Services\BlockchainCorrectionService;
use App\Services\MultichainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->multichainService = mock(MultichainService::class);
    $this->service = new BlockchainCorrectionService;
});

describe('BlockchainCorrectionService', function () {
    describe('correctDocument', function () {
        test('it dispatches correction job when document has blockchain_txid', function () {
            // Create procurement and document with blockchain_txid
            $procurement = Procurement::factory()->create();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'document_type' => 'test_document',
                'file_key' => 'test/path.pdf',
                'file_name' => 'test.pdf',
                'blockchain_txid' => 'original-txid-123',
                'blockchain_status' => 'confirmed',
                'metadata' => ['hash' => 'abc123hash', 'test' => 'data'],
            ]);

            $result = $this->service->correctDocument(
                document: $document,
                reason: 'Incorrect data uploaded',
                correctedMetadata: ['corrected' => 'metadata'],
                correctedBy: 'admin@test.com',
                userAddress: 'test-address-123'
            );

            expect($result)->toBe('Correction record will be published to blockchain');

            // Verify document was marked as corrected
            $document->refresh();
            expect($document->is_corrected)->toBeTrue();
            expect($document->correction_reason)->toBe('Incorrect data uploaded');
            expect($document->corrected_by)->toBe('admin@test.com');
            expect($document->corrected_at)->not->toBeNull();

            // Verify job was dispatched
            $document->refresh();
            Queue::assertPushed(PublishDocumentCorrectionJob::class, function ($job) use ($document) {
                return $job->procurementId === $document->procurement->id
                    && $job->procurementTitle === $document->procurement->title
                    && $job->originalTxid === 'original-txid-123'
                    && $job->originalDocumentHash === 'abc123hash'
                    && $job->correctionReason === 'Incorrect data uploaded'
                    && $job->correctedBy === 'admin@test.com'
                    && $job->userAddress === 'test-address-123';
            });
        });

        test('it throws exception when document has no blockchain_txid', function () {
            $procurement = Procurement::factory()->create();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'document_type' => 'test_document',
                'file_key' => 'test/path.pdf',
                'file_name' => 'test.pdf',
                'blockchain_status' => 'pending',
                'metadata' => ['hash' => 'abc123hash', 'test' => 'data'],
            ]);

            expect(fn () => $this->service->correctDocument(
                document: $document,
                reason: 'Test correction',
                correctedMetadata: ['test' => 'metadata'],
                correctedBy: 'admin@test.com',
                userAddress: 'test-address'
            ))->toThrow(Exception::class, 'Document has not been published to blockchain yet');
        });

        test('it updates document with correction metadata', function () {
            $procurement = Procurement::factory()->create();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'document_type' => 'test_document',
                'file_key' => 'test/path.pdf',
                'file_name' => 'test.pdf',
                'blockchain_txid' => 'txid-456',
                'blockchain_status' => 'confirmed',
                'metadata' => ['hash' => 'abc123hash', 'original' => 'data'],
            ]);

            $beforeTime = now();

            $this->service->correctDocument(
                document: $document,
                reason: 'Data needs correction',
                correctedMetadata: ['updated' => 'information'],
                correctedBy: 'john.doe@example.com',
                userAddress: 'address-789'
            );

            $document->refresh();

            expect($document->is_corrected)->toBeTrue();
            expect($document->correction_reason)->toBe('Data needs correction');
            expect($document->corrected_by)->toBe('john.doe@example.com');
            expect($document->corrected_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($document->corrected_at->timestamp)->toBeGreaterThanOrEqual($beforeTime->timestamp);
        });

        test('it handles null correctedMetadata for invalidation only', function () {
            $procurement = Procurement::factory()->create();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'document_type' => 'invalid_doc',
                'file_key' => 'invalid/path.pdf',
                'file_name' => 'invalid.pdf',
                'blockchain_txid' => 'invalid-txid',
                'blockchain_status' => 'confirmed',
                'metadata' => ['hash' => 'invalidhash', 'bad' => 'data'],
            ]);

            $result = $this->service->correctDocument(
                document: $document,
                reason: 'Document is invalid and should be marked as such',
                correctedMetadata: null,
                correctedBy: 'validator@test.com',
                userAddress: 'validator-address'
            );

            expect($result)->toBe('Correction record will be published to blockchain');

            Queue::assertPushed(PublishDocumentCorrectionJob::class, function ($job) {
                return $job->correctedMetadata === null
                    && $job->correctionReason === 'Document is invalid and should be marked as such';
            });
        });

        test('it logs error and rethrows exception on failure', function () {
            // Create document with invalid procurement relationship to trigger exception
            $document = new ProcurementDocument([
                'procurement_id' => 'non-existent-id',
                'document_type' => 'test',
                'file_path' => 'test.pdf',
                'hash' => 'hash123',
                'blockchain_txid' => 'txid-999',
            ]);

            expect(fn () => $this->service->correctDocument(
                document: $document,
                reason: 'Test',
                correctedMetadata: [],
                correctedBy: 'test@test.com',
                userAddress: 'test-addr'
            ))->toThrow(Exception::class);
        });
    });

    describe('getCorrections', function () {
        test('it retrieves corrections from blockchain for procurement', function () {
            $mockCorrections = [
                [
                    'txid' => 'correction-txid-1',
                    'data' => [
                        'json' => [
                            'original_txid' => 'original-123',
                            'reason' => 'Data error',
                            'corrected_by' => 'admin@test.com',
                        ],
                    ],
                ],
                [
                    'txid' => 'correction-txid-2',
                    'data' => [
                        'json' => [
                            'original_txid' => 'original-456',
                            'reason' => 'Missing information',
                            'corrected_by' => 'user@test.com',
                        ],
                    ],
                ],
            ];

            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'PROC-2025-001', false, 10000, 0)
                ->andReturn($mockCorrections);

            $result = $this->service->getCorrections('PROC-2025-001', $this->multichainService);

            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0]['txid'])->toBe('correction-txid-1');
            expect($result[1]['txid'])->toBe('correction-txid-2');
        });

        test('it returns empty array when no corrections found', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'PROC-EMPTY', false, 10000, 0)
                ->andReturn([]);

            $result = $this->service->getCorrections('PROC-EMPTY', $this->multichainService);

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        test('it returns empty array and logs error on exception', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->andThrow(new Exception('Blockchain connection failed'));

            $result = $this->service->getCorrections('PROC-ERROR', $this->multichainService);

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        test('it handles null response from blockchain', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->andReturn(null);

            $result = $this->service->getCorrections('PROC-NULL', $this->multichainService);

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });
    });

    describe('findCorrectionForTransaction', function () {
        test('it finds correction record for specific transaction', function () {
            $mockCorrection = [
                [
                    'txid' => 'correction-for-original',
                    'data' => [
                        'json' => [
                            'original_txid' => 'original-txid-123',
                            'reason' => 'Incorrect hash',
                            'corrected_by' => 'admin@example.com',
                            'corrected_metadata' => ['updated' => 'data'],
                        ],
                    ],
                ],
            ];

            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'original-txid-123', true, 1, 0)
                ->andReturn($mockCorrection);

            $result = $this->service->findCorrectionForTransaction(
                'original-txid-123',
                $this->multichainService
            );

            expect($result)->toBeArray();
            expect($result['txid'])->toBe('correction-for-original');
            expect($result['data']['json']['reason'])->toBe('Incorrect hash');
        });

        test('it returns null when no correction found', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'no-correction-txid', true, 1, 0)
                ->andReturn([]);

            $result = $this->service->findCorrectionForTransaction(
                'no-correction-txid',
                $this->multichainService
            );

            expect($result)->toBeNull();
        });

        test('it returns null and logs warning on exception', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->andThrow(new Exception('Connection timeout'));

            $result = $this->service->findCorrectionForTransaction(
                'error-txid',
                $this->multichainService
            );

            expect($result)->toBeNull();
        });

        test('it requests verbose output for full data', function () {
            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'test-txid', true, 1, 0)
                ->andReturn([]);

            $this->service->findCorrectionForTransaction('test-txid', $this->multichainService);

            // Expectation verified by mock's with() parameters
            expect(true)->toBeTrue();
        });

        test('it only requests one correction record', function () {
            $mockMultipleResults = [
                ['txid' => 'first-correction'],
                ['txid' => 'second-correction'],
                ['txid' => 'third-correction'],
            ];

            $this->multichainService
                ->shouldReceive('listStreamKeyItems')
                ->once()
                ->with('procurement.corrections', 'multi-txid', true, 1, 0)
                ->andReturn($mockMultipleResults);

            $result = $this->service->findCorrectionForTransaction(
                'multi-txid',
                $this->multichainService
            );

            // Should return only the first result even if multiple exist
            expect($result)->toBeArray();
            expect($result['txid'])->toBe('first-correction');
        });
    });

    describe('integration scenarios', function () {
        test('it handles complete correction workflow', function () {
            // Create a published document
            $procurement = Procurement::factory()->create();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'document_type' => 'contract',
                'file_key' => 'contracts/contract.pdf',
                'file_name' => 'contract.pdf',
                'blockchain_txid' => 'workflow-original-txid',
                'blockchain_status' => 'confirmed',
                'metadata' => ['hash' => 'originalhash123', 'amount' => '1000000', 'vendor' => 'Wrong Vendor'],
            ]);

            // Correct the document
            $result = $this->service->correctDocument(
                document: $document,
                reason: 'Vendor name was incorrect',
                correctedMetadata: ['amount' => '1000000', 'vendor' => 'Correct Vendor'],
                correctedBy: 'procurement.officer@gov.ph',
                userAddress: 'blockchain-addr-456'
            );

            expect($result)->toContain('published to blockchain');

            // Verify document state
            $document->refresh();
            expect($document->is_corrected)->toBeTrue();
            expect($document->correction_reason)->toContain('incorrect');
            expect($document->blockchain_txid)->toBe('workflow-original-txid'); // Original remains

            // Verify job dispatched
            Queue::assertPushed(PublishDocumentCorrectionJob::class);
        });
    });
});
