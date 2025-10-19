<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    // Create roles if they don't exist
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('bac_secretariat');
});

describe('ProcurementListController', function () {
    describe('indexProcurementsList', function () {
        it('returns procurements list page for bac secretariat', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements-list.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for bac chairman', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-chairman.procurements-list.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for hope', function () {
            $hope = User::factory()->create();
            $hope->assignRole('hope');
            actingAs($hope);

            get(route('hope.procurements-list.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('returns procurements list page for admin', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');
            actingAs($admin);

            get(route('admin.procurements-list.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/procurements-list')
                );
        });

        it('passes procurements data to view', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements-list.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('procurements')
                );
        });
    });

    describe('showProcurement', function () {
        it('shows single procurement for bac secretariat', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements.show', ['id' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for bac chairman', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-chairman.procurements.show', ['id' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for hope', function () {
            $hope = User::factory()->create();
            $hope->assignRole('hope');
            actingAs($hope);

            get(route('hope.procurements.show', ['id' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('shows single procurement for admin', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');
            actingAs($admin);

            get(route('admin.procurements.show', ['id' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });

        it('loads procurement show page successfully', function () {
            actingAs($this->user);

            get(route('bac-secretariat.procurements.show', ['id' => 'TEST-001']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('procurements/show-procurement')
                );
        });
    });

    describe('authorization', function () {
        it('requires authentication to view procurements list', function () {
            get(route('bac-secretariat.procurements-list.index'))
                ->assertRedirect(route('login'));
        });

        it('requires authentication to view single procurement', function () {
            get(route('bac-secretariat.procurements.show', ['id' => 'TEST-001']))
                ->assertRedirect(route('login'));
        });

        it('requires correct role to access bac secretariat procurements list', function () {
            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');
            actingAs($chairman);

            get(route('bac-secretariat.procurements-list.index'))
                ->assertForbidden();
        });

        it('requires correct role to access bac chairman procurements list', function () {
            actingAs($this->user); // bac_secretariat

            get(route('bac-chairman.procurements-list.index'))
                ->assertForbidden();
        });
    });
});
