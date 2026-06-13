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
    $permissions = [
        'view documents',
        'download documents',
        'upload documents',
        'view procurement',
        'edit procurement',
        'approve procurement',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $bacSecretariatRole->givePermissionTo([
        'view documents', 'download documents', 'upload documents', 'view procurement', 'edit procurement',
    ]);

    $bacChairmanRole = Role::firstOrCreate(['name' => 'bac_chairman']);
    $bacChairmanRole->givePermissionTo([
        'view documents', 'download documents', 'view procurement', 'approve procurement',
    ]);

    $hopeRole = Role::firstOrCreate(['name' => 'hope']);
    $hopeRole->givePermissionTo([
        'view documents', 'download documents', 'view procurement', 'approve procurement',
    ]);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo([
        'view documents', 'download documents', 'upload documents',
        'view procurement', 'edit procurement', 'approve procurement',
    ]);

    $this->secretariat = User::factory()->create()->assignRole('bac_secretariat');
    $this->chairman = User::factory()->create()->assignRole('bac_chairman');
    $this->hope = User::factory()->create()->assignRole('hope');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->guest = User::factory()->create(); // no role, no permissions
});

describe('DocumentPolicy', function () {
    describe('view-document', function () {
        it('allows bac_secretariat to view documents', function () {
            expect($this->secretariat->can('view-document'))->toBeTrue();
        });

        it('allows bac_chairman to view documents', function () {
            expect($this->chairman->can('view-document'))->toBeTrue();
        });

        it('allows hope to view documents', function () {
            expect($this->hope->can('view-document'))->toBeTrue();
        });

        it('allows admin to view documents', function () {
            expect($this->admin->can('view-document'))->toBeTrue();
        });

        it('denies users with no role from viewing documents', function () {
            expect($this->guest->can('view-document'))->toBeFalse();
        });

        it('denies bac_secretariat from viewing inaccessible procurement documents', function () {
            $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

            bindInaccessibleDocumentContext($this, 'locked-File', 'PR-2025-998-0004');

            expect($this->secretariat->can('view-document', 'locked-File'))->toBeFalse();
        });
    });

    describe('download-document', function () {
        it('allows bac_secretariat to download', function () {
            expect($this->secretariat->can('download-document'))->toBeTrue();
        });

        it('allows bac_chairman to download', function () {
            expect($this->chairman->can('download-document'))->toBeTrue();
        });

        it('allows hope to download', function () {
            expect($this->hope->can('download-document'))->toBeTrue();
        });

        it('allows admin to download', function () {
            expect($this->admin->can('download-document'))->toBeTrue();
        });

        it('denies users with no role from downloading', function () {
            expect($this->guest->can('download-document'))->toBeFalse();
        });

        it('denies bac_secretariat from downloading inaccessible procurement documents', function () {
            $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

            bindInaccessibleDocumentContext($this, 'locked-File', 'PR-2025-998-0004');

            expect($this->secretariat->can('download-document', 'locked-File'))->toBeFalse();
        });
    });

    describe('upload-document', function () {
        it('allows bac_secretariat to upload', function () {
            expect($this->secretariat->can('upload-document'))->toBeTrue();
        });

        it('allows admin to upload', function () {
            expect($this->admin->can('upload-document'))->toBeTrue();
        });

        it('denies bac_chairman from uploading (read-only oversight role)', function () {
            expect($this->chairman->can('upload-document'))->toBeFalse();
        });

        it('denies hope from uploading (read-only oversight role)', function () {
            expect($this->hope->can('upload-document'))->toBeFalse();
        });
    });

    describe('correct-document', function () {
        it('allows bac_secretariat to correct documents', function () {
            expect($this->secretariat->can('correct-document'))->toBeTrue();
        });

        it('allows bac_chairman to correct documents (oversight approval role)', function () {
            expect($this->chairman->can('correct-document'))->toBeTrue();
        });

        it('allows hope to correct documents (oversight approval role)', function () {
            expect($this->hope->can('correct-document'))->toBeTrue();
        });

        it('allows admin to correct documents', function () {
            expect($this->admin->can('correct-document'))->toBeTrue();
        });

        it('denies users with no role from correcting documents', function () {
            expect($this->guest->can('correct-document'))->toBeFalse();
        });

        it('denies bac_secretariat from correcting inaccessible procurement documents', function () {
            $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

            bindInaccessibleDocumentContext($this, str_repeat('a', 64), 'PR-2025-998-0004', true);

            expect($this->secretariat->can('correct-document', str_repeat('a', 64)))->toBeFalse();
        });
    });

    describe('download route authorization (HTTP)', function () {
        it('redirects unauthenticated users to login when accessing a File', function () {
            $this->get('/BlockchainFiles/some-File-key.pdf')
                ->assertRedirect('/login');
        });

        it('returns 403 when a user without download permission tries to download', function () {
            $this->actingAs($this->guest)
                ->get('/BlockchainFiles/some-File-key.pdf')
                ->assertForbidden();
        });
    });
});

function bindInaccessibleDocumentContext($testCase, string $reference, string $prNumber, bool $isTxid = false): void
{
    $dataService = Mockery::mock(ProcurementDataService::class);
    $dataService->shouldReceive('getDocumentDataByfileKey')
        ->zeroOrMoreTimes()
        ->andReturn($isTxid ? null : [
            'pr_number' => $prNumber,
        ]);
    $dataService->shouldReceive('fetchStatusItems')
        ->once()
        ->with($prNumber)
        ->andReturn(collect([
            ['user_address' => 'different-address'],
        ]));

    $documentRepository = Mockery::mock(DocumentRepository::class);
    $documentRepository->shouldReceive('findByfileKey')
        ->once()
        ->with($reference)
        ->andReturn($isTxid ? null : inaccessibleDocumentFixture($reference, $prNumber));
    $documentRepository->shouldReceive('findByTxid')
        ->zeroOrMoreTimes()
        ->andReturn($isTxid ? inaccessibleDocumentFixture('locked-File.pdf', $prNumber, $reference) : null);

    $repository = Mockery::mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->once()
        ->with($prNumber)
        ->andReturn(inaccessibleDocumentProcurementFixture($prNumber));

    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(DocumentRepository::class, $documentRepository);
    app()->instance(ProcurementRepository::class, $repository);
}

function inaccessibleDocumentProcurementFixture(string $prNumber): ProcurementData
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

function inaccessibleDocumentFixture(string $fileKey, string $prNumber, string $txid = 'metadata-txid'): DocumentData
{
    return new DocumentData(
        prNumber: $prNumber,
        procurementTitle: 'Locked Procurement',
        userAddress: 'different-address',
        stage: 'procurement_initiation',
        status: 'draft',
        documentType: 'test_document',
        fileKey: $fileKey,
        filename: 'test.pdf',
        fileSize: 1000,
        mimeType: 'application/pdf',
        hash: 'hash',
        dataTxid: $txid,
        metadataTxid: 'metadata-txid',
        uploadedBy: 'System',
        timestamp: Carbon::now(),
    );
}
