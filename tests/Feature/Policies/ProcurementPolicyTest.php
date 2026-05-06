<?php

use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage procurements', 'guard_name' => 'web']);
    Role::findByName('bac_secretariat', 'web')->givePermissionTo('manage procurements');
    Role::findByName('admin', 'web')->givePermissionTo('manage procurements');

    $this->secretariat = User::factory()->create()->assignRole('bac_secretariat');
    $this->chairman = User::factory()->create()->assignRole('bac_chairman');
    $this->hope = User::factory()->create()->assignRole('hope');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->guest = User::factory()->create();
});

describe('ProcurementPolicy', function () {
    it('applies base procurement abilities by role', function () {
        $matrix = [
            ['ability' => 'view-procurement', 'users' => [$this->secretariat, $this->chairman, $this->hope, $this->admin], 'expected' => true],
            ['ability' => 'view-procurement', 'users' => [$this->guest], 'expected' => false],
            ['ability' => 'create-procurement', 'users' => [$this->secretariat], 'expected' => true],
            ['ability' => 'create-procurement', 'users' => [$this->chairman, $this->hope, $this->guest], 'expected' => false],
            ['ability' => 'archive-procurement', 'users' => [$this->secretariat, $this->admin], 'expected' => true],
            ['ability' => 'archive-procurement', 'users' => [$this->chairman, $this->hope, $this->guest], 'expected' => false],
            ['ability' => 'restore-procurement', 'users' => [$this->secretariat, $this->admin], 'expected' => true],
            ['ability' => 'restore-procurement', 'users' => [$this->chairman, $this->hope], 'expected' => false],
            ['ability' => 'correct-procurement', 'users' => [$this->secretariat, $this->admin], 'expected' => true],
            ['ability' => 'correct-procurement', 'users' => [$this->chairman, $this->hope], 'expected' => false],
            ['ability' => 'approve-procurement', 'users' => [$this->chairman, $this->hope, $this->admin], 'expected' => true],
            ['ability' => 'approve-procurement', 'users' => [$this->secretariat], 'expected' => false],
            ['ability' => 'publish-procurement', 'users' => [$this->secretariat, $this->admin], 'expected' => true],
            ['ability' => 'publish-procurement', 'users' => [$this->chairman, $this->hope], 'expected' => false],
        ];

        foreach ($matrix as $case) {
            foreach ($case['users'] as $user) {
                expect($user->can($case['ability']))->toBe($case['expected']);
            }
        }
    });

    it('allows bac secretariat to view their own procurement by pr number', function () {
        $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

        bindScopedProcurementPolicyStubs(
            prNumber: 'PR-OWNED',
            procurementUserId: (string) $this->secretariat->id,
            touchedAddress: null,
        );

        expect($this->secretariat->can('view-procurement', 'PR-OWNED'))->toBeTrue();
    });

    it('allows bac secretariat to view a procurement they interacted with', function () {
        $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

        bindScopedProcurementPolicyStubs(
            prNumber: 'PR-TOUCHED',
            procurementUserId: '999',
            touchedAddress: 'secretariat-address',
        );

        expect($this->secretariat->can('view-procurement', 'PR-TOUCHED'))->toBeTrue();
    });

    it('denies inaccessible scoped procurement abilities for bac secretariat', function () {
        $this->secretariat->forceFill(['blockchain_address' => 'secretariat-address'])->save();

        foreach (['view-procurement', 'archive-procurement', 'restore-procurement', 'correct-procurement'] as $ability) {
            bindScopedProcurementPolicyStubs(
                prNumber: 'PR-BLOCKED',
                procurementUserId: '999',
                touchedAddress: 'different-address',
            );

            expect($this->secretariat->can($ability, 'PR-BLOCKED'))->toBeFalse();
        }
    });

    it('enforces archive route authorization', function () {
        $this->actingAs($this->chairman)
            ->post('/procurement/PR-2026-001/archive')
            ->assertForbidden();

        $this->actingAs($this->hope)
            ->post('/procurement/PR-2026-001/archive')
            ->assertForbidden();

        $this->post('/logout');

        $this->post('/procurement/PR-2026-001/archive')
            ->assertRedirect('/login');
    });
});

function bindScopedProcurementPolicyStubs(string $prNumber, string $procurementUserId, ?string $touchedAddress): void
{
    $repository = Mockery::mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->once()
        ->with($prNumber)
        ->andReturn(policyProcurementFixture($prNumber, $procurementUserId));

    $dataService = Mockery::mock(ProcurementDataService::class);

    if ($touchedAddress === null) {
        $dataService->shouldNotReceive('fetchStatusItems');
    } else {
        $dataService->shouldReceive('fetchStatusItems')
            ->once()
            ->with($prNumber)
            ->andReturn(collect([
                ['user_address' => $touchedAddress],
            ]));
    }

    app()->instance(ProcurementRepository::class, $repository);
    app()->instance(ProcurementDataService::class, $dataService);
}

function policyProcurementFixture(string $prNumber, string $userId): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => $prNumber,
        'title' => 'Policy Fixture',
        'description' => 'Fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => $userId,
        'created_at' => now()->toIso8601String(),
    ]);
}
