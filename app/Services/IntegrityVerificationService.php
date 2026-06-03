<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\IntegrityAuditLog;
use App\Models\ProcurementMirror;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Integrity Verification Service
 *
 * Comprehensive data integrity verification system that:
 * 1. Detects unauthorized DB modifications (field-level diff)
 * 2. Detects deleted records (chain-vs-mirror existence check)
 * 3. Compares current DB records with blockchain records (content-level)
 * 4. Generates integrity violation reports (permanent audit trail)
 * 5. Restores original records from trusted blockchain data
 * 6. Maintains a permanent audit trail of all recovery operations
 *
 * Architecture:
 *   Blockchain → Source of truth (immutable)
 *   MySQL Mirror → Query cache (mutable, verifiable)
 *   IntegrityAuditLog → Permanent forensic record (append-only)
 *
 * Verification layers:
 *   Layer 1: SHA-256 hash check (fast, detects any change)
 *   Layer 2: Field-level diff (identifies exactly what changed)
 *   Layer 3: Content comparison against chain (authoritative)
 *   Layer 4: Row existence check (detects deletions)
 *   Layer 5: Publisher authorization check (detects unauthorized writes)
 */
class IntegrityVerificationService
{
    private string $runId;

    private string $source;

    /** @var array<string, int> Violation counts by type */
    private array $violationCounts = [];

    private int $verifiedCount = 0;

    private int $restoredCount = 0;

    private int $failedCount = 0;

