<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Integrity Audit Log — Permanent Record
 *
 * Append-only audit trail for all integrity verification results,
 * detected violations, and recovery operations.
 *
 * This table is separated from procurement_mirror so that:
 * - Audit records survive mirror row deletion
 * - Forensic analysis is possible even after recovery
 * - Compliance with RA 12009 Sec. 3 (accountability) and Sec. 20 (electronic records)
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
 * @property int|null $mirror_id
 * @property string|null $verification_run_id
 * @property string $source
 * @property array|null $revision_lineage
 * @property Carbon $created_at
 */
class IntegrityAuditLog extends Model
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
        'mirror_id',
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
        return match ($violationType) {
            'hash_mismatch', 'content_mismatch' => 'critical',
            'user_address_tampered' => 'high',
            'unauthorized_publisher' => 'medium',
            'row_deleted' => 'low',
            default => 'medium',
        };
    }

    /**
     * Record a violation with field-level differences and revision context.
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
        ?int $mirrorId = null,
        ?string $runId = null,
        string $source = 'scheduled',
        ?int $revisionNumber = null,
        ?string $parentTxid = null,
        ?array $revisionLineage = null,
    ): self {
        return self::create([
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
            'mirror_id' => $mirrorId,
            'verification_run_id' => $runId ?? self::newRunId(),
            'source' => $source,
            'revision_lineage' => $revisionLineage,
        ]);
    }

    /**
     * Record a violation from a ProcurementMirror model, automatically
     * extracting revision context (revision_number, parent_txid, lineage).
     */
    public static function recordViolationFromMirror(
        ProcurementMirror $mirror,
        string $violationType,
        array $fieldDifferences = [],
        ?array $chainSnapshot = null,
        ?string $runId = null,
        string $source = 'scheduled',
    ): self {
        return self::recordViolation(
            stream: $mirror->stream,
            streamKey: $mirror->stream_key,
            violationType: $violationType,
            txid: $mirror->txid,
            fieldDifferences: $fieldDifferences,
            mirrorSnapshot: $mirror->data_json,
            chainSnapshot: $chainSnapshot,
            mirrorId: $mirror->id,
            runId: $runId,
            source: $source,
            revisionNumber: $mirror->revision_number,
            parentTxid: $mirror->parent_txid,
            revisionLineage: $mirror->getRevisionLineage(),
        );
    }

    /**
     * Mark this violation as successfully restored.
     */
    public function markRestored(array $result = []): void
    {
        $this->update([
            'recovery_status' => 'restored',
            'recovered_at' => Carbon::now(),
            'recovery_result' => $result,
        ]);
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
