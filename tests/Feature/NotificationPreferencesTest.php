<?php

use App\Models\User;
use App\Services\NotificationPreferenceService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('NotificationPreferenceService', function () {
    it('returns default preferences for user with no saved preferences', function () {
        $user = User::factory()->create(['notification_preferences' => null]);
        $service = new NotificationPreferenceService;

        $prefs = $service->getMergedPreferences($user);

        expect($prefs)->toHaveKey('procurement_stage_updates')
            ->and($prefs['procurement_stage_updates'])->toBe(['email' => true, 'push' => true])
            ->and($prefs['document_uploads'])->toBe(['email' => false, 'push' => true]);
    });

    it('merges user preferences with defaults', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => false, 'push' => true],
            ],
        ]);
        $service = new NotificationPreferenceService;

        $prefs = $service->getMergedPreferences($user);

        expect($prefs['procurement_stage_updates']['email'])->toBeFalse()
            ->and($prefs['account_security']['email'])->toBeTrue(); // default
    });

    it('checks if notification is enabled for type and channel', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => true, 'push' => false],
            ],
        ]);
        $service = new NotificationPreferenceService;

        expect($service->isEnabled($user, 'procurement_stage_updates', 'email'))->toBeTrue()
            ->and($service->isEnabled($user, 'procurement_stage_updates', 'push'))->toBeFalse();
    });

    it('respects master email toggle', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => false,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => true, 'push' => true],
            ],
        ]);
        $service = new NotificationPreferenceService;

        // Email disabled via master toggle even though per-type is enabled
        expect($service->isEnabled($user, 'procurement_stage_updates', 'email'))->toBeFalse()
            ->and($service->isEnabled($user, 'procurement_stage_updates', 'push'))->toBeTrue();
    });

    it('updates user preferences filtering invalid keys', function () {
        $user = User::factory()->create(['notification_preferences' => null]);
        $service = new NotificationPreferenceService;

        $service->updatePreferences($user, [
            'procurement_stage_updates' => ['email' => false, 'push' => true],
            'invalid_type' => ['email' => true, 'push' => true],
        ]);

        $user->refresh();
        expect($user->notification_preferences)->toHaveKey('procurement_stage_updates')
            ->and($user->notification_preferences)->not->toHaveKey('invalid_type')
            ->and($user->notification_preferences['procurement_stage_updates']['email'])->toBeFalse();
    });

    it('returns correct channels for event type', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => true, 'push' => false],
            ],
        ]);
        $service = new NotificationPreferenceService;

        $channels = $service->getChannelsForEvent($user, 'procurement_stage_updates');

        expect($channels)->toContain('database')
            ->and($channels)->toContain('mail')
            ->and($channels)->not->toContain(\NotificationChannels\WebPush\WebPushChannel::class);
    });

    it('provides frontend payload with all required keys', function () {
        $user = User::factory()->create(['email_notifications_enabled' => true]);
        $service = new NotificationPreferenceService;

        $payload = $service->getPreferencesForFrontend($user);

        expect($payload)->toHaveKeys(['email_notifications_enabled', 'notification_preferences', 'categories'])
            ->and($payload['categories'])->toHaveKeys(['Procurement', 'Security', 'System']);
    });
});

describe('User notification preference helpers', function () {
    it('returns default preferences via static method', function () {
        $defaults = User::getDefaultNotificationPreferences();

        expect($defaults)->toBeArray()
            ->and($defaults)->toHaveCount(6)
            ->and($defaults)->toHaveKeys([
                'procurement_stage_updates',
                'procurement_corrections',
                'document_uploads',
                'account_security',
                'user_invitations',
                'system_announcements',
            ]);
    });

    it('isNotificationEnabled works with no saved preferences', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => null,
        ]);

        // Defaults: account_security email=true
        expect($user->isNotificationEnabled('account_security', 'email'))->toBeTrue()
            // Defaults: document_uploads email=false
            ->and($user->isNotificationEnabled('document_uploads', 'email'))->toBeFalse();
    });
});

describe('EmailNotificationController', function () {
    it('renders notification preferences page with all data', function () {
        $user = createUserWithRole('admin');

        $response = $this->actingAs($user)->get('/settings/email-notification');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/email-notification')
            ->has('email_notifications_enabled')
            ->has('notification_preferences')
            ->has('categories')
        );
    });

    it('updates master email toggle', function () {
        $user = createUserWithRole('admin', ['email_notifications_enabled' => true]);

        $response = $this->actingAs($user)->patch('/settings/email-notification', [
            'email_notifications_enabled' => false,
        ]);

        $response->assertRedirect();
        expect($user->fresh()->email_notifications_enabled)->toBeFalse();
    });

    it('updates granular notification preferences', function () {
        $user = createUserWithRole('admin', ['email_notifications_enabled' => true]);

        $response = $this->actingAs($user)->patch('/settings/email-notification', [
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => false, 'push' => true],
                'account_security' => ['email' => true, 'push' => false],
            ],
        ]);

        $response->assertRedirect();
        $user->refresh();
        expect($user->notification_preferences['procurement_stage_updates']['email'])->toBeFalse()
            ->and($user->notification_preferences['account_security']['push'])->toBeFalse();
    });

    it('validates email_notifications_enabled is required', function () {
        $user = createUserWithRole('admin');

        $response = $this->actingAs($user)->patch('/settings/email-notification', []);

        $response->assertSessionHasErrors('email_notifications_enabled');
    });

    it('rejects invalid preference structure', function () {
        $user = createUserWithRole('admin');

        $response = $this->actingAs($user)->patch('/settings/email-notification', [
            'email_notifications_enabled' => true,
            'notification_preferences' => 'not-an-array',
        ]);

        $response->assertSessionHasErrors('notification_preferences');
    });
});
