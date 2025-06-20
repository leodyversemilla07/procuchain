<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for procurement stage updates sent to stakeholders via email and database
 */
class ProcurementStageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification data
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new notification instance
     *
     * @param  array  $data  Procurement update data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Generate role-specific URL for procurement details
     *
     * @param  object  $notifiable
     * @return string
     */
    protected function getRoleSpecificUrl(object $notifiable): string
    {
        $id = $this->data['procurement_id'];

        // Generate URL based on user role
        switch ($notifiable->role) {
            case 'bac_chairman':
                return url("/bac-chairman/procurements-list/{$id}");
            case 'hope':
                return url("/hope/procurements-list/{$id}");
            case 'bac_secretariat':
                return url("/bac-secretariat/procurements-list/{$id}");
            case 'admin':
                return url("/admin/procurements-list/{$id}");
            default:
                return url("/procurements/{$id}");
        }
    }

    /**
     * Format action type into human-readable message
     *
     * @param  string  $actionType
     * @return string
     */
    protected function formatActionType(string $actionType): string
    {
        switch (strtolower($actionType)) {
            case 'submitted':
                return 'has been submitted';
            case 'uploaded':
                return 'has been uploaded';
            case 'completed':
                return 'has been completed';
            case 'published':
                return 'has been published';
            case 'opened':
                return 'have been opened';
            case 'evaluated':
                return 'have been evaluated';
            case 'verified':
                return 'has been verified';
            case 'failed':
                return 'has failed verification';
            case 'recorded':
                return 'has been recorded';
            case 'awarded':
                return 'has been awarded';
            case 'started':
                return 'has begun';
            case 'held':
                return 'was held';
            case 'skipped':
                return 'was skipped';
            default:
                return 'has been updated';
        }
    }

    /**
     * Generate email notification
     *
     * @param  object  $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->getRoleSpecificUrl($notifiable);
        $formattedAction = $this->formatActionType($this->data['action_type'] ?? 'updated');
        $documentCount = $this->data['document_count'] ?? 0;

        $subject = "Procurement Update: {$this->data['stage_identifier']} - {$this->data['procurement_title']}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.procurement-notification', [
                'notifiable' => $notifiable,
                'subject' => $subject,
                'procurementId' => $this->data['procurement_id'],
                'procurementTitle' => $this->data['procurement_title'],
                'stageIdentifier' => $this->data['stage_identifier'],
                'currentStatus' => $this->data['current_status'],
                'timestamp' => $this->data['timestamp'],
                'actionType' => $this->data['action_type'] ?? 'updated',
                'formattedAction' => $formattedAction,
                'documentCount' => $documentCount,
                'nextStage' => $this->data['next_stage'] ?? null,
                'actionUrl' => $url,
            ]);
    }

    /**
     * Get array representation of notification
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $data = [
            'procurement_id' => $this->data['procurement_id'],
            'procurement_title' => $this->data['procurement_title'],
            'stage_identifier' => $this->data['stage_identifier'],
            'current_status' => $this->data['current_status'],
            'timestamp' => $this->data['timestamp'],
            'document_count' => $this->data['document_count'] ?? 0,
            'action_type' => $this->data['action_type'] ?? 'updated',
        ];

        // Include next stage information if available
        if (! empty($this->data['next_stage'])) {
            $data['next_stage'] = $this->data['next_stage'];
            $data['next_stage_timestamp'] = $this->data['next_timestamp'] ?? null;
        }

        return $data;
    }

    /**
     * Get database representation of notification
     *
     * @param  object  $notifiable
     * @return DatabaseMessage
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        // Get the role-specific URL for this user
        $url = $this->getRoleSpecificUrl($notifiable);

        $actionText = $this->formatActionType($this->data['action_type'] ?? 'updated');

        $title = $this->data['stage_identifier'] . ' Update';
        $message = "The {$this->data['stage_identifier']} stage {$actionText} for \"{$this->data['procurement_title']}\". Current status: {$this->data['current_status']}";

        // Add stage transition info to the message if applicable
        if (! empty($this->data['next_stage'])) {
            $message .= ". The procurement is now moving to the {$this->data['next_stage']} stage.";
            $title = "Stage Transition: {$this->data['stage_identifier']} to {$this->data['next_stage']}";
        }

        $data = [
            'title' => $title,
            'message' => $message,
            'procurement_id' => $this->data['procurement_id'],
            'procurement_title' => $this->data['procurement_title'],
            'stage_identifier' => $this->data['stage_identifier'],
            'current_status' => $this->data['current_status'],
            'timestamp' => $this->data['timestamp'],
            'document_count' => $this->data['document_count'] ?? 0,
            'action_type' => $this->data['action_type'] ?? 'updated',
            'url' => $url,
        ];

        // Add next stage info if available
        if (! empty($this->data['next_stage'])) {
            $data['next_stage'] = $this->data['next_stage'];
            $data['next_stage_timestamp'] = $this->data['next_timestamp'] ?? null;
        }

        return new DatabaseMessage($data);
    }
}
