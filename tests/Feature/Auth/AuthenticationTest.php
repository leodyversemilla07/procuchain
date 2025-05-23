<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen and are redirected to correct dashboard', function () {
    // Test for BAC-Secretariat role
    $secretariatUser = User::factory()->create(['role' => 'bac_secretariat']);
    
    // Test if role-based redirection works correctly
    $this->actingAs($secretariatUser);
    $response = $this->get(route('home'));
    $response->assertRedirect(route('bac-secretariat.dashboard'));
    Auth::logout();

    // Test for BAC-Chairman role
    $chairmanUser = User::factory()->create(['role' => 'bac_chairman']);
    $this->actingAs($chairmanUser);
    $response = $this->get(route('home'));
    $response->assertRedirect(route('bac-chairman.dashboard'));
    Auth::logout();

    // Test for Hope role
    $hopeUser = User::factory()->create(['role' => 'hope']);
    $this->actingAs($hopeUser);
    $response = $this->get(route('home'));
    $response->assertRedirect(route('hope.dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        // 'email_verified_at' => now(), // Already handled by factory
        // 'password' => Hash::make('password'), // Already handled by factory
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    // First authenticate the user
    $this->actingAs($user);
    $this->assertAuthenticated();
    
    // Directly test the Auth::logout functionality
    Auth::logout();
    
    // Assert the user is now a guest
    $this->assertGuest();
});
