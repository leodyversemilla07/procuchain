<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Create test roles
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Role::create(['name' => 'bac_chairman', 'guard_name' => 'web']);
});

it('returns the primary role as role attribute', function (): void {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

    expect($user->role)->toBe('bac_secretariat');
});

it('returns the first role when user has multiple roles', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->assignRole('bac_secretariat');

    expect($user->role)->toBe('admin');
});

it('returns null when user has no roles', function (): void {
    $user = User::factory()->create();

    expect($user->role)->toBeNull();
});

it('includes role attribute in array serialization', function (): void {
    $user = User::factory()->create();
    $user->assignRole('bac_chairman');

    $userArray = $user->toArray();

    expect($userArray)->toHaveKey('role');
    expect($userArray['role'])->toBe('bac_chairman');
});

it('includes role attribute in json serialization', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $userJson = $user->toJson();
    $decoded = json_decode($userJson, true);

    expect($decoded)->toHaveKey('role');
    expect($decoded['role'])->toBe('admin');
});
