<?php

use App\Models\User;
use App\Services\IntegrityVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('does not run integrity verification while rendering the breaches index', function () {
    $this->mock(IntegrityVerificationService::class, function ($mock) {
        $mock->shouldNotReceive('verifyAndRepair');
    });

    $this->actingAs($this->admin)
        ->get('/admin/integrity-breaches')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('admin/integrity-breaches')
            ->has('breaches')
            ->has('verificationStatus')
        );
});
