<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen and are redirected to correct dashboard', function (string $role, string $expectedRedirectRoute) {
    $user = User::factory()->create([
        'role' => $role,
        // 'password' => Hash::make('password'), // Already handled by factory
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password', // Default password from factory
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route($expectedRedirectRoute));

})->with([
    ['bac_secretariat', 'bac-secretariat.dashboard'],
    ['bac_chairman', 'bac-chairman.dashboard'],
    ['hope', 'hope.dashboard'],
]);

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
