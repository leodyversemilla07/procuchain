<?php

use App\Events\AccountLocked;
use App\Events\AccountUnlocked;
use App\Events\SuspiciousLoginDetected;
use App\Events\UserInvited;
use App\Listeners\SendAccountLockedNotification;
use App\Listeners\SendAccountUnlockedNotification;
use App\Listeners\SendLoginAnomalyAlert;
use App\Listeners\SendUserInvitationEmail;
use App\Mail\AccountLockedMail;
use App\Mail\AccountUnlockedMail;
use App\Mail\NewLocationLoginAlert;
use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use App\Services\AccountLockoutService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

// ============================================================
// Event Dispatch Tests
// ============================================================

describe('Event dispatching', function () {
    it('dispatches AccountLocked event from AccountLockoutService', function () {
        Event::fake([AccountLocked::class]);

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => true,
        ]);

        $service = app(AccountLockoutService::class);
        $service->handleFailedLoginAttempt($user);
        $service->handleFailedLoginAttempt($user);
        $service->handleFailedLoginAttempt($user);

        Event::assertDispatched(AccountLocked::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && str_contains($event->reason, 'failed login attempts');
        });
    });

    it('dispatches AccountUnlocked event when account is unlocked', function () {
        Event::fake([AccountUnlocked::class]);

        $user = createUserWithRole('bac_secretariat', [
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(30),
            'locked_reason' => 'Test lock',
            'email_notifications_enabled' => true,
        ]);

        $user->unlockAccount('admin', false);

        Event::assertDispatched(AccountUnlocked::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->isAutoUnlock === false
                && $event->unlockedBy === 'admin';
        });
    });

    it('does not dispatch AccountUnlocked event if account is already unlocked', function () {
        Event::fake([AccountUnlocked::class]);

        $user = createUserWithRole('bac_secretariat', [
            'account_locked' => false,
        ]);

        $user->unlockAccount('admin', false);

        Event::assertNotDispatched(AccountUnlocked::class);
    });

    it('dispatches UserInvited event from UserInvitationController', function () {
        Event::fake([UserInvited::class]);

        $admin = createUserWithRole('admin');

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'name' => 'Test User',
            'email' => 'testinvite@example.com',
            'role' => 'bac_secretariat',
        ]);

        Event::assertDispatched(UserInvited::class, function ($event) {
            return $event->invitation->email === 'testinvite@example.com'
                && ! empty($event->acceptUrl);
        });
    });
});

// ============================================================
// Listener Tests — AccountLocked
// ============================================================

describe('SendAccountLockedNotification listener', function () {
    it('sends AccountLockedMail when event is handled', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => true,
        ]);

        $event = new AccountLocked($user, 'Multiple failed attempts', '30 minutes');
        (new SendAccountLockedNotification)->handle($event);

        Mail::assertSent(AccountLockedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('does not send mail when email notifications are disabled', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => false,
        ]);

        $event = new AccountLocked($user, 'Multiple failed attempts', '30 minutes');
        (new SendAccountLockedNotification)->handle($event);

        Mail::assertNothingSent();
    });

    it('does not throw when mail fails', function () {
        Mail::shouldReceive('to')->andThrow(new Exception('SMTP error'));

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => true,
        ]);

        $event = new AccountLocked($user, 'Test', '30 minutes');

        expect(fn () => (new SendAccountLockedNotification)->handle($event))
            ->not->toThrow(Exception::class);
    });
});

// ============================================================
// Listener Tests — AccountUnlocked
// ============================================================

describe('SendAccountUnlockedNotification listener', function () {
    it('sends AccountUnlockedMail when event is handled', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => true,
        ]);

        $event = new AccountUnlocked($user, 'Admin unlocked', false, 'admin');
        (new SendAccountUnlockedNotification)->handle($event);

        Mail::assertSent(AccountUnlockedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('sends mail for auto-unlock events', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => true,
        ]);

        $event = new AccountUnlocked(
            $user,
            'Account automatically unlocked after lock period expired',
            true,
            'system',
        );
        (new SendAccountUnlockedNotification)->handle($event);

        Mail::assertSent(AccountUnlockedMail::class);
    });

    it('does not send mail when email notifications are disabled', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat', [
            'email_notifications_enabled' => false,
        ]);

        $event = new AccountUnlocked($user, 'Admin unlocked', false, 'admin');
        (new SendAccountUnlockedNotification)->handle($event);

        Mail::assertNothingSent();
    });
});

// ============================================================
// Listener Tests — SuspiciousLoginDetected
// ============================================================

describe('SendLoginAnomalyAlert listener', function () {
    it('queues NewLocationLoginAlert when event is handled', function () {
        Mail::fake();

        $user = createUserWithRole('bac_secretariat');

        $event = new SuspiciousLoginDetected(
            $user,
            ['country' => 'US', 'city' => 'New York', 'region' => 'NY'],
            '192.168.1.1',
            'Mozilla/5.0',
        );
        (new SendLoginAnomalyAlert)->handle($event);

        Mail::assertQueued(NewLocationLoginAlert::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('does not throw when mail queue fails', function () {
        Mail::shouldReceive('to')->andThrow(new Exception('Queue error'));

        $user = createUserWithRole('bac_secretariat');

        $event = new SuspiciousLoginDetected(
            $user,
            ['country' => 'PH', 'city' => 'Manila'],
            '10.0.0.1',
            null,
        );

        expect(fn () => (new SendLoginAnomalyAlert)->handle($event))
            ->not->toThrow(Exception::class);
    });
});

// ============================================================
// Listener Tests — UserInvited
// ============================================================

describe('SendUserInvitationEmail listener', function () {
    it('sends UserInvitationMail when event is handled', function () {
        Mail::fake();

        $invitation = UserInvitation::factory()->create();
        $acceptUrl = 'https://example.com/accept?token=test';

        $event = new UserInvited($invitation, $acceptUrl);
        (new SendUserInvitationEmail)->handle($event);

        Mail::assertQueued(UserInvitationMail::class, function ($mail) use ($invitation) {
            return $mail->hasTo($invitation->email);
        });
    });

    it('does not throw when mail fails', function () {
        Mail::shouldReceive('to')->andThrow(new Exception('SMTP error'));

        $invitation = UserInvitation::factory()->create();
        $acceptUrl = 'https://example.com/accept?token=test';

        $event = new UserInvited($invitation, $acceptUrl);

        expect(fn () => (new SendUserInvitationEmail)->handle($event))
            ->not->toThrow(Exception::class);
    });
});
