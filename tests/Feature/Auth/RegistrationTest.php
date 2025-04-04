<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register with different roles', function () {
    // Test registration with bac_secretariat role
    $response = $this->post('/register', [
        'name' => 'Secretariat User',
        'email' => 'secretariat@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'bac_secretariat',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('bac-secretariat.dashboard', absolute: false));

    // Log out the current user before testing the next one
    $this->post('/logout');

    // Test registration with bac_chairman role
    $response = $this->post('/register', [
        'name' => 'Chairman User',
        'email' => 'chairman@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'bac_chairman',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('bac-chairman.dashboard', absolute: false));

    // Log out the current user before testing the next one
    $this->post('/logout');

    // Test registration with hope role
    $response = $this->post('/register', [
        'name' => 'Hope User',
        'email' => 'hope@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'hope',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('hope.dashboard', absolute: false));
});

test('users with default role can register', function () {
    // If you have a default role when no role is specified, test that scenario
    $response = $this->post('/register', [
        'name' => 'Default User',
        'email' => 'default@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    // Assert that the user is redirected to the appropriate dashboard
    // Assuming 'bac_secretariat' is the default role
    $response->assertRedirect(route('bac-secretariat.dashboard', absolute: false));
});
