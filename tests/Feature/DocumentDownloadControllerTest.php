<?php

use App\Models\User;
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
    $dataService->shouldReceive('getDocumentDataByfileKey')
        ->once()
        ->with('locked-File.pdf')
        ->andReturn([
            'pr_number' => 'PR-2025-998-0001',
        ]);
    $dataService->shouldReceive('fetchStatusItems')
        ->once()
        ->with('PR-2025-998-0001')
        ->andReturn(collect([
            ['user_address' => 'different-address'],
        ]));
    app()->instance(ProcurementDataService::class, $dataService);

    $this->actingAs($user)
        ->get('/files/locked-File.pdf')
        ->assertForbidden();
});

function downloadLockedProcurementFixture(): array
{
    return [
        'pr_number' => 'PR-2025-998-0001',
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
