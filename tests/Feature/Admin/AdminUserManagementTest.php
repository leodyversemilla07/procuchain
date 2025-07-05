<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an admin user for testing
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@test.com',
    ]);
});

test('admin can access user management page', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/users');

    $response->assertStatus(200);
    $response->assertInertia(
        fn($assert) => $assert
            ->component('admin/users')
            ->has('users')
            ->has('roles')
    );
});

test('admin can create new user', function () {
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'bac_secretariat',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'blockchain_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/users', $userData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'bac_secretariat',
    ]);
});

test('admin can update existing user', function () {
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
        'email' => 'original@example.com',
    ]);

    $updateData = [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'bac_chairman',
        'blockchain_address' => '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
    ];

    $response = $this->actingAs($this->admin)
        ->put("/admin/users/{$user->id}", $updateData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'bac_chairman',
    ]);
});

test('admin can delete user', function () {
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/users/{$user->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

test('admin cannot delete own account', function () {
    $response = $this->actingAs($this->admin)
        ->delete("/admin/users/{$this->admin->id}");

    $response->assertRedirect();
    $response->assertSessionHasErrors(['error']);

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
    ]);
});

test('non admin cannot access user management', function () {
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
    ]);

    $response = $this->actingAs($user)
        ->get('/admin/users');

    $response->assertStatus(403);
});

test('guest cannot access user management', function () {
    $response = $this->get('/admin/users');

    $response->assertRedirect('/login');
});
