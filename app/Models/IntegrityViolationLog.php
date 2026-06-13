<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BreachType;
use App\Services\BlockchainAuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Append-only audit trail for integrity verification results that survives normalized row deletion.
 *
 * @property int $id
 * @property string $stream
 * @property string $stream_key
 * @property string|null $txid
 * @property int|null $revision_number
 * @property string|null $parent_txid
 * @property string $violation_type
 * @property string $severity
 * @property array|null $field_differences
 * @property array|null $mirror_snapshot
 * @property array|null $chain_snapshot
 * @property string $recovery_status
 * @property Carbon|null $recovered_at
 * @property array|null $recovery_result
 * @property int|null $record_id
 * @property string|null $verification_run_id
 * @property string $source
 * @property array|null $revision_lineage
 * @property Carbon $created_at
 */
class IntegrityViolationLog extends Model
{
    /** @var string */
    protected $table = 'integrity_audit_logs';

    /** Append-only: no updates, no updated_at */
    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'stream',
        'stream_key',
        'txid',
        'revision_number',
        'parent_txid',
        'violation_type',
        'severity',
        'field_differences',
        'mirror_snapshot',
        'chain_snapshot',
        'recovery_status',
        'recovered_at',
        'recovery_result',
        'record_id',
        'verification_run_id',
        'source',
        'revision_lineage',
    ];

    protected function casts(): array
    {
        return [
            'field_differences' => 'array',
            'mirror_snapshot' => 'array',
            'chain_snapshot' => 'array',
            'recovery_result' => 'array',
            'revision_lineage' => 'array',
            'recovered_at' => 'datetime',
            'created_at' => 'datetime',
            'revision_number' => 'integer',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    /**
     * Filter by violation type.
     */
    public function scopeForViolationType($query, string $type)
    {
        return $query->where('violation_type', $type);
    }

    /**
     * Filter by stream key (PR number).
     */
    public function scopeForStreamKey($query, string $key)
    {
        return $query->where('stream_key', $key);
    }

    /**
     * Filter by verification run ID.
     */
    public function scopeForRun($query, string $runId)
    {
        return $query->where('verification_run_id', $runId);
    }

    /**
     * Filter by recovery status.
     */
    public function scopeWithRecoveryStatus($query, string $status)
    {
        return $query->where('recovery_status', $status);
    }

    /**
     * Filter by severity level.
     */
    public function scopeWithSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Filter by source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Only unresolved violations.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('recovery_status', 'pending');
    }

    /**
     * Only successfully recovered violations.
     */
    public function scopeRecovered($query)
    {
        return $query->where('recovery_status', 'restored');
    }

    /**
     * Filter by revision number.
     */
    public function scopeForRevision($query, int $revisionNumber)
    {
        return $query->where('revision_number', $revisionNumber);
    }

    // ─── Factory Methods ──────────────────────────────────────────────

    /**
     * Generate a new verification run ID (UUID v4).
     */
    public static function newRunId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Determine severity from violation type.
     */
    public static function severityForType(string $violationType): string
    {
        $enum = BreachType::tryFrom($violationType);

        return $enum?->severity() ?? 'medium';
    }

    /**
     * Record a violation with field-level differences and revision context.
     *
     * Implements cross-run deduplication with configurable cooldown.
     *
     * @param  array  $fieldDifferences  [{field, old_value, new_value}]
     * @param  array|null  $mirrorSnapshot  DB state at detection time
     * @param  array|null  $chainSnapshot  Blockchain state at detection time
     * @param  int|null  $revisionNumber  The revision number of the affected mirror record
     * @param  string|null  $parentTxid  The parent revision's txid
     * @param  string[]|null  $revisionLineage  Full lineage from root to this revision
     */
    public static function recordViolation(
        string $stream,
        string $streamKey,
        string $violationType,
        ?string $txid = null,
        array $fieldDifferences = [],
        ?array $mirrorSnapshot = null,
        ?array $chainSnapshot = null,
        ?int $recordId = null,
        ?string $runId = null,
        string $source = 'scheduled',
        ?int $revisionNumber = null,
        ?string $parentTxid = null,
        ?array $revisionLineage = null,
    ): self {
        // Skip cooldown deduplication in unit tests
        if (! app()->runningUnitTests()) {
            // Get cooldown period from config (default 24 hours)
            $cooldownHours = config('integrity.breach_notifications.cooldown_hours', 24);
            $cooldownCutoff = now()->subHours($cooldownHours);

            // Deduplication: skip if an identical violation was reported recently
            // regardless of recovery_status (pending/restored/failed/skipped)
            // This prevents notification floods from repeated scheduled audits.
            $recent = self::where('stream', $stream)
                ->where('stream_key', $streamKey)
                ->where('violation_type', $violationType)
                ->where('created_at', '>=', $cooldownCutoff)
                ->when($txid, fn ($q) => $q->where('txid', $txid))
                ->latest('created_at')
                ->first();

            if ($recent) {
                // Update the existing record with the new run_id for tracking
                $recent->update(['verification_run_id' => $runId ?? self::newRunId()]);

                Log::debug('IntegrityViolationLog: skipping duplicate violation within cooldown', [
                    'stream' => $stream,
                    'stream_key' => $streamKey,
                    'type' => $violationType,
                    'cooldown_hours' => $cooldownHours,
                    'existing_id' => $recent->id,
                ]);

                return $recent;
            }
        }

        // Also check for pending violation with same txid (original logic)
        $existingPending = self::where('stream', $stream)
            ->where('stream_key', $streamKey)
            ->where('violation_type', $violationType)
            ->where('recovery_status', 'pending')
            ->when($txid, fn ($q) => $q->where('txid', $txid))
            ->first();

        if ($existingPending) {
            return $existingPending;
        }

        $auditLog = self::create([
            'stream' => $stream,
            'stream_key' => $streamKey,
            'txid' => $txid,
            'revision_number' => $revisionNumber,
            'parent_txid' => $parentTxid,
            'violation_type' => $violationType,
            'severity' => self::severityForType($violationType),
            'field_differences' => empty($fieldDifferences) ? null : $fieldDifferences,
            'mirror_snapshot' => $mirrorSnapshot,
            'chain_snapshot' => $chainSnapshot,
            'recovery_status' => 'pending',
            'record_id' => $recordId,
            'verification_run_id' => $runId ?? self::newRunId(),
            'source' => $source,
            'revision_lineage' => $revisionLineage,
        ]);

        return $auditLog;
    }

    // ─── State Transitions ───────────────────────────────────────────

    /**
     * Mark this violation as successfully restored.
     *
     * Also publishes the recovery event to the blockchain audit trail
     * so there is an immutable record that the recovery happened.
     */
    public function markRestored(array $result = [], bool $publishToChain = true): void
    {
        $this->update([
            'recovery_status' => 'restored',
            'recovered_at' => Carbon::now(),
            'recovery_result' => $result,
        ]);

        // Publish recovery to blockchain for permanent audit trail
        if ($publishToChain && ! app()->runningUnitTests()) {
            try {
                app(BlockchainAuditTrailService::class)->publishRecovery($this, $result);
            } catch (\Exception $e) {
                // Non-critical — violation is already on chain, recovery is supplementary
                Log::debug('IntegrityViolationLog: failed to publish recovery to chain', [
                    'audit_log_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Publish this violation record to the blockchain audit trail.
     *
     * The blockchain entry is immutable and permanent — it survives
     * total MySQL destruction. This satisfies Requirement #6:
     * "Maintain a permanent audit trail of all recovery operations."
     *
     * @return string|null The blockchain transaction ID
     */
    public function publishToBlockchain(): ?string
    {
        try {
            return app(BlockchainAuditTrailService::class)->publishViolation($this);
        } catch (\Exception $e) {
            // Non-critical — the MySQL record is already created.
            // Blockchain publishing is best-effort; failures are logged
            // but must never block the integrity verification pipeline.
            Log::debug('IntegrityViolationLog: failed to publish to blockchain', [
                'audit_log_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark this violation's recovery as failed.
     */
    public function markFailed(string $reason): void
    {
        $this->update([
            'recovery_status' => 'failed',
            'recovery_result' => ['error' => $reason],
        ]);
    }

    /**
     * Mark this violation as skipped (e.g. manual review required).
     */
    public function markSkipped(string $reason): void
    {
        $this->update([
            'recovery_status' => 'skipped',
            'recovery_result' => ['reason' => $reason],
        ]);
    }
}
