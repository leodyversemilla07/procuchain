<?php

namespace App\Listeners;

use App\Events\SuspiciousLoginDetected;
use App\Mail\NewLocationLoginAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLoginAnomalyAlert
{
    public function handle(SuspiciousLoginDetected $event): void
    {
        try {
            Mail::to($event->user->email)->queue(new NewLocationLoginAlert(
                user: $event->user,
                location: $event->location,
                ipAddress: $event->ipAddress,
                userAgent: $event->userAgent,
                loginTime: now(),
            ));

            Log::info('Login anomaly alert queued', [
                'user_id' => $event->user->id,
                'ip_address' => $event->ipAddress,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue login anomaly alert', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
