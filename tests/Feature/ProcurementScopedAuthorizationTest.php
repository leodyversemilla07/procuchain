<?php

use App\Models\User;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'view procurement',
        'create procurement',
        'edit procurement',
        'manage procurements',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $bacSecretariatRole->givePermissionTo([
        'view procurement',
        'create procurement',
        'edit procurement',
        'manage procurements',
    ]);

    $this->secretariat = User::factory()->create([
        'blockchain_address' => 'secretariat-address',
    ])->assignRole('bac_secretariat');
});

describe('procurement-scoped authorization', function () {
    it('forbids inaccessible procurement initiation pages for bac secretariat', function () {
        denyProcurementAccess('PR-2025-999-0001');

        $this->actingAs($this->secretariat)
            ->get(route('bac-secretariat.procurement.initiation.show', ['pr_number' => 'PR-2025-999-0001']))
            ->assertForbidden();
    });

    it('forbids inaccessible stage pages for bac secretariat', function () {
        denyProcurementAccess('PR-2025-999-0001');

        $this->actingAs($this->secretariat)
            ->get(route('bac-secretariat.procurement.pre-procurement.show', [
                'pr_number' => 'PR-2025-999-0001',
                'stage' => 'pre_procurement_conference',
            ]))
            ->assertForbidden();
    });

    it('forbids inaccessible verification pages for bac secretariat', function () {
        denyProcurementAccess('PR-2025-999-0001');

        $this->actingAs($this->secretariat)
            ->get(route('procurement.verification', ['pr_number' => 'PR-2025-999-0001']))
            ->assertForbidden();
    });

    it('forbids inaccessible procurement correction pages for bac secretariat', function () {
        denyProcurementAccess('PR-2025-999-0001');

        $this->actingAs($this->secretariat)
            ->get(route('procurements.corrections.show', ['pr_number' => 'PR-2025-999-0001']))
            ->assertForbidden();
    });

    it('forbids archiving inaccessible procurements for bac secretariat', function () {
        denyProcurementAccess('PR-2025-999-0001');

        $this->actingAs($this->secretariat)
            ->post(route('procurement.archive', ['pr_number' => 'PR-2025-999-0001']))
            ->assertForbidden();
    });
});

function denyProcurementAccess(string $prNumber): void
{
    $dataService = Mockery::mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->atLeast()->once()
        ->with($prNumber)
        ->andReturn(collect([
            ['user_address' => 'different-address'],
        ]));

    app()->instance(ProcurementDataService::class, $dataService);
}

function scopedProcurementFixture(string $prNumber, string $userId): array
{
    return [
        'pr_number' => $prNumber,
        'title' => 'Scoped Fixture',
        'description' => 'Fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => $userId,
        'created_at' => now()->toIso8601String(),
    ];
}
