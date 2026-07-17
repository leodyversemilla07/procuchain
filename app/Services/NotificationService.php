<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AuditEventNotification;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
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
        array $rolesToNotify = [UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value, UserRole::ADMIN->value]
    ): void {
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
            'document_count' => $documentCount,
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

    public function notifyAuditEvent(
        string $action,
        string $actorName,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null,
        ?string $timestamp = null,
        array $rolesToNotify = [UserRole::ADMIN->value]
    ): void {
        $usersToNotify = User::whereHas('roles', function ($query) use ($rolesToNotify) {
            $query->whereIn('name', $rolesToNotify);
        })->get();

        if ($usersToNotify->isEmpty()) {
            Log::warning('No users found with specified roles to notify for audit event', [
                'action' => $action,
                'roles' => $rolesToNotify,
            ]);

            return;
        }

        $notificationData = [
            'action' => $action,
            'actor_name' => $actorName,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details ?? '',
            'timestamp' => $timestamp ?? now()->toIso8601String(),
        ];

        Notification::send($usersToNotify, new AuditEventNotification($notificationData));
        Log::info('Audit event notification sent', [
            'action' => $action,
            'actor_name' => $actorName,
            'recipients_count' => $usersToNotify->count(),
        ]);
    }
}
