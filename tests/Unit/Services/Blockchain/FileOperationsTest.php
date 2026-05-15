<?php

use App\Enums\StreamEnums;
use App\Services\Blockchain\FileRetriever;
use App\Services\Blockchain\FileUploader;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
    $this->multichain = Mockery::mock(Manager::class);
});

// Helper to create a FileUploader with configurable settings
function createUploader(object $multichain, array $overrides = []): FileUploader
{
    return new FileUploader(
        multichain: $multichain,
        maxChunkSize: $overrides['maxChunkSize'] ?? 10 * 1024 * 1024,
        recommendedMaxSize: $overrides['recommendedMaxSize'] ?? 5 * 1024 * 1024,
        chunkThreshold: $overrides['chunkThreshold'] ?? 1024 * 100,
        chunkSize: $overrides['chunkSize'] ?? 1024 * 50,
        chunkingEnabled: $overrides['chunkingEnabled'] ?? true,
    );
}

// Helper to create an UploadedFile with actual content
function createFileWithContent(string $name, string $content, string $mimeType = 'application/pdf'): UploadedFile
{
    $tempPath = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tempPath, $content);

    return new UploadedFile($tempPath, $name, $mimeType, null, true);
}

describe('FileUploader', function () {
    describe('uploadFile - single transaction', function () {
        it('uploads small file in a single transaction', function () {
            $uploader = createUploader($this->multichain);

            $file = createFileWithContent('document.pdf', str_repeat('A', 500));

            $this->multichain
                ->shouldReceive('publishmulti')
                ->once()
                ->andReturn('txid_single_abc123');

            $result = $uploader->uploadFile($file, 'PR-2025-001', 1, 'Purchase Request');

            expect($result)
                ->toHaveKeys(['file_key', 'data_txid', 'metadata_txid', 'filename', 'size', 'mime_type', 'hash', 'storage_method', 'chunked'])
                ->and($result['filename'])->toBe('document.pdf')
                ->and($result['data_txid'])->toBe('txid_single_abc123')
                ->and($result['storage_method'])->toBe('on_chain')
                ->and($result['chunked'])->toBeFalse();
        });
    });

    describe('uploadFile - chunked upload', function () {
        it('uploads large file using chunked storage', function () {
            $uploader = createUploader($this->multichain, [
                'chunkThreshold' => 100,
                'chunkSize' => 50,
            ]);

            // Create a file larger than chunk threshold (100 bytes)
            $file = createFileWithContent('large-doc.pdf', str_repeat('X', 200));

            // Mock chunk uploads (4 chunks of 50 bytes each for 200 bytes) and metadata publish
            $this->multichain
                ->shouldReceive('publishmulti')
                ->andReturn('chunk_txid_1', 'chunk_txid_2', 'chunk_txid_3', 'chunk_txid_4');

            $this->multichain
                ->shouldReceive('publish')
                ->once()
                ->andReturn('metadata_txid_abc');

            $result = $uploader->uploadFile($file, 'PR-2025-001', 1, 'Purchase Request');

            expect($result)
                ->toHaveKeys(['file_key', 'data_txid', 'metadata_txid', 'filename', 'size', 'mime_type', 'hash', 'storage_method', 'chunked', 'total_chunks', 'chunk_txids'])
                ->and($result['storage_method'])->toBe('on_chain_chunked')
                ->and($result['chunked'])->toBeTrue()
                ->and($result['metadata_txid'])->toBe('metadata_txid_abc');
        });
    });

    describe('generateFileKey', function () {
        it('generates file key with correct format', function () {
            $uploader = createUploader($this->multichain);

            // Use reflection to test the private method
            $method = new ReflectionMethod(FileUploader::class, 'generateFileKey');
            $method->setAccessible(true);

            $fileKey = $method->invoke(
                $uploader,
                'PR-2025-001',
                1,
                'Purchase Request',
                'pdf',
                'a3f5b8c9d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8'
            );

            // Pattern: {pr_number}/{phase}/{stage_id}/{type}_{timestamp}_{hash_short}.{ext}
            expect($fileKey)->toStartWith('PR-2025-001/pre-procurement/stage-01/purchase_request_')
                ->and($fileKey)->toEndWith('.pdf')
                ->and($fileKey)->toContain('a3f5b8c');
        });

        it('determines correct phase for procurement stages', function () {
            $uploader = createUploader($this->multichain);

            $method = new ReflectionMethod(FileUploader::class, 'getPhaseFromStage');
            $method->setAccessible(true);

 expect($method->invoke($uploader, 1))->toBe('pre-procurement')
 ->and($method->invoke($uploader, 3))->toBe('pre-procurement')
 ->and($method->invoke($uploader, 4))->toBe('pre-procurement')
 ->and($method->invoke($uploader, 5))->toBe('procurement')
 ->and($method->invoke($uploader, 9))->toBe('procurement')
 ->and($method->invoke($uploader, 11))->toBe('procurement')
 ->and($method->invoke($uploader, 12))->toBe('post-procurement')
 ->and($method->invoke($uploader, 15))->toBe('post-procurement')
 ->and($method->invoke($uploader, 99))->toBe('unknown-phase');
        });
    });

    describe('uploadAndPrepare', function () {
        it('orchestrates multiple file uploads', function () {
            $uploader = createUploader($this->multichain);

            $file1 = createFileWithContent('doc1.pdf', str_repeat('A', 500));
            $file2 = createFileWithContent('doc2.pdf', str_repeat('B', 500));

            $metadata = [
                ['document_type' => 'Purchase Request', 'description' => 'PR Document'],
                ['document_type' => 'Budget Allocation', 'description' => 'Budget Doc'],
            ];

            $this->multichain
                ->shouldReceive('publishmulti')
                ->twice()
                ->andReturn('txid_1', 'txid_2');

            $this->actingAs(createUserWithRole('bac_secretariat'));

            $results = $uploader->uploadAndPrepare(
                [$file1, $file2],
                $metadata,
                'PR-2025-001',
                1,
                'Test Procurement'
            );

            expect($results)->toHaveCount(2)
                ->and($results[0]['document_type'])->toBe('Purchase Request')
                ->and($results[0]['description'])->toBe('PR Document')
                ->and($results[0]['phase'])->toBe('pre-procurement')
                ->and($results[1]['document_type'])->toBe('Budget Allocation');
        });
    });
});

