<?php

namespace Tests\Feature\Admin; // <--- 1. ADD THIS LINE

use App\Models\User;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery; // <--- 2. This line is now valid and necessary!
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an admin user for testing with 2FA enabled
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create([
        'email' => 'admin@test.com',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->admin->assignRole('admin');
});

test('admin can access user management page', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/users');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($assert) => $assert
            ->component('admin/user-management')
            ->has('users')
            ->has('roles')
    );
});

test('admin can create new user', function () {
    // Create the role first
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

    // Mock the Manager service
    $managerMock = Mockery::mock(Manager::class);
    $managerMock->shouldReceive('getnewaddress')
        ->once()
        ->andReturn('1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2');
    $this->app->instance(Manager::class, $managerMock);

    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'bac_secretariat',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        // blockchain_address will be auto-generated
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/users', $userData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    expect($user->hasRole('bac_secretariat'))->toBeTrue();
    expect($user->blockchain_address)->not->toBeNull(); // Should have auto-generated address
});

test('admin can update existing user', function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);
    $user->assignRole('bac_secretariat');

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
    ]);

    $user->refresh();
    expect($user->hasRole('bac_chairman'))->toBeTrue();
    expect($user->hasRole('bac_secretariat'))->toBeFalse();
});

test('admin can delete user', function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

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

    $response->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
    ]);
});

test('non admin cannot access user management', function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

    $response = $this->actingAs($user)
        ->get('/admin/users');

    $response->assertStatus(403);
});

test('guest cannot access user management', function () {
    $response = $this->get('/admin/users');

    $response->assertRedirect('/login');
});