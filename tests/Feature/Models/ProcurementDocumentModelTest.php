<?php

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ProcurementDocument Model', function () {
    describe('Model Configuration', function () {
        it('has correct fillable fields', function () {
            $document = new ProcurementDocument;
            $expectedFillable = [
                'procurement_id',
                'file_key',
                'file_name',
                'document_type',
                'stage',
                'metadata',
                'blockchain_txid',
                'data_txid',
                'metadata_txid',
                'blockchain_status',
                'blockchain_status_updated_at',
                'blockchain_error',
                'blockchain_retry_count',
                'is_corrected',
                'correction_reason',
                'corrected_at',
                'corrected_by',
                'correction_txid',
            ];

            expect($document->getFillable())->toBe($expectedFillable);
        });

        it('casts metadata to array', function () {
            $document = ProcurementDocument::factory()->create([
                'metadata' => ['file_size' => 1024, 'mime_type' => 'application/pdf'],
            ]);

            expect($document->metadata)->toBeArray();
            expect($document->metadata)->toHaveKey('file_size');
        });

        it('casts is_corrected to boolean', function () {
            $document = ProcurementDocument::factory()->create([
                'is_corrected' => true,
            ]);

            expect($document->is_corrected)->toBeBool();
            expect($document->is_corrected)->toBeTrue();
        });

        it('casts blockchain_status_updated_at to datetime', function () {
            $document = ProcurementDocument::factory()->create([
                'blockchain_status_updated_at' => now(),
            ]);

            expect($document->blockchain_status_updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('casts corrected_at to datetime', function () {
            $document = ProcurementDocument::factory()->create([
                'corrected_at' => now(),
            ]);

            expect($document->corrected_at)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('casts blockchain_retry_count to integer', function () {
            $document = ProcurementDocument::factory()->create([
                'blockchain_retry_count' => '2',
            ]);

            expect($document->blockchain_retry_count)->toBeInt();
            expect($document->blockchain_retry_count)->toBe(2);
        });
    });

    describe('CRUD Operations', function () {
        it('can create a procurement document', function () {
            $procurement = Procurement::factory()->create(['id' => 'PR-2025-0001']);

            $document = ProcurementDocument::create([
                'procurement_id' => 'PR-2025-0001',
                'file_key' => 'procurement-documents/file.pdf',
                'file_name' => 'tender_document.pdf',
                'document_type' => 'Tender Document',
                'stage' => 'Bidding',
                'metadata' => ['file_size' => 2048],
                'blockchain_status' => 'pending',
            ]);

            expect($document->id)->not->toBeNull();
            expect($document->procurement_id)->toBe('PR-2025-0001');
            expect($document->file_name)->toBe('tender_document.pdf');

            $this->assertDatabaseHas('procurement_documents', [
                'procurement_id' => 'PR-2025-0001',
                'file_name' => 'tender_document.pdf',
            ]);
        });

        it('can update document blockchain status', function () {
            $document = ProcurementDocument::factory()->create([
                'blockchain_status' => 'pending',
            ]);

            $document->update([
                'blockchain_status' => 'confirmed',
                'blockchain_txid' => 'txid-abc-123',
                'blockchain_status_updated_at' => now(),
            ]);

            expect($document->fresh()->blockchain_status)->toBe('confirmed');
            expect($document->fresh()->blockchain_txid)->toBe('txid-abc-123');
        });

        it('can mark document as corrected', function () {
            $document = ProcurementDocument::factory()->create([
                'is_corrected' => false,
            ]);

            $document->update([
                'is_corrected' => true,
                'correction_reason' => 'Updated requirements',
                'corrected_at' => now(),
                'corrected_by' => '1AdminAddress',
                'correction_txid' => 'correction-txid-123',
            ]);

            expect($document->fresh()->is_corrected)->toBeTrue();
            expect($document->fresh()->correction_reason)->toBe('Updated requirements');
            expect($document->fresh()->correction_txid)->toBe('correction-txid-123');
        });

        it('can delete document', function () {
            $document = ProcurementDocument::factory()->create();
            $documentId = $document->id;

            $document->delete();

            $this->assertDatabaseMissing('procurement_documents', [
                'id' => $documentId,
            ]);
        });
    });

    describe('Query Operations', function () {
        it('can query by procurement_id', function () {
            $procurement1 = Procurement::factory()->create(['id' => 'PR-001']);
            $procurement2 = Procurement::factory()->create(['id' => 'PR-002']);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'file_name' => 'doc1.pdf',
            ]);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'file_name' => 'doc2.pdf',
            ]);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-002',
                'file_name' => 'doc3.pdf',
            ]);

            $documents = ProcurementDocument::where('procurement_id', 'PR-001')->get();

            expect($documents)->toHaveCount(2);
        });

        it('can query by blockchain_status', function () {
            ProcurementDocument::factory()->create([
                'blockchain_status' => 'pending',
            ]);

            ProcurementDocument::factory()->create([
                'blockchain_status' => 'confirmed',
            ]);

            ProcurementDocument::factory()->create([
                'blockchain_status' => 'failed',
            ]);

            $pending = ProcurementDocument::where('blockchain_status', 'pending')->get();
            $confirmed = ProcurementDocument::where('blockchain_status', 'confirmed')->get();
            $failed = ProcurementDocument::where('blockchain_status', 'failed')->get();

            expect($pending)->toHaveCount(1);
            expect($confirmed)->toHaveCount(1);
            expect($failed)->toHaveCount(1);
        });

        it('can query pending documents for retry', function () {
            $procurement1 = Procurement::factory()->create(['id' => 'PR-001']);
            $procurement2 = Procurement::factory()->create(['id' => 'PR-002']);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'blockchain_status' => 'pending',
                'blockchain_retry_count' => 0,
            ]);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-002',
                'blockchain_status' => 'failed',
                'blockchain_retry_count' => 3,
            ]);

            $pendingForRetry = ProcurementDocument::where(function ($query) {
                $query->where('blockchain_status', 'pending')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('blockchain_status', 'failed')
                            ->where('blockchain_retry_count', '<', 5);
                    });
            })->get();

            expect($pendingForRetry)->toHaveCount(2);
        });

        it('can query corrected documents', function () {
            ProcurementDocument::factory()->create([
                'is_corrected' => true,
                'correction_reason' => 'Updated info',
            ]);

            ProcurementDocument::factory()->create([
                'is_corrected' => false,
            ]);

            $corrected = ProcurementDocument::where('is_corrected', true)->get();

            expect($corrected)->toHaveCount(1);
            expect($corrected->first()->correction_reason)->toBe('Updated info');
        });

        it('can get latest documents for procurement', function () {
            $procurement = Procurement::factory()->create(['id' => 'PR-001']);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'created_at' => now()->subDays(5),
            ]);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'created_at' => now()->subDays(2),
            ]);

            ProcurementDocument::factory()->create([
                'procurement_id' => 'PR-001',
                'created_at' => now(),
            ]);

            $latestDocuments = ProcurementDocument::where('procurement_id', 'PR-001')
                ->latest('created_at')
                ->limit(2)
                ->get();

            expect($latestDocuments)->toHaveCount(2);
        });
    });

    describe('Document Correction Flow', function () {
        it('tracks full correction lifecycle', function () {
            $document = ProcurementDocument::factory()->create([
                'file_name' => 'original.pdf',
                'is_corrected' => false,
                'blockchain_status' => 'confirmed',
                'blockchain_txid' => 'original-txid',
            ]);

            // Mark as corrected
            $document->update([
                'is_corrected' => true,
                'correction_reason' => 'Missing signature',
                'corrected_at' => now(),
                'corrected_by' => '1AdminUser',
            ]);

            expect($document->fresh()->is_corrected)->toBeTrue();
            expect($document->fresh()->correction_reason)->toBe('Missing signature');

            // Publish correction to blockchain
            $document->update([
                'correction_txid' => 'correction-txid-abc',
            ]);

            expect($document->fresh()->correction_txid)->toBe('correction-txid-abc');
        });
    });

    describe('Blockchain Status Tracking', function () {
        it('tracks publication status summary', function () {
            $procurement = Procurement::factory()->create(['id' => 'PR-SUMMARY']);

            ProcurementDocument::factory()->count(5)->create([
                'procurement_id' => 'PR-SUMMARY',
                'blockchain_status' => 'confirmed',
            ]);

            ProcurementDocument::factory()->count(2)->create([
                'procurement_id' => 'PR-SUMMARY',
                'blockchain_status' => 'pending',
            ]);

            ProcurementDocument::factory()->count(1)->create([
                'procurement_id' => 'PR-SUMMARY',
                'blockchain_status' => 'failed',
            ]);

            $documents = ProcurementDocument::where('procurement_id', 'PR-SUMMARY')->get();

            $summary = [
                'pending' => $documents->where('blockchain_status', 'pending')->count(),
                'confirmed' => $documents->where('blockchain_status', 'confirmed')->count(),
                'failed' => $documents->where('blockchain_status', 'failed')->count(),
                'total' => $documents->count(),
            ];

            expect($summary['pending'])->toBe(2);
            expect($summary['confirmed'])->toBe(5);
            expect($summary['failed'])->toBe(1);
            expect($summary['total'])->toBe(8);
        });

        it('handles retry logic correctly', function () {
            $document = ProcurementDocument::factory()->create([
                'blockchain_status' => 'pending',
                'blockchain_retry_count' => 0,
            ]);

            // First attempt fails
            $document->update([
                'blockchain_status' => 'failed',
                'blockchain_error' => 'Connection timeout',
                'blockchain_retry_count' => 1,
            ]);

            expect($document->fresh()->blockchain_retry_count)->toBe(1);

            // Retry
            $document->update([
                'blockchain_status' => 'pending',
            ]);

            // Second attempt succeeds
            $document->update([
                'blockchain_status' => 'confirmed',
                'blockchain_txid' => 'success-txid',
                'blockchain_error' => null,
            ]);

            expect($document->fresh()->blockchain_status)->toBe('confirmed');
            expect($document->fresh()->blockchain_txid)->toBe('success-txid');
        });
    });

    describe('Metadata Handling', function () {
        it('stores and retrieves complex metadata', function () {
            $metadata = [
                'file_size' => 2048576,
                'mime_type' => 'application/pdf',
                'hash' => 'abc123def456',
                'upload_date' => '2025-10-18',
                'custom_fields' => [
                    'department' => 'IT',
                    'category' => 'Technical',
                ],
            ];

            $document = ProcurementDocument::factory()->create([
                'metadata' => $metadata,
            ]);

            $retrieved = $document->fresh()->metadata;

            expect($retrieved)->toBeArray();
            expect($retrieved['file_size'])->toBe(2048576);
            expect($retrieved['custom_fields'])->toBeArray();
            expect($retrieved['custom_fields']['department'])->toBe('IT');
        });
    });
});
