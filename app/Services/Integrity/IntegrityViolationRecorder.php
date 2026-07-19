<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\BreachType;
use App\Enums\Stream;
use App\Enums\UserRole;
use App\Models\File;
use App\Models\IntegrityViolationLog;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use App\Services\BlockchainAuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Records integrity violations with deduplication, audit log creation, and breach notification.
 *
 * Shared mutable state lives in IntegrityVerificationRunState (passed by reference).
 */
class IntegrityViolationRecorder
{
    private const TABLE_STREAM_MAP = [
        'procurements' => Stream::METADATA,
        'procurement_stages' => Stream::STATUS,
        'procurement_documents' => Stream::DOCUMENTS,
        'procurement_events' => Stream::EVENTS,
        'procurement_corrections' => Stream::CORRECTIONS,
        'procurement_archives' => Stream::ARCHIVE,
        'procurement_metadata_corrections' => Stream::PROCUREMENTS_CORRECTIONS,
        'Files' => Stream::FILE_METADATA,
    ];

    public function __construct(
        private readonly IntegrityVerificationRunState $state,
        private readonly BlockchainAuditTrailService $auditTrailService,
    ) {}

    public function record(
        string $type,
        string $tableName,
        ?Model $record,
        string $prNumber,
        string $message,
        ?string $currentHash = null,
        ?string $storedHash = null,
        ?array $fieldDiffs = null,
        ?array $chainData = null,
        ?string $chainTxid = null,
    ): void {
        $this->state->violationCounts[$type] = ($this->state->violationCounts[$type] ?? 0) + 1;

        $severity = BreachType::tryFrom($type)?->severity() ?? 'medium';

        $stream = self::TABLE_STREAM_MAP[$tableName]?->value ?? $tableName;
        $violationTxid = $record?->txid ?? $chainTxid;

        // Deduplicate repeated audit runs
        $dedupeQuery = IntegrityViolationLog::where('stream', $stream)
            ->where('stream_key', $prNumber)
            ->where('violation_type', $type)
            ->where('recovery_status', 'pending');

        if ($violationTxid) {
            $dedupeQuery->where('txid', $violationTxid);
        } elseif ($record) {
            $dedupeQuery->where('record_id', $record->id);
        }

        $dedupeQuery->update([
            'recovery_status' => 'superseded',
            'recovery_result' => ['reason' => 'Superseded by a newer audit run (run_id: '.$this->state->runId.')'],
        ]);

        $auditLog = IntegrityViolationLog::create([
            'record_id' => $record?->id,
            'stream' => $stream,
            'stream_key' => $prNumber,
            'txid' => $violationTxid,
            'violation_type' => $type,
            'severity' => $severity,
            'mirror_snapshot' => $record ? $this->recordSnapshot($record, $tableName) : null,
            'chain_snapshot' => $chainData,
            'field_differences' => $fieldDiffs ?? ($currentHash ? [['field' => 'hash', 'old_value' => $storedHash, 'new_value' => $currentHash]] : null),
            'recovery_status' => 'pending',
            'verification_run_id' => $this->state->runId,
            'source' => $this->state->source,
            'created_at' => now(),
        ]);

        try {
            $this->auditTrailService->publishViolation($auditLog);
        } catch (\Exception $e) {
            Log::debug('IntegrityViolationRecorder: failed to publish violation to blockchain', [
                'audit_log_id' => $auditLog->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($record && method_exists($record, 'update')) {
            $record->update(['has_breach' => true]);
        }

        // Only notify for NEW breaches
        $existingPending = IntegrityViolationLog::where('stream', $stream)
            ->where('stream_key', $prNumber)
            ->where('violation_type', $type)
            ->where('recovery_status', 'pending')
            ->where('id', '!=', $auditLog->id)
            ->exists();

        if (! $existingPending) {
            $this->notifyBreach($type, $prNumber, $message, $fieldDiffs, $stream, $violationTxid);
        }

        Log::warning('IntegrityVerification: breach', ['type' => $type, 'pr' => $prNumber, 'table' => $tableName]);
    }

    private function recordSnapshot(Model $record, string $tableName): array
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            'procurement_corrections' => ProcurementCorrection::getHashableFields(),
            'procurement_archives' => ProcurementArchive::getHashableFields(),
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::getHashableFields(),
            'Files' => File::getHashableFields(),
            default => [],
        };

        $data = [];
        foreach ($fields as $field) {
            $value = $record->{$field} ?? null;
            if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_string($value) && is_numeric($value)) {
                $value = (float) $value;
            }
            $data[$field] = $value;
        }

        return $data;
    }

    private function notifyBreach(string $type, string $prNumber, string $message, ?array $fieldDiffs, ?string $stream, ?string $txid): void
    {
        try {
            $recipientRoles = config('integrity.breach_notifications.recipient_roles', [UserRole::ADMIN->value, UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value]);
            $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $recipientRoles))->get();
            if ($recipients->isEmpty()) {
                return;
            }

            foreach ($recipients as $recipient) {
                $recipient->notify(new IntegrityBreachNotification(
                    breachType: $type,
                    stream: $stream ?? 'normalized_db',
                    streamKey: $prNumber,
                    txid: $txid ?? '',
                    breachData: ['message' => $message, 'run_id' => $this->state->runId],
                    recordId: null,
                    runId: $this->state->runId,
                    fieldDiffs: $fieldDiffs,
                    isDigest: false,
                ));
            }
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: notification failed', ['error' => $e->getMessage()]);
        }
    }
}
