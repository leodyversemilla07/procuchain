<?php

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Enums\StreamEnums;
use App\Enums\UserRoleEnums;
use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Libraries\MultichainClient;
use App\Models\User;
use App\Services\BlockchainService;
use App\Services\FileStorageService;
use App\Services\MultichainService;
use App\Services\NotificationService;
use App\Services\StreamKeyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery;

beforeEach(function () {
    Storage::fake('spaces');

    // Mock stream key service
    $streamKeyService = mock(StreamKeyService::class);
    $streamKeyService->shouldReceive('generate')
        ->andReturn('test-stream-key');

    // Create a proper MultichainService mock
    $multichainService = Mockery::mock(MultichainService::class);
    
    $this->blockchainService = new BlockchainService(
        $multichainService,
        $streamKeyService
    );

    $this->fileStorageService = new FileStorageService();
    $this->notificationService = mock(NotificationService::class)->makePartial();
    
    $this->handler = new ProcurementInitiationHandler(
        $this->blockchainService,
        $this->fileStorageService,
        $this->notificationService
    );

    $this->user = User::factory()->create([
        'role' => UserRoleEnums::BAC_SECRETARIAT->value,
        'blockchain_address' => 'test-blockchain-address'
    ]);

    Auth::login($this->user);

    // Setup blockchain service expectations
    $multichainService->shouldReceive('validateAddress')
        ->with('test-blockchain-address')
        ->andReturn(true);

    $multichainService->shouldReceive('publishMultiFrom')
        ->withArgs(function ($address, $stream, $items) {
            return $address === 'test-blockchain-address' 
                && $stream === StreamEnums::DOCUMENTS->value 
                && is_array($items);
        })
        ->andReturnNull();

    $multichainService->shouldReceive('publishFrom')
        ->withAnyArgs()
        ->andReturnNull();
});

test('successfully handles procurement initiation with valid data', function () {
    // Prepare test data
    $procurementId = 'PROC-2025-001';
    $procurementTitle = 'Test Procurement';
    $file = UploadedFile::fake()->create('document.pdf', 100);
    $documentType = 'Requirements_Document';
    $metadata = [
        ['document_type' => $documentType, 'submission_date' => now()->toDateString()]
    ];

    // Expected file path construction matching BaseStageHandler implementation
    $sanitizedTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $procurementTitle);
    $expectedFilePath = trim("$procurementId-{$sanitizedTitle}/".StageEnums::PROCUREMENT_INITIATION->getStoragePathSegment()."/$documentType.pdf", '/');

    // Mock notification service
    $this->notificationService
        ->shouldReceive('notifyStageUpdate')
        ->once()
        ->withArgs(function ($id, $title, $stage, $status, $timestamp, $count, $submitted) 
            use ($procurementId, $procurementTitle) {
            return $id === $procurementId 
                && $title === $procurementTitle 
                && $stage === StageEnums::PROCUREMENT_INITIATION->getDisplayName()
                && $status === StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName()
                && $count === 1
                && $submitted === true;
        });

    $request = request()->merge([
        'procurement_id' => $procurementId,
        'procurement_title' => $procurementTitle,
        'files' => [$file],
        'metadata' => $metadata
    ]);

    $result = $this->handler->handle($request);

    // Verify file was uploaded
    Storage::disk('spaces')->assertExists($expectedFilePath);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message')
        ->and($result['message'])->toContain('documents published successfully');
});

test('fails gracefully when file upload fails', function () {
    // Mock Storage facade directly
    Storage::shouldReceive('disk')
        ->with('spaces')
        ->andReturn(mock(\Illuminate\Contracts\Filesystem\Filesystem::class));

    Storage::disk('spaces')->shouldReceive('put')
        ->andThrow(new Exception('Storage error'));

    $procurementId = 'PROC-2025-001';
    $procurementTitle = 'Test Procurement';
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $request = request()->merge([
        'procurement_id' => $procurementId,
        'procurement_title' => $procurementTitle,
        'files' => [$file],
        'metadata' => [['document_type' => 'Requirements Document']]
    ]);

    $result = $this->handler->handle($request);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message')
        ->and($result['message'])->toContain('Failed to publish');
});

test('fails when user has no blockchain address', function () {
    $this->user->blockchain_address = null;
    $this->user->save();

    $request = request()->merge([
        'procurement_id' => 'PROC-2025-001',
        'procurement_title' => 'Test Procurement',
        'files' => [UploadedFile::fake()->create('document.pdf', 100)],
        'metadata' => [['document_type' => 'Requirements Document']]
    ]);

    $result = $this->handler->handle($request);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message')
        ->and($result['message'])->toContain('Blockchain address not set');
});

test('requires all mandatory fields', function () {
    $request = request()->merge([
        'procurement_id' => '',
        'procurement_title' => '',
        'files' => [],
        'metadata' => []
    ]);

    $result = $this->handler->handle($request);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message')
        ->and($result['message'])->toContain('Failed to publish');
});