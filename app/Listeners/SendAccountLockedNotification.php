<?php

namespace App\Listeners;

use App\Events\AccountLocked;
use App\Mail\AccountLockedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountLockedNotification
{
    public function handle(AccountLocked $event): void
    {
        if (! $event->user->email_notifications_enabled) {
            return;
        }

        try {
            Mail::to($event->user->email)->send(new AccountLockedMail(
                $event->user,
                $event->reason,
                $event->duration,
            ));

            Log::info('Account locked notification sent', [
                'user_id' => $event->user->id,
                'reason' => $event->reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send account locked notification', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
