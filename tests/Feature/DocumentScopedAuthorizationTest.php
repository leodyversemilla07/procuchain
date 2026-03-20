<?php

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'view documents',
        'download documents',
        'view procurement',
        'edit procurement',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $bacSecretariatRole->givePermissionTo([
        'view documents',
        'download documents',
        'view procurement',
        'edit procurement',
    ]);

    $this->secretariat = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ])->assignRole('bac_secretariat');
});

it('forbids bac secretariat from verifying inaccessible documents by file key', function () {
    bindLockedDocumentAccess('locked-file.pdf', 'PR-LOCKED');

    $this->actingAs($this->secretariat)
        ->post(route('documents.verify', ['fileKey' => 'locked-file.pdf']))
        ->assertForbidden();
});

it('forbids bac secretariat from correcting inaccessible documents by txid', function () {
    $txid = str_repeat('a', 64);
    bindLockedDocumentAccess($txid, 'PR-LOCKED', true);

    $this->actingAs($this->secretariat)
        ->post(route('documents.correct', ['document' => $txid]), [
            'correction_reason' => 'Correcting inaccessible document',
            'correction_type' => 'invalidate',
            'pr_number' => 'PR-LOCKED',
            'procurement_title' => 'Locked Procurement',
            'original_document_hash' => 'hash',
            'original_txid' => $txid,
        ])
        ->assertForbidden();
});

function bindLockedDocumentAccess(string $reference, string $prNumber, bool $isTxid = false): void
{
    $dataService = \Mockery::mock(ProcurementDataService::class);
    $dataService->shouldReceive('getDocumentDataByFileKey')
        ->zeroOrMoreTimes()
        ->andReturn($isTxid ? null : lockedDocumentFixture($reference, $prNumber)->toBlockchainArray());
    $dataService->shouldReceive('fetchStatusItems')
        ->once()
        ->with($prNumber)
        ->andReturn(collect([
            ['user_address' => 'different-address'],
        ]));
    app()->instance(ProcurementDataService::class, $dataService);

    $documentRepository = \Mockery::mock(DocumentRepository::class);
    $documentRepository->shouldReceive('findByFileKey')
        ->once()
        ->with($reference)
        ->andReturn($isTxid ? null : lockedDocumentFixture($reference, $prNumber));
    $documentRepository->shouldReceive('findByTxid')
        ->zeroOrMoreTimes()
        ->andReturn($isTxid ? lockedDocumentFixture('locked-file.pdf', $prNumber, $reference) : null);
    app()->instance(DocumentRepository::class, $documentRepository);

    $procurementRepository = \Mockery::mock(ProcurementRepository::class);
    $procurementRepository->shouldReceive('findByProcurement')
        ->once()
        ->with($prNumber)
        ->andReturn(lockedProcurementFixture($prNumber));
    app()->instance(ProcurementRepository::class, $procurementRepository);
}

function lockedDocumentFixture(string $fileKey, string $prNumber, string $txid = 'data-txid'): DocumentData
{
    return new DocumentData(
        prNumber: $prNumber,
        procurementTitle: 'Locked Procurement',
        userAddress: 'different-address',
        stage: 'procurement_initiation',
        status: 'draft',
        documentType: 'test_document',
        fileKey: $fileKey,
        fileName: 'test.pdf',
        fileSize: 1000,
        mimeType: 'application/pdf',
        hash: 'hash',
        dataTxid: $txid,
        metadataTxid: 'metadata-txid',
        uploadedBy: 'System',
        timestamp: Carbon::now(),
    );
}

function lockedProcurementFixture(string $prNumber): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => $prNumber,
        'title' => 'Locked Procurement',
        'description' => 'Fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => '999',
        'created_at' => now()->toIso8601String(),
    ]);
}
