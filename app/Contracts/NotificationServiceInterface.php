<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for notification services
 *
 * Implementations handle sending notifications to users about procurement events
 */
interface NotificationServiceInterface
{
    /**
     * Notify users about procurement stage updates
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
     * @param  array<int, string>  $rolesToNotify  Roles to notify
     */
    /**
     * Notify users about procurement stage updates
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
     * @param  array<int, string>  $rolesToNotify  Roles to notify
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
    ): void;

    /**
     * Notify admin users about audit events.
     *
     * @param  string  $action  The audit action name
     * @param  string  $actorName  Name of the user who performed the action
     * @param  string|null  $subjectType  Type of subject (user, account, etc.)
     * @param  string|null  $subjectId  ID of the subject
     * @param  string|null  $details  Human-readable details
     * @param  string|null  $timestamp  ISO timestamp
     * @param  array<int, string>  $rolesToNotify  Roles to notify
     */
    public function notifyAuditEvent(
        string $action,
        string $actorName,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null,
        ?string $timestamp = null,
        array $rolesToNotify = ['admin']
    ): void;
}
