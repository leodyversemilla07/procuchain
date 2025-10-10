<?php

use App\Services\DocumentMetadataService;
use App\Services\DocumentUploadService;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->fileStorageService = mock(FileStorageService::class);
    $this->documentMetadataService = mock(DocumentMetadataService::class);

    $this->service = new DocumentUploadService(
        $this->fileStorageService,
        $this->documentMetadataService
    );

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
                ->with($file, 'procurements/PROC-001/bidding/', 'bid_document')
                ->andReturn('procurements/PROC-001/bidding/bid_document.pdf');

            $result = $this->service->uploadAndPrepare(
                $files,
                $metadata,
                $this->procurementId,
                $this->procurementTitle,
                $this->stageFolder
            );

            expect($result)->toBeArray();
            expect($result[0])->toHaveKey('file_key');
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
                ->with($file1, 'procurements/PROC-001/bidding/', 'bid_document')
                ->andReturn('path/to/file1.pdf');

            $this->fileStorageService
                ->shouldReceive('uploadFile')
                ->with($file2, 'procurements/PROC-001/bidding/', 'specification_document')
                ->andReturn('path/to/file2.pdf');

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
                ->with($file, 'custom/path/here/', 'bid_doc')
                ->andReturn('custom/path/here/bid_doc.pdf');

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
                ->with($file, \Mockery::any(), 'technical_specification')
                ->andReturn('path/to/file.pdf');

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
                ->andReturn('path/to/file.pdf');

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
                ->andReturn('path/to/file.pdf');

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
        });
    });
});

