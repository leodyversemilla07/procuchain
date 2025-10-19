<?php

use App\Services\DocumentMetadataService;
use Illuminate\Http\UploadedFile;

describe('DocumentMetadataService', function () {
    beforeEach(function () {
        $this->service = new DocumentMetadataService;
    });

    describe('prepareMetadata', function () {
        it('prepares metadata for single file', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [
                ['document_type' => 'bid_proposal'],
            ];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-2024-001',
                'Road Construction Project',
                'bidding'
            );

            expect($result)->toHaveCount(1);
            expect($result[0])->toHaveKeys(['hash', 'file_size', 'document_type', 'sanitized_document_type', 'base_path']);
            expect($result[0]['document_type'])->toBe('bid_proposal');
            expect($result[0]['sanitized_document_type'])->toBe('bid_proposal');
            expect($result[0]['base_path'])->toBe('PROC-2024-001-Road_Construction_Project/bidding');
            expect($result[0]['file_size'])->toBe($file->getSize());
            expect($result[0]['hash'])->toHaveLength(64); // SHA256 hash
        });

        it('prepares metadata for multiple files', function () {
            $files = [
                UploadedFile::fake()->create('doc1.pdf', 100),
                UploadedFile::fake()->create('doc2.pdf', 200),
                UploadedFile::fake()->create('doc3.pdf', 300),
            ];
            $metadata = [
                ['document_type' => 'bid'],
                ['document_type' => 'spec'],
                ['document_type' => 'contract'],
            ];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-2024-002',
                'School Building',
                'preparation'
            );

            expect($result)->toHaveCount(3);
            expect($result[0]['document_type'])->toBe('bid');
            expect($result[1]['document_type'])->toBe('spec');
            expect($result[2]['document_type'])->toBe('contract');
        });

        it('sanitizes procurement title in base path', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Road & Bridge Construction (Phase 1)',
                'bidding'
            );

            expect($result[0]['base_path'])->toBe('PROC-001-Road___Bridge_Construction__Phase_1_/bidding');
        });

        it('sanitizes document type', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid proposal & specs']];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Project',
                'bidding'
            );

            expect($result[0]['sanitized_document_type'])->toBe('bid_proposal___specs');
        });

        it('generates default document type when not provided', function () {
            $files = [
                UploadedFile::fake()->create('doc1.pdf', 100),
                UploadedFile::fake()->create('doc2.pdf', 200),
            ];
            $metadata = [
                [],
                [],
            ];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Project',
                'bidding'
            );

            expect($result[0]['document_type'])->toBe('doc-0');
            expect($result[1]['document_type'])->toBe('doc-1');
        });

        it('generates unique hash for each file', function () {
            $files = [
                UploadedFile::fake()->create('doc1.pdf', 100)->storeAs('tmp', 'file1.pdf'),
                UploadedFile::fake()->create('doc2.pdf', 200)->storeAs('tmp', 'file2.pdf'),
            ];
            $metadata = [
                ['document_type' => 'bid'],
                ['document_type' => 'spec'],
            ];

            // Create files with actual different content
            $file1 = UploadedFile::fake()->createWithContent('doc1.pdf', 'content1');
            $file2 = UploadedFile::fake()->createWithContent('doc2.pdf', 'different content');

            $result = $this->service->prepareMetadata(
                [$file1, $file2],
                $metadata,
                'PROC-001',
                'Project',
                'bidding'
            );

            expect($result[0]['hash'])->toHaveLength(64);
            expect($result[1]['hash'])->toHaveLength(64);
        });

        it('merges additional metadata fields', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [
                [
                    'document_type' => 'bid',
                    'custom_field' => 'custom_value',
                    'another_field' => 123,
                ],
            ];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Project',
                'bidding'
            );

            expect($result[0]['custom_field'])->toBe('custom_value');
            expect($result[0]['another_field'])->toBe(123);
        });

        it('handles empty stage folder', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Project',
                ''
            );

            expect($result[0]['base_path'])->toBe('PROC-001-Project');
        });

        it('trims slashes from base path', function () {
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $files = [$file];
            $metadata = [['document_type' => 'bid']];

            $result = $this->service->prepareMetadata(
                $files,
                $metadata,
                'PROC-001',
                'Project',
                '/bidding/'
            );

            expect($result[0]['base_path'])->not->toStartWith('/');
            expect($result[0]['base_path'])->not->toEndWith('/');
        });
    });
});