    public function __construct(
        private readonly BlockchainMirrorSyncService $syncService,
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Run a full integrity verification against all procurement streams.
     *
     * Phase 1: Verify existing mirror rows (hash + content + publisher)
     * Phase 2: Detect missing rows (on chain but not in mirror)
     * Phase 3: Auto-repair detected violations
     *
     * @param  bool  $autoRepair  Whether to automatically restore from blockchain
     * @param  string  $source  Source label: 'scheduled', 'manual', 'read_time'
     * @return array{run_id: string, verified: int, violations: array<string, int>, restored: int, failed: int}
     */
    public function verifyAndRepair(bool $autoRepair = false, string $source = 'scheduled'): array
    {
        $this->reset($source);

        Log::info('IntegrityVerification: starting full verification', [
            'run_id' => $this->runId,
            'auto_repair' => $autoRepair,
            'source' => $source,
        ]);

        // Phase 1: Verify existing mirror rows
        $this->verifyExistingMirrorRows($autoRepair);

        // Phase 2: Detect missing rows (on chain but deleted from mirror)
        $this->detectMissingMirrorRows($autoRepair);

        // Phase 3: Verify user registration integrity
        $this->verifyUserRegistrations($autoRepair);

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
     * Verify a specific PR number's mirror integrity.
     */
    public function verifyPr(string $prNumber, bool $autoRepair = false, string $source = 'manual'): array
    {
        $this->reset($source);

        Log::info('IntegrityVerification: starting PR verification', [
            'run_id' => $this->runId,
            'pr_number' => $prNumber,
            'auto_repair' => $autoRepair,
        ]);

        // Verify existing mirror rows for this PR
        $mirrors = ProcurementMirror::forKey($prNumber)->get();

        foreach ($mirrors as $mirror) {
            $this->verifyAndCompareMirrorRow($mirror, $autoRepair);
        }

        // Check chain for missing rows
        $this->detectMissingRowsForPr($prNumber, $autoRepair);

        return [
            'run_id' => $this->runId,
            'pr_number' => $prNumber,
            'verified' => $this->verifiedCount,
            'violations' => $this->violationCounts,
            'restored' => $this->restoredCount,
            'failed' => $this->failedCount,
        ];
    }

    /**
     * Verify a single mirror record on read (lightweight, reactive).
     *
     * Called by ProcurementMirrorRepository when reading individual records.
     * Only does Layer 1 (hash check) + Layer 5 (publisher auth).
     * Does NOT do full chain comparison — that's for scheduled runs.
     *
     * @return array{valid: bool, audit_log_id: ?int}
     */
    public function verifyOnRead(ProcurementMirror $mirror): array
    {
        $this->reset('read_time');

        // Layer 1: Hash integrity
        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($computedHash !== $mirror->data_hash) {
            $fieldDiffs = $this->computeFieldDifferences($mirror->data_json, []);

            $auditLog = IntegrityAuditLog::recordViolationFromMirror(
                mirror: $mirror,
                violationType: BreachTypeEnums::HASH_MISMATCH->value,
                fieldDifferences: $fieldDiffs,
                chainSnapshot: null, // Don't hit chain on read
                runId: $this->runId,
                source: 'read_time',
            );

            if (! $mirror->isBreached()) {
                $mirror->markAsBreached(BreachTypeEnums::HASH_MISMATCH->value, [
                    'stored_hash' => $mirror->data_hash,
                    'computed_hash' => $computedHash,
                    'detected_during' => 'read',
                ]);
            }

            $this->notifyBreach(BreachTypeEnums::HASH_MISMATCH->value, $mirror->stream, $mirror->stream_key, $mirror->txid);

            return ['valid' => false, 'audit_log_id' => $auditLog->id];
        }

        // Layer 5: Publisher authorization
        if ($mirror->publisher_address && $mirror->is_authorized && ! $this->isAuthorizedPublisher($mirror->publisher_address)) {
            $mirror->update(['is_authorized' => false]);

            if (! $mirror->isBreached()) {
                $mirror->markAsBreached(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value, [
                    'publisher_address' => $mirror->publisher_address,
                ]);
            }

            $auditLog = IntegrityAuditLog::recordViolationFromMirror(
                mirror: $mirror,
                violationType: BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value,
                runId: $this->runId,
                source: 'read_time',
            );

            $this->notifyBreach(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value, $mirror->stream, $mirror->stream_key, $mirror->txid);

            return ['valid' => false, 'audit_log_id' => $auditLog->id];
        }

        // Update verified_at for clean rows
        if (! $mirror->isBreached()) {
            $mirror->verified_at = now();
            $mirror->save();
        }

        return ['valid' => true, 'audit_log_id' => null];
    }

    /**
     * Restore a specific violation from the blockchain.
     *
     * @param  IntegrityAuditLog  $auditLog  The violation to restore
     * @return array{success: bool, items_restored: int, error: ?string}
     */
    public function restoreViolation(IntegrityAuditLog $auditLog): array
    {
        if ($auditLog->recovery_status !== 'pending') {
            return ['success' => false, 'items_restored' => 0, 'error' => 'Violation already processed'];
        }

        try {
            $count = $this->syncService->repairFromChain(
                $auditLog->stream_key,
                $auditLog->stream,
            );

            $auditLog->markRestored([
                'items_restored' => $count,
                'restored_by' => 'system',
                'restored_at' => now()->toIso8601String(),
            ]);

            // Also mark mirror breach as repaired
            if ($auditLog->mirror_id) {
                $mirror = ProcurementMirror::find($auditLog->mirror_id);
                if ($mirror && $mirror->isBreached()) {
                    $mirror->markAsRepaired();
                }
            }

            Log::info('IntegrityVerification: violation restored', [
                'audit_log_id' => $auditLog->id,
                'stream' => $auditLog->stream,
                'stream_key' => $auditLog->stream_key,
                'items_restored' => $count,
            ]);

            return ['success' => true, 'items_restored' => $count, 'error' => null];
        } catch (\Exception $e) {
            $auditLog->markFailed($e->getMessage());

            Log::error('IntegrityVerification: restoration failed', [
                'audit_log_id' => $auditLog->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'items_restored' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a violation report for a verification run.
     *
     * @return array{run_id: string, summary: array, violations: array}
     */
    public function generateReport(string $runId): array
    {
        $logs = IntegrityAuditLog::forRun($runId)
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->get();

        $summary = [
            'total_violations' => $logs->count(),
            'critical' => $logs->where('severity', 'critical')->count(),
            'high' => $logs->where('severity', 'high')->count(),
            'medium' => $logs->where('severity', 'medium')->count(),
            'low' => $logs->where('severity', 'low')->count(),
            'restored' => $logs->where('recovery_status', 'restored')->count(),
            'failed' => $logs->where('recovery_status', 'failed')->count(),
            'pending' => $logs->where('recovery_status', 'pending')->count(),
            'by_type' => $logs->groupBy('violation_type')->map->count()->toArray(),
        ];

        return [
            'run_id' => $runId,
            'summary' => $summary,
            'violations' => $logs->toArray(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 1: VERIFY EXISTING MIRROR ROWS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Verify all existing mirror rows against the blockchain.
     */
    private function verifyExistingMirrorRows(bool $autoRepair): void
    {
        Log::info('IntegrityVerification: Phase 1 — verifying existing mirror rows');

        $bar = null; // Progress tracking for CLI

        ProcurementMirror::chunk(200, function ($mirrors) use ($autoRepair) {
            foreach ($mirrors as $mirror) {
                $this->verifyAndCompareMirrorRow($mirror, $autoRepair);
            }
        });
    }

    /**
     * Verify a single mirror row with full blockchain comparison.
     *
     * Layer 1: SHA-256 hash integrity
     * Layer 2: Field-level diff (what specifically changed)
     * Layer 3: Content comparison against chain (authoritative)
     * Layer 5: Publisher authorization
     */
    private function verifyAndCompareMirrorRow(ProcurementMirror $mirror, bool $autoRepair): void
    {
        $this->verifiedCount++;

        // Layer 1: Hash integrity
        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($computedHash !== $mirror->data_hash) {
            // Layer 3: Fetch chain data for field-level comparison
            $chainData = $this->fetchChainData($mirror->stream, $mirror->stream_key, $mirror->txid);

            // Layer 2: Field-level diff
            $fieldDiffs = $chainData !== null
                ? $this->computeFieldDifferences($mirror->data_json, $chainData)
                : [];

            $auditLog = IntegrityAuditLog::recordViolationFromMirror(
                mirror: $mirror,
                violationType: BreachTypeEnums::HASH_MISMATCH->value,
                fieldDifferences: $fieldDiffs,
                chainSnapshot: $chainData,
                runId: $this->runId,
                source: $this->source,
            );

            $this->incrementViolation(BreachTypeEnums::HASH_MISMATCH->value);

            if (! $mirror->isBreached()) {
                $mirror->markAsBreached(BreachTypeEnums::HASH_MISMATCH->value, [
                    'stored_hash' => $mirror->data_hash,
                    'computed_hash' => $computedHash,
                    'field_differences' => $fieldDiffs,
                ]);
            }

            $this->notifyBreach(BreachTypeEnums::HASH_MISMATCH->value, $mirror->stream, $mirror->stream_key, $mirror->txid);

            if ($autoRepair) {
                $result = $this->restoreViolation($auditLog);

                if ($result['success']) {
                    $this->restoredCount++;
                } else {
                    $this->failedCount++;
                }
            }

            return;
        }

        // Layer 3: Content comparison even when hash matches
        // (detects if chain data differs from mirror — shouldn't happen if hash matches,
        //  but verifies the chain itself agrees with the mirror)
        $chainData = $this->fetchChainData($mirror->stream, $mirror->stream_key, $mirror->txid);

        if ($chainData !== null && $chainData !== $mirror->data_json) {
            $fieldDiffs = $this->computeFieldDifferences($mirror->data_json, $chainData);

            if (! empty($fieldDiffs)) {
                $auditLog = IntegrityAuditLog::recordViolationFromMirror(
                    mirror: $mirror,
                    violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
                    fieldDifferences: $fieldDiffs,
                    chainSnapshot: $chainData,
                    runId: $this->runId,
                    source: $this->source,
                );

                $this->incrementViolation(BreachTypeEnums::CONTENT_MISMATCH->value);

                if (! $mirror->isBreached()) {
                    $mirror->markAsBreached(BreachTypeEnums::CONTENT_MISMATCH->value, [
                        'field_differences' => $fieldDiffs,
                    ]);
                }

                $this->notifyBreach(BreachTypeEnums::CONTENT_MISMATCH->value, $mirror->stream, $mirror->stream_key, $mirror->txid);

                if ($autoRepair) {
                    $result = $this->restoreViolation($auditLog);

                    if ($result['success']) {
                        $this->restoredCount++;
                    } else {
                        $this->failedCount++;
                    }
                }

                return;
            }
        }

        // Layer 5: Publisher authorization
        if ($mirror->publisher_address && $mirror->is_authorized && ! $this->isAuthorizedPublisher($mirror->publisher_address)) {
            $mirror->update(['is_authorized' => false]);

            $auditLog = IntegrityAuditLog::recordViolationFromMirror(
                mirror: $mirror,
                violationType: BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value,
                runId: $this->runId,
                source: $this->source,
            );

            $this->incrementViolation(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value);

            if (! $mirror->isBreached()) {
                $mirror->markAsBreached(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value, [
                    'publisher_address' => $mirror->publisher_address,
                ]);
            }

            $this->notifyBreach(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value, $mirror->stream, $mirror->stream_key, $mirror->txid);

            if ($autoRepair) {
                $result = $this->restoreViolation($auditLog);
                $this->restoredCount += $result['success'] ? 1 : 0;
                $this->failedCount += $result['success'] ? 0 : 1;
            }

            return;
        }

        // Clean row — update verified_at
        if (! $mirror->isBreached()) {
            $mirror->verified_at = now();
            $mirror->save();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 2: DETECT MISSING ROWS (DELETED FROM MIRROR)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detect blockchain records that are missing from the mirror (deleted).
     */
    private function detectMissingMirrorRows(bool $autoRepair): void
    {
        Log::info('IntegrityVerification: Phase 2 — detecting missing mirror rows');

        foreach (StreamEnums::cases() as $case) {
            if (! $case->isProcurementStream()) {
                continue;
            }

            $stream = $case->value;

            try {
                $manager = app(Manager::class);
                $items = $manager->liststreamitems($stream, true, 10000);
            } catch (\Exception $e) {
                Log::warning('IntegrityVerification: failed to read stream for missing row check', [
                    'stream' => $stream,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! is_array($items) || empty($items)) {
                continue;
            }

            foreach ($items as $item) {
                $this->checkItemExists($stream, $item, $autoRepair);
            }
        }
    }

    /**
     * Detect missing rows for a specific PR number.
     */
    private function detectMissingRowsForPr(string $prNumber, bool $autoRepair): void
    {
        foreach (StreamEnums::cases() as $case) {
            if (! $case->isProcurementStream()) {
                continue;
            }

            $stream = $case->value;

            try {
                $manager = app(Manager::class);
                $items = $manager->liststreamkeyitems($stream, $prNumber);
            } catch (\Exception $e) {
                continue;
            }

            if (! is_array($items) || empty($items)) {
                continue;
            }

            foreach ($items as $item) {
                $this->checkItemExists($stream, $item, $autoRepair);
            }
        }
    }

    /**
     * Check if a single chain item exists in the mirror.
     */
    private function checkItemExists(string $stream, array $item, bool $autoRepair): void
    {
        $key = $item['key'] ?? ($item['publishers'][0] ?? null);
        $txid = $item['txid'] ?? null;

        if (! $key || ! $txid) {
            return;
        }

        $this->verifiedCount++;

        $exists = ProcurementMirror::where('stream', $stream)
            ->where('stream_key', $key)
            ->where('txid', $txid)
            ->exists();

        if (! $exists) {
            $chainData = $item['data']['json'] ?? [];

            // Look up the latest revision for this stream+key to determine revision context.
            // If the row was deleted, there may still be older revisions in the mirror.
            $latestExisting = ProcurementMirror::where('stream', $stream)
                ->where('stream_key', $key)
                ->orderByDesc('revision_number')
                ->first();

            $auditLog = IntegrityAuditLog::recordViolation(
                stream: $stream,
                streamKey: $key,
                violationType: BreachTypeEnums::ROW_DELETED->value,
                txid: $txid,
                chainSnapshot: is_array($chainData) ? $chainData : [],
                runId: $this->runId,
                source: $this->source,
                revisionNumber: $latestExisting?->revision_number,
                parentTxid: $latestExisting?->parent_txid,
            );

            $this->incrementViolation(BreachTypeEnums::ROW_DELETED->value);

            Log::warning('IntegrityVerification: deleted row detected', [
                'stream' => $stream,
                'stream_key' => $key,
                'txid' => $txid,
            ]);

            $this->notifyBreach(BreachTypeEnums::ROW_DELETED->value, $stream, $key, $txid);

            if ($autoRepair) {
                $result = $this->restoreViolation($auditLog);

                if ($result['success']) {
                    $this->restoredCount++;
                } else {
                    $this->failedCount++;
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 3: USER REGISTRATION INTEGRITY
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Verify user registration integrity by comparing on-chain
     * user.registrations data with MySQL user records.
     */
    private function verifyUserRegistrations(bool $autoRepair): void
    {
        Log::info('IntegrityVerification: Phase 3 — verifying user registrations');

        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems(
                StreamEnums::USER_REGISTRATIONS->value,
                true,
                10000,
            );
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to read user.registrations stream', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! is_array($items) || empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];

            if (! is_array($data) || empty($data)) {
                continue;
            }

            $userId = $data['user_id'] ?? null;
            $chainAddress = $data['blockchain_address'] ?? null;

            if (! $userId || ! $chainAddress) {
                continue;
            }

            $this->verifiedCount++;

            $user = User::find($userId);

            // Check: user deleted from MySQL but registration exists on chain
            if (! $user) {
                // Look up mirror revision context for this user registration
                $userMirror = ProcurementMirror::where('stream', StreamEnums::USER_REGISTRATIONS->value)
                    ->where('stream_key', (string) $userId)
                    ->orderByDesc('revision_number')
                    ->first();

                $auditLog = IntegrityAuditLog::recordViolation(
                    stream: StreamEnums::USER_REGISTRATIONS->value,
                    streamKey: (string) $userId,
                    violationType: BreachTypeEnums::ROW_DELETED->value,
                    txid: $item['txid'] ?? '',
                    chainSnapshot: $data,
                    runId: $this->runId,
                    source: $this->source,
                    revisionNumber: $userMirror?->revision_number,
                    parentTxid: $userMirror?->parent_txid,
                );

                $this->incrementViolation(BreachTypeEnums::ROW_DELETED->value);

                $this->notifyBreach(BreachTypeEnums::ROW_DELETED->value, StreamEnums::USER_REGISTRATIONS->value, (string) $userId, $item['txid'] ?? '');

                // User deletions cannot be auto-repaired (would need to recreate user)
                $auditLog->markSkipped('User record deletion cannot be auto-repaired — requires manual user recreation');

                continue;
            }

            // Check: blockchain_address tampered in MySQL
            if ($user->blockchain_address !== $chainAddress) {
                $fieldDiffs = [[
                    'field' => 'blockchain_address',
                    'old_value' => $chainAddress,
                    'new_value' => $user->blockchain_address,
                ]];

                // Look up mirror revision context for this user registration
                $userMirror = ProcurementMirror::where('stream', StreamEnums::USER_REGISTRATIONS->value)
                    ->where('stream_key', (string) $userId)
                    ->orderByDesc('revision_number')
                    ->first();

                $auditLog = IntegrityAuditLog::recordViolation(
                    stream: StreamEnums::USER_REGISTRATIONS->value,
                    streamKey: (string) $userId,
                    violationType: BreachTypeEnums::USER_ADDRESS_TAMPERED->value,
                    txid: $item['txid'] ?? '',
                    fieldDifferences: $fieldDiffs,
                    mirrorSnapshot: ['blockchain_address' => $user->blockchain_address],
                    chainSnapshot: ['blockchain_address' => $chainAddress],
                    runId: $this->runId,
                    source: $this->source,
                    revisionNumber: $userMirror?->revision_number,
                    parentTxid: $userMirror?->parent_txid,
                );

                $this->incrementViolation(BreachTypeEnums::USER_ADDRESS_TAMPERED->value);

                $this->notifyBreach(BreachTypeEnums::USER_ADDRESS_TAMPERED->value, StreamEnums::USER_REGISTRATIONS->value, (string) $userId, $item['txid'] ?? '');

                if ($autoRepair) {
                    // Restore the blockchain address from chain
                    try {
                        $user->update(['blockchain_address' => $chainAddress]);

                        $auditLog->markRestored([
                            'field_restored' => 'blockchain_address',
                            'restored_value' => $chainAddress,
                        ]);

                        $this->restoredCount++;
                    } catch (\Exception $e) {
                        $auditLog->markFailed($e->getMessage());
                        $this->failedCount++;
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // FIELD-LEVEL DIFF ENGINE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Compute field-level differences between mirror data and chain data.
     *
     * Returns an array of differences, each with:
     * - field: the key that differs
     * - old_value: the blockchain (trusted) value
     * - new_value: the mirror (potentially tampered) value
     *
     * @param  array  $mirrorData  Current DB state
     * @param  array  $chainData  Blockchain state (trusted)
     * @return array<int, array{field: string, old_value: mixed, new_value: mixed}>
     */
    public function computeFieldDifferences(array $mirrorData, array $chainData): array
    {
        $differences = [];

        if (empty($chainData)) {
            // No chain data available — can't diff, but hash already mismatched
            return [[
                'field' => '*',
                'old_value' => null,
                'new_value' => '<data_modified>',
                'note' => 'Chain data unavailable for field-level comparison',
            ]];
        }

        // Compare all keys from both arrays
        $allKeys = array_unique(array_merge(array_keys($chainData), array_keys($mirrorData)));

        foreach ($allKeys as $key) {
            $chainValue = $chainData[$key] ?? null;
            $mirrorValue = $mirrorData[$key] ?? null;

            if ($this->valuesDiffer($chainValue, $mirrorValue)) {
                $differences[] = [
                    'field' => $key,
                    'old_value' => $chainValue,
                    'new_value' => $mirrorValue,
                ];
            }
        }

        return $differences;
    }

    /**
     * Determine if two values differ (recursive for nested arrays).
     */
    private function valuesDiffer(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                !== json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Normalize scalar comparison
        if (is_string($a) && is_string($b)) {
            return $a !== $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a !== (float) $b;
        }

        if (is_bool($a) && is_bool($b)) {
            return $a !== $b;
        }

        if ($a === null && $b === null) {
            return false;
        }

        // Mixed types — compare string representations
        return (string) $a !== (string) $b;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CHAIN DATA FETCHING
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Fetch the blockchain data for a specific stream/key/txid.
     */
    private function fetchChainData(string $stream, string $key, string $txid): ?array
    {
        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamkeyitems($stream, $key);

            if (! is_array($items) || empty($items)) {
                return null;
            }

            foreach ($items as $item) {
                if (($item['txid'] ?? null) === $txid) {
                    return $item['data']['json'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::debug('IntegrityVerification: chain data fetch failed', [
                'stream' => $stream,
                'key' => $key,
                'txid' => $txid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Send breach notification to BAC Chairman, HOPE, and admins.
     */
    private function notifyBreach(
        string $breachType,
        string $stream,
        string $key,
        string $txid,
    ): void {
        try {
            $recipients = User::whereHas('roles', function ($query): void {
                $query->whereIn('name', ['bac_chairman', 'hope', 'admin']);
            })->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new IntegrityBreachNotification(
                breachType: $breachType,
                stream: $stream,
                streamKey: $key,
                txid: $txid,
                breachData: [
                    'verification_run_id' => $this->runId,
                    'source' => $this->source,
                ],
            ));
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: breach notification failed', [
                'breach_type' => $breachType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Check if a publisher address belongs to an authorized (non-locked) user.
     */
    private function isAuthorizedPublisher(string $address): bool
    {
        return User::where('blockchain_address', $address)
            ->where('account_locked', false)
            ->exists();
    }

    /**
     * Increment violation count for a type.
     */
    private function incrementViolation(string $type): void
    {
        $this->violationCounts[$type] = ($this->violationCounts[$type] ?? 0) + 1;
    }

    /**
     * Reset state for a new verification run.
     */
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
