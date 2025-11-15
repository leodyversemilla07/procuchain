<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Notify users about procurement stage updates.
     *
     * @param  string  $pr_number  The procurement ID
     * @param  string  $procurementTitle  The procurement title
     * @param  string  $stageIdentifier  The stage identifier
     * @param  string  $currentStatus  The current status
     * @param  string  $timestamp  The timestamp
     * @param  string  $actionType  The action type
     * @param  int  $documentCount  The document count
     * @param  bool  $stageTransition  Whether this is a stage transition
     * @param  string  $nextStage  The next stage (if transitioning)
     * @param  array  $rolesToNotify  Roles to notify (defaults to bac_chairman, hope, admin)
     */
    public function notifyStageUpdate(
        string $pr_number,
        string $procurementTitle,
        string $stageIdentifier,
        string $currentStatus,
        string $timestamp,
        string $actionType,
        int $documentCount = 0,
        bool $stageTransition = false,
        string $nextStage = '',
        array $rolesToNotify = ['bac_chairman', 'hope', 'admin']
    ): void {
        // Use Spatie's whereHas helper to query users with specific roles
        // Note: We can't use User::role() directly due to conflict with role attribute accessor
        $usersToNotify = User::whereHas('roles', function ($query) use ($rolesToNotify) {
            $query->whereIn('name', $rolesToNotify);
        })->get();

        if ($usersToNotify->isEmpty()) {
            Log::warning('No users found with specified roles to notify for procurement update', [
                'pr_number' => $pr_number,
                'roles' => $rolesToNotify,
            ]);

            return;
        }

        $notificationData = [
            'pr_number' => $pr_number,
            'procurement_title' => $procurementTitle,
            'stage_identifier' => $stageIdentifier,
            'current_status' => $currentStatus,
            'timestamp' => $timestamp,
            'action_type' => $actionType,
            'document_count' => $documentCount, // Pass document_count
        ];

        if ($stageTransition && ! empty($nextStage)) {
            $notificationData['next_stage'] = $nextStage;
            $notificationData['transition_message'] = "This procurement will now proceed to the {$nextStage} stage.";
        }

        Notification::send($usersToNotify, new ProcurementStageNotification($notificationData));
        Log::info('Procurement stage update notification sent', [
            'pr_number' => $pr_number,
            'stage' => $stageIdentifier,
            'next_stage' => $stageTransition ? $nextStage : 'none',
            'roles_notified' => $rolesToNotify,
            'recipients_count' => $usersToNotify->count(),
        ]);
    }
}
