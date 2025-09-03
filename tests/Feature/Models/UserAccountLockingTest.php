<?php

use App\Mail\AccountUnlockedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

describe('User Account Locking', function () {
    beforeEach(fn () => Mail::fake());

    describe('Unlock Operations', function () {
        it('can be unlocked manually by admin', function () {
            $user = createLockedUser([
                'email' => 'test@example.com',
                'name' => 'Test User',
            ]);

            $user->unlockAccount('admin', false);
            $user->refresh();

            expect($user)
                ->account_locked->toBeFalse()
                ->locked_at->toBeNull()
                ->lock_expires_at->toBeNull()
                ->failed_login_attempts->toBe(0)
                ->locked_reason->toBeNull()
                ->last_failed_login_at->toBeNull();

            Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email) &&
                       $mail->user->id === $user->id &&
                       $mail->unlockReason === 'admin' &&
                       $mail->wasAutoUnlocked === false;
            });
        });

        it('can be unlocked automatically by system', function () {
            $user = createLockedUser([
                'email' => 'test@example.com',
                'name' => 'Test User',
                'locked_at' => now()->subMinutes(35),
                'lock_expires_at' => now()->subMinutes(5),
            ]);

            $user->unlockAccount('system', true);
            $user->refresh();

            expect($user)
                ->account_locked->toBeFalse()
                ->locked_at->toBeNull()
                ->lock_expires_at->toBeNull()
                ->failed_login_attempts->toBe(0)
                ->locked_reason->toBeNull()
                ->last_failed_login_at->toBeNull();

            Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email) &&
                       $mail->user->id === $user->id &&
                       $mail->unlockedBy === 'system' &&
                       $mail->wasAutoUnlocked === true;
            });
        });

        it('does not send email if already unlocked', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);

            $user->unlockAccount('admin', false);

            Mail::assertNotSent(AccountUnlockedMail::class);
        });
    });

    describe('Lock Status Checking', function () {
        it('returns false for unlocked account', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);

            expect($user->isAccountLocked())->toBeFalse();
        });

        it('returns true for locked account that has not expired', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addMinutes(30),
            ]);

            expect($user->isAccountLocked())->toBeTrue();
        });

        it('auto-unlocks expired locked account', function () {
            $user = createLockedUser([
                'locked_at' => now()->subMinutes(35),
                'lock_expires_at' => now()->subMinutes(5),
            ]);

            $result = $user->isAccountLocked();

            expect($result)->toBeFalse();

            $user->refresh();
            expect($user)
                ->account_locked->toBeFalse()
                ->locked_at->toBeNull()
                ->lock_expires_at->toBeNull()
                ->failed_login_attempts->toBe(0)
                ->locked_reason->toBeNull()
                ->last_failed_login_at->toBeNull();

            Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email) &&
                       $mail->user->id === $user->id &&
                       $mail->unlockedBy === 'system' &&
                       $mail->wasAutoUnlocked === true;
            });
        });
    });

    describe('Lock Operations', function () {
        it('locks user account with proper attributes', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'failed_login_attempts' => 2,
            ]);

            $lockReason = 'Multiple failed login attempts';
            $lockDuration = 30;

            $user->lockAccount($lockReason, $lockDuration);
            $user->refresh();

            expect($user)
                ->account_locked->toBeTrue()
                ->locked_at->not->toBeNull()
                ->lock_expires_at->not->toBeNull()
                ->locked_reason->toBe($lockReason);

            $expectedExpiration = $user->locked_at->addMinutes($lockDuration);
            expect($user->lock_expires_at->equalTo($expectedExpiration))->toBeTrue();
        });
    });

    describe('Remaining Lock Time', function () {
        it('returns correct remaining time for active lock', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now(),
                'lock_expires_at' => now()->addMinutes(15),
            ]);

            $remainingTime = $user->getRemainingLockTimeAttribute();

            expect($remainingTime)
                ->toBeGreaterThan(14)
                ->toBeLessThanOrEqual(15);
        });

        it('returns 0 for expired lock', function () {
            $user = User::factory()->create([
                'account_locked' => true,
                'locked_at' => now()->subMinutes(35),
                'lock_expires_at' => now()->subMinutes(5),
            ]);

            expect($user->getRemainingLockTimeAttribute())->toBe(0);
        });

        it('returns 0 for unlocked account', function () {
            $user = User::factory()->create([
                'account_locked' => false,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);

            expect($user->getRemainingLockTimeAttribute())->toBe(0);
        });
    });
});
