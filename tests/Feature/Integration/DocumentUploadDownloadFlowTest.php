<?php

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\assertDatabaseHas;

describe('Document Upload and Download Integration', function () {
    beforeEach(function () {
        $this->fileStorageService = app(FileStorageService::class);

        // Create a procurement for testing
        $this->procurement = Procurement::factory()->create([
            'id' => 'PR-TEST-001',
            'title' => 'Test Procurement for File Upload',
        ]);
    });

    it('creates procurement document record when uploading file', function () {
        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $metadata = [[
            'document_type' => 'Test Document',
        ]];

        $result = $this->fileStorageService->uploadAndPrepare(
            [$file],
            $metadata,
            $this->procurement->id,
            $this->procurement->title,
            'testing'
        );

        // Verify metadata array contains the required fields
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toHaveKeys(['file_key', 'data_txid', 'metadata_txid', 'file_hash']);

        // Verify database record was created
        assertDatabaseHas('procurement_documents', [
            'procurement_id' => $this->procurement->id,
            'file_name' => 'test-document.pdf',
            'document_type' => 'Test Document',
            'stage' => 'testing',
            'blockchain_status' => 'pending',
        ]);

        // Verify data_txid and metadata_txid are stored
        $document = ProcurementDocument::where('file_key', $result[0]['file_key'])->first();

        expect($document)->not->toBeNull()
            ->and($document->data_txid)->not->toBeNull()
            ->and($document->metadata_txid)->not->toBeNull()
            ->and($document->data_txid)->toBe($result[0]['data_txid'])
            ->and($document->metadata_txid)->toBe($result[0]['metadata_txid']);
    })->skip('Requires blockchain connection');

    it('retrieves file from blockchain using data_txid', function () {
        // Upload a file first
        $file = UploadedFile::fake()->create('retrieve-test.pdf', 50);
        $originalContent = $file->get();

        $metadata = [[
            'document_type' => 'Retrieval Test',
        ]];

        $uploadResult = $this->fileStorageService->uploadAndPrepare(
            [$file],
            $metadata,
            $this->procurement->id,
            $this->procurement->title,
            'testing'
        );

        $document = ProcurementDocument::where('file_key', $uploadResult[0]['file_key'])->first();

        // Retrieve the file using data_txid
        $retrievedFile = $this->fileStorageService->retrieveFile(
            $document->file_key,
            $document->data_txid
        );

        expect($retrievedFile)->toBeArray()
            ->and($retrievedFile)->toHaveKeys(['content', 'metadata'])
            ->and($retrievedFile['content'])->toBe($originalContent);
    })->skip('Requires blockchain connection');

    it('stores multiple files and creates multiple records', function () {
        $file1 = UploadedFile::fake()->create('document1.pdf', 100);
        $file2 = UploadedFile::fake()->create('document2.pdf', 150);

        $metadata = [
            ['document_type' => 'Document One'],
            ['document_type' => 'Document Two'],
        ];

        $result = $this->fileStorageService->uploadAndPrepare(
            [$file1, $file2],
            $metadata,
            $this->procurement->id,
            $this->procurement->title,
            'testing'
        );

        expect($result)->toHaveCount(2);

        // Verify both database records were created
        assertDatabaseHas('procurement_documents', [
            'procurement_id' => $this->procurement->id,
            'file_name' => 'document1.pdf',
            'document_type' => 'Document One',
        ]);

        assertDatabaseHas('procurement_documents', [
            'procurement_id' => $this->procurement->id,
            'file_name' => 'document2.pdf',
            'document_type' => 'Document Two',
        ]);

        // Verify both have data_txid
        $documents = ProcurementDocument::where('procurement_id', $this->procurement->id)->get();

        expect($documents)->toHaveCount(2);

        foreach ($documents as $doc) {
            expect($doc->data_txid)->not->toBeNull()
                ->and($doc->metadata_txid)->not->toBeNull();
        }
    })->skip('Requires blockchain connection');

    it('handles file metadata correctly in database', function () {
        $file = UploadedFile::fake()->create('metadata-test.pdf', 200);

        $metadata = [[
            'document_type' => 'Metadata Test',
            'custom_field' => 'custom_value',
            'submission_date' => '2025-11-08',
        ]];

        $result = $this->fileStorageService->uploadAndPrepare(
            [$file],
            $metadata,
            $this->procurement->id,
            $this->procurement->title,
            'testing'
        );

        $document = ProcurementDocument::where('file_key', $result[0]['file_key'])->first();

        expect($document->metadata)->toBeArray()
            ->and($document->metadata)->toHaveKey('custom_field')
            ->and($document->metadata['custom_field'])->toBe('custom_value')
            ->and($document->metadata)->toHaveKey('submission_date')
            ->and($document->metadata['submission_date'])->toBe('2025-11-08');
    })->skip('Requires blockchain connection');
});
