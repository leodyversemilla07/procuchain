<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Services\Publishers\EventPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Action labels for human-readable audit descriptions organized by NGPA domain.
     */
    private const ACTION_LABELS = [
        // User management (existing)
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.deleted' => 'User deleted',
        'user.bulk_deleted' => 'Bulk user deletion',
        'user.password_reset_sent' => 'Password reset sent',

        // Account security (existing)
        'account.locked' => 'Account locked',
        'account.unlocked' => 'Account unlocked',
        'account.attempts_reset' => 'Login attempts reset',
        'account.bulk_unlocked' => 'Bulk accounts unlocked',
        'account.bulk_attempts_reset' => 'Bulk attempts reset',

        // Procurement lifecycle — NGPA Sec. 2 (transparency), Sec. 7 (planning)
        'procurement.initiated' => 'Procurement initiated',
        'procurement.stage_completed' => 'Procurement stage completed',
        'procurement.stage_skipped' => 'Procurement stage skipped',
        'procurement.stage_repeated' => 'Procurement stage repeated',
        'procurement.document_uploaded' => 'Procurement document uploaded',
        'procurement.decision_published' => 'Procurement decision published',
        'procurement.pre_bid_decision_published' => 'Pre-bid decision published',
        'procurement.supplemental_bulletin_published' => 'Supplemental bid bulletin published',
        'procurement.delivery_updated' => 'Delivery details updated',
        'procurement.archived' => 'Procurement archived',
        'procurement.restored' => 'Procurement restored from archive',
        'procurement.corrected' => 'Procurement metadata corrected',

        // Document management — NGPA Sec. 20 (electronic records)
        'document.corrected' => 'Document correction applied',
        'document.downloaded' => 'Document downloaded',

        // Authentication — NGPA Sec. 3 (accountability)
        'auth.login' => 'User logged in',
        'auth.logout' => 'User logged out',
        'auth.password_reset' => 'Password reset completed',
        'auth.password_changed' => 'Password changed',
        'auth.password_reset_requested' => 'Password reset requested',
        'auth.invitation_accepted' => 'Invitation accepted',

        // Admin — NGPA Sec. 6 (standardization)
        'admin.invitation_sent' => 'User invitation sent',
        'admin.invitation_resent' => 'User invitation resent',
        'admin.invitation_revoked' => 'User invitation revoked',
        'admin.workflow_config_updated' => 'Workflow configuration updated',
        'admin.workflow_config_reset' => 'Workflow configuration reset to defaults',
        'admin.stage_document_config_updated' => 'Stage document configuration updated',
        'admin.stage_document_config_reset' => 'Stage document configuration reset to defaults',
        // Legacy aliases (kept for backward compatibility)
        'admin.stage_config_updated' => 'Stage document configuration updated',
        'admin.stage_config_reset' => 'Stage document configuration reset to defaults',

        // Settings
        'settings.profile_updated' => 'ProFile updated',
        'settings.password_changed' => 'Password changed',
        'settings.account_deleted' => 'Account deleted',
        'settings.two_factor_enabled' => 'Two-factor authentication enabled',
        'settings.two_factor_confirmed' => 'Two-factor authentication confirmed',
        'settings.two_factor_disabled' => 'Two-factor authentication disabled',

        // Blockchain node operations — NGPA Sec. 20 (electronic records), Sec. 3 (accountability)
        'node.full_purge' => 'Full node purge — all data removed',
        'node.file_purge' => 'File-level node purge',
        'node.resync' => 'Node resync — data restored from peers',

        // Security
        'security.ip_blocked' => 'IP address blocked',
        'security.ip_unblocked' => 'IP address unblocked',
        'security.notification_read' => 'Notification marked as read',
        'security.notifications_all_read' => 'All notifications marked as read',
    ];

    /**
     * Actions that trigger immediate notifications (critical for NGPA accountability).
     * These align with RA 12009 Sec. 2 (accountability) and Sec. 38 (transparency).
     */
    private const CRITICAL_ACTIONS = [
        // Account security
        'account.locked',
        'account.bulk_unlocked',
        'account.bulk_attempts_reset',
        'user.deleted',
        'user.bulk_deleted',

        // Procurement — high-value actions requiring oversight
        'procurement.initiated',
        'procurement.corrected',
        'procurement.archived',
        'procurement.restored',
        'procurement.decision_published',
        'procurement.pre_bid_decision_published',

        // Document integrity — NGPA Sec. 20
        'document.corrected',

        // Blockchain node operations — NGPA Sec. 3 (accountability), Sec. 20 (electronic records)
        'node.full_purge',
        'node.file_purge',
        'node.resync',

        // Auth anomalies
        'auth.invitation_accepted',
        'auth.password_reset',
        'settings.password_changed',
        'settings.account_deleted',

        // 2FA changes — NGPA Sec. 3 (accountability)
        'settings.two_factor_enabled',
        'settings.two_factor_disabled',

        // Admin configuration changes
        'admin.workflow_config_updated',
        'admin.workflow_config_reset',
        'admin.stage_document_config_updated',
        'admin.stage_document_config_reset',

        // Security
        'security.ip_blocked',
        'security.ip_unblocked',

        // Admin — irreversible actions
        'admin.invitation_revoked',
    ];

    public function __construct(
        protected Request $request,
        protected EventPublisher $events,
        protected NotificationService $notifications,
    ) {}

    /**
     * Get the human-readable label for an action key.
     * Returns the raw key if no label is defined.
     */
    public function getActionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? str_replace('_', ' ', $action);
    }

    /**
     * Check if an action is classified as critical per NGPA.
     */
    public function isCritical(string $action): bool
    {
        return in_array($action, self::CRITICAL_ACTIONS, true);
    }

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
            Log::error('AuditLogService: failed to write MySQL audit entry', [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->publishToBlockchain($action, $subjectType, $subjectId, $oldValues, $newValues, $log);

        // 3. Send notifications for critical events
        $this->notifyForCriticalAction($action, $subjectType, $subjectId, $oldValues, $newValues);
    }

    /**
     * Categorize action for blockchain event publishing.
     */
    private function categorizeAction(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'procurement.') => 'procurement',
            str_starts_with($action, 'document.') => 'document',
            str_starts_with($action, 'auth.') => 'authentication',
            str_starts_with($action, 'admin.') => 'administration',
            str_starts_with($action, 'user.') => 'user_management',
            str_starts_with($action, 'account.') => 'account_security',
            str_starts_with($action, 'settings.') => 'user_settings',
            str_starts_with($action, 'security.') => 'security',
            str_starts_with($action, 'node.') => 'node_operations',
            default => 'system',
        };
    }

    /**
     * Send notifications for critical audit events (NGPA accountability).
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
        if (! in_array($action, self::CRITICAL_ACTIONS, true)) {
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
            Log::warning('AuditLogService: failed to send notification for critical action', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function publishToBlockchain(
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        array $oldValues,
        array $newValues,
        ?AuditLog $log,
    ): void {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $user = $this->request->user();
            $userAddress = $user?->blockchain_address ?? '';
            $userName = $user?->name ?? 'System';
            $details = $this->buildBlockchainDetails($action, $subjectType, $subjectId, $oldValues, $newValues);

            $docCount = 0;
            if (str_starts_with($action, 'node.')) {
                $docCount = (int) ($newValues['items_purged'] ?? $newValues['items_resynced'] ?? 0);
            }

            $this->events->publish(
                prNumber: $subjectType === 'procurement' ? ($subjectId ?? 'system') : 'system',
                procurementTitle: $subjectType === 'procurement' ? "PR #{$subjectId}" : 'System Administration',
                stage: 'administration',
                eventType: $action,
                category: $this->categorizeAction($action),
                severity: in_array($action, self::CRITICAL_ACTIONS, true) ? 'warning' : 'info',
                details: $details,
                documentCount: $docCount,
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
            Log::warning('AuditLogService: failed to publish to blockchain (non-critical)', [
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
        $label = $this->getActionLabel($action);
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
                    $changes[] = "{$key}: '{$oldDisplay}' -> '{$newDisplay}'";
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
