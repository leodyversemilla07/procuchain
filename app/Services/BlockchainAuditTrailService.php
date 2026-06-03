<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\IntegrityAuditLog;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Audit Trail Service
 *
 * Writes integrity violation records to an immutable blockchain stream
 * (integrity.violations) so that audit evidence survives total MySQL destruction.
 *
 * Architecture:
 *   integrity_audit_logs (MySQL)  ← fast queries, mutable, can be wiped
 *   integrity.violations (Chain)  ← immutable, permanent, append-only
 *
 * Every violation detected by IntegrityVerificationService is written to
 * BOTH tables simultaneously. If MySQL is destroyed, the blockchain
 * retains the complete forensic history which can be recovered.
 *
 * Requirement #6: "Maintain a permanent audit trail of all recovery operations"
 */
class BlockchainAuditTrailService
{
    /**
     * Publish an integrity violation to the blockchain audit trail.
     *
     * Called immediately after recording a violation in integrity_audit_logs.
     * The blockchain record is the permanent, immutable backup.
     *
     * @param  IntegrityAuditLog  $auditLog  The MySQL audit log entry
     * @param  array  $extraData  Additional context to include on-chain
     * @return string|null The blockchain transaction ID, or null on failure
     */
    public function publishViolation(IntegrityAuditLog $auditLog, array $extraData = []): ?string
    {
        try {
            $manager = app(Manager::class);

            $chainPayload = $this->buildChainPayload($auditLog, $extraData);

            // Key: violation ID for unique lookup, also indexable by stream_key
            $key = (string) $auditLog->id;

            $result = $manager->publish(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                $key,
                ['json' => $chainPayload],
            );

            if ($result === null || $result === false) {
                Log::error('BlockchainAuditTrail: failed to publish violation', [
                    'audit_log_id' => $auditLog->id,
                    'error' => $manager->getClient()->errormessage(),
                ]);

                return null;
            }

            $txid = is_string($result) ? $result : ($result['txid'] ?? null);

            // Record the blockchain txid back to the MySQL audit log
            // so we can cross-reference later
            if ($txid !== null) {
                $this->attachTxidToAuditLog($auditLog, $txid);
            }

            Log::info('BlockchainAuditTrail: violation published to chain', [
                'audit_log_id' => $auditLog->id,
                'txid' => $txid,
                'violation_type' => $auditLog->violation_type,
                'stream_key' => $auditLog->stream_key,
            ]);

            return $txid;
        } catch (Exception $e) {
            Log::error('BlockchainAuditTrail: exception publishing violation', [
                'audit_log_id' => $auditLog->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Publish a recovery operation to the blockchain audit trail.
     *
     * Called when a violation is restored from blockchain data.
     * Records that the recovery happened, creating a permanent
     * chain of: violation detected → recovery performed.
     *
     * @param  IntegrityAuditLog  $auditLog  The restored audit log entry
     * @param  array  $recoveryResult  What was restored
     * @return string|null The blockchain transaction ID
     */
    public function publishRecovery(IntegrityAuditLog $auditLog, array $recoveryResult = []): ?string
    {
        try {
            $manager = app(Manager::class);

            $chainPayload = [
                'type' => 'recovery',
                'violation_id' => $auditLog->id,
                'violation_type' => $auditLog->violation_type,
                'stream' => $auditLog->stream,
                'stream_key' => $auditLog->stream_key,
                'txid' => $auditLog->txid,
                'violation_severity' => $auditLog->severity,
                'recovery_status' => 'restored',
                'recovery_result' => $recoveryResult,
                'recovered_at' => now()->toIso8601String(),
                'detected_at' => $auditLog->created_at->toIso8601String(),
                'verification_run_id' => $auditLog->verification_run_id,
                'source' => $auditLog->source,
                'chain_hash' => hash('sha256', json_encode($auditLog->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];

            // Key: recovery-<violation_id> for unique lookup
            $key = 'recovery-'.$auditLog->id;

            $result = $manager->publish(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                $key,
                ['json' => $chainPayload],
            );

            if ($result === null || $result === false) {
                Log::error('BlockchainAuditTrail: failed to publish recovery', [
                    'audit_log_id' => $auditLog->id,
                    'error' => $manager->getClient()->errormessage(),
                ]);

                return null;
            }

            $txid = is_string($result) ? $result : ($result['txid'] ?? null);

            Log::info('BlockchainAuditTrail: recovery published to chain', [
                'audit_log_id' => $auditLog->id,
                'recovery_txid' => $txid,
            ]);

            return $txid;
        } catch (Exception $e) {
            Log::error('BlockchainAuditTrail: exception publishing recovery', [
                'audit_log_id' => $auditLog->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Recover the full audit trail from the blockchain.
     *
     * Reads all integrity.violations entries from the chain and
     * returns them for display or re-import into MySQL.
     *
     * @param  int  $limit  Maximum number of entries to retrieve
     * @return array<int, array{key: string, txid: string, data: array, blocktime: int|null}>
     */
    public function recoverAuditTrail(int $limit = 10000): array
    {
        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                true,
                $limit,
            );

            if (! is_array($items) || empty($items)) {
                Log::info('BlockchainAuditTrail: no entries found on chain');

                return [];
            }

            $entries = [];

            foreach ($items as $item) {
                $entries[] = [
                    'key' => $item['key'] ?? null,
                    'txid' => $item['txid'] ?? null,
                    'data' => $item['data']['json'] ?? [],
                    'blocktime' => $item['blocktime'] ?? null,
                    'publishers' => $item['publishers'] ?? [],
                ];
            }

            Log::info('BlockchainAuditTrail: recovered audit trail from chain', [
                'count' => count($entries),
            ]);

            return $entries;
        } catch (Exception $e) {
            Log::error('BlockchainAuditTrail: failed to recover audit trail', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Recover audit trail for a specific stream key (PR number).
     *
     * @return array<int, array{key: string, txid: string, data: array, blocktime: int|null}>
     */
    public function recoverAuditTrailForKey(string $streamKey): array
    {
        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems(
                StreamEnums::INTEGRITY_VIOLATIONS->value,
                true,
                10000,
            );

            if (! is_array($items) || empty($items)) {
                return [];
            }

            $entries = [];

            foreach ($items as $item) {
                $data = $item['data']['json'] ?? [];

                // Filter by stream_key in the payload
                if (($data['stream_key'] ?? null) === $streamKey) {
                    $entries[] = [
                        'key' => $item['key'] ?? null,
                        'txid' => $item['txid'] ?? null,
                        'data' => $data,
                        'blocktime' => $item['blocktime'] ?? null,
                        'publishers' => $item['publishers'] ?? [],
                    ];
                }
            }

            return $entries;
        } catch (Exception $e) {
            Log::error('BlockchainAuditTrail: failed to recover audit trail for key', [
                'stream_key' => $streamKey,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Restore audit logs from blockchain to MySQL.
     *
     * Called after a MySQL wipe to rebuild the integrity_audit_logs table
     * from the immutable blockchain records.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function restoreAuditLogsToMySQL(): array
    {
        $entries = $this->recoverAuditTrail();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            $data = $entry['data'];
            $type = $data['type'] ?? 'violation';

            // Skip recovery entries — they reference violation entries
            if ($type === 'recovery') {
                $this->restoreRecoveryEntry($data);
                $skipped++;

                continue;
            }

            // Check if this violation already exists in MySQL (dedup)
            $existingId = $data['violation_id'] ?? null;

            if ($existingId && IntegrityAuditLog::where('id', $existingId)->exists()) {
                $skipped++;

                continue;
            }

            try {
                IntegrityAuditLog::create([
                    'stream' => $data['stream'] ?? '',
                    'stream_key' => $data['stream_key'] ?? '',
                    'txid' => $data['txid'] ?? null,
                    'revision_number' => $data['revision_number'] ?? null,
                    'parent_txid' => $data['parent_txid'] ?? null,
                    'violation_type' => $data['violation_type'] ?? 'unknown',
                    'severity' => $data['severity'] ?? 'medium',
                    'field_differences' => $data['field_differences'] ?? null,
                    'mirror_snapshot' => $data['mirror_snapshot'] ?? null,
                    'chain_snapshot' => $data['chain_snapshot'] ?? null,
                    'recovery_status' => $data['recovery_status'] ?? 'pending',
                    'recovered_at' => $data['recovered_at'] ?? null,
                    'recovery_result' => $data['recovery_result'] ?? null,
                    'record_id' => null, // Original record may not exist
                    'verification_run_id' => $data['verification_run_id'] ?? null,
                    'source' => $data['source'] ?? 'chain_recovery',
                    'revision_lineage' => $data['revision_lineage'] ?? null,
                    'created_at' => $data['detected_at'] ?? now(),
                ]);

                $imported++;
            } catch (Exception $e) {
                Log::error('BlockchainAuditTrail: failed to import violation', [
                    'key' => $entry['key'],
                    'error' => $e->getMessage(),
                ]);

                $errors++;
            }
        }

        Log::info('BlockchainAuditTrail: restore to MySQL completed', [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Build the JSON payload for the blockchain audit trail entry.
     */
    private function buildChainPayload(IntegrityAuditLog $auditLog, array $extraData): array
    {
        return array_merge([
            'type' => 'violation',
            'violation_id' => $auditLog->id,
            'stream' => $auditLog->stream,
            'stream_key' => $auditLog->stream_key,
            'txid' => $auditLog->txid,
            'revision_number' => $auditLog->revision_number,
            'parent_txid' => $auditLog->parent_txid,
            'violation_type' => $auditLog->violation_type,
            'severity' => $auditLog->severity,
            'field_differences' => $auditLog->field_differences,
            'mirror_snapshot' => $auditLog->mirror_snapshot,
            'chain_snapshot' => $auditLog->chain_snapshot,
            'recovery_status' => $auditLog->recovery_status,
            'record_id' => $auditLog->record_id,
            'verification_run_id' => $auditLog->verification_run_id,
            'source' => $auditLog->source,
            'revision_lineage' => $auditLog->revision_lineage,
            'detected_at' => $auditLog->created_at->toIso8601String(),
            'chain_hash' => hash('sha256', json_encode($auditLog->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ], $extraData);
    }

    /**
     * Attach a blockchain txid to the MySQL audit log entry.
     */
    private function attachTxidToAuditLog(IntegrityAuditLog $auditLog, string $txid): void
    {
        try {
            // Store the blockchain txid in revision_lineage metadata
            // (the audit log doesn't have a dedicated blockchain_txid column,
            //  so we append it to revision_lineage which is a JSON array)
            $lineage = $auditLog->revision_lineage ?? [];
            $lineage['_blockchain_txid'] = $txid;

            $auditLog->update(['revision_lineage' => $lineage]);
        } catch (Exception $e) {
            // Non-critical — the violation is already on chain
            Log::debug('BlockchainAuditTrail: could not attach txid to audit log', [
                'audit_log_id' => $auditLog->id,
                'txid' => $txid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update an existing MySQL audit log entry with recovery information
     * from a chain recovery entry.
     */
    private function restoreRecoveryEntry(array $data): void
    {
        $violationId = $data['violation_id'] ?? null;

        if ($violationId === null) {
            return;
        }

        try {
            $log = IntegrityAuditLog::find($violationId);

            if ($log && $log->recovery_status === 'pending') {
                $log->markRestored($data['recovery_result'] ?? []);
            }
        } catch (Exception $e) {
            Log::debug('BlockchainAuditTrail: could not update recovery status', [
                'violation_id' => $violationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
