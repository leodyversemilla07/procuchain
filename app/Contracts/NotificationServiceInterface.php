<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\UserRole;

interface NotificationServiceInterface
{
    /**
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
        array $rolesToNotify = [UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value, UserRole::ADMIN->value]
    ): void;

    /**
     * @param  array<int, string>  $rolesToNotify  Roles to notify
     */
    public function notifyAuditEvent(
        string $action,
        string $actorName,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null,
        ?string $timestamp = null,
        array $rolesToNotify = [UserRole::ADMIN->value]
    ): void;
}
