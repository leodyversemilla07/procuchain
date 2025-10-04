<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user without two factor authentication is redirected', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertRedirect(route('two-factor.show'));
    $response->assertSessionHas('error', 'Two-factor authentication is required to access this page.');
});

test('user with two factor authentication can access protected routes', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'two_factor_secret' => 'secret',
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertStatus(200);
});

test('api request without two factor authentication returns json error', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)->getJson('/admin/dashboard');

    $response->assertStatus(403);
    $response->assertJson(['message' => 'Two-factor authentication is required.']);
});
