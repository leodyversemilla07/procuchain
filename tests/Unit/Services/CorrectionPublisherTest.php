<?php

use App\Repositories\CorrectionRepository;
use App\Services\Manager;
use App\Services\Publishers\CorrectionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->mockMultichain = Mockery::mock(Manager::class);
    $this->mockFileStorageMultichain = Mockery::mock(Manager::class);

    // Mock file storage multichain for uploadFile
    $this->mockFileStorageMultichain->shouldReceive('publish')
        ->andReturn('file_data_txid', 'file_metadata_txid');

    $this->fileStorage = new \App\Services\BlockchainStorageService($this->mockFileStorageMultichain);
    $this->repository = new CorrectionRepository($this->mockMultichain);
    $this->publisher = new CorrectionPublisher($this->repository, $this->fileStorage);
});

it('can publish a replacement correction', function () {
    // Mock the multichain publish call for correction
    $this->mockMultichain
        ->shouldReceive('publish')
        ->once()
        ->with(
            'procurement.corrections',
            'PR-2024-001',
            Mockery::on(function ($data) {
                return isset($data['json'])
                    && $data['json']['pr_number'] === 'PR-2024-001'
                    && $data['json']['correction_type'] === 'document_correction'
                    && $data['json']['action'] === 'replace';
            })
        )
        ->andReturn('correction_txid_123');

    // Create a fake uploaded file
    $file = UploadedFile::fake()->create('test.pdf', 1000);

    // Test the publishReplacement method
    $result = $this->publisher->publishReplacement(
        prNumber: 'PR-2024-001',
        procurementTitle: 'Test Procurement',
        originalTxid: 'test_txid_123',
        originalDocumentHash: 'test_hash_123',
        correctionType: 'document_correction',
        reason: 'Test correction',
        correctedBy: 'Test User',
        userAddress: 'test_address',
        correctedFile: $file
    );

    expect($result)->toBeArray();
    expect($result)->toHaveKey('correction_txid');
    expect($result['correction_txid'])->toBe('correction_txid_123');
});

it('can publish an invalidation correction', function () {
    // Mock the multichain publish call
    $this->mockMultichain
        ->shouldReceive('publish')
        ->once()
        ->with(
            'procurement.corrections',
            'PR-2024-001',
            Mockery::on(function ($data) {
                return isset($data['json'])
                    && $data['json']['pr_number'] === 'PR-2024-001'
                    && $data['json']['correction_type'] === 'status_correction'
                    && $data['json']['action'] === 'invalidate';
            })
        )
        ->andReturn('correction_txid_456');

    // Test the publishInvalidation method
    $result = $this->publisher->publishInvalidation(
        prNumber: 'PR-2024-001',
        procurementTitle: 'Test Procurement',
        originalTxid: 'test_txid_123',
        originalDocumentHash: 'test_hash_123',
        correctionType: 'status_correction',
        reason: 'Document invalid',
        correctedBy: 'Test User',
        userAddress: 'test_address',
        originalDocumentData: ['some' => 'data']
    );

    expect($result)->toBeArray();
    expect($result)->toHaveKey('correction_txid');
    expect($result['correction_txid'])->toBe('correction_txid_456');
});
