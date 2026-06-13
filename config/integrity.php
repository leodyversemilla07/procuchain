<?php

declare(strict_types=1);

use App\Enums\UserRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Breach Notification Settings
    |--------------------------------------------------------------------------
    |
    | Controls how and when integrity breach notifications are sent.
    |
    */

    'breach_notifications' => [

        /*
         | Enable/disable email notifications for breaches.
         | When false, only database + push notifications are sent.
         */
        'email_enabled' => env('INTEGRITY_BREACH_EMAIL_ENABLED', true),

        /*
         | Use daily digest instead of per-violation emails.
         | When true, violations are queued and sent as a single daily summary.
         */
        'digest_enabled' => env('INTEGRITY_BREACH_DIGEST_ENABLED', true),

        /*
         | Time to send daily digest (24-hour format).
         | Only used when digest_enabled is true.
         */
        'digest_time' => env('INTEGRITY_BREACH_DIGEST_TIME', '08:00'),

        /*
         | Cooldown period (in hours) before re-notifying about the same
         | breach type on the same PR after it was already reported.
         | Prevents email floods from repeated scheduled audits.
         */
        'cooldown_hours' => env('INTEGRITY_BREACH_COOLDOWN_HOURS', 24),

        /*
         | Recipient roles that receive breach notifications.
         | Can be customized to limit who gets alerts.
         */
        'recipient_roles' => [UserRole::ADMIN->value, UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value],

        /*
         | Minimum severity level to trigger notifications.
         | Options: critical, high, medium, low
         */
        'min_severity' => env('INTEGRITY_BREACH_MIN_SEVERITY', 'high'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Repair Settings
    |--------------------------------------------------------------------------
    |
    | Controls auto-repair behavior during integrity verification.
    |
    */

    'auto_repair' => [

        /*
         | Enable automatic repair of detected breaches.
         | When true, verifyAndRepair() will restore from blockchain.
         */
        'enabled' => env('INTEGRITY_AUTO_REPAIR_ENABLED', false),

        /*
         | Only auto-repair certain breach types.
         | Leave empty to repair all types.
         */
        'allowed_types' => [
            'hash_mismatch',
            'content_mismatch',
            'row_deleted',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Schedule
    |--------------------------------------------------------------------------
    |
    | Default schedule for automated integrity verification.
    |
    */

    'schedule' => [

        /*
         | Enable scheduled verification.
         */
        'enabled' => env('INTEGRITY_SCHEDULE_ENABLED', true),

        /*
         | Cron expression for full verification.
         | Default: 2 AM daily.
         */
        'cron' => env('INTEGRITY_SCHEDULE_CRON', '0 2 * * *'),

        /*
         | Run with deep publisher check (extra getrawtransaction calls).
         */
        'deep_publisher_check' => env('INTEGRITY_SCHEDULE_DEEP_CHECK', false),

    ],

];
