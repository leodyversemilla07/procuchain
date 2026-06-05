<?php

declare(strict_types=1);

use App\Exceptions\BlockchainException;
use App\Exceptions\DocumentUploadException;
use App\Exceptions\ProcurementNotFoundException;
use Illuminate\Http\UploadedFile;

describe('BlockchainException', function () {
    it('creates exception with default message', function () {
        $exception = new BlockchainException;
        expect($exception->getMessage())->toBe('A blockchain error occurred');
        expect($exception->getOperation())->toBeNull();
        expect($exception->getContext())->toBe([]);
    });

    it('creates connection failed exception', function () {
        $exception = BlockchainException::connectionFailed('Node unreachable', ['node' => 'localhost']);
        expect($exception->getMessage())->toContain('connection failed');
        expect($exception->getOperation())->toBe('connection');
        expect($exception->getContext())->toHaveKey('node');
    });

    it('creates publish failed exception', function () {
        $exception = BlockchainException::publishFailed('procurement.documents', 'Write error');
        expect($exception->getMessage())->toContain('procurement.documents');
        expect($exception->getOperation())->toBe('publish');
        expect($exception->getContext())->toHaveKey('stream');
    });

    it('creates stream read failed exception', function () {
        $exception = BlockchainException::streamReadFailed('procurement.status', 'Read timeout');
        expect($exception->getMessage())->toContain('procurement.status');
        expect($exception->getOperation())->toBe('stream_read');
    });
});

describe('ProcurementNotFoundException', function () {
    it('creates exception with default message', function () {
        $exception = new ProcurementNotFoundException;
        expect($exception->getMessage())->toBe('Procurement record not found');
        expect($exception->getProcurementId())->toBeNull();
    });

    it('creates exception for specific procurement ID', function () {
        $exception = ProcurementNotFoundException::forId('PR-2025-000-0001');
        expect($exception->getMessage())->toContain('PR-2025-000-0001');
        expect($exception->getProcurementId())->toBe('PR-2025-000-0001');
    });

    it('creates exception for specific stage', function () {
        $exception = ProcurementNotFoundException::forStage('PR-2025-000-0001', 'bidding');
        expect($exception->getMessage())->toContain('PR-2025-000-0001');
        expect($exception->getMessage())->toContain('bidding');
    });

    it('returns false for report method', function () {
        $exception = new ProcurementNotFoundException;
        expect($exception->report())->toBeFalse();
    });
});

describe('DocumentUploadException', function () {
    it('creates exception with default message', function () {
        $exception = new DocumentUploadException;
        expect($exception->getMessage())->toBe('Document upload failed');
        expect($exception->getFilename())->toBeNull();
        expect($exception->getProcurementId())->toBeNull();
    });

    it('creates validation failed exception', function () {
        $file = UploadedFile::fake()->create('test.exe', 1024);
        $exception = DocumentUploadException::validationFailed($file, 'Invalid file type', 'PR-2025-000-0001');

        expect($exception->getMessage())->toContain('validation failed');
        expect($exception->getFilename())->toBe('test.exe');
        expect($exception->getProcurementId())->toBe('PR-2025-000-0001');
        expect($exception->getContext())->toHaveKey('reason');
    });

    it('creates storage failed exception', function () {
        $exception = DocumentUploadException::storageFailed('document.pdf', 'Disk full', 'PR-2025-000-0001');
        expect($exception->getMessage())->toContain('Failed to store document');
        expect($exception->getFilename())->toBe('document.pdf');
    });

    it('creates blockchain storage failed exception', function () {
        $exception = DocumentUploadException::blockchainStorageFailed('document.pdf', 'Node offline');
        expect($exception->getMessage())->toContain('blockchain');
        expect($exception->getFilename())->toBe('document.pdf');
    });

    it('creates invalid document type exception', function () {
        $exception = DocumentUploadException::invalidDocumentType('test.docx', 'pdf', 'docx');
        expect($exception->getMessage())->toContain('Invalid document type');
        expect($exception->getContext()['expected_type'])->toBe('pdf');
        expect($exception->getContext()['actual_type'])->toBe('docx');
    });

    it('creates file size exceeded exception', function () {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $actualSize = 15 * 1024 * 1024; // 15MB
        $exception = DocumentUploadException::fileSizeExceeded('large-file.pdf', $maxSize, $actualSize);

        expect($exception->getMessage())->toContain('exceeds maximum');
        expect($exception->getContext()['max_size'])->toBe($maxSize);
        expect($exception->getContext()['actual_size'])->toBe($actualSize);
    });
});
