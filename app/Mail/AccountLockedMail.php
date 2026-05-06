<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $lockReason;

    public ?string $lockDuration;

    public string $unlockTime;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $lockReason, ?string $lockDuration = null)
    {
        $this->user = $user;
        $this->lockReason = $lockReason;
        $this->lockDuration = $lockDuration;
        $this->unlockTime = $user->lock_expires_at
            ? $user->lock_expires_at->format('F j, Y \a\t g:i A')
            : 'Unknown';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Security Alert - Account Locked',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-locked',
            with: [
                'user' => $this->user,
                'lockReason' => $this->lockReason,
                'lockDuration' => $this->lockDuration,
                'unlockTime' => $this->unlockTime,
                'supportEmail' => config('mail.support_email', 'support@procuchain.local'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
