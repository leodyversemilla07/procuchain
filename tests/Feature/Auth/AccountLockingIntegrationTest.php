<?php

use App\Mail\AccountLockedMail;
use App\Mail\AccountUnlockedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('complete account locking flow works with email notifications', function () {
    Mail::fake();

    // Create a regular user
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Test User',
        'password' => Hash::make('password'),
        'role' => 'bac_secretariat',
    ]);

    // Attempt login with wrong password 3 times
    for ($i = 1; $i <= 3; $i++) {
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();

        $user->refresh();
        expect($user->failed_login_attempts)->toBe($i);

        if ($i < 3) {
            expect($user->account_locked)->toBeFalse();
            Mail::assertNotSent(AccountLockedMail::class);
        }
    }

    // After 3rd attempt, account should be locked and email sent
    $user->refresh();
    expect($user->account_locked)->toBeTrue()
        ->and($user->locked_at)->not->toBeNull()
        ->and($user->lock_expires_at)->not->toBeNull()
        ->and($user->locked_reason)->toBe('Account locked due to multiple failed login attempts');

    Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id;
    });

    // Try to login with correct password - should still fail because account is locked
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('admin can view locked accounts', function () {
    // Create admin user
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@example.com',
    ]);

    // Create locked user
    $lockedUser = User::factory()->create([
        'email' => 'locked@example.com',
        'name' => 'Locked User',
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    // Login as admin
    $this->actingAs($admin);

    // Access locked accounts page
    $response = $this->get('/admin/accounts/locked');
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('admin/locked-accounts')
        ->has('lockedAccounts', 1)
        ->has('lockedAccounts.0', fn ($user) => $user
            ->where('id', $lockedUser->id)
            ->where('email', 'locked@example.com')
            ->where('account_locked', true)
            ->etc()
        )
    );
});

test('admin can unlock user account via API', function () {
    Mail::fake();

    // Create admin user
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@example.com',
    ]);

    // Create locked user
    $lockedUser = User::factory()->create([
        'email' => 'locked@example.com',
        'name' => 'Locked User',
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);    // Login as admin
    $this->actingAs($admin);

    // Unlock user account
    $response = $this->post("/admin/accounts/{$lockedUser->id}/unlock");

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Account unlocked successfully',
    ]);

    // Check that user is unlocked
    $lockedUser->refresh();
    expect($lockedUser->account_locked)->toBeFalse()
        ->and($lockedUser->locked_at)->toBeNull()
        ->and($lockedUser->lock_expires_at)->toBeNull()
        ->and($lockedUser->failed_login_attempts)->toBe(0);

    // Check that unlock email was sent
    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($lockedUser) {
        return $mail->hasTo($lockedUser->email) &&
               $mail->user->id === $lockedUser->id &&
               $mail->unlockReason === 'Manually unlocked by admin' &&
               $mail->wasAutoUnlocked === false;
    });
});

test('admin can lock user account via API', function () {
    Mail::fake();

    // Create admin user
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@example.com',
    ]);

    // Create regular user
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Regular User',
        'account_locked' => false,
    ]);    // Login as admin
    $this->actingAs($admin);

    // Lock user account
    $response = $this->post("/admin/accounts/{$user->id}/lock", [
        'reason' => 'Administrative action',
        'duration' => 60, // 1 hour
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Account locked successfully',
    ]);

    // Check that user is locked
    $user->refresh();
    expect($user->account_locked)->toBeTrue()
        ->and($user->locked_at)->not->toBeNull()
        ->and($user->lock_expires_at)->not->toBeNull()
        ->and($user->locked_reason)->toBe('Administrative action');

    // Check that lock email was sent
    Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->lockReason === 'Administrative action';
    });
});

test('non-admin cannot access locked accounts page', function () {
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
    ]);

    $this->actingAs($user);
    $response = $this->get('/admin/accounts/locked');
    $response->assertStatus(403);
});

test('non-admin cannot unlock accounts', function () {
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
    ]);

    $lockedUser = User::factory()->create([
        'account_locked' => true,
    ]);

    $this->actingAs($user);
    $response = $this->post("/admin/accounts/{$lockedUser->id}/unlock");
    $response->assertStatus(403);
});

test('locked user cannot login even with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('user can login after account is automatically unlocked', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
        'role' => 'bac_secretariat',
        'account_locked' => true,
        'locked_at' => now()->subMinutes(35),
        'lock_expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('bac-secretariat.dashboard'));

    // Check that unlock email was sent
    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->unlockReason === 'Account automatically unlocked after lock period expired' &&
               $mail->wasAutoUnlocked === true;
    });
});
