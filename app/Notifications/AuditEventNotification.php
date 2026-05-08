<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notification sent when a critical audit event occurs
 * (account locks, user deletions, bulk actions, etc.)
 */
class AuditEventNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected array $data
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->isNotificationEnabled('account_security', 'push')) {
            $channels[] = WebPushChannel::class;
        }

        if ($notifiable->isNotificationEnabled('account_security', 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Generate URL based on the audit event type.
     */
    protected function getAuditEventUrl(): string
    {
        $action = $this->data['action'];

        return match (true) {
            str_contains($action, 'account') => url('/admin/locked-accounts'),
            str_contains($action, 'user') => url('/admin/users'),
            default => url('/admin/audit-log'),
        };
    }

    /**
     * Get the role-based colour/severity for the notification.
     */
    protected function getSeverity(): string
    {
        $action = $this->data['action'];

        return match (true) {
            str_contains($action, 'deleted') => 'error',
            str_contains($action, 'locked') => 'warning',
            default => 'info',
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->getAuditEventUrl();
        $action = $this->data['action'];
        $actorName = $this->data['actor_name'] ?? 'System';
        $details = $this->data['details'] ?? '';

        $subject = "Security Alert: {$actorName} performed {$action}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.audit-event-notification', [
                'notifiable' => $notifiable,
                'subject' => $subject,
                'actorName' => $actorName,
                'action' => $action,
                'details' => $details,
                'severity' => $this->getSeverity(),
                'timestamp' => $this->data['timestamp'] ?? now()->toIso8601String(),
                'actionUrl' => $url,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'action' => $this->data['action'],
            'actor_name' => $this->data['actor_name'] ?? 'System',
            'subject_type' => $this->data['subject_type'] ?? null,
            'subject_id' => $this->data['subject_id'] ?? null,
            'details' => $this->data['details'] ?? '',
            'severity' => $this->getSeverity(),
            'timestamp' => $this->data['timestamp'] ?? now()->toIso8601String(),
            'type' => 'audit_event',
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $url = $this->getAuditEventUrl();
        $action = $this->data['action'];
        $actorName = $this->data['actor_name'] ?? 'System';
        $subjectType = $this->data['subject_type'] ?? '';
        $details = $this->data['details'] ?? '';

        $label = str_replace(['.', '_'], ' ', $action);
        $label = ucwords($label);

        $title = "Security Alert: {$label}";

        $message = "{$actorName} performed {$label}";
        if ($subjectType) {
            $subjectId = $this->data['subject_id'] ?? '';
            $message .= " on {$subjectType}";
            if ($subjectId) {
                $message .= " #{$subjectId}";
            }
        }
        if ($details) {
            $message .= ": {$details}";
        }

        return new DatabaseMessage([
            'title' => $title,
            'message' => $message,
            'action' => $action,
            'actor_name' => $actorName,
            'subject_type' => $subjectType,
            'subject_id' => $this->data['subject_id'] ?? null,
            'details' => $details,
            'severity' => $this->getSeverity(),
            'timestamp' => $this->data['timestamp'] ?? now()->toIso8601String(),
            'type' => 'audit_event',
            'url' => $url,
        ]);
    }

    /**
     * Get the WebPush representation of the notification.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        $url = $this->getAuditEventUrl();
        $action = $this->data['action'];
        $actorName = $this->data['actor_name'] ?? 'System';

        $label = str_replace(['.', '_'], ' ', $action);
        $label = ucwords($label);

        $title = "ProcuChain: {$label}";
        $body = "{$actorName} performed {$label} on the system";

        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->data([
                'action' => $action,
                'url' => $url,
                'type' => 'audit_event',
            ])
            ->action('View Details', $url)
            ->requireInteraction(true);
    }
}