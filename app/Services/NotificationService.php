<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Handles procurement-related notifications in the system
 * 
 * This service manages sending notifications to relevant stakeholders
 * about procurement stage updates and transitions. It specifically
 * targets users with BAC Chairman or HOPE roles.
 *
 * Uses Laravel's built-in notification system to send messages through
 * configured channels (database and/or mail). Notifications are persisted
 * in the notifications table and can be marked as read/unread.
 */
class NotificationService
{
    /**
     * Sends notifications about procurement stage updates to relevant users
     *
     * Notifies BAC Chairman and HOPE users about:
     * - Document uploads
     * - Status changes
     * - Stage transitions
     * 
     * Uses Laravel's notification system to send messages to multiple
     * recipients. Logs notification attempts and any issues encountered.
     * Notifications are sent via channels configured in ProcurementStageNotification.
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @param string $stageIdentifier Current stage in the workflow
     * @param string $currentStatus Current status of the procurement
     * @param string $timestamp ISO 8601 formatted timestamp of the update
     * @param string $actionType Type of action that triggered the notification
     * @param bool $stageTransition Whether this update involves a stage transition
     * @param string $nextStage The next stage if this is a transition (optional)
     * @return void
     */
    public function notifyStageUpdate(
        string $procurementId,
        string $procurementTitle,
        string $stageIdentifier,
        string $currentStatus,
        string $timestamp,
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
            'action_type' => $actionType,
        ];

        if ($stageTransition && ! empty($nextStage)) {
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
