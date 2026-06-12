<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\File;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use App\Services\Integrity\BlockchainPayloadProjector;
use App\Services\Integrity\BlockchainVerificationIndex;
use App\Services\Integrity\IntegrityComparator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    private bool $verifyPublishers = false;

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

    public function __construct(
        private readonly Manager $manager,
        private readonly NormalizedTableSyncService $syncService,
        private readonly BlockchainPayloadProjector $payloadProjector,
        private readonly IntegrityComparator $comparator,
        private BlockchainVerificationIndex $blockchainIndex,
        private readonly BlockchainAuditTrailService $auditTrail,
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Run full integrity verification against all normalized tables.
     *
     * @return array{run_id: string, verified: int, violations: array, restored: int, failed: int}
     */
    public function verifyAndRepair(bool $autoRepair = false, string $source = 'scheduled', bool $deepPublisherCheck = false): array
    {
        // Prevent concurrent verification runs (race condition protection)
        $lock = Cache::lock('integrity:verification:lock', 300);

        if (! $lock->get()) {
            Log::warning('IntegrityVerification: skipped - another run is in progress', ['source' => $source]);

            return [
                'run_id' => $this->runId ?? uniqid('skip-'),
                'verified' => 0,
                'violations' => [],
                'restored' => 0,
                'failed' => 0,
                'skipped' => true,
            ];
        }

        try {
            $this->reset($source);
            $this->verifyPublishers = $deepPublisherCheck;

            Log::info('IntegrityVerification: starting', ['run_id' => $this->runId, 'auto_repair' => $autoRepair]);

            $this->preloadBlockchainIndex();
            $this->ensureBlockchainIndexLoaded();

            // Phase 1: Verify hashes on all normalized tables
            $this->verifyAllTables();

            // Phase 2: Detect deleted records (chain has it, DB doesn't)
            $this->detectDeletedRecords();

            // Audit log integrity check disabled — audit trail is on blockchain;
            // DB mismatches are restored via restoreAuditLogsToMySQL()

            if (empty($this->violationCounts)) {
                $this->resolveStalePendingViolationsAfterCleanRun();
            }

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

        } finally {
            $lock->release();
        }
    }

    /**
     * Verify a specific PR number.
     */
    public function verifyPr(string $prNumber, bool $autoRepair = false, string $source = 'manual', bool $deepPublisherCheck = false): array
    {
        $this->reset($source);
        $this->verifyPublishers = $deepPublisherCheck;

        $procurement = Procurement::where('pr_number', $prNumber)->first();
        if (! $procurement) {
            // Record may have been hard-deleted. Check blockchain before giving up.
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();
            if (in_array($prNumber, $blockchainPrNumbers, true)) {
                // Exists on chain but missing from DB — record as deleted violation
                $this->recordViolation(
                    type: BreachTypeEnums::ROW_DELETED->value,
                    tableName: 'procurements',
                    record: null,
                    prNumber: $prNumber,
                    message: 'PR exists on blockchain but is absent from the database (deleted)',
                    chainData: null,
                );

                if ($autoRepair) {
                    $this->autoRepair();
                }
            }

            return [
                'run_id' => $this->runId,
                'verified' => 0,
                'violations' => $this->violationCounts,
                'restored' => $this->restoredCount,
                'failed' => $this->failedCount,
            ];
        }

        // Verify procurement record
        $this->verifyRecord($procurement, 'procurements', StreamEnums::METADATA);

        // Verify related records across all stream-backed mirror tables.
        foreach ($procurement->stages as $stage) {
            $this->verifyRecord($stage, 'procurement_stages', StreamEnums::STATUS);
        }
        foreach ($procurement->documents as $doc) {
            $this->verifyRecord($doc, 'procurement_documents', StreamEnums::DOCUMENTS);
        }
        foreach ($procurement->events as $event) {
            $this->verifyRecord($event, 'procurement_events', StreamEnums::EVENTS);
        }
        foreach ($procurement->corrections as $correction) {
            $this->verifyRecord($correction, 'procurement_corrections', StreamEnums::CORRECTIONS);
        }
        foreach (ProcurementArchive::where('procurement_id', $procurement->id)->get() as $archive) {
            $this->verifyRecord($archive, 'procurement_archives', StreamEnums::ARCHIVE);
        }
        foreach (ProcurementMetadataCorrection::where('procurement_id', $procurement->id)->get() as $metadataCorrection) {
            $this->verifyRecord($metadataCorrection, 'procurement_metadata_corrections', StreamEnums::PROCUREMENTS_CORRECTIONS);
        }
        foreach (File::where('pr_number', $procurement->pr_number)->get() as $file) {
            $this->verifyRecord($file, 'files', StreamEnums::FILE_METADATA);
        }

        if ($autoRepair) {
            $this->autoRepair();
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

            // Re-sync from blockchain to restore, then prove the violation no
            // longer reproduces before marking it restored.
            $syncCounts = $this->syncService->syncAll();

            // Hash must be refreshed after sync so the post-repair verifier
            // sees the new DB state matches the chain.
            $this->refreshHashesAfterRepair();

            if (! $this->violationIsResolved($auditLog)) {
                $auditLog->markFailed('Post-repair verification failed; the violation still reproduces after syncing from blockchain.');
                $this->failedCount++;

                return ['success' => false, 'items_restored' => 0, 'deleted' => $deletedCount, 'error' => 'Post-repair verification failed'];
            }

            $auditLog->markRestored([
                'restored_by' => 'system',
                'restored_at' => now()->toIso8601String(),
                'deleted_records' => $deletedCount,
                'sync_counts' => $syncCounts,
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
            return $this->blockchainIndex->prNumbers(StreamEnums::METADATA);
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

        ProcurementCorrection::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_corrections', StreamEnums::CORRECTIONS);
            }
        });

        ProcurementArchive::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_archives', StreamEnums::ARCHIVE);
            }
        });

        ProcurementMetadataCorrection::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'procurement_metadata_corrections', StreamEnums::PROCUREMENTS_CORRECTIONS);
            }
        });

        File::chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->verifyRecord($record, 'files', StreamEnums::FILE_METADATA);
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
        $prNumber = $record->pr_number ?? $record->procurement?->pr_number ?? null;
        if (! $prNumber) {
            return;
        }

        $recordHadViolation = false;

        if ($this->recordReferencesSupersededChainRevision($record, $tableName, $prNumber, $stream)) {
            return;
        }

        // Layer 1: Recompute hash and compare with stored data_hash
        $currentHash = $this->computeRecordHash($record, $tableName);
        $storedHash = $record->data_hash;

        if ($storedHash && $currentHash !== $storedHash) {
            // Hash mismatch - record may have been modified in DB, or the local
            // hash may be stale because the projection/hash algorithm changed.
            // Blockchain content comparison is authoritative.
            $chainData = $this->fetchChainData($stream->value, $prNumber, $record->txid);
            $fieldDiffs = $chainData
                ? $this->computeFieldDifferences(
                    $this->recordToArray($record, $tableName),
                    $this->payloadProjector->projectForTable($chainData, $tableName, $record),
                )
                : null;

            if ($chainData && empty($fieldDiffs)) {
                $this->refreshTrustedRecordHash($record, $currentHash);
                $this->resolvePendingStaleHashViolations($record, $prNumber, $stream->value);
            } else {
                $this->recordViolation(
                    type: BreachTypeEnums::HASH_MISMATCH->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'Record was modified in database since last sync',
                    currentHash: $currentHash,
                    storedHash: $storedHash,
                    fieldDiffs: ! empty($fieldDiffs) ? $fieldDiffs : null,
                    chainData: $chainData,
                );

                return;
            }
        }

        // Layer 2: Compare with blockchain data
        $chainData = $this->fetchChainData($stream->value, $prNumber, $record->txid);
        if ($chainData) {
            $fieldDiffs = $this->computeFieldDifferences(
                $this->recordToArray($record, $tableName),
                $this->payloadProjector->projectForTable($chainData, $tableName, $record),
            );

            if (! empty($fieldDiffs)) {
                $recordHadViolation = true;

                $this->recordViolation(
                    type: BreachTypeEnums::CONTENT_MISMATCH->value,
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
                $recordHadViolation = true;

                $this->recordViolation(
                    type: BreachTypeEnums::USER_ADDRESS_TAMPERED->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'User address was modified from original blockchain record',
                    fieldDiffs: [['field' => 'user_address', 'old_value' => $chainUserAddress, 'new_value' => $dbUserAddress]],
                    chainData: $chainData,
                );
            }
        }

        if ($tableName === 'procurements' && $this->procurementStatusDiffersFromLatestStatusStream($record, $prNumber)) {
            $recordHadViolation = true;
        }

        // Layer 4: Check if the blockchain publisher is unauthorized
        // (verifies that the txid publisher matches the expected authorized address)
        if ($this->verifyPublishers && $record->txid) {
            $recordHadViolation = $this->checkUnauthorizedPublisher($record, $tableName, $prNumber, $stream->value) || $recordHadViolation;
        }

        if (! $recordHadViolation) {
            $this->resolvePendingFalsePositiveViolations($record, $tableName, $prNumber, $stream->value);

            $record->update([
                'last_verified_at' => now(),
                'is_blockchain_verified' => true,
                'has_breach' => $this->hasPendingViolationsForRecord($record, $tableName, $prNumber, $stream->value),
            ]);
        }
    }

    /**
     * Check if the publisher of a blockchain transaction is authorized.
     * Compares the publisher address of the record's txid against
     * the known authorized publisher stored on the record.
     */
    private function checkUnauthorizedPublisher(Model $record, string $tableName, string $prNumber, string $stream): bool
    {
        try {
            $txid = $record->txid;
            if (! $txid) {
                return false;
            }

            // Get the txid details from blockchain (verbose = true)
            $txData = $this->manager->getrawtransaction($txid, 1);
            if (! $txData || ! is_array($txData)) {
                return false;
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
                return false;
            }

            // Get the authorized publisher address for this record
            $authorizedAddress = $record->user_address ?? null;

            if (! $authorizedAddress) {
                return false;
            }

            // Check if any publisher is NOT the authorized address
            foreach ($publishers as $publisher) {
                if ($publisher !== $authorizedAddress) {
                    $this->recordViolation(
                        type: BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value,
                        tableName: $tableName,
                        record: $record,
                        prNumber: $prNumber,
                        message: "Unauthorized publisher {$publisher} - expected {$authorizedAddress}",
                        chainData: ['publishers' => $publishers, 'authorized_address' => $authorizedAddress],
                    );

                    return true; // One violation per record
                }
            }
        } catch (\Exception $e) {
            // Non-critical — publisher check is best-effort
            Log::debug('IntegrityVerification: publisher check failed', [
                'txid' => $record->txid,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function procurementStatusDiffersFromLatestStatusStream(Model $record, string $prNumber): bool
    {
        $diffs = $this->procurementStatusDifferencesFromLatestStatusStream($record, $prNumber);

        if (empty($diffs)) {
            return false;
        }

        $latestStatus = $this->latestStatusItemForPrNumber($prNumber);
        $chainData = is_array($latestStatus) ? ($latestStatus['data']['json'] ?? null) : null;

        $this->recordViolation(
            type: BreachTypeEnums::CONTENT_MISMATCH->value,
            tableName: 'procurements',
            record: $record,
            prNumber: $prNumber,
            message: 'Procurement mirror status differs from the latest procurement.status blockchain entry',
            fieldDiffs: $diffs,
            chainData: is_array($chainData) ? $chainData : null,
        );

        return true;
    }

    private function procurementStatusDifferencesFromLatestStatusStream(Model $record, string $prNumber): array
    {
        $latestStatus = $this->latestStatusItemForPrNumber($prNumber);

        if (! $latestStatus) {
            return [];
        }

        $chainData = $latestStatus['data']['json'] ?? null;
        if (! is_array($chainData)) {
            return [];
        }

        $diffs = [];
        $chainStatus = $chainData['current_status'] ?? null;
        $chainStage = $chainData['stage'] ?? null;

        if ($chainStatus !== null && ! $this->valuesMatch($chainStatus, $record->current_status ?? null)) {
            $diffs[] = ['field' => 'current_status', 'old_value' => $chainStatus, 'new_value' => $record->current_status ?? null];
        }

        if ($chainStage !== null && ! $this->valuesMatch($chainStage, $record->current_stage ?? null)) {
            $diffs[] = ['field' => 'current_stage', 'old_value' => $chainStage, 'new_value' => $record->current_stage ?? null];
        }

        return $diffs;
    }

    private function latestStatusItemForPrNumber(string $prNumber): ?array
    {
        $items = $this->blockchainIndex->itemsByPrNumber(StreamEnums::STATUS, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }

    private function valuesMatch(mixed $chainValue, mixed $dbValue): bool
    {
        if ($chainValue === $dbValue) {
            return true;
        }

        if (is_numeric($chainValue) && is_numeric($dbValue)) {
            return (float) $chainValue === (float) $dbValue;
        }

        return (string) $chainValue === (string) $dbValue;
    }

    private function recordReferencesSupersededChainRevision(Model $record, string $tableName, string $prNumber, StreamEnums $stream): bool
    {
        if ($this->recordReferencesLatestChainRevision($record, $tableName, $prNumber, $stream)) {
            return false;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        $latestTxid = $latest['txid'] ?? null;
        $recordTxid = $record->txid ?? null;
        $fieldDiffs = [[
            'field' => 'txid',
            'old_value' => $latestTxid,
            'new_value' => $recordTxid,
        ]];

        // If the DB row's txid belongs to a different blockchain PR, surface
        // that as an explicit PR-number tampering diff. A plain latest-txid
        // mismatch is not enough forensic detail for PR swap attempts.
        $recordTxidChainData = is_string($recordTxid) && $recordTxid !== ''
            ? $this->blockchainIndex->jsonByTxid($stream, $recordTxid)
            : null;
        $recordTxidPrNumber = $recordTxidChainData['pr_number'] ?? null;
        if (is_string($recordTxidPrNumber) && $recordTxidPrNumber !== '' && $recordTxidPrNumber !== $prNumber) {
            $fieldDiffs[] = [
                'field' => 'pr_number',
                'old_value' => $recordTxidPrNumber,
                'new_value' => $prNumber,
            ];
        }

        $this->recordViolation(
            type: BreachTypeEnums::CONTENT_MISMATCH->value,
            tableName: $tableName,
            record: $record,
            prNumber: $prNumber,
            message: 'Procurement mirror references an older blockchain revision instead of the latest trusted chain record',
            fieldDiffs: $fieldDiffs,
            chainData: is_array($latest['data']['json'] ?? null) ? $latest['data']['json'] : null,
        );

        return true;
    }

    private function recordReferencesLatestChainRevision(Model $record, string $tableName, string $prNumber, StreamEnums $stream): bool
    {
        if ($tableName !== 'procurements') {
            return true;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        if (! is_array($latest)) {
            return true;
        }

        $latestTxid = $latest['txid'] ?? null;

        return ! is_string($latestTxid) || $latestTxid === '' || $latestTxid === ($record->txid ?? null);
    }

    private function latestChainItemForRecord(string $tableName, string $prNumber, StreamEnums $stream): ?array
    {
        if ($tableName !== 'procurements') {
            return null;
        }

        $items = $this->blockchainIndex->itemsByPrNumber($stream, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }

    private function refreshTrustedRecordHash(Model $record, string $currentHash): void
    {
        $record->forceFill([
            'data_hash' => $currentHash,
            'blockchain_hash' => $currentHash,
        ])->save();

        $record->refresh();
    }

    private function resolvePendingStaleHashViolations(Model $record, string $prNumber, string $stream): void
    {
        $query = IntegrityAuditLog::query()
            ->where('recovery_status', 'pending')
            ->where('violation_type', BreachTypeEnums::HASH_MISMATCH->value)
            ->where('stream', $stream)
            ->where('stream_key', $prNumber);

        if ($record->txid) {
            $query->where('txid', $record->txid);
        } else {
            $query->where('record_id', $record->id);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            $log->markSkipped('Verifier re-ran with canonical blockchain comparison and found the DB record content matches trusted chain data. Local stale hash was refreshed; original audit record retained.');
        }
    }

    private function resolvePendingFalsePositiveViolations(Model $record, string $tableName, string $prNumber, string $stream): void
    {
        $query = IntegrityAuditLog::query()
            ->where('recovery_status', 'pending')
            ->where('violation_type', BreachTypeEnums::CONTENT_MISMATCH->value)
            ->where('stream', $stream)
            ->where('stream_key', $prNumber);

        if ($record->txid) {
            $query->where('txid', $record->txid);
        } else {
            $query->where('record_id', $record->id);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            $log->markSkipped('Verifier re-ran with blockchain payload projection and found no remaining DB/blockchain mismatch. Marked non-actionable; original audit record retained.');
        }

        if ($logs->isNotEmpty()) {
            Log::info('IntegrityVerification: resolved stale pending content mismatches', [
                'count' => $logs->count(),
                'stream' => $stream,
                'stream_key' => $prNumber,
                'txid' => $record->txid,
                'table' => $tableName,
            ]);
        }
    }

    private function hasPendingViolationsForRecord(Model $record, string $tableName, string $prNumber, string $stream): bool
    {
        return IntegrityAuditLog::query()
            ->where('recovery_status', 'pending')
            ->where('stream', $stream)
            ->where('stream_key', $prNumber)
            ->when(
                $record->txid,
                fn ($query, string $txid) => $query->where('txid', $txid),
                fn ($query) => $query->where('record_id', $record->id),
            )
            ->exists();
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
     * Verify audit log integrity: compare DB audit logs against blockchain.
     *
     * Requirement 6: "Maintain a permanent audit trail of all recovery operations."
     * If panel deletes/modifies DB audit logs, this detects the tampering
     * and can restore from blockchain.
     */
    private function verifyAuditLogIntegrity(): void
    {
        try {
            $blockchainEntries = $this->auditTrail->recoverAuditTrail();
            if (empty($blockchainEntries)) {
                return;
            }

            // Build map of blockchain txids -> entries
            $chainTxids = [];
            foreach ($blockchainEntries as $entry) {
                $txid = $entry['txid'] ?? null;
                if ($txid) {
                    $chainTxids[$txid] = $entry['data'];
                }
            }

            // Check each blockchain entry exists in DB
            $missingFromDb = 0;
            foreach ($chainTxids as $txid => $chainData) {
                $exists = IntegrityAuditLog::where('txid', $txid)
                    ->where('stream', $chainData['stream'] ?? '')
                    ->exists();

                if (! $exists) {
                    $missingFromDb++;
                }
            }

            if ($missingFromDb > 0) {
                $this->recordViolation(
                    type: BreachTypeEnums::CONTENT_MISMATCH->value,
                    tableName: 'integrity_audit_logs',
                    record: null,
                    prNumber: 'audit_trail',
                    message: "Audit log tampering detected: {$missingFromDb} blockchain entries missing from DB",
                    chainData: ['missing_count' => $missingFromDb, 'total_chain' => count($chainTxids)],
                );

                Log::warning('IntegrityVerification: audit log tampering detected', [
                    'missing_from_db' => $missingFromDb,
                    'total_on_chain' => count($chainTxids),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: audit log integrity check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect records on blockchain that are missing from DB.
     * (Unauthorized deletion)
     */
    private function detectMissingFromDb(): void
    {
        $this->detectMissingProcurementsFromDb();
        $this->detectMissingStreamRowsFromDb(StreamEnums::STATUS, ProcurementStage::class, 'procurement_stages');
        $this->detectMissingStreamRowsFromDb(StreamEnums::DOCUMENTS, ProcurementDocument::class, 'procurement_documents');
        $this->detectMissingStreamRowsFromDb(StreamEnums::EVENTS, ProcurementEvent::class, 'procurement_events', skipSystemPr: true);
        $this->detectMissingStreamRowsFromDb(StreamEnums::CORRECTIONS, ProcurementCorrection::class, 'procurement_corrections');
        $this->detectMissingStreamRowsFromDb(StreamEnums::ARCHIVE, ProcurementArchive::class, 'procurement_archives');
        $this->detectMissingStreamRowsFromDb(StreamEnums::PROCUREMENTS_CORRECTIONS, ProcurementMetadataCorrection::class, 'procurement_metadata_corrections');
        $this->detectMissingFileMetadataRowsFromDb();
    }

    private function detectMissingProcurementsFromDb(): void
    {
        try {
            $reportedPrNumbers = [];

            foreach ($this->blockchainIndex->items(StreamEnums::METADATA) as $item) {
                $data = $item['data']['json'] ?? [];
                $prNumber = $data['pr_number'] ?? null;

                if (! $prNumber || isset($reportedPrNumbers[$prNumber]) || Procurement::where('pr_number', $prNumber)->exists()) {
                    continue;
                }

                $reportedPrNumbers[$prNumber] = true;

                $this->recordViolation(
                    type: BreachTypeEnums::ROW_DELETED->value,
                    tableName: 'procurements',
                    record: null,
                    prNumber: $prNumber,
                    message: 'PR exists on blockchain but not in database',
                    chainData: $data,
                    chainTxid: $item['txid'] ?? null,
                );
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted procurements', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Detect child stream records that exist on-chain but are missing from DB.
     */
    private function detectMissingStreamRowsFromDb(StreamEnums $stream, string $modelClass, string $tableName, bool $skipSystemPr = false): void
    {
        try {
            foreach ($this->blockchainIndex->items($stream) as $item) {
                $txid = $item['txid'] ?? null;
                $data = $item['data']['json'] ?? [];
                $prNumber = $data['pr_number'] ?? data_get($item, 'keys.0');

                if (! $txid || ! $prNumber || ($skipSystemPr && $prNumber === 'system')) {
                    continue;
                }

                if (! $modelClass::where('txid', $txid)->exists()) {
                    $this->recordViolation(
                        type: BreachTypeEnums::ROW_DELETED->value,
                        tableName: $tableName,
                        record: null,
                        prNumber: $prNumber,
                        message: "{$stream->value} item exists on blockchain but not in database",
                        chainData: $data,
                        chainTxid: $txid,
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted stream rows', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);
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

            // Find procurements in DB that don't exist on chain by PR number.
            // A PR change in DB creates a txid-mismatch: the txid still exists
            // on chain but under the original PR. We fetch chain data via txid
            // so field differences can be shown in the breach detail.
            $fakeRecords = Procurement::whereNotIn('pr_number', $blockchainPrNumbers)->get();

            foreach ($fakeRecords as $record) {
                $fieldDiffs = null;
                $chainData = null;

                // The DB pr_number has been tampered, so we cannot use it as a stream key
                // to look up the blockchain entry — the chain still stores the record under
                // the ORIGINAL pr_number. Instead, look up by txid across the full index
                // (which is keyed by txid, not by pr_number), then fall back to a
                // pr_number-based lookup only when there is no txid to go on.
                if ($record->txid) {
                    // Primary: resolve by txid — works regardless of the tampered pr_number
                    $chainData = $this->blockchainIndex->jsonByTxid(StreamEnums::METADATA->value, $record->txid);

                    // Fallback: the index may not be loaded yet; try a direct RPC call by txid
                    if (! $chainData) {
                        try {
                            $txData = $this->manager->getrawtransaction($record->txid, 1);
                            if (is_array($txData)) {
                                foreach ($txData['data'] ?? [] as $dataItem) {
                                    if (isset($dataItem['json']) && is_array($dataItem['json'])) {
                                        $chainData = $dataItem['json'];
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception) {
                            // Non-fatal — field diff will be empty but breach is still recorded
                        }
                    }

                    if ($chainData) {
                        $fieldDiffs = $this->computeFieldDifferences(
                            $this->recordToArray($record, 'procurements'),
                            $this->payloadProjector->projectForTable($chainData, 'procurements', $record),
                        );
                    }
                }

                $this->recordViolation(
                    type: BreachTypeEnums::UNAUTHORIZED_RECORD->value,
                    tableName: 'procurements',
                    record: $record,
                    prNumber: $record->pr_number,
                    message: 'Record exists in database but not on blockchain - unauthorized injection or PR tampering',
                    fieldDiffs: $fieldDiffs,
                    chainData: $chainData,
                );
            }

            $this->detectUnauthorizedProcurementTxidsInDb($blockchainPrNumbers);

            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::STATUS, ProcurementStage::class, 'procurement_stages');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::DOCUMENTS, ProcurementDocument::class, 'procurement_documents');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::EVENTS, ProcurementEvent::class, 'procurement_events');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::CORRECTIONS, ProcurementCorrection::class, 'procurement_corrections');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::ARCHIVE, ProcurementArchive::class, 'procurement_archives');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::PROCUREMENTS_CORRECTIONS, ProcurementMetadataCorrection::class, 'procurement_metadata_corrections');
            $this->detectUnauthorizedStreamRowsInDb(StreamEnums::FILE_METADATA, File::class, 'files');

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

    /**
     * @param  list<string>  $blockchainPrNumbers
     */
    private function detectUnauthorizedProcurementTxidsInDb(array $blockchainPrNumbers): void
    {
        $chainTxids = $this->getBlockchainTxids(StreamEnums::METADATA);

        if (empty($chainTxids)) {
            return;
        }

        $fakeRecords = Procurement::query()
            ->whereIn('pr_number', $blockchainPrNumbers)
            ->where(function ($query) use ($chainTxids) {
                $query->whereNull('txid')
                    ->orWhere('txid', '')
                    ->orWhereNotIn('txid', $chainTxids);
            })
            ->get();

        foreach ($fakeRecords as $record) {
            $this->recordViolation(
                type: BreachTypeEnums::UNAUTHORIZED_RECORD->value,
                tableName: 'procurements',
                record: $record,
                prNumber: $record->pr_number,
                message: 'Procurement record exists in database but its txid is absent from procurement.metadata blockchain stream',
                chainData: null,
            );
        }
    }

    private function detectMissingFileMetadataRowsFromDb(): void
    {
        try {
            foreach ($this->blockchainIndex->items(StreamEnums::FILE_METADATA) as $item) {
                $txid = $item['txid'] ?? null;
                $data = $item['data']['json'] ?? [];
                $fileKey = $data['file_key'] ?? null;

                if (! $txid || ! $fileKey) {
                    continue;
                }

                if (! File::where('txid', $txid)->exists()) {
                    $this->recordViolation(
                        type: BreachTypeEnums::ROW_DELETED->value,
                        tableName: 'files',
                        record: null,
                        prNumber: (string) ($data['pr_number'] ?? $fileKey),
                        message: 'file.metadata item exists on blockchain but not in database',
                        chainData: $data,
                        chainTxid: $txid,
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted file metadata rows', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Detect DB child rows whose txid is not present in the corresponding blockchain stream.
     */
    private function detectUnauthorizedStreamRowsInDb(StreamEnums $stream, string $modelClass, string $tableName): void
    {
        try {
            $chainTxids = $this->getBlockchainTxids($stream);

            $fakeRows = $modelClass::query()
                ->where(function ($query) use ($chainTxids) {
                    $query->whereNull('txid')
                        ->orWhere('txid', '');

                    if (empty($chainTxids)) {
                        $query->orWhereNotNull('txid');
                    } else {
                        $query->orWhereNotIn('txid', $chainTxids);
                    }
                })
                ->get();

            foreach ($fakeRows as $record) {
                $prNumber = $record->pr_number ?? $record->procurement?->pr_number ?? 'unknown';

                $this->recordViolation(
                    type: BreachTypeEnums::UNAUTHORIZED_RECORD->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: "Record exists in {$tableName} but txid is absent from {$stream->value} blockchain stream",
                    chainData: null,
                );
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check unauthorized stream rows', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getBlockchainTxids(StreamEnums $stream): array
    {
        try {
            return $this->blockchainIndex->txids($stream);
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to get blockchain txids', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function resolveStalePendingViolationsAfterCleanRun(): void
    {
        $resolved = 0;

        foreach (IntegrityAuditLog::where('recovery_status', 'pending')->lazy() as $violation) {
            $violation->markSkipped('Verifier completed a full clean run (run_id: '.$this->runId.') with no current blockchain/database breaches. This pending record is historical and no longer actionable.');
            $resolved++;
        }

        if ($resolved > 0) {
            Log::info('IntegrityVerification: resolved stale pending violations after clean run', [
                'run_id' => $this->runId,
                'count' => $resolved,
            ]);
        }
    }

    private function violationIsResolved(IntegrityAuditLog $violation): bool
    {
        $modelClass = $this->modelClassForStream($violation->stream);

        return match ($violation->violation_type) {
            BreachTypeEnums::ROW_DELETED->value => $this->rowDeletedViolationIsResolved($violation, $modelClass),
            BreachTypeEnums::UNAUTHORIZED_RECORD->value => $this->unauthorizedRecordViolationIsResolved($violation, $modelClass),
            BreachTypeEnums::HASH_MISMATCH->value,
            BreachTypeEnums::CONTENT_MISMATCH->value,
            BreachTypeEnums::USER_ADDRESS_TAMPERED->value => $this->contentViolationIsResolved($violation, $modelClass),
            default => false,
        };
    }

    private function rowDeletedViolationIsResolved(IntegrityAuditLog $violation, ?string $modelClass): bool
    {
        if (! $modelClass) {
            return false;
        }

        if ($violation->stream === StreamEnums::METADATA->value) {
            return $modelClass::where('pr_number', $violation->stream_key)->exists();
        }

        return is_string($violation->txid) && $violation->txid !== ''
            ? $modelClass::where('txid', $violation->txid)->exists()
            : false;
    }

    private function unauthorizedRecordViolationIsResolved(IntegrityAuditLog $violation, ?string $modelClass): bool
    {
        if (! $modelClass) {
            return false;
        }

        if ($violation->record_id && ! $modelClass::whereKey($violation->record_id)->exists()) {
            return true;
        }

        if (is_string($violation->txid) && $violation->txid !== '') {
            return $this->blockchainIndex->hasTxid($violation->stream, $violation->txid);
        }

        return false;
    }

    private function contentViolationIsResolved(IntegrityAuditLog $violation, ?string $modelClass): bool
    {
        $record = $this->findMirrorRecordForViolation($violation, $modelClass);

        if (! $record) {
            return false;
        }

        $tableName = $this->tableNameForStream($violation->stream);
        if (! $tableName) {
            return false;
        }

        $prNumber = $record->pr_number ?? $record->procurement?->pr_number ?? $violation->stream_key;
        $chainData = $this->fetchChainData($violation->stream, $prNumber, $record->txid ?? null);

        if (! $chainData) {
            return false;
        }

        if (! $this->recordReferencesLatestChainRevision($record, $tableName, $prNumber, StreamEnums::from($violation->stream))) {
            return false;
        }

        $fieldDiffs = $this->computeFieldDifferences(
            $this->recordToArray($record, $tableName),
            $this->payloadProjector->projectForTable($chainData, $tableName, $record),
        );

        if (! empty($fieldDiffs)) {
            return false;
        }

        if ($tableName === 'procurements' && ! empty($this->procurementStatusDifferencesFromLatestStatusStream($record, $prNumber))) {
            return false;
        }

        $currentHash = $this->computeRecordHash($record, $tableName);

        return ! $record->data_hash || $currentHash === $record->data_hash;
    }

    private function findMirrorRecordForViolation(IntegrityAuditLog $violation, ?string $modelClass): ?Model
    {
        if (! $modelClass) {
            return null;
        }

        if ($violation->record_id) {
            $record = $modelClass::find($violation->record_id);
            if ($record) {
                return $record;
            }
        }

        if (is_string($violation->txid) && $violation->txid !== '') {
            $record = $modelClass::where('txid', $violation->txid)->first();
            if ($record) {
                return $record;
            }
        }

        if ($violation->stream === StreamEnums::METADATA->value) {
            return $modelClass::where('pr_number', $violation->stream_key)->first();
        }

        return null;
    }

    private function modelClassForStream(string $stream): ?string
    {
        return match ($stream) {
            StreamEnums::METADATA->value => Procurement::class,
            StreamEnums::STATUS->value => ProcurementStage::class,
            StreamEnums::DOCUMENTS->value => ProcurementDocument::class,
            StreamEnums::EVENTS->value => ProcurementEvent::class,
            StreamEnums::CORRECTIONS->value => ProcurementCorrection::class,
            StreamEnums::ARCHIVE->value => ProcurementArchive::class,
            StreamEnums::PROCUREMENTS_CORRECTIONS->value => ProcurementMetadataCorrection::class,
            StreamEnums::FILE_METADATA->value => File::class,
            default => null,
        };
    }

    private function tableNameForStream(string $stream): ?string
    {
        return match ($stream) {
            StreamEnums::METADATA->value => 'procurements',
            StreamEnums::STATUS->value => 'procurement_stages',
            StreamEnums::DOCUMENTS->value => 'procurement_documents',
            StreamEnums::EVENTS->value => 'procurement_events',
            StreamEnums::CORRECTIONS->value => 'procurement_corrections',
            StreamEnums::ARCHIVE->value => 'procurement_archives',
            StreamEnums::PROCUREMENTS_CORRECTIONS->value => 'procurement_metadata_corrections',
            StreamEnums::FILE_METADATA->value => 'files',
            default => null,
        };
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
            Log::info('IntegrityVerification: auto-repair skipped - no pending violations', [
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
            // Step 1: Get all blockchain identities (source of truth)
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();
            $stageTxids = $this->getBlockchainTxids(StreamEnums::STATUS);
            $documentTxids = $this->getBlockchainTxids(StreamEnums::DOCUMENTS);
            $eventTxids = $this->getBlockchainTxids(StreamEnums::EVENTS);
            $correctionTxids = $this->getBlockchainTxids(StreamEnums::CORRECTIONS);
            $archiveTxids = $this->getBlockchainTxids(StreamEnums::ARCHIVE);
            $metadataCorrectionTxids = $this->getBlockchainTxids(StreamEnums::PROCUREMENTS_CORRECTIONS);
            $fileTxids = $this->getBlockchainTxids(StreamEnums::FILE_METADATA);

            // Step 2: Delete DB records that don't exist on blockchain
            // Requirement 5: "Restore original records from trusted blockchain data."
            // Records in DB not on chain = unauthorized injection → must be removed
            if (! empty($blockchainPrNumbers)) {
                $dbCount = Procurement::withTrashed()->count();
                $chainCount = count($blockchainPrNumbers);

                // Safety guard: if blockchain returned suspiciously few PRs
                // compared to what's in the DB, it likely means a partial read
                // due to connection degradation. Don't risk wiping legitimate records.
                if ($dbCount > 0 && $chainCount < max(1, intdiv($dbCount, 2))) {
                    Log::warning('IntegrityVerification: safety guard triggered — skipping forceDelete (partial blockchain read)', [
                        'db_count' => $dbCount,
                        'chain_count' => $chainCount,
                    ]);
                } else {
                    $deletedCount = Procurement::withTrashed()
                        ->whereNotIn('pr_number', $blockchainPrNumbers)
                        ->forceDelete();

                    $deletedStages = ! empty($stageTxids)
                        ? ProcurementStage::whereNotNull('txid')->whereNotIn('txid', $stageTxids)->delete()
                        : 0;
                    $deletedDocuments = ! empty($documentTxids)
                        ? ProcurementDocument::withTrashed()->whereNotNull('txid')->whereNotIn('txid', $documentTxids)->forceDelete()
                        : 0;
                    $deletedEvents = ! empty($eventTxids)
                        ? ProcurementEvent::whereNotNull('txid')->whereNotIn('txid', $eventTxids)->delete()
                        : 0;
                    $deletedCorrections = ! empty($correctionTxids)
                        ? ProcurementCorrection::whereNotNull('txid')->whereNotIn('txid', $correctionTxids)->delete()
                        : 0;
                    $deletedArchives = ! empty($archiveTxids)
                        ? ProcurementArchive::whereNotNull('txid')->whereNotIn('txid', $archiveTxids)->delete()
                        : 0;
                    $deletedMetadataCorrections = ! empty($metadataCorrectionTxids)
                        ? ProcurementMetadataCorrection::whereNotNull('txid')->whereNotIn('txid', $metadataCorrectionTxids)->delete()
                        : 0;
                    $deletedFiles = ! empty($fileTxids)
                        ? File::withTrashed()->whereNotNull('txid')->whereNotIn('txid', $fileTxids)->forceDelete()
                        : 0;

                    // Also clean up orphaned child records
                    ProcurementStage::whereDoesntHave('procurement')->delete();
                    ProcurementDocument::withTrashed()->whereDoesntHave('procurement')->forceDelete();
                    ProcurementEvent::whereDoesntHave('procurement')->delete();
                    ProcurementCorrection::whereDoesntHave('procurement')->delete();
                    ProcurementArchive::whereDoesntHave('procurement')->delete();
                    ProcurementMetadataCorrection::whereDoesntHave('procurement')->delete();

                    if ($deletedCount > 0 || $deletedStages > 0 || $deletedDocuments > 0 || $deletedEvents > 0 || $deletedCorrections > 0 || $deletedArchives > 0 || $deletedMetadataCorrections > 0 || $deletedFiles > 0) {
                        Log::info('IntegrityVerification: removed unauthorized DB records', [
                            'deleted_procurements' => $deletedCount,
                            'deleted_stages' => $deletedStages,
                            'deleted_documents' => $deletedDocuments,
                            'deleted_events' => $deletedEvents,
                            'deleted_corrections' => $deletedCorrections,
                            'deleted_archives' => $deletedArchives,
                            'deleted_metadata_corrections' => $deletedMetadataCorrections,
                            'deleted_files' => $deletedFiles,
                        ]);
                    }
                }
            }

            // Step 3: Re-sync all data FROM blockchain to populate what's authentic
            $syncCounts = $this->syncService->syncAll();

            // Step 3b: Refresh data_hash on all synced records so violationIsResolved()
            // can verify the repair. The sync sets data_hash, but our hash computation
            // may differ slightly (e.g. null vs empty string normalization).
            $this->refreshHashesAfterRepair();

            // Step 4: Mark only violations that no longer reproduce as restored.
            // A repair is not successful merely because sync ran; it must prove
            // the affected mirror row now matches trusted chain state.
            foreach ($pending as $violation) {
                if ($this->violationIsResolved($violation)) {
                    $violation->markRestored([
                        'restored_by' => 'system_auto_repair',
                        'restored_at' => now()->toIso8601String(),
                        'verification_run_id' => $this->runId,
                        'sync_counts' => $syncCounts,
                    ]);

                    $this->restoredCount++;
                } else {
                    $violation->markFailed('Auto-repair completed sync, but post-repair verification still reproduces this violation.');
                    $this->failedCount++;
                }
            }

            Log::info('IntegrityVerification: auto-repair completed', [
                'run_id' => $this->runId,
                'restored' => $this->restoredCount,
                'failed' => $this->failedCount,
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

    /**
     * After auto-repair sync, refresh data_hash on all synced records so
     * violationIsResolved() can verify the repair correctly.
     */
    private function refreshHashesAfterRepair(): void
    {
        $tables = [
            'procurements' => Procurement::class,
            'procurement_stages' => ProcurementStage::class,
            'procurement_documents' => ProcurementDocument::class,
            'procurement_events' => ProcurementEvent::class,
            'procurement_corrections' => ProcurementCorrection::class,
            'procurement_archives' => ProcurementArchive::class,
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::class,
            'files' => File::class,
        ];

        foreach ($tables as $tableName => $modelClass) {
            foreach ($modelClass::query()->lazy() as $record) {
                $currentHash = $this->computeRecordHash($record, $tableName);
                if ($record->data_hash !== $currentHash) {
                    $record->forceFill([
                        'data_hash' => $currentHash,
                        'blockchain_hash' => $currentHash,
                    ])->save();
                }
            }
        }
    }

    private function computeRecordHash(Model $record, string $tableName): string
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            'procurement_corrections' => ProcurementCorrection::getHashableFields(),
            'procurement_archives' => ProcurementArchive::getHashableFields(),
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::getHashableFields(),
            'files' => File::getHashableFields(),
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
            'procurement_corrections' => ProcurementCorrection::getHashableFields(),
            'procurement_archives' => ProcurementArchive::getHashableFields(),
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::getHashableFields(),
            'files' => File::getHashableFields(),
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
            if ($this->blockchainIndex->isLoaded($stream)) {
                if ($txid) {
                    $json = $this->blockchainIndex->jsonByTxid($stream, $txid);
                    if ($json) {
                        return $json;
                    }
                } else {
                    $json = $this->blockchainIndex->latestJsonByPrNumber($stream, $prNumber);
                    if ($json) {
                        return $json;
                    }
                }
            }

            $items = $this->manager->liststreamkeyitems($stream, $prNumber);
            $items = is_array($items) ? $items : [];

            if ($txid) {
                foreach ($items as $item) {
                    if (($item['txid'] ?? null) === $txid) {
                        $json = $item['data']['json'] ?? null;

                        return is_array($json) ? $json : null;
                    }
                }

                $this->blockchainIndex->loadStream($stream);

                return $this->blockchainIndex->jsonByTxid($stream, $txid);
            }

            if (empty($items)) {
                return null;
            }

            $latest = end($items);
            $json = is_array($latest) ? ($latest['data']['json'] ?? null) : null;

            return is_array($json) ? $json : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function computeFieldDifferences(array $dbData, array $chainData): array
    {
        return $this->comparator->diff($dbData, $chainData);
    }

    private function recordViolation(
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
        $this->violationCounts[$type] = ($this->violationCounts[$type] ?? 0) + 1;

        // Always derive severity from the canonical model mapping — never hardcode.
        $severity = IntegrityAuditLog::severityForType($type);

        $stream = self::TABLE_STREAM_MAP[$tableName]?->value ?? $tableName;
        $violationTxid = $record?->txid ?? $chainTxid;

        // Deduplicate repeated audit runs without collapsing separate missing
        // stream rows for the same PR. Child-row deletions are txid-scoped;
        // DB-only injected rows without txids are record-id scoped; whole-record
        // missing violations fall back to the stream/key/type tuple.
        $dedupeQuery = IntegrityAuditLog::where('stream', $stream)
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
            'recovery_result' => ['reason' => 'Superseded by a newer audit run (run_id: '.$this->runId.')'],
        ]);

        $dbSnapshot = $record ? $this->recordToArray($record, $tableName) : null;

        $auditLog = IntegrityAuditLog::create([
            'record_id' => $record?->id,
            'stream' => $stream,
            'stream_key' => $prNumber,
            'txid' => $violationTxid,
            'violation_type' => $type,
            'severity' => $severity,
            'mirror_snapshot' => $dbSnapshot,
            'chain_snapshot' => $chainData,
            'field_differences' => $fieldDiffs ?? ($currentHash ? [['field' => 'hash', 'old_value' => $storedHash, 'new_value' => $currentHash]] : null),
            'recovery_status' => 'pending',
            'verification_run_id' => $this->runId,
            'source' => $this->source,
            'created_at' => now(),
        ]);

        $auditLog->publishToBlockchain();

        // Mark record as breached
        if ($record && method_exists($record, 'update')) {
            $record->update(['has_breach' => true]);
        }

        // Only notify for NEW breaches, not re-detections of existing pending ones
        $existingPending = IntegrityAuditLog::where('stream', $stream)
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

    private function notifyBreach(string $type, string $prNumber, string $message, ?array $fieldDiffs = null, ?string $stream = null, ?string $txid = null): void
    {
        try {
            $recipientRoles = config('integrity.breach_notifications.recipient_roles', ['admin', 'bac_chairman', 'hope']);
            $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $recipientRoles))->get();
            if ($recipients->isEmpty()) {
                return;
            }

            $digestEnabled = config('integrity.breach_notifications.digest_enabled', true);

            // If digest is enabled, we don't send individual emails immediately.
            // The daily digest job will pick up violations from IntegrityAuditLog.
            // We still send push notifications for critical/high severity.
            $sendEmailNow = ! $digestEnabled;

            foreach ($recipients as $recipient) {
                $recipient->notify(new IntegrityBreachNotification(
                    breachType: $type,
                    stream: $stream ?? 'normalized_db',
                    streamKey: $prNumber,
                    txid: $txid ?? '',
                    breachData: ['message' => $message, 'run_id' => $this->runId],
                    recordId: null,
                    runId: $this->runId,
                    fieldDiffs: $fieldDiffs,
                    isDigest: false,
                ));
            }
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function preloadBlockchainIndex(): void
    {
        $this->blockchainIndex->loadStreams(array_values(array_unique(array_map(
            fn (StreamEnums $stream) => $stream->value,
            self::TABLE_STREAM_MAP,
        ))));
    }

    private function ensureBlockchainIndexLoaded(): void
    {
        if (! $this->blockchainIndex->hasFailures()) {
            return;
        }

        throw new \RuntimeException('Integrity audit aborted because one or more blockchain streams could not be read: '.json_encode($this->blockchainIndex->failedStreams(), JSON_UNESCAPED_SLASHES));
    }

    private function reset(string $source): void
    {
        $this->runId = IntegrityAuditLog::newRunId();
        $this->source = $source;
        $this->violationCounts = [];
        $this->verifiedCount = 0;
        $this->restoredCount = 0;
        $this->failedCount = 0;
        $this->verifyPublishers = false;
        $this->blockchainIndex = app(BlockchainVerificationIndex::class);
    }
}
