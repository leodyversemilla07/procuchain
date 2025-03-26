<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to login for all dashboards', function () {
    // Test BAC Secretariat dashboard
    $this->get(route('bac-secretariat.dashboard'))->assertRedirect('/login');

    // Test BAC Chairman dashboard
    $this->get(route('bac-chairman.dashboard'))->assertRedirect('/login');

    // Test Hope dashboard
    $this->get(route('hope.dashboard'))->assertRedirect('/login');
});

test('users can access their role-specific dashboard', function () {
    // Test BAC Secretariat user
    $secretariatUser = User::factory()->create(['role' => 'bac_secretariat']);
    $this->actingAs($secretariatUser);
    $this->get(route('bac-secretariat.dashboard'))->assertOk();
    $this->post('/logout');

    // Test BAC Chairman user
    $chairmanUser = User::factory()->create(['role' => 'bac_chairman']);
    $this->actingAs($chairmanUser);
    $this->get(route('bac-chairman.dashboard'))->assertOk();
    $this->post('/logout');

    // Test Hope user
    $hopeUser = User::factory()->create(['role' => 'hope']);
    $this->actingAs($hopeUser);
    $this->get(route('hope.dashboard'))->assertOk();
});

test('users cannot access dashboards for other roles', function () {
    // BAC Secretariat user cannot access other dashboards
    $secretariatUser = User::factory()->create(['role' => 'bac_secretariat']);
    $this->actingAs($secretariatUser);
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // BAC Chairman user cannot access other dashboards
    $chairmanUser = User::factory()->create(['role' => 'bac_chairman']);
    $this->actingAs($chairmanUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('hope.dashboard'))->assertForbidden();
    $this->post('/logout');

    // Hope user cannot access other dashboards
    $hopeUser = User::factory()->create(['role' => 'hope']);
    $this->actingAs($hopeUser);
    $this->get(route('bac-secretariat.dashboard'))->assertForbidden();
    $this->get(route('bac-chairman.dashboard'))->assertForbidden();
});
