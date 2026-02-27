<?php

namespace App\Listeners;

use App\Events\AccountUnlocked;
use App\Mail\AccountUnlockedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountUnlockedNotification
{
    public function handle(AccountUnlocked $event): void
    {
        if (! $event->user->email_notifications_enabled) {
            return;
        }

        try {
            Mail::to($event->user->email)->send(new AccountUnlockedMail(
                $event->user,
                $event->reason,
                $event->isAutoUnlock,
                $event->unlockedBy,
            ));

            Log::info('Account unlocked notification sent', [
                'user_id' => $event->user->id,
                'auto_unlock' => $event->isAutoUnlock,
                'unlocked_by' => $event->unlockedBy,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send account unlocked notification', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
