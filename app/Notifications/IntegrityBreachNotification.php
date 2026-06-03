<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\BreachTypeEnums;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Integrity Breach Notification
 *
 * Notifies administrators, BAC Chairman, and HOPE when a data integrity
 * breach is detected in the procurement mirror. Supports database,
 * WebPush, and email channels.
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

    /**
     * Severity mapping from breach type.
     *
     * @var array<string, string>
     */
    private const SEVERITY_MAP = [
        'hash_mismatch' => 'critical',
        'content_mismatch' => 'critical',
        'user_address_tampered' => 'high',
        'unauthorized_publisher' => 'medium',
        'row_deleted' => 'low',
    ];

    /**
     * @param string $breachType The breach type (BreachTypeEnums value)
     * @param string $stream The blockchain stream where the breach was detected
     * @param string $streamKey The stream key (e.g. PR number)
     * @param string $txid The transaction ID
     * @param array $breachData Additional context about the breach
     * @param int|null $mirrorId The procurement_mirror record ID
     */
    public function __construct(
        private readonly string $breachType,
        private readonly string $stream,
        private readonly string $streamKey,
        private readonly string $txid,
        private readonly array $breachData = [],
        private readonly ?int $mirrorId = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        // Always send email for critical and high severity
        $severity = $this->severity();

        if ($severity === 'critical' || $severity === 'high' || ($notifiable->email_notifications_enabled ?? false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severity = $this->severity();
        $displayName = $this->breachDisplayName();
        $isCritical = $severity === 'critical' || $severity === 'high';

        $mail = (new MailMessage)
            ->subject("[ProcuChain Security] {$displayName} — {$this->streamKey}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A data integrity breach has been detected in the procurement mirror system.')
            ->line("**Breach Type:** {$displayName}")
            ->line("**Severity:** " . ucfirst($severity))
            ->line("**Stream:** {$this->stream}")
            ->line("**PR Number:** {$this->streamKey}")
            ->line("**Transaction ID:** `{$this->txid}`");

        if (! empty($this->breachData)) {
            $mail->line('**Breach Details:**');

            foreach ($this->breachData as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $mail->line("- **{$key}:** {$value}");
                }
            }
        }

        if ($isCritical) {
            $mail->line('⚠ **This is a critical security event.** The data in the procurement mirror does not match the blockchain — this may indicate database tampering. Immediate review is required.');
        }

        $mail->action('View Integrity Breaches', route('admin.integrity-breaches.index'))
            ->line('Run `php artisan blockchain:repair ' . $this->streamKey . '` to auto-repair from the blockchain.');

        return $mail;
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $displayName = $this->breachDisplayName();

        return new DatabaseMessage([
            'title' => "Integrity Breach: {$displayName}",
            'message' => "Breach detected in {$this->stream} for PR {$this->streamKey}. Severity: " . ucfirst($this->severity()),
            'breach_type' => $this->breachType,
            'stream' => $this->stream,
            'stream_key' => $this->streamKey,
            'txid' => $this->txid,
            'severity' => $this->severity(),
            'mirror_id' => $this->mirrorId,
            'action_type' => 'integrity_breach',
        ]);
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $displayName = $this->breachDisplayName();
        $severity = $this->severity();

        $body = match ($severity) {
            'critical' => "CRITICAL: {$displayName} detected for PR {$this->streamKey}. Data may have been tampered.",
            'high' => "HIGH: {$displayName} detected for PR {$this->streamKey}. Identity data may be compromised.",
            'medium' => "Unauthorized publisher detected in {$this->stream} for PR {$this->streamKey}.",
            default => "Breach detected in {$this->stream} for PR {$this->streamKey}.",
        };

        return (new WebPushMessage)
            ->title("ProcuChain Security Alert: {$displayName}")
            ->body($body)
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->tag("breach-{$this->streamKey}-{$this->txid}")
            ->data([
                'breach_type' => $this->breachType,
                'stream' => $this->stream,
                'stream_key' => $this->streamKey,
                'txid' => $this->txid,
                'severity' => $severity,
                'action_type' => 'integrity_breach',
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
