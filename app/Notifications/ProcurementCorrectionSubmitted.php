<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notification sent when a procurement correction is submitted
 */
class ProcurementCorrectionSubmitted extends Notification
{
    use Queueable;

    /**
     * Correction data
     */
    protected array $correctionData;

    /**
     * Create a new notification instance
     */
    public function __construct(array $correctionData)
    {
        $this->correctionData = $correctionData;
    }

    /**
     * Get the notification's delivery channels
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        if ($notifiable->email_notifications_enabled ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Generate role-specific URL for procurement corrections
     */
    protected function getRoleSpecificUrl(object $notifiable): string
    {
        $prNumber = $this->correctionData['pr_number'];

        // Generate URL based on user role
        switch ($notifiable->getPrimaryRole()) {
            case 'bac_chairman':
                return url("/bac-chairman/procurements/{$prNumber}/corrections");
            case 'hope':
                return url("/hope/procurements/{$prNumber}/corrections");
            case 'bac_secretariat':
                return url("/bac-secretariat/procurements/{$prNumber}/corrections");
            case 'admin':
                return url("/admin/procurements/{$prNumber}/corrections");
            default:
                return url("/procurements/{$prNumber}/corrections");
        }
    }

    /**
     * Get the mail representation of the notification
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->getRoleSpecificUrl($notifiable);
        $prNumber = $this->correctionData['pr_number'];
        $procurementTitle = $this->correctionData['procurement_title'];
        $correctedBy = $this->correctionData['corrected_by'];
        $changedFields = $this->correctionData['changed_fields'];

        $subject = "Procurement Correction Submitted: {$prNumber} - {$procurementTitle}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.procurement-correction-submitted', [
                'notifiable' => $notifiable,
                'subject' => $subject,
                'prNumber' => $prNumber,
                'procurementTitle' => $procurementTitle,
                'correctedBy' => $correctedBy,
                'correctionReason' => $this->correctionData['reason'],
                'changedFields' => $changedFields,
                'timestamp' => $this->correctionData['timestamp'],
                'correctionTxId' => $this->correctionData['correction_txid'],
                'actionUrl' => $url,
            ]);
    }

    /**
     * Get the array representation of the notification
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pr_number' => $this->correctionData['pr_number'],
            'procurement_title' => $this->correctionData['procurement_title'],
            'corrected_by' => $this->correctionData['corrected_by'],
            'reason' => $this->correctionData['reason'],
            'changed_fields' => $this->correctionData['changed_fields'],
            'timestamp' => $this->correctionData['timestamp'],
            'correction_txid' => $this->correctionData['correction_txid'],
            'type' => 'procurement_correction_submitted',
        ];
    }

    /**
     * Get the database representation of the notification
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $url = $this->getRoleSpecificUrl($notifiable);
        $prNumber = $this->correctionData['pr_number'];
        $procurementTitle = $this->correctionData['procurement_title'];
        $correctedBy = $this->correctionData['corrected_by'];
        $changedFieldsCount = count($this->correctionData['changed_fields']);

        $title = 'Procurement Correction Submitted';
        $message = "{$correctedBy} submitted a correction for procurement {$prNumber} ({$procurementTitle}). {$changedFieldsCount} field(s) were corrected.";

        return new DatabaseMessage([
            'title' => $title,
            'message' => $message,
            'pr_number' => $prNumber,
            'procurement_title' => $procurementTitle,
            'corrected_by' => $correctedBy,
            'reason' => $this->correctionData['reason'],
            'changed_fields' => $this->correctionData['changed_fields'],
            'timestamp' => $this->correctionData['timestamp'],
            'correction_txid' => $this->correctionData['correction_txid'],
            'type' => 'procurement_correction_submitted',
            'url' => $url,
        ]);
    }

    /**
     * Get the WebPush representation of the notification
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        $url = $this->getRoleSpecificUrl($notifiable);
        $prNumber = $this->correctionData['pr_number'];
        $procurementTitle = $this->correctionData['procurement_title'];
        $correctedBy = $this->correctionData['corrected_by'];

        $title = 'ProcuChain: Procurement Correction';
        $body = "{$correctedBy} submitted a correction for {$prNumber} - {$procurementTitle}";

        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->data([
                'pr_number' => $prNumber,
                'procurement_title' => $procurementTitle,
                'corrected_by' => $correctedBy,
                'reason' => $this->correctionData['reason'],
                'changed_fields' => $this->correctionData['changed_fields'],
                'timestamp' => $this->correctionData['timestamp'],
                'correction_txid' => $this->correctionData['correction_txid'],
                'url' => $url,
                'type' => 'procurement_correction_submitted',
            ])
            ->action('View Details', $url)
            ->requireInteraction(true);
    }
}
