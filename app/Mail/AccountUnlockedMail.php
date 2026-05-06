<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountUnlockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $unlockReason;

    public bool $wasAutoUnlocked;

    public string $unlockedBy;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $unlockReason = 'Account unlocked', bool $wasAutoUnlocked = false, string $unlockedBy = 'system')
    {
        $this->user = $user;
        $this->unlockReason = $unlockReason;
        $this->wasAutoUnlocked = $wasAutoUnlocked;
        $this->unlockedBy = $unlockedBy;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Security Update - Account Unlocked',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-unlocked',
            with: [
                'user' => $this->user,
                'unlockReason' => $this->unlockReason,
                'wasAutoUnlocked' => $this->wasAutoUnlocked,
                'unlockedBy' => $this->unlockedBy,
                'loginUrl' => route('login'),
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
