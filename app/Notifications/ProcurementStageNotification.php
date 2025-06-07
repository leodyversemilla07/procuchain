<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Handles procurement stage update notifications for stakeholders
 *
 * This notification class manages the delivery of procurement-related updates
 * to BAC Chairman and HOPE users through configurable channels (mail/database).
 * It formats and delivers notifications about document uploads, status changes,
 * and stage transitions in the procurement workflow.
 *
 * Implements Laravel's queued notifications for asynchronous delivery.
 */
class ProcurementStageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification data containing procurement details and update information
     *
     * @var array
     */
    protected $data;

    /**
     * Creates a new notification instance
     *
     * @param  array  $data  Procurement update data including:
     *                       - procurement_id: Unique identifier
     *                       - procurement_title: Title of the procurement
     *                       - stage_identifier: Current workflow stage
     *                       - current_status: Current status
     *                       - timestamp: Update timestamp
     *                       - action_type: Type of update (uploaded/submitted/etc.)
     *                       - document_count: Number of documents (optional)
     *                       - next_stage: Next workflow stage (for transitions)
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels
     *
     * Currently configured for email and database notifications.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<int, string> Active notification channels
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Generate the role-specific URL for procurement details
     *
     * Creates a URL to the procurement details page based on the user's role,
     * ensuring users are directed to their appropriate dashboard views.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return string The role-specific procurement URL
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
     * Format the action type into a human-readable message
     *
     * Maps action types to past-tense verbs for notification messages.
     * Examples: submitted -> "has been submitted"
     *
     * @param  string  $actionType  The type of action that occurred
     * @return string Formatted action description
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
     * Generate the email representation of the notification
     *
     * Creates a detailed email notification using a custom Blade template with:
     * - Custom styling and layout
     * - Action description and document count
     * - Stage transition information if applicable
     * - Procurement details and status
     * - Call-to-action button linking to details
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return MailMessage The formatted email message
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
     * Get the array representation of the notification
     *
     * Used for API responses and general data access.
     * Includes all relevant procurement update information.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<string, mixed> Notification data array
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
     * Get the database representation of the notification
     *
     * Formats notification data for database storage with:
     * - Title and descriptive message
     * - Procurement details and timestamps
     * - Stage transition information if applicable
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return DatabaseMessage The formatted database notification
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        // Get the role-specific URL for this user
        $url = $this->getRoleSpecificUrl($notifiable);

        $actionText = $this->formatActionType($this->data['action_type'] ?? 'updated');

        $title = $this->data['stage_identifier'].' Update';
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
