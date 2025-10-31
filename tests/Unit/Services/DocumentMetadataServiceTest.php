<?php

use App\Services\DocumentMetadataService;
use Illuminate\Http\UploadedFile;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = app(DocumentMetadataService::class);
});

describe('DocumentMetadataService', function () {
    it('generates SHA-256 hash for a file', function () {
        $file = UploadedFile::fake()->create('test.pdf', 512);

        $hash = $this->service->generateFileHash($file);

        expect($hash)->toBeString()
            ->and(strlen($hash))->toBe(64) // SHA-256 produces 64 hex characters
            ->and(ctype_xdigit($hash))->toBeTrue();
    });

    it('generates consistent hashes for identical content', function () {
        $content = 'Test content for hashing';
        $file1 = UploadedFile::fake()->createWithContent('file1.txt', $content);
        $file2 = UploadedFile::fake()->createWithContent('file2.txt', $content);

        $hash1 = $this->service->generateFileHash($file1);
        $hash2 = $this->service->generateFileHash($file2);

        expect($hash1)->toBe($hash2);
    });

    it('generates different hashes for different content', function () {
        $file1 = UploadedFile::fake()->createWithContent('file1.txt', 'Content A');
        $file2 = UploadedFile::fake()->createWithContent('file2.txt', 'Content B');

        $hash1 = $this->service->generateFileHash($file1);
        $hash2 = $this->service->generateFileHash($file2);

        expect($hash1)->not->toBe($hash2);
    });

    it('extracts file metadata', function () {
        $file = UploadedFile::fake()->create('document.pdf', 2048);

        $metadata = $this->service->extractMetadata($file);

        expect($metadata)->toBeArray()
            ->and($metadata)->toHaveKeys(['original_name', 'size', 'mime_type', 'extension'])
            ->and($metadata['original_name'])->toBe('document.pdf')
            ->and($metadata['mime_type'])->toBe('application/pdf')
            ->and($metadata['extension'])->toBe('pdf')
            ->and($metadata['size'])->toBeInt();
    });

    it('validates file integrity against hash', function () {
        $file = UploadedFile::fake()->create('verify.pdf', 512);
        $correctHash = $this->service->generateFileHash($file);
        $incorrectHash = hash('sha256', 'different content');

        expect($this->service->verifyFileIntegrity($file, $correctHash))->toBeTrue()
            ->and($this->service->verifyFileIntegrity($file, $incorrectHash))->toBeFalse();
    });

    it('prepares blockchain metadata', function () {
        $file = UploadedFile::fake()->create('blockchain.pdf', 1024);
        $procurementId = 'PROC-2025-001';
        $stage = 'bidding_documents';

        $metadata = $this->service->prepareBlockchainMetadata(
            $file,
            $procurementId,
            $stage,
            'bid_documents'
        );

        expect($metadata)->toBeArray()
            ->and($metadata)->toHaveKeys([
                'file_hash',
                'file_name',
                'file_size',
                'mime_type',
                'procurement_id',
                'stage',
                'document_type',
                'uploaded_at',
            ])
            ->and($metadata['procurement_id'])->toBe($procurementId)
            ->and($metadata['stage'])->toBe($stage)
            ->and($metadata['file_hash'])->toBeString();
    });

    it('formats file size in human-readable format', function () {
        expect($this->service->formatFileSize(1024))->toBe('1.00 KB')
            ->and($this->service->formatFileSize(1048576))->toBe('1.00 MB')
            ->and($this->service->formatFileSize(1073741824))->toBe('1.00 GB')
            ->and($this->service->formatFileSize(512))->toBe('512 B');
    });
});
