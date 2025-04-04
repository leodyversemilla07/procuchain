<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function notifyStageUpdate(
        string $procurementId,
        string $procurementTitle,
        string $stageIdentifier,
        string $currentStatus,
        string $timestamp,
        int $documentIndex,
        string $actionType,
        bool $stageTransition = false,
        string $nextStage = ''
    ): void {
        $usersToNotify = User::whereIn('role', ['bac_chairman', 'hope'])->get();
        if ($usersToNotify->isEmpty()) {
            Log::warning('No BAC Chairman or HOPE users found to notify for procurement update', [
                'procurement_id' => $procurementId,
            ]);
            return;
        }

        $notificationData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'stage_identifier' => $stageIdentifier,
            'current_status' => $currentStatus,
            'timestamp' => $timestamp,
            'document_index' => $documentIndex,
            'action_type' => $actionType,
        ];

        if ($stageTransition && !empty($nextStage)) {
            $notificationData['next_stage'] = $nextStage;
            $notificationData['transition_message'] = "This procurement will now proceed to the {$nextStage} stage.";
        }

        Notification::send($usersToNotify, new ProcurementStageNotification($notificationData));
        Log::info('Procurement stage update notification sent', [
            'procurement_id' => $procurementId,
            'stage' => $stageIdentifier,
            'next_stage' => $stageTransition ? $nextStage : 'none',
            'recipients_count' => $usersToNotify->count(),
        ]);
    }
}
