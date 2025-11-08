<?php

use App\Models\Procurement;
use App\Services\DocumentMetadataService;
use App\Services\DocumentUploadService;
use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fileStorageService = mock(FileStorageService::class);
    $this->documentMetadataService = mock(DocumentMetadataService::class);

    $this->service = new DocumentUploadService(
        $this->fileStorageService,
        $this->documentMetadataService
    );

    // Create a real procurement for foreign key constraint
    $this->procurement = Procurement::factory()->create([
        'id' => 'PROC-001',
        'title' => 'Test Procurement',
    ]);

    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->stageFolder = 'bidding';
});

describe('DocumentUploadService', function () {
    describe('constructor', function () {
        it('accepts FileStorageService and DocumentMetadataService', function () {
            expect($this->service)->toBeInstanceOf(DocumentUploadService::class);
        });
    });

    describe('uploadAndPrepare', function () {
        it('uploads single file and prepares metadata', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $preparedMetadata = [
                [
                    'document_type' => 'bid',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'bid_document',
                    'hash' => 'hash123',
                    'file_size' => 102400,
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->once()
                ->with($files, $metadata, $this->procurementId, $this->procurementTitle, $this->stageFolder)
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->once()
                ->with($file, 'procurements/PROC-001/bidding/', 'bid_document', \Mockery::any())
                ->andReturn([
                    'file_key' => 'procurements/PROC-001/bidding/bid_document.pdf',
                    'data_txid' => 'data_txid_123',
                    'metadata_txid' => 'metadata_txid_456',
                    'hash' => 'hash123',
                    'size' => 102400,
                    'filename' => 'document.pdf',
                ]);

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result)->toBeArray();
            expect($result[0])->toHaveKey('file_key');
            expect($result[0])->toHaveKey('data_txid');
            expect($result[0])->toHaveKey('metadata_txid');
            expect($result[0]['file_key'])->toBe('procurements/PROC-001/bidding/bid_document.pdf');
        });

        it('uploads multiple files and prepares metadata for each', function () {
            $file1 = UploadedFile::fake()->create('document1.pdf', 100);
            $file2 = UploadedFile::fake()->create('document2.pdf', 200);
            $files = [$file1, $file2];

            $metadata = [
                ['document_type' => 'bid'],
                ['document_type' => 'specification'],
            ];

            $preparedMetadata = [
                [
                    'document_type' => 'bid',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'bid_document',
                    'hash' => 'hash1',
                ],
                [
                    'document_type' => 'specification',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'specification_document',
                    'hash' => 'hash2',
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->once()
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->with($file1, 'procurements/PROC-001/bidding/', 'bid_document', \Mockery::any())
                ->andReturn([
                    'file_key' => 'path/to/file1.pdf',
                    'data_txid' => 'data_txid_1',
                    'metadata_txid' => 'metadata_txid_1',
                    'hash' => 'hash1',
                    'size' => 102400,
                    'filename' => 'document1.pdf',
                ]);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->with($file2, 'procurements/PROC-001/bidding/', 'specification_document', \Mockery::any())
                ->andReturn([
                    'file_key' => 'path/to/file2.pdf',
                    'data_txid' => 'data_txid_2',
                    'metadata_txid' => 'metadata_txid_2',
                    'hash' => 'hash2',
                    'size' => 204800,
                    'filename' => 'document2.pdf',
                ]);

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result)->toHaveCount(2);
            expect($result[0]['file_key'])->toBe('path/to/file1.pdf');
            expect($result[1]['file_key'])->toBe('path/to/file2.pdf');
        });

        it('uses base_path from prepared metadata', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $preparedMetadata = [
                [
                    'document_type' => 'bid',
                    'base_path' => 'custom/path/here',
                    'sanitized_document_type' => 'bid_doc',
                    'hash' => 'hash123',
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->with($file, 'custom/path/here/', 'bid_doc', \Mockery::any())
                ->andReturn([
                    'file_key' => 'custom/path/here/bid_doc.pdf',
                    'data_txid' => 'data_txid_123',
                    'metadata_txid' => 'metadata_txid_456',
                    'hash' => 'hash123',
                    'size' => 102400,
                    'filename' => 'document.pdf',
                ]);

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result[0]['file_key'])->toBe('custom/path/here/bid_doc.pdf');
        });

        it('uses sanitized_document_type from prepared metadata', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'Technical Specification']];

            $preparedMetadata = [
                [
                    'document_type' => 'Technical Specification',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'technical_specification',
                    'hash' => 'hash123',
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->with($file, \Mockery::any(), 'technical_specification', \Mockery::any())
                ->andReturn([
                    'file_key' => 'path/to/file.pdf',
                    'data_txid' => 'data_txid_123',
                    'metadata_txid' => 'metadata_txid_456',
                    'hash' => 'hash123',
                    'size' => 102400,
                    'filename' => 'document.pdf',
                ]);

            $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            // Assertion passed via mock expectations
            expect(true)->toBeTrue();
        });

        it('adds file_key to each metadata entry', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $preparedMetadata = [
                [
                    'document_type' => 'bid',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'bid_document',
                    'hash' => 'hash123',
                    'file_size' => 102400,
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->andReturn([
                    'file_key' => 'path/to/file.pdf',
                    'data_txid' => 'data_txid_123',
                    'metadata_txid' => 'metadata_txid_456',
                    'hash' => 'hash123',
                    'size' => 102400,
                    'filename' => 'document.pdf',
                ]);

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result[0])->toHaveKey('file_key');
            expect($result[0])->toHaveKey('document_type');
            expect($result[0])->toHaveKey('hash');
            expect($result[0])->toHaveKey('data_txid');
            expect($result[0])->toHaveKey('metadata_txid');
        });

        it('returns array with all metadata including file keys', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $preparedMetadata = [
                [
                    'document_type' => 'bid',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'bid_document',
                    'hash' => 'hash123',
                    'file_size' => 102400,
                    'custom_field' => 'value',
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->andReturn([
                    'file_key' => 'path/to/file.pdf',
                    'data_txid' => 'data_txid_123',
                    'metadata_txid' => 'metadata_txid_456',
                    'hash' => 'hash123',
                    'size' => 102400,
                    'filename' => 'document.pdf',
                ]);

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result[0]['document_type'])->toBe('bid');
            expect($result[0]['hash'])->toBe('hash123');
            expect($result[0]['file_size'])->toBe(102400);
            expect($result[0]['custom_field'])->toBe('value');
            expect($result[0]['file_key'])->toBe('path/to/file.pdf');
            expect($result[0]['data_txid'])->toBe('data_txid_123');
            expect($result[0]['metadata_txid'])->toBe('metadata_txid_456');
        });

        it('creates procurement document records in database', function () {
            $file = UploadedFile::fake()->create('test.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'Test Document']];

            $preparedMetadata = [
                [
                    'document_type' => 'Test Document',
                    'base_path' => 'procurements/PROC-001/bidding',
                    'sanitized_document_type' => 'test_document',
                    'hash' => 'testhash',
                    'file_size' => 102400,
                ],
            ];

            $this->documentMetadataService
                ->shouldReceive('prepareMetadata')
                ->andReturn($preparedMetadata);

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->andReturn([
                    'file_key' => 'procurements/PROC-001/bidding/test.pdf',
                    'data_txid' => 'data_test_123',
                    'metadata_txid' => 'meta_test_456',
                    'hash' => 'testhash',
                    'size' => 102400,
                    'filename' => 'test.pdf',
                ]);

            $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            // Verify database record was created
            $this->assertDatabaseHas('procurement_documents', [
                'procurement_id' => 'PROC-001',
                'file_name' => 'test.pdf',
                'document_type' => 'Test Document',
                'stage' => 'bidding',
                'data_txid' => 'data_test_123',
                'metadata_txid' => 'meta_test_456',
                'blockchain_status' => 'pending',
            ]);
        });
    });
});
