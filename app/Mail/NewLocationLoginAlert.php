<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alert email sent when a user logs in from a new geographic location
 */
class NewLocationLoginAlert extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public array $location;

    public string $ipAddress;

    public ?string $userAgent;

    public Carbon $loginTime;

    public string $formattedLocation;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,
        array $location,
        string $ipAddress,
        ?string $userAgent,
        Carbon $loginTime
    ) {
        $this->user = $user;
        $this->location = $location;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->loginTime = $loginTime;
        $this->formattedLocation = $this->formatLocation($location);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Security Alert: New Login Location Detected - ProcuChain',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-location-login-alert',
            with: [
                'user' => $this->user,
                'location' => $this->location,
                'formattedLocation' => $this->formattedLocation,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'loginTime' => $this->loginTime->format('F j, Y \a\t g:i A T'),
                'supportEmail' => config('mail.support_email', 'support@procuchain.local'),
                'appName' => config('app.name', 'ProcuChain'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Format location for display
     */
    private function formatLocation(array $location): string
    {
        $parts = array_filter([
            $location['city'] ?? null,
            $location['region'] ?? null,
            $location['country'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'Unknown Location';
    }
}
