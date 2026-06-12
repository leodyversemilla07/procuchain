<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\BreachTypeEnums;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Integrity Breach Notification
 *
 * Notifies administrators, BAC Chairman, and HOPE when a data integrity
 * breach is detected in the procurement mirror. Supports database,
 * WebPush, and email channels.
 *
 * Uses Blade template for emails and respects user notification preferences.
 *
 * Severity levels:
 * - critical: hash_mismatch, content_mismatch (data was tampered)
 * - high: user_address_tampered (identity anchor compromised)
 * - medium: unauthorized_publisher (suspicious activity)
 * - low: row_deleted (data loss, potentially accidental)
 */
class IntegrityBreachNotification extends Notification
{
    use Queueable;

    /** Severity mapping from breach type. */
    private const SEVERITY_MAP = [
        'hash_mismatch' => 'critical',
        'content_mismatch' => 'critical',
        'user_address_tampered' => 'high',
        'unauthorized_publisher' => 'medium',
        'unauthorized_record' => 'critical',
        'row_deleted' => 'low',
        'digest_summary' => 'medium',
    ];

    /** @var array<string, string> Severity to alert box class mapping */
    private const SEVERITY_ALERT_CLASS = [
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'info',
    ];

    /** @var array<string, string> Severity icons */
    private const SEVERITY_ICONS = [
        'critical' => '[CRITICAL]',
        'high' => '[HIGH]',
        'medium' => '[MEDIUM]',
        'low' => '[LOW]',
    ];

    /**
     * @param  string  $breachType  The breach type (BreachTypeEnums value)
     * @param  string  $stream  The blockchain stream where the breach was detected
     * @param  string  $streamKey  The stream key (e.g. PR number)
     * @param  string  $txid  The transaction ID
     * @param  array  $breachData  Additional context about the breach
     * @param  int|null  $recordId  The procurement record ID
     * @param  string|null  $runId  The verification run ID
     * @param  array|null  $fieldDiffs  Field-level differences
     * @param  bool  $isDigest  Whether this is a daily digest notification
     * @param  array|null  $violations  For digest: array of violation summaries
     * @param  array|null  $summary  For digest: severity counts
     */
    public function __construct(
        private readonly string $breachType,
        private readonly string $stream,
        private readonly string $streamKey,
        private readonly string $txid,
        private readonly array $breachData = [],
        private readonly ?int $recordId = null,
        private readonly ?string $runId = null,
        private readonly ?array $fieldDiffs = null,
        private readonly bool $isDigest = false,
        private readonly ?array $violations = null,
        private readonly ?array $summary = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Respects user notification preferences and config settings.
     */
    public function via(object $notifiable): array
    {
        // Database is always included
        $channels = ['database'];

        // Check if email is enabled in config
        if (! Config::get('integrity.breach_notifications.email_enabled', true)) {
            return array_merge($channels, [WebPushChannel::class]);
        }

        // Check min severity threshold from config
        $minSeverity = Config::get('integrity.breach_notifications.min_severity', 'high');
        $severity = $this->severity();
        $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

        if (($severityOrder[$severity] ?? 0) < ($severityOrder[$minSeverity] ?? 2)) {
            return array_merge($channels, [WebPushChannel::class]);
        }

        // Check user preferences for 'integrity_breach' event type
        $prefService = app(NotificationPreferenceService::class);
        if ($prefService->isEnabled($notifiable, 'integrity_breach', 'email')) {
            $channels[] = 'mail';
        }

        if ($prefService->isEnabled($notifiable, 'integrity_breach', 'push')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $severity = $this->severity();
        $displayName = $this->breachDisplayName();
        $isCritical = in_array($severity, ['critical', 'high'], true);

        $mail = (new MailMessage)
            ->subject("[ProcuChain Security] {$displayName} — {$this->streamKey}")
            ->view('emails.integrity-breach', [
                'notifiable' => $notifiable,
                'subject' => "[ProcuChain Security] {$displayName} — {$this->streamKey}",
                'severity' => $severity,
                'severityClass' => self::SEVERITY_ALERT_CLASS[$severity] ?? 'info',
                'severityIcon' => self::SEVERITY_ICONS[$severity] ?? '[INFO]',
                'displayName' => $displayName,
                'stream' => $this->stream,
                'streamKey' => $this->streamKey,
                'txid' => $this->txid,
                'runId' => $this->runId,
                'detectedAt' => now()->format('F j, Y \a\t g:i A'),
                'fieldDiffs' => $this->fieldDiffs ?? [],
                'breachData' => $this->breachData,
                'isCritical' => $isCritical,
                'isDigest' => $this->isDigest,
                'violations' => $this->violations ?? [],
                'summary' => $this->summary ?? [],
                'date' => now()->format('F j, Y'),
                'actionUrl' => route('admin.integrity-breaches.index'),
                'repairCommand' => "php artisan blockchain:repair {$this->streamKey}",
            ]);

        return $mail;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $displayName = $this->breachDisplayName();

        return new DatabaseMessage([
            'title' => "Integrity Breach: {$displayName}",
            'message' => "Breach detected in {$this->stream} for PR {$this->streamKey}. Severity: ".ucfirst($this->severity()),
            'breach_type' => $this->breachType,
            'stream' => $this->stream,
            'stream_key' => $this->streamKey,
            'txid' => $this->txid,
            'severity' => $this->severity(),
            'record_id' => $this->recordId,
            'verification_run_id' => $this->runId,
            'is_digest' => $this->isDigest,
            'action_type' => 'integrity_breach',
        ]);
    }

    /**
     * Get the WebPush representation of the notification.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        $displayName = $this->breachDisplayName();
        $severity = $this->severity();
        $isDigest = $this->isDigest;

        if ($isDigest) {
            $total = $this->summary['total'] ?? count($this->violations ?? []);
            $critical = $this->summary['critical'] ?? 0;
            $body = "Daily Integrity Digest: {$total} breach(es) detected ({$critical} critical).";
        } else {
            $body = match ($severity) {
                'critical' => "CRITICAL: {$displayName} detected for PR {$this->streamKey}. Data may have been tampered.",
                'high' => "HIGH: {$displayName} detected for PR {$this->streamKey}. Identity data may be compromised.",
                'medium' => "Unauthorized publisher detected in {$this->stream} for PR {$this->streamKey}.",
                default => "Breach detected in {$this->stream} for PR {$this->streamKey}.",
            };
        }

        return (new WebPushMessage)
            ->title($isDigest ? 'ProcuChain Daily Integrity Digest' : "ProcuChain Security Alert: {$displayName}")
            ->body($body)
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->tag($isDigest ? 'breach-digest-'.now()->format('Y-m-d') : "breach-{$this->streamKey}-{$this->txid}")
            ->data([
                'breach_type' => $this->breachType,
                'stream' => $this->stream,
                'stream_key' => $this->streamKey,
                'txid' => $this->txid,
                'severity' => $severity,
                'action_type' => 'integrity_breach',
                'is_digest' => $isDigest,
                'url' => route('admin.integrity-breaches.index'),
            ]);
    }

    /**
     * Get the severity level for this breach type.
     */
    public function severity(): string
    {
        return self::SEVERITY_MAP[$this->breachType] ?? 'medium';
    }

    /**
     * Get the human-readable display name for the breach type.
     */
    private function breachDisplayName(): string
    {
        $enum = BreachTypeEnums::tryFrom($this->breachType);

        return $enum ? $enum->getDisplayName() : ucfirst(str_replace('_', ' ', $this->breachType));
    }
}
