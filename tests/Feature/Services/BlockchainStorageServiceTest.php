<?php

use App\Services\BlockchainRpcClient;
use App\Services\BlockchainStorageService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    // Mock BlockchainRpcClient for on-chain storage
    $this->multichainMock = Mockery::mock(BlockchainRpcClient::class);
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

/**
 * Helper: Mock the isBlockchainFileDeleted check that FileRetriever calls
 * before every liststreamkeyitems metadata mock in retrieveFile tests.
 *
 * FileRetriever::retrieveFile() calls isBlockchainFileDeleted() first,
 * which calls liststreamkeyitems with a "{dataKey}_deleted" key.
 * We mock it to return [] (not deleted) by default.
 */
function mockBlockchainFileNotDeleted($mockService): void
{
    $mockService->shouldReceive('liststreamkeyitems')
        ->once()
        ->withArgs(function (string $stream, string $key) {
            // Match deletion key pattern: "{dataKey}_deleted"
            return str_ends_with($key, '_deleted');
        })
        ->andReturn([]); // Empty = File not deleted
}

describe('BlockchainStorageService - On-Chain Storage', function () {
    describe('uploadFile', function () {
        it('uploads File successfully to blockchain with hex encoding', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Mock publishmulti for batch upload (data + metadata)
            $mockService->shouldReceive('publishmulti')
                ->once()
                ->with('File.data', Mockery::type('array'))
                ->andReturn('batch_txid_123');

            $service = new BlockchainStorageService($mockService);
            // Use createWithContent to ensure File has readable content
            $file = UploadedFile::fake()->createWithContent('document.pdf', str_repeat('x', 100));
            $prNumber = 'PROC-001';
            $stageId = 1;
            $documentType = 'bid_document';

            $result = $service->uploadFile($file, $prNumber, $stageId, $documentType, ['pr_number' => 'PROC-001']);

            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['file_key', 'data_txid', 'metadata_txid', 'filename', 'size', 'hash']);
            expect($result['file_key'])->toContain($prNumber);
            expect($result['file_key'])->toContain($documentType);
            expect($result['data_txid'])->toBe('batch_txid_123');
            expect($result['metadata_txid'])->toBe('batch_txid_123'); // Same txid for batch
        });

        it('calculates sha256 hash correctly', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);
            $mockService->shouldReceive('publishmulti')->andReturn('batch_txid');

            $service = new BlockchainStorageService($mockService);
            // Use createWithContent to ensure File has readable content
            $file = UploadedFile::fake()->createWithContent('document.pdf', str_repeat('x', 100));
            $result = $service->uploadFile($file, 'TEST', 1, 'doc', ['pr_number' => 'TEST']);

            expect($result['hash'])->toBeString();
            expect($result['hash'])->toHaveLength(64); // SHA-256 hex length
        });

        it('converts File to hex for on-chain storage', function () {
            $blockchainFileContent = 'test content';
            $expectedHex = bin2hex($blockchainFileContent);

            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Verify hex is in batch items
            $mockService->shouldReceive('publishmulti')
                ->once()
                ->with('File.data', Mockery::on(function ($items) use ($expectedHex) {
                    return count($items) === 2 && // data + metadata
                        $items[0]['data'] === $expectedHex &&
                        $items[0]['for'] === 'File.data';
                }))
                ->andReturn('batch_txid');

            $service = new BlockchainStorageService($mockService);
            $file = UploadedFile::fake()->createWithContent('test.txt', $blockchainFileContent);

            $result = $service->uploadFile($file, 'test', 1, 'File', []);

            expect($result['data_txid'])->toBe('batch_txid');
        });

        it('includes storage_method as on_chain in metadata', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            $mockService->shouldReceive('publishmulti')
                ->once()
                ->with(
                    'File.data',
                    Mockery::on(function ($items) {
                        return count($items) === 2 &&
                            $items[1]['for'] === 'File.metadata' &&
                            $items[1]['data']['json']['storage_method'] === 'on_chain';
                    })
                )
                ->andReturn('batch_txid');

            $service = new BlockchainStorageService($mockService);
            // Use createWithContent to ensure File has readable content
            $file = UploadedFile::fake()->createWithContent('doc.pdf', str_repeat('x', 100));

            $result = $service->uploadFile($file, 'test', 1, 'File', []);

            expect($result['metadata_txid'])->toBe('batch_txid');
        });

        it('throws exception for BlockchainFiles exceeding max size', function () {
            $service = new BlockchainStorageService($this->multichainMock);

            // Create File larger than 50MB with actual content
            // We need to create content that exceeds the limit
            $largeContent = str_repeat('x', 52428801); // Just over 50MB limit
            $largeFile = UploadedFile::fake()->createWithContent('large.pdf', $largeContent);

            expect(fn () => $service->uploadFile($largeFile, 'test', 1, 'File', []))
                ->toThrow(Exception::class, 'exceeds maximum');
        });

        it('includes context in blockchain metadata', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            $mockService->shouldReceive('publishmulti')
                ->once()
                ->with(
                    'File.data',
                    Mockery::on(function ($items) {
                        return count($items) === 2 &&
                            $items[1]['data']['json']['pr_number'] === 'PROC-123' &&
                            $items[1]['data']['json']['title'] === 'Bid Document';
                    })
                )
                ->andReturn('batch_txid_with_context');

            $service = new BlockchainStorageService($mockService);
            // Use createWithContent to ensure File has readable content
            $file = UploadedFile::fake()->createWithContent('bid.pdf', str_repeat('x', 100));
            $result = $service->uploadFile(
                $file,
                'PROC-123',
                1,
                'bid',
                ['pr_number' => 'PROC-123', 'title' => 'Bid Document']
            );

            expect($result['metadata_txid'])->toBe('batch_txid_with_context');
        });
    });

    describe('retrieveFile', function () {
        it('retrieves File from blockchain and converts hex to binary', function () {
            $blockchainFileContent = 'test File content';
            $blockchainFileHex = bin2hex($blockchainFileContent);

            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Mock deletion check (FileRetriever calls this first)
            mockBlockchainFileNotDeleted($mockService);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.metadata', 'test_File.pdf', false, 1)
                ->andReturn([]);

            // Mock retrieval from blockchain
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.data', 'data_txid_123', true)
                ->andReturn([
                    'txid' => 'data_txid_123',
                    'data' => $blockchainFileHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $retrieved = $service->retrieveFile('test/File.pdf', 'data_txid_123');

            expect($retrieved)->toBeArray();
            expect($retrieved['content'])->toBe($blockchainFileContent);
            expect($retrieved['hash'])->toBe(hash('sha256', $blockchainFileContent));
        });

        it('retrieves File by key when no txid provided', function () {
            $blockchainFileContent = 'test content';
            $blockchainFileHex = bin2hex($blockchainFileContent);

            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Mock deletion check (FileRetriever calls this first)
            mockBlockchainFileNotDeleted($mockService);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.metadata', 'test_File.pdf', false, 1)
                ->andReturn([]);

            // Mock listStreamKeyItems to find the File
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.data', 'test_File.pdf', false, 1)
                ->andReturn([[
                    'txid' => 'found_txid',
                    'data' => $blockchainFileHex,
                ]]);

            $service = new BlockchainStorageService($mockService);

            $retrieved = $service->retrieveFile('test/File.pdf');

            expect($retrieved['content'])->toBe($blockchainFileContent);
            expect($retrieved['storage_method'])->toBe('on_chain');
            expect($retrieved['hash'])->toBe(hash('sha256', $blockchainFileContent));
        });

        it('throws exception for non-existent File', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Mock deletion check (FileRetriever calls this first)
            mockBlockchainFileNotDeleted($mockService);

            // Mock metadata retrieval (returns empty)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.metadata', 'non_existent_File.pdf', false, 1)
                ->andReturn([]);

            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->andReturn([]); // No items found

            $service = new BlockchainStorageService($mockService);

            expect(fn () => $service->retrieveFile('non/existent/File.pdf'))
                ->toThrow(Exception::class, 'File not found on blockchain');
        });
    });

    describe('verifyFileIntegrity', function () {
        it('verifies File integrity against blockchain metadata', function () {
            $blockchainFileContent = 'test File content for integrity verification';
            $expectedHash = hash('sha256', $blockchainFileContent);
            $blockchainFileHex = bin2hex($blockchainFileContent);

            $mockService = Mockery::mock(BlockchainRpcClient::class);

            // Mock metadata retrieval for verifyFileIntegrity
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.metadata', 'metadata_txid', true)
                ->andReturn([
                    'data' => ['json' => [
                        'hash' => $expectedHash,
                        'data_txid' => 'data_txid_123',
                    ]],
                ]);

            // Mock deletion check (FileRetriever calls this first)
            mockBlockchainFileNotDeleted($mockService);

            // Mock metadata list for retrieveFile (returns empty since txid provided)
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.metadata', 'test_File.pdf', false, 1)
                ->andReturn([]);

            // Mock File data retrieval
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.data', 'data_txid_123', true)
                ->andReturn([
                    'txid' => 'data_txid_123',
                    'data' => $blockchainFileHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $isValid = $service->verifyFileIntegrity('test/File.pdf', 'metadata_txid');

            expect($isValid)->toBeTrue();
        });

        it('returns false for corrupted File', function () {
            $blockchainFileContent = 'original content';
            $corruptedContent = 'tampered content';
            $originalHash = hash('sha256', $blockchainFileContent);
            $corruptedHex = bin2hex($corruptedContent);

            $mockService = Mockery::mock(BlockchainRpcClient::class);

            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.metadata', 'metadata_txid', true)
                ->andReturn([
                    'data' => ['json' => [
                        'hash' => $originalHash,
                        'data_txid' => 'data_txid',
                    ]],
                ]);

            // Mock deletion check (FileRetriever calls this first)
            mockBlockchainFileNotDeleted($mockService);

            // Mock metadata list for retrieveFile
            $mockService->shouldReceive('liststreamkeyitems')
                ->once()
                ->with('File.metadata', 'test_File.pdf', false, 1)
                ->andReturn([]);

            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.data', 'data_txid', true)
                ->andReturn([
                    'txid' => 'data_txid',
                    'data' => $corruptedHex,
                ]);

            $service = new BlockchainStorageService($mockService);

            $isValid = $service->verifyFileIntegrity('test/File.pdf', 'metadata_txid');

            expect($isValid)->toBeFalse();
        });
    });

    describe('getFileMetadata', function () {
        it('retrieves metadata from blockchain', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);
            $mockService->shouldReceive('getstreamitem')
                ->once()
                ->with('File.metadata', 'test_txid', true)
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
        it('marks File as deleted on blockchain', function () {
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'File.metadata',
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
            $mockService = Mockery::mock(BlockchainRpcClient::class);

            $mockService->shouldReceive('publish')
                ->once()
                ->with(
                    'File.metadata',
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

    describe('getMaxfileSize', function () {
        it('returns maximum File size in bytes', function () {
            $service = new BlockchainStorageService($this->multichainMock);
            $maxSize = $service->getMaxfileSize();

            expect($maxSize)->toBe(52428800); // 50MB in bytes
        });

        it('returns formatted max File size', function () {
            $service = new BlockchainStorageService($this->multichainMock);
            $formatted = $service->getMaxfileSizeFormatted();

            expect($formatted)->toBe('50 MB');
        });
    });
});
