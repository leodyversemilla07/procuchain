<?php

use App\Mail\AccountUnlockedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('isAccountLocked returns false for unlocked account', function () {
    $user = User::factory()->create([
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);

    expect($user->isAccountLocked())->toBeFalse();
});

test('isAccountLocked returns true for locked account that has not expired', function () {
    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30), // Expires in future
    ]);

    expect($user->isAccountLocked())->toBeTrue();
});

test('lockAccount locks user account with proper attributes', function () {
    $user = User::factory()->create([
        'account_locked' => false,
        'failed_login_attempts' => 2,
    ]);

    $lockReason = 'Multiple failed login attempts';
    $lockDuration = 30; // minutes

    $user->lockAccount($lockReason, $lockDuration);

    $user->refresh();

    expect($user->account_locked)->toBeTrue()
        ->and($user->locked_at)->not->toBeNull()
        ->and($user->lock_expires_at)->not->toBeNull()
        ->and($user->locked_reason)->toBe($lockReason);

    // Check that lock expires in the specified duration
    $expectedExpiration = $user->locked_at->addMinutes($lockDuration);
    expect($user->lock_expires_at->equalTo($expectedExpiration))->toBeTrue();
});

test('getLockTimeRemaining returns correct remaining time string', function () {
    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(15), // 15 minutes remaining
    ]);

    $remainingTime = $user->getLockTimeRemaining();

    // Should return a human-readable string like "in 15 minutes"
    expect($remainingTime)->not->toBeNull()
        ->and($remainingTime)->toBeString()
        ->and($remainingTime)->toContain('minute');
});

test('getLockTimeRemaining returns null for expired lock', function () {
    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now()->subMinutes(35),
        'lock_expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
    ]);

    $remainingTime = $user->getLockTimeRemaining();

    // Should return null since account auto-unlocks when expired
    expect($remainingTime)->toBeNull();
});

test('getLockTimeRemaining returns null for unlocked account', function () {
    $user = User::factory()->create([
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);

    $remainingTime = $user->getLockTimeRemaining();

    expect($remainingTime)->toBeNull();
});

test('unlockAccount with email notifications', function () {
    Mail::fake();

    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
        'email_notifications_enabled' => true,
    ]);

    $user->unlockAccount(false);

    $user->refresh();

    expect($user->account_locked)->toBeFalse()
        ->and($user->locked_at)->toBeNull()
        ->and($user->lock_expires_at)->toBeNull()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_reason)->toBeNull();

    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id;
    });
});

test('unlockAccount does not send email if already unlocked', function () {
    Mail::fake();

    $user = User::factory()->create([
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);

    $user->unlockAccount(false);

    Mail::assertNotSent(AccountUnlockedMail::class);
});

