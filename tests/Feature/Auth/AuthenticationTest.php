<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen and are redirected to correct dashboard', function () {
    // Test for BAC-Secretariat role - use bac_secretariat (with underscore) to match the database
    $secretariatUser = User::factory()->create([
        'role' => 'bac_secretariat'
    ]);

    $response = $this->post('/login', [
        'email' => $secretariatUser->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('bac-secretariat.dashboard', absolute: false));

    $this->post('/logout');

    // Test for BAC-Chairman role - use bac_chairman (with underscore) to match the database
    $chairmanUser = User::factory()->create([
        'role' => 'bac_chairman'
    ]);

    $response = $this->post('/login', [
        'email' => $chairmanUser->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('bac-chairman.dashboard', absolute: false));

    $this->post('/logout');

    // Test for Hope role
    $hopeUser = User::factory()->create([
        'role' => 'hope'
    ]);

    $response = $this->post('/login', [
        'email' => $hopeUser->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('hope.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
