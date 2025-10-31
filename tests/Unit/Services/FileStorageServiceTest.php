<?php

use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->service = app(FileStorageService::class);
});

describe('FileStorageService', function () {
    it('stores a file successfully', function () {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $result = $this->service->storeFile($file, 'documents/test');

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['file_key', 'file_name', 'file_size', 'mime_type'])
            ->and($result['file_name'])->toBe('document.pdf')
            ->and($result['file_size'])->toBe(1024 * 1024);

        Storage::disk('s3')->assertExists($result['file_key']);
    });

    it('generates unique file keys for duplicate names', function () {
        $file1 = UploadedFile::fake()->create('document.pdf', 512);
        $file2 = UploadedFile::fake()->create('document.pdf', 512);

        $result1 = $this->service->storeFile($file1, 'documents');
        $result2 = $this->service->storeFile($file2, 'documents');

        expect($result1['file_key'])->not->toBe($result2['file_key']);
    });

    it('retrieves file URL', function () {
        $file = UploadedFile::fake()->create('test.pdf', 512);
        $stored = $this->service->storeFile($file, 'documents');

        $url = $this->service->getFileUrl($stored['file_key']);

        expect($url)->toBeString()
            ->and($url)->toContain($stored['file_key']);
    });

    it('checks if file exists', function () {
        $file = UploadedFile::fake()->create('exists.pdf', 512);
        $stored = $this->service->storeFile($file, 'documents');

        expect($this->service->fileExists($stored['file_key']))->toBeTrue()
            ->and($this->service->fileExists('nonexistent/file.pdf'))->toBeFalse();
    });

    it('deletes a file', function () {
        $file = UploadedFile::fake()->create('delete-me.pdf', 512);
        $stored = $this->service->storeFile($file, 'documents');

        expect($this->service->fileExists($stored['file_key']))->toBeTrue();

        $this->service->deleteFile($stored['file_key']);

        expect($this->service->fileExists($stored['file_key']))->toBeFalse();
    });

    it('validates file type', function () {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');
        $imageFile = UploadedFile::fake()->image('photo.jpg');
        $invalidFile = UploadedFile::fake()->create('script.exe', 512);

        expect($this->service->validateFileType($pdfFile, ['pdf', 'doc']))->toBeTrue()
            ->and($this->service->validateFileType($imageFile, ['jpg', 'png']))->toBeTrue()
            ->and($this->service->validateFileType($invalidFile, ['pdf', 'doc']))->toBeFalse();
    });

    it('gets file metadata', function () {
        $file = UploadedFile::fake()->create('metadata.pdf', 1024);
        $stored = $this->service->storeFile($file, 'documents');

        $metadata = $this->service->getFileMetadata($stored['file_key']);

        expect($metadata)->toBeArray()
            ->and($metadata)->toHaveKeys(['size', 'last_modified', 'mime_type'])
            ->and($metadata['size'])->toBeInt();
    });

    it('generates download response', function () {
        $file = UploadedFile::fake()->create('download.pdf', 512);
        $stored = $this->service->storeFile($file, 'documents');

        $response = $this->service->downloadFile($stored['file_key'], 'custom-name.pdf');

        expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class)
            ->and($response->headers->get('content-disposition'))->toContain('custom-name.pdf');
    });
});
