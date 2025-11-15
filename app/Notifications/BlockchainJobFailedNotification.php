<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BlockchainJobFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $jobName,
        public int $pr_number,
        public string $procurementTitle,
        public string $errorMessage,
        public int $attemptNumber
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Blockchain Job Failed: {$this->jobName}")
            ->greeting('Blockchain Job Failure Alert')
            ->line("A blockchain job has permanently failed after {$this->attemptNumber} attempts.")
            ->line("**Job Name:** {$this->jobName}")
            ->line("**Procurement ID:** {$this->pr_number}")
            ->line("**Procurement Title:** {$this->procurementTitle}")
            ->line("**Error:** {$this->errorMessage}")
            ->action('View Procurement', url("/procurements/{$this->pr_number}"))
            ->line('Please investigate this issue as it may indicate problems with the blockchain connection or data integrity.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_name' => $this->jobName,
            'pr_number' => $this->pr_number,
            'procurement_title' => $this->procurementTitle,
            'error_message' => $this->errorMessage,
            'attempt_number' => $this->attemptNumber,
            'severity' => 'critical',
        ];
    }
}
