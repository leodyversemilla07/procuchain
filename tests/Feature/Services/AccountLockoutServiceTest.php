<?php

use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Services\AccountLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
    Mail::fake();

    $this->service = new AccountLockoutService;
});

describe('AccountLockoutService', function () {
    describe('unlockAccount', function () {
        test('it unlocks a locked account successfully', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
                'locked_reason' => 'Too many failed login attempts',
            ]);

            $result = $this->service->unlockAccount($user, 'Manually unlocked by admin');

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->account_locked)->toBeFalse();
            expect($user->locked_at)->toBeNull();
            expect($user->lock_expires_at)->toBeNull();

            Log::shouldHaveReceived('info')
                ->with('User account unlocked', Mockery::type('array'))
                ->once();
        });

        test('it returns false when account is not locked', function () {
            $user = User::factory()->create([
                'account_locked' => false,
            ]);

            $result = $this->service->unlockAccount($user, 'Manually unlocked by admin');

            expect($result)->toBeFalse();
        });

        test('it logs the unlock action with admin ID when authenticated', function () {
            $admin = User::factory()->create();
            Auth::login($admin);

            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $this->service->unlockAccount($user, 'Admin override', $admin);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message, $context) use ($admin, $user) {
                    return $message === 'User account unlocked' &&
                           $context['user_id'] === $user->id &&
                           $context['unlocked_by'] === $admin->id &&
                           $context['reason'] === 'Admin override';
                })
                ->once();
        });

        test('it logs unlock action with null admin ID when not authenticated', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $this->service->unlockAccount($user, 'System unlock');

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message, $context) use ($user) {
                    return $message === 'User account unlocked' &&
                           $context['user_id'] === $user->id &&
                           $context['unlocked_by'] === null;
                })
                ->once();
        });

        test('it handles exceptions gracefully', function () {
            $user = mock(User::class)->makePartial();
            $user->shouldReceive('isAccountLocked')->andReturn(true);
            $user->shouldReceive('unlockAccount')->andThrow(new Exception('Database error'));
            $user->id = 1;

            $result = $this->service->unlockAccount($user, 'Test unlock');

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('error')
                ->with('Failed to unlock user account', Mockery::type('array'))
                ->once();
        });

        test('it passes reason to user model', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
                'locked_reason' => 'Original reason',
            ]);

            $customReason = 'Administrator verified identity';
            $this->service->unlockAccount($user, $customReason);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message, $context) use ($customReason) {
                    return $context['reason'] === $customReason;
                })
                ->once();
        });
    });

    describe('resetFailedAttempts', function () {
        test('it resets failed login attempts to zero', function () {
            $user = User::factory()->create([
                'failed_login_attempts' => 5,
            ]);

            $result = $this->service->resetFailedAttempts($user);

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->failed_login_attempts)->toBe(0);

            Log::shouldHaveReceived('info')
                ->with('Failed login attempts reset', Mockery::type('array'))
                ->once();
        });

        test('it logs previous attempts count', function () {
            $user = User::factory()->create([
                'failed_login_attempts' => 3,
            ]);

            $this->service->resetFailedAttempts($user);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message, $context) use ($user) {
                    return $message === 'Failed login attempts reset' &&
                           $context['previous_attempts'] === 3 &&
                           $context['user_id'] === $user->id;
                })
                ->once();
        });

        test('it logs the admin who reset attempts when authenticated', function () {
            $admin = User::factory()->create();
            Auth::login($admin);

            $user = User::factory()->create([
                'failed_login_attempts' => 2,
            ]);

            $this->service->resetFailedAttempts($user, $admin);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message, $context) use ($admin) {
                    return $context['reset_by'] === $admin->id;
                })
                ->once();
        });

        test('it handles exceptions gracefully', function () {
            $user = mock(User::class)->makePartial();
            $user->shouldReceive('resetFailedLoginAttempts')->andThrow(new Exception('Database error'));
            $user->id = 1;
            $user->failed_login_attempts = 2;

            $result = $this->service->resetFailedAttempts($user);

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('error')
                ->with('Failed to reset failed login attempts', Mockery::type('array'))
                ->once();
        });

        test('it resets attempts even when count is already zero', function () {
            $user = User::factory()->create([
                'failed_login_attempts' => 0,
            ]);

            $result = $this->service->resetFailedAttempts($user);

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->failed_login_attempts)->toBe(0);
        });
    });

    describe('isAccountLocked', function () {
        test('it returns true when account is locked', function () {
            $user = User::factory()->create([
                'email' => 'locked@example.com',
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $result = $this->service->isAccountLocked('locked@example.com');

            expect($result)->toBeTrue();
        });

        test('it returns false when account is not locked', function () {
            $user = User::factory()->create([
                'email' => 'unlocked@example.com',
                'account_locked' => false,
            ]);

            $result = $this->service->isAccountLocked('unlocked@example.com');

            expect($result)->toBeFalse();
        });

        test('it returns false when user does not exist', function () {
            $result = $this->service->isAccountLocked('nonexistent@example.com');

            expect($result)->toBeFalse();
        });

        test('it returns false when lock has expired', function () {
            $user = User::factory()->create([
                'email' => 'expired@example.com',
                'account_locked' => true,
                'locked_at' => now()->subHours(25),
                'lock_expires_at' => now()->subHour(),
            ]);

            $result = $this->service->isAccountLocked('expired@example.com');

            expect($result)->toBeFalse(); // User model auto-unlocks expired locks
        });

        test('it is case sensitive for email', function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            // Email lookup should be case-sensitive as per Laravel default
            $result = $this->service->isAccountLocked('TEST@EXAMPLE.COM');

            expect($result)->toBeFalse(); // No user found with uppercase email
        });
    });

    describe('getLockedAccounts', function () {
        test('it retrieves all locked accounts', function () {
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            $unlockedUser = User::factory()->create([
                'account_locked' => false,
            ]);

            $lockedUser1 = User::factory()->create([
                'name' => 'Locked User 1',
                'email' => 'locked1@example.com',
                'account_locked' => true,
                'locked_at' => now()->subHours(2),
                'lock_expires_at' => now()->addHours(22),
                'locked_reason' => 'Too many failed attempts',
                'failed_login_attempts' => 5,
            ]);
            $lockedUser1->assignRole('admin');

            $lockedUser2 = User::factory()->create([
                'name' => 'Locked User 2',
                'email' => 'locked2@example.com',
                'account_locked' => true,
                'locked_at' => now()->subHour(),
                'lock_expires_at' => now()->addHours(23),
                'locked_reason' => 'Suspicious activity',
                'failed_login_attempts' => 3,
            ]);

            $result = $this->service->getLockedAccounts();

            expect($result)->toHaveCount(2);
            expect($result[0]['email'])->toBe('locked2@example.com'); // Most recent first
            expect($result[1]['email'])->toBe('locked1@example.com');
        });

        test('it returns correct structure for locked accounts', function () {
            $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
                'locked_reason' => 'Security policy violation',
                'failed_login_attempts' => 4,
            ]);
            $user->assignRole('bac_secretariat');

            $result = $this->service->getLockedAccounts();

            expect($result)->toHaveCount(1);
            expect($result[0])->toHaveKeys([
                'id',
                'name',
                'email',
                'role',
                'two_factor_enabled',
                'two_factor_confirmed_at',
                'locked_at',
                'lock_expires_at',
                'locked_reason',
                'failed_attempts',
                'time_remaining',
                'time_remaining_minutes',
                'recent_failed_logins',
            ]);
            expect($result[0]['name'])->toBe('Test User');
            expect($result[0]['email'])->toBe('test@example.com');
            expect($result[0]['role'])->toBe('bac_secretariat');
            expect($result[0]['failed_attempts'])->toBe(4);
        });

        test('it orders locked accounts by locked_at descending', function () {
            $older = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now()->subHours(5),
                'lock_expires_at' => now()->addHours(19),
            ]);

            $newer = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now()->subHour(),
                'lock_expires_at' => now()->addHours(23),
            ]);

            $result = $this->service->getLockedAccounts();

            expect($result[0]['id'])->toBe($newer->id); // Newest lock first
            expect($result[1]['id'])->toBe($older->id);
        });

        test('it returns empty collection when no accounts are locked', function () {
            User::factory()->count(3)->create([
                'account_locked' => false,
            ]);

            $result = $this->service->getLockedAccounts();

            expect($result)->toBeEmpty();
        });

        test('it includes two factor authentication status', function () {
            $userWith2FA = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
                'two_factor_secret' => encrypt('secret'),
                'two_factor_confirmed_at' => now(),
            ]);

            $userWithout2FA = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
            ]);

            $result = $this->service->getLockedAccounts();

            $with2FA = $result->firstWhere('id', $userWith2FA->id);
            $without2FA = $result->firstWhere('id', $userWithout2FA->id);

            expect($with2FA['two_factor_enabled'])->toBeTrue();
            expect($without2FA['two_factor_enabled'])->toBeFalse();
        });

        test('it excludes accounts with null locked_at even if account_locked is true', function () {
            $invalidLock = User::factory()->create([
                'account_locked' => true,
                'locked_at' => null, // Invalid state
                'lock_expires_at' => now()->addHours(24),
            ]);

            $validLock = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $result = $this->service->getLockedAccounts();

            expect($result)->toHaveCount(1);
            expect($result[0]['id'])->toBe($validLock->id);
        });
    });

    describe('unlockUserAccount', function () {
        test('it unlocks user account by ID', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $result = $this->service->unlockUserAccount($user->id, 'Admin verified identity');

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->account_locked)->toBeFalse();

            Log::shouldHaveReceived('info')
                ->with('User account manually unlocked', Mockery::type('array'))
                ->once();
        });

        test('it returns false when account is not locked', function () {
            $user = User::factory()->create([
                'account_locked' => false,
            ]);

            $result = $this->service->unlockUserAccount($user->id);

            expect($result)->toBeFalse();
        });

        test('it uses default admin reason when not provided', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $this->service->unlockUserAccount($user->id);

            Log::shouldHaveReceived('info')
                ->with('User account manually unlocked', Mockery::type('array'))
                ->once();
        });

        test('it handles non-existent user ID gracefully', function () {
            $result = $this->service->unlockUserAccount(99999, 'Test unlock');

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('error')
                ->with('Failed to unlock user account', Mockery::type('array'))
                ->once();
        });

        test('it logs custom admin reason', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addHours(24),
            ]);

            $customReason = 'Verified through support ticket #12345';
            $this->service->unlockUserAccount($user->id, $customReason);

            Log::shouldHaveReceived('info')
                ->with('User account manually unlocked', Mockery::type('array'))
                ->once();
        });
    });

    describe('lockAccount', function () {
        test('it locks user account with default 24 hour duration', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => false,
            ]);

            $result = $this->service->lockAccount($user, 'Policy violation');

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->account_locked)->toBeTrue();
            expect($user->locked_reason)->toBe('Policy violation');

            Log::shouldHaveReceived('warning')
                ->with('User account manually locked', Mockery::type('array'))
                ->once();
        });

        test('it locks account with custom duration', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => false,
            ]);

            $this->service->lockAccount($user, 'Temporary suspension', 48);

            Log::shouldHaveReceived('warning')
                ->withArgs(function ($message, $context) {
                    return $context['duration_hours'] === 48;
                })
                ->once();
        });

        test('it sends email notification when email notifications are enabled', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email' => 'user@example.com',
                'email_notifications_enabled' => true,
            ]);

            $this->service->lockAccount($user, 'Security review', 12);

            Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email);
            });

            Log::shouldHaveReceived('info')
                ->with('Account locked notification sent', Mockery::type('array'))
                ->once();
        });

        test('it does not send email when email notifications are disabled', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => false,
            ]);

            $this->service->lockAccount($user, 'Test lock');

            Mail::assertNotSent(AccountLockedMail::class);
        });

        test('it handles email sending exceptions gracefully', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => true,
            ]);

            Mail::shouldReceive('to')->andThrow(new Exception('SMTP error'));

            $result = $this->service->lockAccount($user, 'Test lock');

            expect($result)->toBeTrue(); // Lock succeeds even if email fails
            $user->refresh();
            expect($user->account_locked)->toBeTrue();
        });

        test('it logs error when email fails', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email' => 'user@example.com',
                'email_notifications_enabled' => true,
            ]);

            // Force Mail facade to throw exception
            Mail::shouldReceive('to')->andReturnSelf();
            Mail::shouldReceive('send')->andThrow(new Exception('Email service unavailable'));

            $this->service->lockAccount($user, 'Test lock');

            Log::shouldHaveReceived('error')
                ->with('Failed to send account locked notification', Mockery::type('array'))
                ->once();
        });

        test('it converts hours to minutes for user model', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => false,
            ]);

            $now = now();
            $this->service->lockAccount($user, 'Test', 2); // 2 hours = 120 minutes

            $user->refresh();
            // Verify lock expires at approximately 2 hours from now
            expect($user->lock_expires_at)->toBeInstanceOf(Carbon::class);
            expect($user->lock_expires_at->greaterThan($now))->toBeTrue();

            // Check duration is approximately 120 minutes (allow 1 minute tolerance)
            $minutesUntilExpiry = $now->diffInMinutes($user->lock_expires_at, false);
            expect($minutesUntilExpiry)->toBeGreaterThanOrEqual(119);
            expect($minutesUntilExpiry)->toBeLessThanOrEqual(121);
        });

        test('it handles exceptions when locking account', function () {
            $user = mock(User::class)->makePartial();
            $user->shouldReceive('lockAccount')->andThrow(new Exception('Database error'));
            $user->id = 1;
            $user->email_notifications_enabled = false;

            $result = $this->service->lockAccount($user, 'Test');

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('error')
                ->with('Failed to lock user account', Mockery::type('array'))
                ->once();
        });

        test('it logs user ID and duration', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'email_notifications_enabled' => false,
            ]);

            $this->service->lockAccount($user, 'Test reason', 6);

            Log::shouldHaveReceived('warning')
                ->withArgs(function ($message, $context) use ($user) {
                    return $message === 'User account manually locked' &&
                           $context['user_id'] === $user->id &&
                           $context['duration_hours'] === 6 &&
                           $context['reason'] === 'Test reason';
                })
                ->once();
        });
    });
});
