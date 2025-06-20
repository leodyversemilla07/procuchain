<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can access mfa settings page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('mfa.edit'));

    $response->assertOk();
});

test('user can setup mfa', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('mfa.setup'));

    $response->assertOk();
    $response->assertJsonStructure(['secret', 'qrCodeUrl']);
    
    $user->refresh();
    expect($user->google2fa_secret)->not->toBeNull();
    expect($user->mfa_enabled)->toBeFalse(); // Not enabled until verified
});

test('user can enable mfa with valid code', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update(['google2fa_secret' => $secret]);
    
    // Generate valid TOTP code
    $validCode = $google2fa->getCurrentOtp($secret);
    
    $response = $this->actingAs($user)->post(route('mfa.enable'), [
        'code' => $validCode,
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'mfa-enabled');
    
    $user->refresh();
    expect($user->mfa_enabled)->toBeTrue();
    expect($user->mfa_enabled_at)->not->toBeNull();
    expect($user->backup_codes)->not->toBeNull();
});

test('user cannot enable mfa with invalid code', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update(['google2fa_secret' => $secret]);
    
    $response = $this->actingAs($user)->post(route('mfa.enable'), [
        'code' => '000000', // Invalid code
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['code']);
    
    $user->refresh();
    expect($user->mfa_enabled)->toBeFalse();
});

test('user can disable mfa with valid code', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update([
        'google2fa_secret' => $secret,
        'mfa_enabled' => true,
        'mfa_enabled_at' => now(),
    ]);
    
    // Generate valid TOTP code
    $validCode = $google2fa->getCurrentOtp($secret);
    
    $response = $this->actingAs($user)->post(route('mfa.disable'), [
        'code' => $validCode,
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'mfa-disabled');
    
    $user->refresh();
    expect($user->mfa_enabled)->toBeFalse();
    expect($user->google2fa_secret)->toBeNull();
    expect($user->backup_codes)->toBeNull();
});

test('user can disable mfa with backup code', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update([
        'google2fa_secret' => $secret,
        'mfa_enabled' => true,
        'mfa_enabled_at' => now(),
    ]);
    
    // Generate backup codes
    $backupCodes = $user->generateBackupCodes();
    $backupCode = $backupCodes[0]; // Use first backup code
    
    $response = $this->actingAs($user)->post(route('mfa.disable'), [
        'code' => $backupCode,
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'mfa-disabled');
    
    $user->refresh();
    expect($user->mfa_enabled)->toBeFalse();
    expect($user->google2fa_secret)->toBeNull();
    expect($user->backup_codes)->toBeNull();
});

test('user is redirected to mfa verification on login when mfa enabled', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update([
        'google2fa_secret' => $secret,
        'mfa_enabled' => true,
        'mfa_enabled_at' => now(),
    ]);
    
    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('mfa.verify.form'));
    expect(session('mfa_user_id'))->toBe($user->id);
    $this->assertGuest(); // User should not be authenticated yet
});

test('user can complete login with mfa verification', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    $user->update([
        'google2fa_secret' => $secret,
        'mfa_enabled' => true,
        'mfa_enabled_at' => now(),
    ]);
    
    // First, attempt login to get to MFA verification
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    
    // Generate valid TOTP code
    $validCode = $google2fa->getCurrentOtp($secret);
    
    // Verify MFA code
    $response = $this->post(route('mfa.verify'), [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(session('mfa_verified_' . $user->id))->toBeTrue();
});
