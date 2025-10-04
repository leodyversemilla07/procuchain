<?php

use App\Enums\UserRoleEnums;
use App\Models\User;

it('allows users without 2fa to access settings pages', function () {
    $user = User::factory()->create([
        'role' => UserRoleEnums::ADMIN,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);

    // User should be able to access various settings pages without 2FA
    $this->actingAs($user)->get('/settings/profile')->assertOk();
    $this->actingAs($user)->get('/settings/password')->assertOk();
    $this->actingAs($user)->get('/settings/two-factor')->assertOk();
    $this->actingAs($user)->get('/settings/appearance')->assertOk();

    // User should be able to navigate between settings pages freely
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});
