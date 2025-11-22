<?php

use App\Services\BlockchainStorageService;
use App\Services\Manager;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    // Mock Manager for on-chain storage
    $this->multichainMock = Mockery::mock(Manager::class);
    $this->multichainMock->shouldReceive('publish')
        ->byDefault()
        ->andReturn('mock_txid_'.uniqid());
    $this->multichainMock->shouldReceive('getstreamitem')
        ->byDefault()
        ->andReturn(['data' => ['json' => []]]);
    $this->multichainMock->shouldReceive('liststreamkeyitems')
        ->byDefault()
        ->andReturn([]);

    $this->service = new BlockchainStorageService($this->multichainMock);
});

describe('BlockchainStorageService - On-Chain Storage', function () {
    describe('uploadFile', function () {
        it('uploads file successfully to blockchain with hex encoding', function () {
            $mockService = Mockery::mock(Manager::class);

            // Mock publish for file data (hex)
            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.data', Mockery::type('string'), Mockery::type('string'))
                ->andReturn('data_txid_123');

            // Mock publish for metadata
            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.metadata', Mockery::type('string'), Mockery::type('array'))
                ->andReturn('metadata_txid_456');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $prNumber = 'PROC-001';
            $stageId = 1;
            $documentType = 'bid_document';

            $result = $service->uploadFile($file, $prNumber, $stageId, $documentType, ['pr_number' => 'PROC-001']);

            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['file_key', 'data_txid', 'metadata_txid', 'filename', 'size', 'hash']);
            expect($result['file_key'])->toContain($prNumber);
            expect($result['file_key'])->toContain($documentType);
            expect($result['data_txid'])->toBe('data_txid_123');
            expect($result['metadata_txid'])->toBe('metadata_txid_456');
        });

        it('calculates sha256 hash correctly', function () {
            $mockService = Mockery::mock(Manager::class);
            $mockService->shouldReceive('publish')->andReturn('txid1', 'txid2');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->create('document.pdf', 100);
            $result = $service->uploadFile($file, 'TEST', 1, 'doc', ['pr_number' => 'TEST']);

            expect($result['hash'])->toBeString();
            expect($result['hash'])->toHaveLength(64); // SHA-256 hex length
        });

        it('converts file to hex for on-chain storage', function () {
            $fileContent = 'test content';
            $expectedHex = bin2hex($fileContent);

            $mockService = Mockery::mock(Manager::class);

            // Verify hex is published to file.data stream
            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.data', Mockery::type('string'), $expectedHex)
                ->andReturn('data_txid');

            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.metadata', Mockery::any(), Mockery::any())
                ->andReturn('metadata_txid');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->createWithContent('test.txt', $fileContent);

            $result = $service->uploadFile($file, 'test', 1, 'file', []);

            expect($result['data_txid'])->toBe('data_txid');
        });

        it('includes storage_method as on_chain in metadata', function () {
            $mockService = Mockery::mock(Manager::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.data', Mockery::any(), Mockery::any())
                ->andReturn('data_txid');

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'file.metadata',
                    Mockery::type('string'),
                    Mockery::on(function ($data) {
                        return $data['json']['storage_method'] === 'on_chain' &&
                               isset($data['json']['data_txid']);
                    })
                )
                ->andReturn('metadata_txid');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $result = $service->uploadFile($file, 'test', 1, 'file', []);

            expect($result['metadata_txid'])->toBe('metadata_txid');
        });

        it('throws exception for files exceeding max size', function () {
            $service = new BlockchainStorageService($this->multichainMock);

            // Create file larger than 50MB
            $largeFile = UploadedFile::fake()->create('large.pdf', 60000); // 60MB

            expect(fn () => $service->uploadFile($largeFile, 'test', 1, 'file', []))
                ->toThrow(Exception::class, 'exceeds maximum');
        });

        it('includes context in blockchain metadata', function () {
            $mockService = Mockery::mock(Manager::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with('file.data', Mockery::any(), Mockery::any())
                ->andReturn('data_txid');

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'file.metadata',
                    Mockery::type('string'),
                    Mockery::on(function ($data) {
                        return $data['json']['pr_number'] === 'PROC-123' &&
                               $data['json']['title'] === 'Bid Document';
                    })
                )
                ->andReturn('txid_with_context');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->create('bid.pdf', 100);
            $result = $service->uploadFile(
                $file,
                'PROC-123',
                1,
                'bid',
                ['pr_number' => 'PROC-123', 'title' => 'Bid Document']
            );

            expect($result['metadata_txid'])->toBe('txid_with_context');
        });
    });

    describe('retrieveFile', function () {
        it('retrieves file from blockchain and converts hex to binary', function () {
            $fileContent = 'test file content';
            $fileHex = bin2hex($fileContent);

            $mockService = Mockery::mock(Manager::class);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.metadata', 'test_file.pdf', false, 1)
                ->andReturn([]);

            // Mock retrieval from blockchain
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.data', 'data_txid_123', true)
                ->andReturn([
                    'txid' => 'data_txid_123',
                    'data' => $fileHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $retrieved = $service->retrieveFile('test/file.pdf', 'data_txid_123');

            expect($retrieved)->toBeArray();
            expect($retrieved['content'])->toBe($fileContent);
            expect($retrieved['hash'])->toBe(hash('sha256', $fileContent));
        });

        it('retrieves file by key when no txid provided', function () {
            $fileContent = 'test content';
            $fileHex = bin2hex($fileContent);

            $mockService = Mockery::mock(Manager::class);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.metadata', 'test_file.pdf', false, 1)
                ->andReturn([]);

            // Mock listStreamKeyItems to find the file
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.data', 'test_file.pdf', false, 1)
                ->andReturn([[
                    'txid' => 'found_txid',
                    'data' => $fileHex,
                ]]);

            $service = new BlockchainStorageService($mockService);

            $retrieved = $service->retrieveFile('test/file.pdf');

            expect($retrieved['content'])->toBe($fileContent);
            expect($retrieved['data_txid'])->toBe('found_txid');
        });

        it('throws exception for non-existent file', function () {
            $mockService = Mockery::mock(Manager::class);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.metadata', 'non_existent_file.pdf', false, 1)
                ->andReturn([]);

            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->andReturn([]); // No items found

            $service = new BlockchainStorageService($mockService);

            expect(fn () => $service->retrieveFile('non/existent/file.pdf'))
                ->toThrow(Exception::class, 'File not found on blockchain');
        });
    });

    describe('verifyFileIntegrity', function () {
        it('verifies file integrity against blockchain metadata', function () {
            $fileContent = 'test file content for integrity verification';
            $expectedHash = hash('sha256', $fileContent);
            $fileHex = bin2hex($fileContent);

            $mockService = Mockery::mock(Manager::class);

            // Mock metadata retrieval for verifyFileIntegrity
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.metadata', 'metadata_txid', true)
                ->andReturn([
                    'data' => ['json' => [
                        'hash' => $expectedHash,
                        'data_txid' => 'data_txid_123',
                    ]],
                ]);

            // Mock metadata list for retrieveFile (returns empty since txid provided)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.metadata', 'test_file.pdf', false, 1)
                ->andReturn([]);

            // Mock file data retrieval
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.data', 'data_txid_123', true)
                ->andReturn([
                    'txid' => 'data_txid_123',
                    'data' => $fileHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $isValid = $service->verifyFileIntegrity('test/file.pdf', 'metadata_txid');

            expect($isValid)->toBeTrue();
        });

        it('returns false for corrupted file', function () {
            $fileContent = 'original content';
            $corruptedContent = 'tampered content';
            $originalHash = hash('sha256', $fileContent);
            $corruptedHex = bin2hex($corruptedContent);

            $mockService = Mockery::mock(Manager::class);

            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.metadata', 'metadata_txid', true)
                ->andReturn([
                    'data' => ['json' => [
                        'hash' => $originalHash,
                        'data_txid' => 'data_txid',
                    ]],
                ]);

            // Mock metadata list for retrieveFile
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('file.metadata', 'test_file.pdf', false, 1)
                ->andReturn([]);

            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.data', 'data_txid', true)
                ->andReturn([
                    'txid' => 'data_txid',
                    'data' => $corruptedHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $isValid = $service->verifyFileIntegrity('test/file.pdf', 'metadata_txid');

            expect($isValid)->toBeFalse();
        });
    });

    describe('getFileMetadata', function () {
        it('retrieves metadata from blockchain', function () {
            $mockService = Mockery::mock(Manager::class);
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('file.metadata', 'test_txid', true)
                ->andReturn([
                    'data' => ['json' => [
                        'filename' => 'test.pdf',
                        'size' => 1024,
                        'hash' => 'abc123',
                        'uploaded_at' => '2024-01-01 00:00:00',
                    ]],
                ]);

            $service = new BlockchainStorageService($mockService);
            $metadata = $service->getFileMetadata('test_txid');

            expect($metadata)->toBeArray();
            expect($metadata['filename'])->toBe('test.pdf');
            expect($metadata['size'])->toBe(1024);
        });
    });

    describe('deleteFile', function () {
        it('marks file as deleted on blockchain', function () {
            $mockService = Mockery::mock(Manager::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'file.metadata',
                    Mockery::type('string'),
                    Mockery::on(function ($data) {
                        return $data['json']['action'] === 'deleted' &&
                               isset($data['json']['deleted_at']) &&
                               isset($data['json']['file_key']) &&
                               isset($data['json']['data_key']);
                    })
                )
                ->andReturn('delete_txid');

            $service = new BlockchainStorageService($mockService);

            $success = $service->deleteFile('test/doc.pdf', 'Test deletion');

            expect($success)->toBeTrue();
        });

        it('includes reason in deletion record', function () {
            $mockService = Mockery::mock(Manager::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'file.metadata',
                    Mockery::any(),
                    Mockery::on(function ($data) {
                        return $data['json']['reason'] === 'Compliance violation';
                    })
                )
                ->andReturn('delete_txid');

            $service = new BlockchainStorageService($mockService);

            $success = $service->deleteFile('test/doc.pdf', 'Compliance violation');

            expect($success)->toBeTrue();
        });
    });

    describe('getMaxFileSize', function () {
        it('returns maximum file size in bytes', function () {
            $service = new BlockchainStorageService($this->multichainMock);
            $maxSize = $service->getMaxFileSize();

            expect($maxSize)->toBe(52428800); // 50MB in bytes
        });

        it('returns formatted max file size', function () {
            $service = new BlockchainStorageService($this->multichainMock);
            $formatted = $service->getMaxFileSizeFormatted();

            expect($formatted)->toBe('50 MB');
        });
    });
});
