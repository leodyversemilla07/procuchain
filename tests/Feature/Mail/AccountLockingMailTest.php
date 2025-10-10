<?php

use App\Mail\AccountLockedMail;
use App\Mail\AccountUnlockedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('account locked email can be sent', function () {
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

    $lockReason = 'Multiple failed login attempts';
    $lockDuration = '30 minutes';

    Mail::to($user->email)->send(new AccountLockedMail($user, $lockReason, $lockDuration));

    Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user, $lockReason, $lockDuration) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->lockReason === $lockReason &&
               $mail->lockDuration === $lockDuration;
    });
});

test('account unlocked email can be sent', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'account_locked' => false,
        'locked_at' => null,
        'lock_expires_at' => null,
        'failed_login_attempts' => 0,
    ]);

    $unlockReason = 'Manually unlocked by admin';
    $wasAutoUnlocked = false;

    Mail::to($user->email)->send(new AccountUnlockedMail($user, $unlockReason, $wasAutoUnlocked));

    Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user, $unlockReason, $wasAutoUnlocked) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->unlockReason === $unlockReason &&
               $mail->wasAutoUnlocked === $wasAutoUnlocked;
    });
});

test('account locked email has correct subject and content', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $lockReason = 'Multiple failed login attempts';
    $lockDuration = '30 minutes';

    $mail = new AccountLockedMail($user, $lockReason, $lockDuration);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Account Security Alert - Account Locked');

    $content = $mail->content();
    expect($content->view)->toBe('emails.account-locked');
    expect($content->with)->toHaveKey('user', $user)
        ->and($content->with)->toHaveKey('lockReason', $lockReason)
        ->and($content->with)->toHaveKey('lockDuration', $lockDuration);
});

test('account unlocked email has correct subject and content for manual unlock', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $unlockReason = 'Manually unlocked by admin';
    $wasAutoUnlocked = false;

    $mail = new AccountUnlockedMail($user, $unlockReason, $wasAutoUnlocked);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Account Security Update - Account Unlocked');

    $content = $mail->content();
    expect($content->view)->toBe('emails.account-unlocked');
    expect($content->with)->toHaveKey('user', $user)
        ->and($content->with)->toHaveKey('unlockReason', $unlockReason)
        ->and($content->with)->toHaveKey('wasAutoUnlocked', $wasAutoUnlocked);
});

test('account unlocked email has correct subject and content for auto unlock', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $unlockReason = 'system';
    $wasAutoUnlocked = true;

    $mail = new AccountUnlockedMail($user, $unlockReason, $wasAutoUnlocked);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Account Security Update - Account Unlocked');

    $content = $mail->content();
    expect($content->view)->toBe('emails.account-unlocked');
    expect($content->with)->toHaveKey('user', $user)
        ->and($content->with)->toHaveKey('unlockReason', $unlockReason)
        ->and($content->with)->toHaveKey('wasAutoUnlocked', $wasAutoUnlocked);
});

test('account locked email contains security information', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
    ]);

    $lockReason = 'Multiple failed login attempts';
    $lockDuration = '30 minutes';

    $mail = new AccountLockedMail($user, $lockReason, $lockDuration);

    // Test that mail can be instantiated without errors
    expect($mail)->toBeInstanceOf(AccountLockedMail::class);
    expect($mail->user)->toBe($user);
    expect($mail->lockReason)->toBe($lockReason);
    expect($mail->lockDuration)->toBe($lockDuration);
});

test('account unlocked email can be built without errors', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $unlockReason = 'admin';
    $wasAutoUnlocked = false;

    $mail = new AccountUnlockedMail($user, $unlockReason, $wasAutoUnlocked);

    // Test that mail can be instantiated without errors
    expect($mail)->toBeInstanceOf(AccountUnlockedMail::class);
    expect($mail->user)->toBe($user);
    expect($mail->unlockReason)->toBe($unlockReason);
    expect($mail->wasAutoUnlocked)->toBe($wasAutoUnlocked);
});

