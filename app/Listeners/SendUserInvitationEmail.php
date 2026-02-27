<?php

namespace App\Listeners;

use App\Events\UserInvited;
use App\Mail\UserInvitationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendUserInvitationEmail
{
    public function handle(UserInvited $event): void
    {
        try {
            Mail::to($event->invitation->email)->send(new UserInvitationMail(
                $event->invitation,
                $event->acceptUrl,
            ));

            Log::info('User invitation email sent', [
                'email' => $event->invitation->email,
                'invitation_id' => $event->invitation->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send user invitation email', [
                'email' => $event->invitation->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
