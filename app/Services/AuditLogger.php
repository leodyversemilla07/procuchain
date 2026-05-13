<?php

namespace App\Services;

use App\Contracts\EventPublisherInterface;
use App\Contracts\NotificationServiceInterface;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /** Action labels for human-readable descriptions. */
    private const ACTION_LABELS = [
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.deleted' => 'User deleted',
        'user.bulk_deleted' => 'Bulk user deletion',
        'user.password_reset_sent' => 'Password reset sent',
        'account.locked' => 'Account locked',
        'account.unlocked' => 'Account unlocked',
        'account.attempts_reset' => 'Login attempts reset',
        'account.bulk_unlocked' => 'Bulk accounts unlocked',
        'account.bulk_attempts_reset' => 'Bulk attempts reset',
    ];

    public function __construct(
        protected Request $request,
        protected EventPublisherInterface $events,
        protected NotificationServiceInterface $notifications,
    ) {}

    /**
     * Record an audit log entry to both MySQL and the blockchain.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        // 1. Always write to MySQL (fast, reliable cache of the record)
        $log = null;
        try {
            $log = AuditLog::create([
                'user_id' => $this->request->user()?->id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'old_values' => empty($oldValues) ? null : $oldValues,
                'new_values' => empty($newValues) ? null : $newValues,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('AuditLogger: failed to write MySQL audit entry', [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Publish to blockchain (immutable record — best effort, never blocks)
        try {
            $user = $this->request->user();
            $userAddress = $user?->blockchain_address ?? '';
            $userName = $user?->name ?? 'System';
            $details = $this->buildBlockchainDetails($action, $subjectType, $subjectId, $oldValues, $newValues);

            $this->events->publish(
                prNumber: 'system',
                procurementTitle: 'System Administration',
                stage: 'administration',
                eventType: $action,
                category: 'system',
                severity: 'info',
                details: $details,
                documentCount: 0,
                userAddress: $userAddress,
                metadata: [
                    'action' => $action,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'actor_name' => $userName,
                    'mysql_log_id' => $log?->id,
                ],
            );
        } catch (\Exception $e) {
            // Never let blockchain publishing break the primary request flow
            Log::warning('AuditLogger: failed to publish to blockchain (non-critical)', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Send notifications for critical events
        $this->notifyForCriticalAction($action, $subjectType, $subjectId, $oldValues, $newValues);
    }

    /**
     * Send notifications for critical audit events (account security, user management).
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function notifyForCriticalAction(
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        array $oldValues,
        array $newValues
    ): void {
        $criticalActions = [
            'account.locked',
            'account.bulk_unlocked',
            'account.bulk_attempts_reset',
            'user.deleted',
            'user.bulk_deleted',
        ];

        if (! in_array($action, $criticalActions, true)) {
            return;
        }

        try {
            $user = $this->request->user();
            $actorName = $user?->name ?? 'System';
            $details = $this->buildBlockchainDetails($action, $subjectType, $subjectId, $oldValues, $newValues);

            $this->notifications->notifyAuditEvent(
                action: $action,
                actorName: $actorName,
                subjectType: $subjectType,
                subjectId: $subjectId,
                details: $details,
                timestamp: now()->toIso8601String(),
            );
        } catch (\Exception $e) {
            Log::warning('AuditLogger: failed to send notification for critical action', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildBlockchainDetails(
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        array $oldValues,
        array $newValues
    ): string {
        $label = self::ACTION_LABELS[$action] ?? str_replace('_', ' ', $action);
        $subject = $subjectType ? "{$subjectType}" : '';

        if ($subjectId) {
            $subject .= " #{$subjectId}";
        }

        $base = $subject ? "{$label}: {$subject}" : $label;

        // Add changed field details
        if (! empty($newValues) && ! empty($oldValues)) {
            $changes = [];
            foreach ($newValues as $key => $newVal) {
                $oldVal = $oldValues[$key] ?? null;
                if ($oldVal !== $newVal) {
                    $oldDisplay = is_scalar($oldVal) ? (string) $oldVal : json_encode($oldVal);
                    $newDisplay = is_scalar($newVal) ? (string) $newVal : json_encode($newVal);
                    $changes[] = "{$key}: '{$oldDisplay}' → '{$newDisplay}'";
                }
            }

            if (! empty($changes)) {
                $base .= ' — '.implode('; ', $changes);
            }
        } elseif (! empty($newValues)) {
            $base .= ' — '.json_encode($newValues);
        }

        return $base;
    }
}
