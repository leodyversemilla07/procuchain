<?php

use App\Models\User;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

it('forbids bac secretariat from verifying inaccessible documents by File key', function () {
    bindLockedDocumentAccess('locked-File.pdf', 'PR-2025-998-0002');

    $this->actingAs($this->secretariat)
        ->post(route('documents.verify', ['fileKey' => 'locked-File.pdf']))
        ->assertForbidden();
});

it('forbids bac secretariat from correcting inaccessible documents by txid', function () {
    $txid = str_repeat('a', 64);
    bindLockedDocumentAccess($txid, 'PR-2025-998-0002', true);

    $this->actingAs($this->secretariat)
        ->post(route('documents.correct', ['document' => $txid]), [
            'correction_reason' => 'Correcting inaccessible document',
            'correction_type' => 'invalidate',
            'pr_number' => 'PR-2025-998-0002',
            'procurement_title' => 'Locked Procurement',
            'original_document_hash' => 'hash',
            'original_txid' => $txid,
        ])
        ->assertForbidden();
});

function bindLockedDocumentAccess(string $reference, string $prNumber, bool $isTxid = false): void
{
    $fixture = $isTxid ? null : lockedDocumentFixture($reference, $prNumber);

    $dataService = new class($reference, $prNumber, $fixture) extends ProcurementDataService
    {
        public function __construct(
            private readonly string $reference,
            private readonly string $prNumber,
            private readonly ?array $fixture,
        ) {}

        public function getDocumentDataByfileKey(string $fileKey): ?array
        {
            return $this->fixture;
        }

        public function fetchStatusItems(string $prNumber): Collection
        {
            return collect([
                ['user_address' => 'different-address'],
            ]);
        }

        public function validateDocumentExistsInBlockchain(string $fileKey): ?array
        {
            return null;
        }
    };

    app()->instance(ProcurementDataService::class, $dataService);
}

function lockedDocumentFixture(string $fileKey, string $prNumber, string $txid = 'data-txid'): array
{
    return [
        'pr_number' => $prNumber,
        'procurement_title' => 'Locked Procurement',
        'user_address' => 'different-address',
        'stage' => 'procurement_initiation',
        'status' => 'draft',
        'document_type' => 'test_document',
        'file_key' => $fileKey,
        'filename' => 'test.pdf',
        'file_size' => 1000,
        'mime_type' => 'application/pdf',
        'hash' => 'hash',
        'data_txid' => $txid,
        'metadata_txid' => 'metadata-txid',
        'uploaded_by' => 'System',
        'timestamp' => now()->toIso8601String(),
    ];
}

function lockedProcurementFixture(string $prNumber): array
{
    return [
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
    ];
}
