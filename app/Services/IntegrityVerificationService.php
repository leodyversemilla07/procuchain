<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Integrity Verification Service
 *
 * Verifies normalized DB tables against blockchain (source of truth).
 *
 * Architecture:
 *   Blockchain → Source of truth (immutable)
 *   Normalized DB → Query cache (mutable, verifiable)
 *   integrity_audit_logs → Permanent forensic record (append-only)
 *
 * Verification layers:
 *   Layer 1: data_hash check (was record modified since sync?)
 *   Layer 2: Field-level diff (what specifically changed)
 *   Layer 3: Content comparison against chain (authoritative)
 *   Layer 4: Row existence check (detects deletions)
 */
class IntegrityVerificationService
{
    private string $runId;

    private string $source;

    private array $violationCounts = [];

    private int $verifiedCount = 0;

    private int $restoredCount = 0;

    private int $failedCount = 0;

    private Manager $manager;

    private NormalizedTableSyncService $syncService;

    /** Tables to verify and their stream mappings */
    private const TABLE_STREAM_MAP = [
        'procurements' => StreamEnums::METADATA,
        'procurement_stages' => StreamEnums::STATUS,
        'procurement_documents' => StreamEnums::DOCUMENTS,
        'procurement_events' => StreamEnums::EVENTS,
        'procurement_corrections' => StreamEnums::CORRECTIONS,
        'procurement_archives' => StreamEnums::ARCHIVE,
        'procurement_metadata_corrections' => StreamEnums::PROCUREMENTS_CORRECTIONS,
        'files' => StreamEnums::FILE_METADATA,
    ];