describe('FileRetriever', function () {
    describe('retrieveFile - single transaction', function () {
        it('retrieves single-transaction file successfully', function () {
            $multichain = Mockery::mock(Manager::class);
            $retriever = new FileRetriever($multichain);

            $testContent = 'Hello World PDF Content';
            $testHex = bin2hex($testContent);
            $expectedHash = hash('sha256', $testContent);

            // Mock metadata retrieval
            $multichain->shouldReceive('liststreamkeyitems')
                ->with(StreamEnums::FILE_METADATA->value, Mockery::any(), false, 1)
                ->once()
                ->andReturn([
                    [
                        'data' => [
                            'json' => [
                                'filename' => 'test.pdf',
                                'file_key' => 'PR-2025-001/pre-procurement/stage-01/test.pdf',
                                'data_txid' => 'data_tx_123',
                                'data_key' => 'PR-2025-001_pre-procurement_stage-01_test.pdf',
                                'mime_type' => 'application/pdf',
                                'size' => strlen($testContent),
                                'hash' => $expectedHash,
                                'storage_method' => 'on_chain',
                                'stored_at' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            // Mock data retrieval
            $multichain->shouldReceive('getstreamitem')
                ->with(StreamEnums::FILE_DATA->value, 'data_tx_123', true)
                ->once()
                ->andReturn([
                    'data' => $testHex,
                ]);

            $result = $retriever->retrieveFile('PR-2025-001/pre-procurement/stage-01/test.pdf');

            expect($result['content'])->toBe($testContent)
                ->and($result['filename'])->toBe('test.pdf')
                ->and($result['mime_type'])->toBe('application/pdf')
                ->and($result['hash'])->toBe($expectedHash)
                ->and($result['storage_method'])->toBe('on_chain');
        });
    });

    describe('retrieveFile - chunked file', function () {
        it('retrieves and reassembles chunked file', function () {
            $multichain = Mockery::mock(Manager::class);
            $retriever = new FileRetriever($multichain);

            $chunk1 = 'First chunk content';
            $chunk2 = 'Second chunk content';
            $fullContent = $chunk1.$chunk2;
            $expectedHash = hash('sha256', $fullContent);

            // Mock metadata retrieval indicating chunked storage
            $multichain->shouldReceive('liststreamkeyitems')
                ->with(StreamEnums::FILE_METADATA->value, Mockery::any(), false, 1)
                ->once()
                ->andReturn([
                    [
                        'data' => [
                            'json' => [
                                'filename' => 'large.pdf',
                                'file_key' => 'PR-2025-001/pre-procurement/stage-01/large.pdf',
                                'data_txid' => 'chunk_tx_1',
                                'data_key' => 'PR-2025-001_pre-procurement_stage-01_large.pdf',
                                'mime_type' => 'application/pdf',
                                'size' => strlen($fullContent),
                                'hash' => $expectedHash,
                                'storage_method' => 'on_chain_chunked',
                                'stored_at' => now()->toIso8601String(),
                                'chunked' => true,
                                'total_chunks' => 2,
                                'chunk_txids' => ['chunk_tx_1', 'chunk_tx_2'],
                            ],
                        ],
                    ],
                ]);

            // Mock chunk retrieval
            $multichain->shouldReceive('liststreamkeyitems')
                ->with(StreamEnums::FILE_CHUNKS->value, Mockery::on(fn ($key) => str_contains($key, 'chunk_0')), false, 1)
                ->once()
                ->andReturn([['data' => bin2hex($chunk1)]]);

            $multichain->shouldReceive('liststreamkeyitems')
                ->with(StreamEnums::FILE_CHUNKS->value, Mockery::on(fn ($key) => str_contains($key, 'chunk_1')), false, 1)
                ->once()
                ->andReturn([['data' => bin2hex($chunk2)]]);

            $result = $retriever->retrieveFile('PR-2025-001/pre-procurement/stage-01/large.pdf');

            expect($result['content'])->toBe($fullContent)
                ->and($result['storage_method'])->toBe('on_chain_chunked')
                ->and($result['total_chunks'])->toBe(2)
                ->and($result['hash'])->toBe($expectedHash);
        });
    });

    describe('retrieveFile - hash verification', function () {
        it('logs warning on hash mismatch for single file', function () {
            $multichain = Mockery::mock(Manager::class);
            $retriever = new FileRetriever($multichain);

            $testContent = 'File content';
            $testHex = bin2hex($testContent);

            $multichain->shouldReceive('liststreamkeyitems')
                ->with(StreamEnums::FILE_METADATA->value, Mockery::any(), false, 1)
                ->once()
                ->andReturn([
                    [
                        'data' => [
                            'json' => [
                                'filename' => 'test.pdf',
                                'file_key' => 'PR-2025-001/test.pdf',
                                'data_txid' => 'data_tx_123',
                                'data_key' => 'PR-2025-001_test.pdf',
                                'mime_type' => 'application/pdf',
                                'size' => strlen($testContent),
                                'hash' => 'wrong_hash_value',
                                'storage_method' => 'on_chain',
                                'stored_at' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            $multichain->shouldReceive('getstreamitem')
                ->once()
                ->andReturn(['data' => $testHex]);

            $result = $retriever->retrieveFile('PR-2025-001/test.pdf');

            // Should still return the file content despite hash mismatch
            expect($result['content'])->toBe($testContent);

            Log::shouldHaveReceived('warning')
                ->withArgs(fn ($msg) => str_contains($msg, 'hash mismatch'));
        });
    });
});
