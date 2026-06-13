<?php

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifies the submitting user when a BlockchainWriteJob exhausts all retries and fails
 * permanently. This is the safety-net notification ensuring no silent data loss.
 */
class BlockchainJobFailedNotification extends Notification
{
    use Queueable;

    /**
     * Human-readable labels for each blockchain operation type.
     *
     * @var array<string, string>
     */
    private const OPERATION_LABELS = [
        'upload_document' => 'Document Upload',
        'initiate_procurement' => 'Procurement Initiation',
        'mark_stage_complete' => 'Stage Completion',
        'skip_stage' => 'Stage Skip',
        'repeat_stage' => 'Stage Repeat',
        'correct_document' => 'Document Correction',
        'correct_procurement' => 'Procurement Correction',
        'update_delivery_details' => 'Delivery Details Update',
        'publish_decision' => 'Decision Publication',
    ];

    public function __construct(
        private readonly string $operation,
        private readonly string $prNumber,
        private readonly string $jobId,
        private readonly string $errorMessage,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        if ($notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $operationLabel = $this->operationLabel();

        return (new MailMessage)
            ->error()
            ->subject("Action Required: {$operationLabel} Failed — {$this->prNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A blockchain operation you submitted has failed permanently after all retry attempts.')
            ->line("**Operation:** {$operationLabel}")
            ->line("**Procurement Number:** {$this->prNumber}")
            ->line("**Job ID:** {$this->jobId}")
            ->line('Please re-submit the action. If the problem persists, contact your system administrator and provide the Job ID above.')
            ->action('Go to Procurement', $this->procurementUrl($notifiable))
            ->line('This failure has been logged for system administrators to review.');
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $operationLabel = $this->operationLabel();

        return new DatabaseMessage([
            'title' => "Blockchain Operation Failed: {$operationLabel}",
            'message' => "Your \"{$operationLabel}\" action for procurement {$this->prNumber} failed permanently. Please re-submit.",
            'pr_number' => $this->prNumber,
            'operation' => $this->operation,
            'job_id' => $this->jobId,
            'action_type' => 'failed',
            'url' => $this->procurementUrl($notifiable),
        ]);
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $operationLabel = $this->operationLabel();
        $url = $this->procurementUrl($notifiable);

        return (new WebPushMessage)
            ->title("ProcuChain: {$operationLabel} Failed")
            ->body("Your \"{$operationLabel}\" for {$this->prNumber} failed. Please re-submit.")
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->data([
                'pr_number' => $this->prNumber,
                'operation' => $this->operation,
                'job_id' => $this->jobId,
                'action_type' => 'failed',
                'url' => $url,
            ])
            ->action('View Procurement', $url)
            ->requireInteraction(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Blockchain Operation Failed: {$this->operationLabel()}",
            'pr_number' => $this->prNumber,
            'operation' => $this->operation,
            'job_id' => $this->jobId,
            'action_type' => 'failed',
        ];
    }

    private function operationLabel(): string
    {
        return self::OPERATION_LABELS[$this->operation] ?? ucwords(str_replace('_', ' ', $this->operation));
    }

    private function procurementUrl(object $notifiable): string
    {
        $role = $notifiable->primary_role;

        return match ($role) {
            UserRole::BAC_CHAIRMAN->value => url("/bac-chairman/procurements-list/{$this->prNumber}"),
            UserRole::HOPE->value => url("/hope/procurements-list/{$this->prNumber}"),
            UserRole::ADMIN->value => url("/admin/procurements-list/{$this->prNumber}"),
            default => url("/bac-secretariat/procurements-list/{$this->prNumber}"),
        };
    }
}