    public function __construct()
    {
        $this->manager = app(Manager::class);
        $this->syncService = app(NormalizedTableSyncService::class);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Run full integrity verification against all normalized tables.
     *
     * @return array{run_id: string, verified: int, violations: array, restored: int, failed: int}
     */
    public function verifyAndRepair(bool $autoRepair = false, string $source = 'scheduled'): array
    {
        $this->reset($source);

        Log::info('IntegrityVerification: starting', ['run_id' => $this->runId, 'auto_repair' => $autoRepair]);

        // Phase 1: Verify hashes on all normalized tables
        $this->verifyAllTables();

        // Phase 2: Detect deleted records (chain has it, DB doesn't)
        $this->detectDeletedRecords();

        // Phase 3: Auto-repair if requested
        if ($autoRepair) {
            $this->autoRepair();
        }

        $result = [
            'run_id' => $this->runId,
            'verified' => $this->verifiedCount,
            'violations' => $this->violationCounts,
            'restored' => $this->restoredCount,
            'failed' => $this->failedCount,
        ];

        Log::info('IntegrityVerification: completed', $result);

        return $result;
    }

    /**
     * Verify a specific PR number.
     */
    public function verifyPr(string $prNumber, bool $autoRepair = false): array
    {
        $this->reset('manual');

        $procurement = Procurement::where('pr_number', $prNumber)->first();
        if (! $procurement) {
            return ['run_id' => $this->runId, 'verified' => 0, 'violations' => [], 'restored' => 0, 'failed' => 0];
        }

        // Verify procurement record
        $this->verifyRecord($procurement, 'procurements', StreamEnums::METADATA);

        // Verify related records
        foreach ($procurement->stages as $stage) {
            $this->verifyRecord($stage, 'procurement_stages', StreamEnums::STATUS);
        }
        foreach ($procurement->documents as $doc) {
            $this->verifyRecord($doc, 'procurement_documents', StreamEnums::DOCUMENTS);
        }
        foreach ($procurement->events as $event) {
            $this->verifyRecord($event, 'procurement_events', StreamEnums::EVENTS);
        }

        return [
            'run_id' => $this->runId,
            'verified' => $this->verifiedCount,
            'violations' => $this->violationCounts,
            'restored' => $this->restoredCount,
            'failed' => $this->failedCount,
        ];
    }

    /**
     * Restore a specific violation from blockchain.
     */
    public function restoreViolation(IntegrityAuditLog $auditLog): array
    {
        if ($auditLog->recovery_status !== 'pending') {
            return ['success' => false, 'items_restored' => 0, 'error' => 'Already processed'];
        }

        try {
            // Get all PR numbers from blockchain
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();

            // Hard-delete records that don't exist on blockchain.
            // Requirement 5 treats these as unauthorized DB injections, not recoverable user deletes.
            $deletedCount = Procurement::withTrashed()
                ->whereNotIn('pr_number', $blockchainPrNumbers)
                ->forceDelete();

            // Re-sync from blockchain to restore
            $this->syncService->syncAll();

            $auditLog->markRestored([
                'restored_by' => 'system',
                'restored_at' => now()->toIso8601String(),
                'deleted_records' => $deletedCount,
            ]);

            $this->restoredCount++;

            return ['success' => true, 'items_restored' => 1, 'deleted' => $deletedCount, 'error' => null];
        } catch (\Exception $e) {
            $auditLog->markFailed($e->getMessage());
            $this->failedCount++;

            return ['success' => false, 'items_restored' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all PR numbers that exist on blockchain.
     */
    private function getBlockchainPrNumbers(): array
    {
        try {
            $items = $this->manager->liststreamitems(StreamEnums::METADATA->value, false, 10000);
            if (! is_array($items)) {
                return [];
            }

            return collect($items)
                ->map(fn ($item) => $item['data']['json']['pr_number'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get blockchain PR numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Generate violation report.
     */
    public function generateReport(string $runId): array
    {
        $logs = IntegrityAuditLog::forRun($runId)
            ->orderByDesc('severity')
            ->get();

        return [
            'run_id' => $runId,
            'summary' => [
                'total_violations' => $logs->count(),
                'critical' => $logs->where('severity', 'critical')->count(),
                'high' => $logs->where('severity', 'high')->count(),
                'medium' => $logs->where('severity', 'medium')->count(),
                'low' => $logs->where('severity', 'low')->count(),
                'restored' => $logs->where('recovery_status', 'restored')->count(),
                'failed' => $logs->where('recovery_status', 'failed')->count(),
                'pending' => $logs->where('recovery_status', 'pending')->count(),
                'by_type' => $logs->groupBy('violation_type')->map->count()->toArray(),
            ],
            'violations' => $logs->toArray(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 1: VERIFY ALL TABLES
    // ═══════════════════════════════════════════════════════════════════

    private function verifyAllTables(): void
    {
        // Verify procurements
        Procurement::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurements', StreamEnums::METADATA);
            }
        });

        // Verify stages
        ProcurementStage::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_stages', StreamEnums::STATUS);
            }
        });

        // Verify documents
        ProcurementDocument::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_documents', StreamEnums::DOCUMENTS);
            }
        });

        // Verify events
        ProcurementEvent::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_events', StreamEnums::EVENTS);
            }
        });
    }

    /**
     * Verify a single record against blockchain.
     *
     * Layer 1: Check if data_hash matches (was record modified since sync?)
     * Layer 2: Fetch blockchain data and compare fields
     */
    private function verifyRecord(Model $record, string $tableName, StreamEnums $stream): void
    {
        $this->verifiedCount++;

        // Get the PR number for this record
        $prNumber = $record->pr_number ?? $record->procurement->pr_number ?? null;
        if (! $prNumber) {
            return;
        }

        // Layer 1: Recompute hash and compare with stored data_hash
        $currentHash = $this->computeRecordHash($record, $tableName);
        $storedHash = $record->data_hash;

        if ($storedHash && $currentHash !== $storedHash) {
            // Hash mismatch - record was modified in DB
            $this->recordViolation(
                type: BreachTypeEnums::HASH_MISMATCH->value,
                severity: 'critical',
                tableName: $tableName,
                record: $record,
                prNumber: $prNumber,
                message: 'Record was modified in database since last sync',
                currentHash: $currentHash,
                storedHash: $storedHash,
            );

            return;
        }

        // Layer 2: Compare with blockchain data
        $chainData = $this->fetchChainData($stream->value, $prNumber, $record->txid);
        if ($chainData) {
            $fieldDiffs = $this->computeFieldDifferences(
                $this->recordToArray($record, $tableName),
                $chainData
            );

            if (! empty($fieldDiffs)) {
                $this->recordViolation(
                    type: BreachTypeEnums::CONTENT_MISMATCH->value,
                    severity: 'high',
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'Record differs from blockchain',
                    fieldDiffs: $fieldDiffs,
                    chainData: $chainData,
                );
            }

            // Layer 3: Check if user_address was tampered
            $dbUserAddress = $record->user_address ?? null;
            $chainUserAddress = $chainData['user_address'] ?? null;
            if ($dbUserAddress && $chainUserAddress && $dbUserAddress !== $chainUserAddress) {
                $this->recordViolation(
                    type: BreachTypeEnums::USER_ADDRESS_TAMPERED->value,
                    severity: 'high',
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'User address was modified from original blockchain record',
                    fieldDiffs: [['field' => 'user_address', 'old_value' => $chainUserAddress, 'new_value' => $dbUserAddress]],
                    chainData: $chainData,
                );
            }
        }

        // Layer 4: Check if the blockchain publisher is unauthorized
        // (verifies that the txid publisher matches the expected authorized address)
        if ($record->txid) {
            $this->checkUnauthorizedPublisher($record, $tableName, $prNumber, $stream->value);
        }

        // Clean - mark verified
        if (! $record->has_breach) {
            $record->update(['last_verified_at' => now(), 'is_blockchain_verified' => true]);
        }
    }

    /**
     * Check if the publisher of a blockchain transaction is authorized.
     * Compares the publisher address of the record's txid against
     * the known authorized publisher stored on the record.
     */
    private function checkUnauthorizedPublisher(Model $record, string $tableName, string $prNumber, string $stream): void
    {
        try {
            $txid = $record->txid;
            if (! $txid) {
                return;
            }

            // Get the txid details from blockchain (verbose = true)
            $txData = $this->manager->getrawtransaction($txid, 1);
            if (! $txData || ! is_array($txData)) {
                return;
            }

            // Extract publisher addresses from the transaction data
            // In MultiChain, publishers are available in the 'data' section
            $publishers = [];
            $dataSection = $txData['data'] ?? [];

            if (is_array($dataSection)) {
                foreach ($dataSection as $keyData) {
                    if (is_array($keyData) && isset($keyData['publishers'])) {
                        $publishers = array_merge($publishers, (array) $keyData['publishers']);
                    }
                }
            }

            $publishers = array_unique($publishers);

            if (empty($publishers)) {
                return;
            }

            // Get the authorized publisher address for this record
            $authorizedAddress = $record->user_address ?? null;

            if (! $authorizedAddress) {
                return;
            }

            // Check if any publisher is NOT the authorized address
            foreach ($publishers as $publisher) {
                if ($publisher !== $authorizedAddress) {
                    $this->recordViolation(
                        type: BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value,
                        severity: 'medium',
                        tableName: $tableName,
                        record: $record,
                        prNumber: $prNumber,
                        message: "Unauthorized publisher {$publisher} — expected {$authorizedAddress}",
                        chainData: ['publishers' => $publishers, 'authorized_address' => $authorizedAddress],
                    );

                    return; // One violation per record
                }
            }
        } catch (\Exception $e) {
            // Non-critical — publisher check is best-effort
            Log::debug('IntegrityVerification: publisher check failed', [
                'txid' => $record->txid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 2: DETECT ALL INCONSISTENCIES
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detect all inconsistencies between DB and blockchain.
     *
     * Two-way check:
     *   A. Items on chain but not in DB → ROW_DELETED (unauthorized deletion)
     *   B. Items in DB but not on chain → UNAUTHORIZED_RECORD (injected fake data)
     */
    private function detectDeletedRecords(): void
    {
        $this->detectMissingFromDb();
        $this->detectUnauthorizedInDb();
    }

    /**
     * Detect records on blockchain that are missing from DB.
     * (Unauthorized deletion)
     */
    private function detectMissingFromDb(): void
    {
        try {
            $chainItems = $this->manager->liststreamitems(StreamEnums::METADATA->value, false, 10000);
            foreach ($chainItems as $item) {
                $data = $item['data']['json'] ?? [];
                $prNumber = $data['pr_number'] ?? null;
                if ($prNumber && ! Procurement::where('pr_number', $prNumber)->exists()) {
                    $this->recordViolation(
                        type: BreachTypeEnums::ROW_DELETED->value,
                        severity: 'critical',
                        tableName: 'procurements',
                        record: null,
                        prNumber: $prNumber,
                        message: 'PR exists on blockchain but not in database',
                        chainData: $data,
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted procurements', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Detect records in DB that do NOT exist on blockchain.
     * (Unauthorized injection / fake records)
     *
     * Per Requirement 5:
     * "Records not on blockchain but in DB should be removed"
     * First we detect them here, then autoRepair handles removal.
     */
    private function detectUnauthorizedInDb(): void
    {
        try {
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();

            if (empty($blockchainPrNumbers)) {
                return;
            }

            // Find procurements in DB that don't exist on chain
            $fakeRecords = Procurement::whereNotIn('pr_number', $blockchainPrNumbers)->get();

            foreach ($fakeRecords as $record) {
                $this->recordViolation(
                    type: BreachTypeEnums::UNAUTHORIZED_RECORD->value,
                    severity: 'critical',
                    tableName: 'procurements',
                    record: $record,
                    prNumber: $record->pr_number,
                    message: 'Record exists in database but not on blockchain — unauthorized injection',
                    chainData: null,
                );
            }

            if ($fakeRecords->isNotEmpty()) {
                Log::warning('IntegrityVerification: detected unauthorized DB records', [
                    'count' => $fakeRecords->count(),
                    'pr_numbers' => $fakeRecords->pluck('pr_number')->toArray(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check unauthorized records', ['error' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 3: AUTO-REPAIR
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Auto-repair all pending violations from this verification run.
     *
     * Re-syncs all data from blockchain (source of truth) back into
     * normalized tables, then marks all pending violations for this
     * run as restored.
     */
    private function autoRepair(): void
    {
        $pending = IntegrityAuditLog::forRun($this->runId)
            ->where('recovery_status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            Log::info('IntegrityVerification: auto-repair skipped — no pending violations', [
                'run_id' => $this->runId,
            ]);

            return;
        }

        $count = $pending->count();
        Log::info('IntegrityVerification: auto-repair starting', [
            'run_id' => $this->runId,
            'pending_violations' => $count,
        ]);

        try {
            // Step 1: Get all PR numbers known to blockchain (source of truth)
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();
            $previousRestoredCount = $this->restoredCount;

            // Step 2: Delete DB records that don't exist on blockchain
            // Requirement 5: "Restore original records from trusted blockchain data."
            // Records in DB not on chain = unauthorized injection → must be removed
            if (! empty($blockchainPrNumbers)) {
                $deletedCount = Procurement::withTrashed()
                    ->whereNotIn('pr_number', $blockchainPrNumbers)
                    ->forceDelete();

                // Also clean up orphaned child records. Parent force-deletes should cascade,
                // but this removes any orphan rows left by earlier soft-delete repairs.
                ProcurementStage::whereDoesntHave('procurement')->delete();
                ProcurementDocument::withTrashed()->whereDoesntHave('procurement')->forceDelete();
                ProcurementEvent::whereDoesntHave('procurement')->delete();

                if ($deletedCount > 0) {
                    Log::info('IntegrityVerification: removed unauthorized DB records', [
                        'deleted_procurements' => $deletedCount,
                    ]);
                }
            }

            // Step 3: Re-sync all data FROM blockchain to populate what's authentic
            $this->syncService->syncAll();

            // Step 4: Mark all pending violations from this run as restored
            foreach ($pending as $violation) {
                $violation->markRestored([
                    'restored_by' => 'system_auto_repair',
                    'restored_at' => now()->toIso8601String(),
                    'verification_run_id' => $this->runId,
                ]);

                $this->restoredCount++;
            }

            Log::info('IntegrityVerification: auto-repair completed', [
                'run_id' => $this->runId,
                'restored' => $this->restoredCount,
            ]);
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: auto-repair failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            foreach ($pending as $violation) {
                $violation->markFailed($e->getMessage());
                $this->failedCount++;
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function computeRecordHash(Model $record, string $tableName): string
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            default => [],
        };

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->normaliseHashValue($record->{$field} ?? null);
        }

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function recordToArray(Model $record, string $tableName): array
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            default => [],
        };

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->normaliseHashValue($record->{$field} ?? null);
        }

        return $data;
    }

    private function normaliseHashValue(mixed $value): mixed
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function fetchChainData(string $stream, string $prNumber, ?string $txid): ?array
    {
        try {
            $items = $this->manager->liststreamkeyitems($stream, $prNumber);
            if (! is_array($items) || empty($items)) {
                return null;
            }

            // If txid provided, find exact match
            if ($txid) {
                foreach ($items as $item) {
                    if (($item['txid'] ?? null) === $txid) {
                        return $item['data']['json'] ?? null;
                    }
                }
            }

            // Return latest
            $latest = end($items);

            return $latest['data']['json'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function computeFieldDifferences(array $dbData, array $chainData): array
    {
        $diffs = [];

        // Only compare fields that exist in BOTH datasets.
        // Extra metadata in chainData (e.g. stream_ref, publisher metadata)
        // that aren't tracked in the DB snapshot should not trigger a violation.
        $sharedKeys = array_intersect(array_keys($chainData), array_keys($dbData));

        foreach ($sharedKeys as $key) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $chainValue = $chainData[$key] ?? null;
            $dbValue = $dbData[$key] ?? null;

            if (! $this->valuesAreEquivalent($chainValue, $dbValue)) {
                $diffs[] = [
                    'field' => $key,
                    'old_value' => $chainValue,
                    'new_value' => $dbValue,
                ];
            }
        }

        return $diffs;
    }

    /**
     * Compare two values for semantic equivalence.
     * Handles numeric type coercion ("100" === 100), null/empty equivalence,
     * and nested arrays via json_encode.
     */
    private function valuesAreEquivalent(mixed $a, mixed $b): bool
    {
        // Both null or same type — direct comparison
        if ($a === $b) {
            return true;
        }

        // Both numeric — compare as floats to handle "100" === 100
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        // null vs empty string — treat as equivalent
        if (($a === null && $b === '') || ($a === '' && $b === null)) {
            return true;
        }

        // Both arrays — compare via json_encode
        if (is_array($a) && is_array($b)) {
            return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Fallback — compare as strings
        return (string) $a === (string) $b;
    }

    private function recordViolation(
        string $type,
        string $severity,
        string $tableName,
        ?Model $record,
        string $prNumber,
        string $message,
        ?string $currentHash = null,
        ?string $storedHash = null,
        ?array $fieldDiffs = null,
        ?array $chainData = null,
    ): void {
        $this->violationCounts[$type] = ($this->violationCounts[$type] ?? 0) + 1;

        $dbSnapshot = $record ? $this->recordToArray($record, $tableName) : null;

        IntegrityAuditLog::create([
            'record_id' => $record?->id,
            'stream' => self::TABLE_STREAM_MAP[$tableName]?->value ?? $tableName,
            'stream_key' => $prNumber,
            'txid' => $record?->txid,
            'violation_type' => $type,
            'severity' => $severity,
            'database_snapshot' => $dbSnapshot,
            'blockchain_snapshot' => $chainData,
            'field_differences' => $fieldDiffs ?? ($currentHash ? [['field' => 'hash', 'old_value' => $storedHash, 'new_value' => $currentHash]] : null),
            'recovery_status' => 'pending',
            'verification_run_id' => $this->runId,
            'source' => $this->source,
            'created_at' => now(),
        ]);

        // Mark record as breached
        if ($record && method_exists($record, 'update')) {
            $record->update(['has_breach' => true]);
        }

        // Notify
        $this->notifyBreach($type, $prNumber, $message);

        Log::warning('IntegrityVerification: breach', ['type' => $type, 'pr' => $prNumber, 'table' => $tableName]);
    }

    private function notifyBreach(string $type, string $prNumber, string $message): void
    {
        try {
            $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['bac_chairman', 'hope', 'admin']))->get();
            if ($recipients->isEmpty()) {
                return;
            }
            Notification::send($recipients, new IntegrityBreachNotification(
                breachType: $type,
                stream: 'normalized_db',
                streamKey: $prNumber,
                txid: '',
                breachData: ['message' => $message, 'run_id' => $this->runId],
            ));
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function reset(string $source): void
    {
        $this->runId = IntegrityAuditLog::newRunId();
        $this->source = $source;
        $this->violationCounts = [];
        $this->verifiedCount = 0;
        $this->restoredCount = 0;
        $this->failedCount = 0;
    }
}
