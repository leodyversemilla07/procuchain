<?php

namespace App\Services;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;

class NotificationPreferenceService
{
    /**
     * All valid event types.
     */
    public const EVENT_TYPES = [
        'procurement_stage_updates',
        'procurement_corrections',
        'document_uploads',
        'account_security',
        'user_invitations',
        'system_announcements',
        'integrity_breach',
    ];

    /**
     * All valid channels (database is always on, not configurable).
     */
    public const CHANNELS = ['email', 'push'];

    /**
     * Event type display metadata grouped by category.
     */
    public const CATEGORIES = [
        'Procurement' => [
            'procurement_stage_updates' => 'Stage transitions, completions, and status changes',
            'procurement_corrections' => 'Correction submissions and approvals',
            'document_uploads' => 'Document upload notifications',
        ],
        'Security' => [
            'account_security' => 'Account lock/unlock and suspicious login alerts',
            'integrity_breach' => 'Data integrity breach alerts (critical/high severity)',
        ],
        'System' => [
            'user_invitations' => 'User invitation emails',
            'system_announcements' => 'System-wide announcements from administrators',
        ],
    ];

    /**
     * Check if a notification is enabled for a user, event type, and channel.
     */
    public function isEnabled(User $user, string $eventType, string $channel): bool
    {
        return $user->isNotificationEnabled($eventType, $channel);
    }

    /**
     * Update a user's notification preferences.
     */
    public function updatePreferences(User $user, array $preferences): void
    {
        // Filter to only valid keys
        $filtered = [];
        foreach (self::EVENT_TYPES as $type) {
            if (isset($preferences[$type])) {
                $filtered[$type] = [
                    'email' => (bool) ($preferences[$type]['email'] ?? false),
                    'push' => (bool) ($preferences[$type]['push'] ?? false),
                ];
            }
        }

        $user->update(['notification_preferences' => $filtered]);
    }

    /**
     * Get the default preferences structure.
     */
    public function getDefaults(): array
    {
        return User::getDefaultNotificationPreferences();
    }

    /**
     * Get the merged preferences for a user (user overrides + defaults).
     */
    public function getMergedPreferences(User $user): array
    {
        return $user->getMergedNotificationPreferences();
    }

    /**
     * Get the full preferences payload for the frontend.
     */
    public function getPreferencesForFrontend(User $user): array
    {
        return [
            'email_notifications_enabled' => $user->email_notifications_enabled,
            'notification_preferences' => $this->getMergedPreferences($user),
            'categories' => self::CATEGORIES,
        ];
    }

    /**
     * Get the notification channels for a user based on their preferences.
     * Always includes 'database'. Conditionally adds 'mail' and WebPush.
     */
    public function getChannelsForEvent(User $user, string $eventType): array
    {
        $channels = ['database'];

        if ($this->isEnabled($user, $eventType, 'email')) {
            $channels[] = 'mail';
        }

        if ($this->isEnabled($user, $eventType, 'push')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }
}
