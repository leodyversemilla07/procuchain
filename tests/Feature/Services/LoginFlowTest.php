<?php

use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\LoginLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loginLogger = app(LoginLoggerService::class);
    $this->accountLockout = app(AccountLockoutService::class);
});

test('sends account locked email when account is locked after failed attempts', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 2, // One more attempt will lock the account
        'account_locked' => false,
        'email_notifications_enabled' => true,
    ]);    // Create a mock request for the failed login
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    // This should lock the account and send email
    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);

    // Refresh user to get updated data
    $user->refresh();

    expect($user->account_locked)->toBeTrue()
        ->and($user->failed_login_attempts)->toBe(3)
        ->and($user->locked_at)->not->toBeNull()
        ->and($user->lock_expires_at)->not->toBeNull();
    Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->lockReason === 'Account locked due to multiple failed login attempts';
    });
});

test('does not send email when account is already locked', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 3,
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
    ]);
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    // This should not send another email since account is already locked
    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);

    Mail::assertNotSent(AccountLockedMail::class);
});

test('does not send email when account is not locked after failed attempt', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',        'failed_login_attempts' => 1, // This will be 2 after the attempt, not locked yet
        'account_locked' => false,
    ]);

    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);

    // Refresh user to get updated data
    $user->refresh();

    expect($user->account_locked)->toBeFalse()
        ->and($user->failed_login_attempts)->toBe(2);

    Mail::assertNotSent(AccountLockedMail::class);
});

test('resets failed login attempts on successful login', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 2,
        'last_failed_login_at' => now()->subMinutes(5),
        'account_locked' => false,
    ]);
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $this->loginLogger->logLogin($user, $request);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->last_failed_login_at)->toBeNull();
});

test('handles failed login attempt with proper tracking', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 1,
        'account_locked' => false,
    ]);
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(2)
        ->and($user->last_failed_login_at)->not->toBeNull()
        ->and($user->account_locked)->toBeFalse();
});

test('locks account after exactly 3 failed attempts', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 2,        'account_locked' => false,
    ]);

    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(3)
        ->and($user->account_locked)->toBeTrue()
        ->and($user->locked_at)->not->toBeNull()
        ->and($user->lock_expires_at)->not->toBeNull();
});

test('sets correct lock expiration time', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
        'failed_login_attempts' => 2,
        'account_locked' => false,
    ]);
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $beforeLock = now();
    $this->loginLogger->logFailedLogin($user->email, $request);
    $this->accountLockout->handleFailedLoginAttempt($user);
    $afterLock = now();

    $user->refresh();

    expect($user->account_locked)->toBeTrue()
        ->and($user->lock_expires_at)->not->toBeNull();

    // Check that lock expiration is approximately 30 minutes from now
    $expectedExpiration = $beforeLock->addMinutes(30);
    $actualExpiration = $user->lock_expires_at;

    expect($actualExpiration)->toBeBetween(
        $expectedExpiration->subSeconds(5),
        $afterLock->addMinutes(30)->addSeconds(5)
    );
});
