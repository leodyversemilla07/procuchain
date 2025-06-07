<?php

use App\Mail\AccountUnlockedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('user can be unlocked manually by admin', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $user->unlockAccount('admin', false);

    $user->refresh();

    expect($user->account_locked)->toBeFalse()
        ->and($user->locked_at)->toBeNull()
        ->and($user->lock_expires_at)->toBeNull()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_reason)->toBeNull()
        ->and($user->last_failed_login_at)->toBeNull();

    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->unlockedBy === 'admin' &&
               $mail->wasAutoUnlocked === false;
    });
});

test('user can be unlocked automatically by system', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'account_locked' => true,
        'locked_at' => now()->subMinutes(35), // Locked 35 minutes ago
        'lock_expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $user->unlockAccount('system', true);

    $user->refresh();

    expect($user->account_locked)->toBeFalse()
        ->and($user->locked_at)->toBeNull()
        ->and($user->lock_expires_at)->toBeNull()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_reason)->toBeNull()
        ->and($user->last_failed_login_at)->toBeNull();

    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->unlockedBy === 'system' &&
               $mail->wasAutoUnlocked === true;
    });
});

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

test('isAccountLocked auto-unlocks expired locked account', function () {
    Mail::fake();

    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now()->subMinutes(35),
        'lock_expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $result = $user->isAccountLocked();

    expect($result)->toBeFalse();

    $user->refresh();

    expect($user->account_locked)->toBeFalse()
        ->and($user->locked_at)->toBeNull()
        ->and($user->lock_expires_at)->toBeNull()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_reason)->toBeNull()
        ->and($user->last_failed_login_at)->toBeNull();

    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->unlockedBy === 'system' &&
               $mail->wasAutoUnlocked === true;
    });
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

test('getRemainingLockTimeAttribute returns correct remaining time', function () {
    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(15), // 15 minutes remaining
    ]);

    $remainingTime = $user->getRemainingLockTimeAttribute();

    // Should be approximately 15 minutes (within 1 minute tolerance)
    expect($remainingTime)->toBeGreaterThan(14)
        ->and($remainingTime)->toBeLessThanOrEqual(15);
});

test('getRemainingLockTimeAttribute returns 0 for expired lock', function () {
    $user = User::factory()->create([
        'account_locked' => true,
        'locked_at' => now()->subMinutes(35),
        'lock_expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
    ]);

    $remainingTime = $user->getRemainingLockTimeAttribute();

    expect($remainingTime)->toBe(0);
});

test('getRemainingLockTimeAttribute returns 0 for unlocked account', function () {
    $user = User::factory()->create([
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);

    $remainingTime = $user->getRemainingLockTimeAttribute();

    expect($remainingTime)->toBe(0);
});

test('unlock account does not send email if already unlocked', function () {
    Mail::fake();

    $user = User::factory()->create([
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);

    $user->unlockAccount('admin', false);

    Mail::assertNotSent(AccountUnlockedMail::class);
});
