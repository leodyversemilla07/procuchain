<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to login for all dashboards', function () {
    // Test Bids and Awards Committee Secretariat Dashboard
    $this->get(route('bac-secretariat.dashboard'))->assertRedirect('/login');

    // Test Bids and Awards Committee Chairman Dashboard
    $this->get(route('bac-chairman.dashboard'))->assertRedirect('/login');

    // Test Head of Procuring Entity Dashboard
    $this->get(route('hope.dashboard'))->assertRedirect('/login');

    // Test Admin dashboard
    $this->get(route('admin.dashboard'))->assertRedirect('/login');
});

test('users can access their role-specific dashboard', function () {
    // Test BAC Secretariat user
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $secretariatUser = User::factory()->create();
    $secretariatUser->assignRole('bac_secretariat');
    $this->actingAs($secretariatUser);
    $this->get(route('bac-secretariat.dashboard'))->assertOk();
    $this->post('/logout');

    // Test BAC Chairman user
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
    $chairmanUser = User::factory()->create();
    $chairmanUser->assignRole('bac_chairman');
    $this->actingAs($chairmanUser);
    $this->get(route('bac-chairman.dashboard'))->assertOk();
    $this->post('/logout');

    // Test Hope user
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $hopeUser = User::factory()->create();
    $hopeUser->assignRole('hope');
    $this->actingAs($hopeUser);
    $this->get(route('hope.dashboard'))->assertOk();
    $this->post('/logout');

    // Test Admin user
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    $this->actingAs($adminUser);
    $this->get(route('admin.dashboard'))->assertOk();
});

test('users cannot access dashboards for other roles', function () {
    // BAC Secretariat user cannot access other dashboards
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $secretariatUser = User::factory()->create();
    $secretariatUser->assignRole('bac_secretariat');
    $this->actingAs($secretariatUser);
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // BAC Chairman user cannot access other dashboards
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
    $chairmanUser = User::factory()->create();
    $chairmanUser->assignRole('bac_chairman');
    $this->actingAs($chairmanUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // Hope user cannot access other dashboards
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $hopeUser = User::factory()->create();
    $hopeUser->assignRole('hope');
    $this->actingAs($hopeUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->post('/logout');

    // Admin user cannot access other dashboards
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    $this->actingAs($adminUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
});

