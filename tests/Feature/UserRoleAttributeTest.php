<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Roles are auto-seeded by TestCase.php for all tests using RefreshDatabase

it('returns the primary role as primary_role attribute', function (): void {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

    expect($user->primary_role)->toBe('bac_secretariat');
});

it('returns the first role when user has multiple roles', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->assignRole('bac_secretariat');

    expect($user->primary_role)->toBe('admin');
});

it('returns null when user has no roles', function (): void {
    $user = User::factory()->create();

    expect($user->primary_role)->toBeNull();
});

it('includes primary_role attribute in array serialization', function (): void {
    $user = User::factory()->create();
    $user->assignRole('bac_chairman');

    $userArray = $user->toArray();

    expect($userArray)->toHaveKey('primary_role');
    expect($userArray['primary_role'])->toBe('bac_chairman');
});

it('includes primary_role attribute in json serialization', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $userJson = $user->toJson();
    $decoded = json_decode($userJson, true);

    expect($decoded)->toHaveKey('primary_role');
    expect($decoded['primary_role'])->toBe('admin');
});
