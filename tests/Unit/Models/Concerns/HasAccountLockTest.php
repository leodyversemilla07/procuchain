<?php

use App\Events\AccountUnlocked;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

describe('HasAccountLock', function () {
    describe('isAccountLocked', function () {
        it('returns true when account is locked and lock has not expired', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addMinutes(30),
                'locked_reason' => 'Multiple failed login attempts',
            ]);

            expect($user->isAccountLocked())->toBeTrue();
        });

        it('returns false when lock has expired', function () {
            Event::fake();

            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now()->subHour(),
                'lock_expires_at' => now()->subMinutes(30),
                'locked_reason' => 'Multiple failed login attempts',
            ]);

            expect($user->isAccountLocked())->toBeFalse();

            // Verify the account was auto-unlocked
            $user->refresh();
            expect($user->account_locked)->toBeFalse();
        });

        it('returns false when account is not locked', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);

            expect($user->isAccountLocked())->toBeFalse();
        });
    });

    describe('lockAccount', function () {
        it('sets locked_until and account_locked fields', function () {
            $user = User::factory()->create([
                'account_locked' => false,
            ]);

            $user->lockAccount('Too many attempts', 60);
            $user->refresh();

            expect($user->account_locked)->toBeTrue()
                ->and($user->locked_reason)->toBe('Too many attempts')
                ->and($user->locked_at)->not->toBeNull()
                ->and($user->lock_expires_at)->not->toBeNull();
        });

        it('uses default values when no arguments provided', function () {
            $user = User::factory()->create([
                'account_locked' => false,
            ]);

            $user->lockAccount();
            $user->refresh();

            expect($user->account_locked)->toBeTrue()
                ->and($user->locked_reason)->toBe('Multiple failed login attempts');
        });
    });

    describe('unlockAccount', function () {
        it('resets lock fields and dispatches event', function () {
            Event::fake();

            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addMinutes(30),
                'failed_login_attempts' => 5,
                'last_failed_login_at' => now(),
                'locked_reason' => 'Too many attempts',
            ]);

            $user->unlockAccount('admin@test.com');
            $user->refresh();

            expect($user->account_locked)->toBeFalse()
                ->and($user->locked_at)->toBeNull()
                ->and($user->lock_expires_at)->toBeNull()
                ->and($user->failed_login_attempts)->toBe(0)
                ->and($user->last_failed_login_at)->toBeNull()
                ->and($user->locked_reason)->toBeNull();

            Event::assertDispatched(AccountUnlocked::class);
        });

        it('does nothing when account is already unlocked', function () {
            Event::fake();

            $user = User::factory()->create([
                'account_locked' => false,
            ]);

            $user->unlockAccount('admin@test.com');

            Event::assertNotDispatched(AccountUnlocked::class);
        });
    });
});
