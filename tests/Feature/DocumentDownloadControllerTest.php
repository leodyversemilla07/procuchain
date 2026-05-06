<?php

use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'view documents',
        'download documents',
        'view procurement',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $bacSecretariatRole->givePermissionTo([
        'view documents',
        'download documents',
        'view procurement',
    ]);
});

it('forbids bac secretariat from downloading inaccessible procurement documents', function () {
    $user = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ]);
    $user->assignRole('bac_secretariat');

    $dataService = Mockery::mock(ProcurementDataService::class);
    $dataService->shouldReceive('getDocumentDataByFileKey')
        ->once()
        ->with('locked-file.pdf')
        ->andReturn([
            'pr_number' => 'PR-LOCKED',
        ]);
    $dataService->shouldReceive('fetchStatusItems')
        ->once()
        ->with('PR-LOCKED')
        ->andReturn(collect([
            ['user_address' => 'different-address'],
        ]));
    app()->instance(ProcurementDataService::class, $dataService);

    $repository = Mockery::mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->once()
        ->with('PR-LOCKED')
        ->andReturn(downloadLockedProcurementFixture());
    app()->instance(ProcurementRepository::class, $repository);

    $this->actingAs($user)
        ->get('/files/locked-file.pdf')
        ->assertForbidden();
});

function downloadLockedProcurementFixture(): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => 'PR-LOCKED',
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
