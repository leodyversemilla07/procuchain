<?php

use App\Enums\UserRoleEnums;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('allows users to enable two factor authentication', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnums::ADMIN->value);

    $response = $this->actingAs($user)->post('/settings/two-factor-authentication');

    $response->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_recovery_codes)->not->toBeNull();
});

it('allows two factor authentication to be disabled', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnums::ADMIN->value);

    // Enable 2FA
    $this->actingAs($user)->post('/settings/two-factor-authentication');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();

    // Disable 2FA
    $response = $this->actingAs($user)->delete('/settings/two-factor-authentication');

    $response->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_recovery_codes)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
});

it('requires authentication to enable two factor', function () {
    $response = $this->post('/settings/two-factor-authentication');

    $response->assertRedirect('/login');
});

it('allows qr code to be retrieved', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnums::ADMIN->value);

    // Enable 2FA first
    $this->actingAs($user)->post('/settings/two-factor-authentication');

    $user->refresh();

    // Get QR code
    $response = $this->actingAs($user)->get('/settings/two-factor-qr-code');

    $response->assertOk();
    $response->assertJsonStructure(['svg', 'url']);
});

it('allows recovery codes to be retrieved', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnums::ADMIN->value);

    // Enable 2FA first
    $this->actingAs($user)->post('/settings/two-factor-authentication');

    $user->refresh();

    // Get recovery codes
    $response = $this->actingAs($user)->get('/settings/two-factor-recovery-codes');

    $response->assertOk();
    $response->assertJsonCount(8);
});

it('allows recovery codes to be regenerated', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnums::ADMIN->value);

    // Enable 2FA first
    $this->actingAs($user)->post('/settings/two-factor-authentication');

    $user->refresh();
    $originalCodes = $user->two_factor_recovery_codes;

    // Regenerate recovery codes
    $response = $this->actingAs($user)->post('/settings/two-factor-recovery-codes');

    $response->assertRedirect();

    $user->refresh();

    expect($user->two_factor_recovery_codes)->not->toEqual($originalCodes);
});
