<?php

use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('spaces');
    $this->service = new FileStorageService('spaces');
});

describe('FileStorageService', function () {
    describe('constructor', function () {
        it('uses default disk when not specified', function () {
            $service = new FileStorageService;

            expect($service)->toBeInstanceOf(FileStorageService::class);
        });

        it('uses specified disk', function () {
            $service = new FileStorageService('local');

            expect($service)->toBeInstanceOf(FileStorageService::class);
        });
    });

    describe('uploadFile', function () {
        it('uploads file successfully', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/PROC-001/bidding';
            $suffix = 'bid_document';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            expect($fileKey)->toBeString();
            expect($fileKey)->toContain($path);
            expect($fileKey)->toContain($suffix);
            Storage::disk('spaces')->assertExists($fileKey);
        });

        it('preserves file extension', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/PROC-001';
            $suffix = 'test_file';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            expect($fileKey)->toEndWith('.pdf');
        });

        it('handles different file types', function () {
            $files = [
                UploadedFile::fake()->create('document.pdf', 100),
                UploadedFile::fake()->create('spreadsheet.xlsx', 50),
                UploadedFile::fake()->create('image.jpg', 200),
                UploadedFile::fake()->create('archive.zip', 300),
            ];

            foreach ($files as $index => $file) {
                $fileKey = $this->service->uploadFile($file, 'test', "file_{$index}");

                Storage::disk('spaces')->assertExists($fileKey);
            }
        });

        it('stores file as private', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/PROC-001';
            $suffix = 'private_doc';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            // File should exist but we can't directly check visibility with fake storage
            // In real implementation, this would be stored with 'private' visibility
            Storage::disk('spaces')->assertExists($fileKey);
        });

        it('uploads file without suffix', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/PROC-001';

            $fileKey = $this->service->uploadFile($file, $path, '');

            expect($fileKey)->toBeString();
            expect($fileKey)->toEndWith('.pdf');
            Storage::disk('spaces')->assertExists($fileKey);
        });

        it('handles nested path structure', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/2024/PROC-001/bidding/round-1';
            $suffix = 'bid';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            expect($fileKey)->toContain('procurements/2024/PROC-001/bidding/round-1');
            Storage::disk('spaces')->assertExists($fileKey);
        });

        it('generates correct filename format', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'test';
            $suffix = 'my_document';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            expect($fileKey)->toBe('test/my_document.pdf');
        });

        it('handles large files', function () {
            $file = UploadedFile::fake()->create('large_document.pdf', 10240); // 10MB
            $path = 'procurements/large';
            $suffix = 'large-doc';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            Storage::disk('spaces')->assertExists($fileKey);
            expect($fileKey)->toContain('large-doc');
            expect($fileKey)->toEndWith('.pdf');
        });

        it('returns full file key path', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $path = 'procurements/PROC-001/bidding';
            $suffix = 'specification';

            $fileKey = $this->service->uploadFile($file, $path, $suffix);

            expect($fileKey)->toBe('procurements/PROC-001/bidding/specification.pdf');
        });
    });

    describe('different file extensions', function () {
        it('handles pdf files')->expect(function () {
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            return $this->service->uploadFile($file, 'test', 'file');
        })->toEndWith('.pdf');

        it('handles docx files')->expect(function () {
            $file = UploadedFile::fake()->create('doc.docx', 100);

            return $this->service->uploadFile($file, 'test', 'file');
        })->toEndWith('.docx');

        it('handles xlsx files')->expect(function () {
            $file = UploadedFile::fake()->create('sheet.xlsx', 100);

            return $this->service->uploadFile($file, 'test', 'file');
        })->toEndWith('.xlsx');

        it('handles jpg files')->expect(function () {
            $file = UploadedFile::fake()->create('image.jpg', 100);

            return $this->service->uploadFile($file, 'test', 'file');
        })->toEndWith('.jpg');
    });
});

