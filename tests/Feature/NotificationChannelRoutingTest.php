<?php

use App\Models\User;
use App\Notifications\ProcurementCorrectionSubmitted;
use App\Notifications\ProcurementStageNotification;
use NotificationChannels\WebPush\WebPushChannel;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('ProcurementStageNotification channels', function () {
    it('includes email when procurement_stage_updates email is enabled', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => true, 'push' => false],
            ],
        ]);

        $notification = new ProcurementStageNotification([
            'pr_number' => 'PR-001',
            'procurement_title' => 'Test',
            'stage_identifier' => 'Bidding',
            'current_status' => 'Active',
            'timestamp' => now()->toISOString(),
        ]);

        $channels = $notification->via($user);

        expect($channels)->toContain('database')
            ->and($channels)->toContain('mail')
            ->and($channels)->not->toContain(WebPushChannel::class);
    });

    it('excludes email when master toggle is off', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => false,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => true, 'push' => true],
            ],
        ]);

        $notification = new ProcurementStageNotification([
            'pr_number' => 'PR-001',
            'procurement_title' => 'Test',
            'stage_identifier' => 'Bidding',
            'current_status' => 'Active',
            'timestamp' => now()->toISOString(),
        ]);

        $channels = $notification->via($user);

        expect($channels)->toContain('database')
            ->and($channels)->not->toContain('mail')
            ->and($channels)->toContain(WebPushChannel::class);
    });

    it('excludes all optional channels when both disabled', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_stage_updates' => ['email' => false, 'push' => false],
            ],
        ]);

        $notification = new ProcurementStageNotification([
            'pr_number' => 'PR-001',
            'procurement_title' => 'Test',
            'stage_identifier' => 'Bidding',
            'current_status' => 'Active',
            'timestamp' => now()->toISOString(),
        ]);

        $channels = $notification->via($user);

        expect($channels)->toBe(['database']);
    });
});

describe('ProcurementCorrectionSubmitted channels', function () {
    it('respects per-type preferences for corrections', function () {
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
            'notification_preferences' => [
                'procurement_corrections' => ['email' => false, 'push' => true],
            ],
        ]);

        $notification = new ProcurementCorrectionSubmitted([
            'pr_number' => 'PR-001',
            'procurement_title' => 'Test',
            'corrected_by' => 'Admin',
            'reason' => 'Typo',
            'changed_fields' => ['title'],
            'timestamp' => now()->toISOString(),
            'correction_txid' => 'tx123',
        ]);

        $channels = $notification->via($user);

        expect($channels)->toContain('database')
            ->and($channels)->not->toContain('mail')
            ->and($channels)->toContain(WebPushChannel::class);
    });
});
