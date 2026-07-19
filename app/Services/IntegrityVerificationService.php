<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachType;
use App\Enums\Stream;
use App\Models\File;
use App\Models\IntegrityViolationLog;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use App\Services\Integrity\BlockchainPayloadProjector;
use App\Services\Integrity\BlockchainVerificationIndex;
use App\Services\Integrity\DeletedRecordDetector;
use App\Services\Integrity\IntegrityAutoRepairer;
use App\Services\Integrity\IntegrityComparator;
use App\Services\Integrity\IntegrityRecordVerifier;
use App\Services\Integrity\IntegrityVerificationRunState;
use App\Services\Integrity\IntegrityViolationRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifies normalized DB tables against blockchain source of truth with multi-layer verification.
 *
 * Orchestration only — actual work delegated to:
 * - IntegrityRecordVerifier (Phase 1: hash/content/publisher checks)
 * - DeletedRecordDetector (Phase 2: missing/injected record detection)
 * - IntegrityAutoRepairer (Phase 3: auto-repair from blockchain)
 * - IntegrityViolationRecorder (violation recording + notification)
 */
class IntegrityVerificationService
{
    public const TABLE_STREAM_MAP = [
        'procurements' => Stream::METADATA,
        'procurement_stages' => Stream::STATUS,
        'procurement_documents' => Stream::DOCUMENTS,
        'procurement_events' => Stream::EVENTS,
        'procurement_corrections' => Stream::CORRECTIONS,
        'procurement_archives' => Stream::ARCHIVE,
        'procurement_metadata_corrections' => Stream::PROCUREMENTS_CORRECTIONS,
        'Files' => Stream::FILE_METADATA,
    ];

    private IntegrityVerificationRunState $state;

    public function __construct(
        private readonly BlockchainRpcClient $blockchainRpcClient,
        private readonly NormalizedTableSyncService $syncService,
        private readonly BlockchainPayloadProjector $payloadProjector,
        private readonly IntegrityComparator $comparator,
        private BlockchainVerificationIndex $blockchainIndex,
    ) {
        $this->state = new IntegrityVerificationRunState;
    }

    // ----------------------------------------------------------------
    // PUBLIC API
    // ----------------------------------------------------------------

    /**
     * Run full integrity verification against all normalized tables.
     *
     * @return array{run_id: string, verified: int, violations: array, restored: int, failed: int}
     */
    public function verifyAndRepair(bool $autoRepair = false, string $source = 'scheduled', bool $deepPublisherCheck = false): array
    {
        $lock = Cache::lock('integrity:verification:lock', 300);

        if (! $lock->get()) {
            Log::warning('IntegrityVerification: skipped - another run is in progress', ['source' => $source]);

            return [
                'run_id' => $this->state->runId ?: uniqid('skip-'),
                'verified' => 0,
                'violations' => [],
                'restored' => 0,
                'failed' => 0,
                'skipped' => true,
            ];
        }

        try {
            $this->reset($source);
            $this->state->verifyPublishers = $deepPublisherCheck;

            Log::info('IntegrityVerification: starting', ['run_id' => $this->state->runId, 'auto_repair' => $autoRepair]);

            $this->preloadBlockchainIndex();
            $this->ensureBlockchainIndexLoaded();

            $recorder = $this->buildRecorder();
            $verifier = $this->buildVerifier($recorder);

            // Phase 1: Verify hashes on all normalized tables
            $verifier->verifyAllTables();

            // Phase 2: Detect deleted records (chain has it, DB doesn't)
            $detector = $this->buildDetector($recorder);
            $detector->detect();

            if (empty($this->state->violationCounts)) {
                $this->resolveStalePendingViolationsAfterCleanRun();
            }

            // Phase 3: Auto-repair if requested
            if ($autoRepair) {
                $repairer = $this->buildRepairer($recorder, $verifier);
                $repairer->repair();
            }

            $result = $this->state->result();

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
        $this->state->verifyPublishers = $deepPublisherCheck;

        $procurement = Procurement::where('pr_number', $prNumber)->first();
        if (! $procurement) {
            $blockchainPrNumbers = $this->blockchainIndex->prNumbers(Stream::METADATA);
            if (in_array($prNumber, $blockchainPrNumbers, true)) {
                $recorder = $this->buildRecorder();
                $recorder->record(
                    type: BreachType::ROW_DELETED->value,
                    tableName: 'procurements',
                    record: null,
                    prNumber: $prNumber,
                    message: 'PR exists on blockchain but is absent from the database (deleted)',
                    chainData: null,
                );

                if ($autoRepair) {
                    $repairer = $this->buildRepairer($recorder, $this->buildVerifier($recorder));
                    $repairer->repair();
                }
            }

            return $this->state->result();
        }

        $recorder = $this->buildRecorder();
        $verifier = $this->buildVerifier($recorder);
        $verifier->verifyPrRecords($procurement);

        if ($autoRepair) {
            $repairer = $this->buildRepairer($recorder, $verifier);
            $repairer->repair();
        }

        return $this->state->result();
    }

    /**
     * Restore a specific violation from blockchain.
     */
    public function restoreViolation(IntegrityViolationLog $auditLog): array
    {
        if ($auditLog->recovery_status !== 'pending') {
            return ['success' => false, 'items_restored' => 0, 'error' => 'Already processed'];
        }

        try {
            $blockchainPrNumbers = $this->blockchainIndex->prNumbers(Stream::METADATA);

            $deletedCount = Procurement::withTrashed()
                ->whereNotIn('pr_number', $blockchainPrNumbers)
                ->forceDelete();

            $syncCounts = $this->syncService->syncAll();

            $recorder = $this->buildRecorder();
            $verifier = $this->buildVerifier($recorder);
            $this->refreshHashesAfterRepair($verifier);

            if (! $this->violationIsResolved($auditLog)) {
                $auditLog->markFailed('Post-repair verification failed; the violation still reproduces after syncing from blockchain.');
                $this->state->failedCount++;

                return ['success' => false, 'items_restored' => 0, 'deleted' => $deletedCount, 'error' => 'Post-repair verification failed'];
            }

            $result = [
                'restored_by' => 'system',
                'restored_at' => now()->toIso8601String(),
                'deleted_records' => $deletedCount,
                'sync_counts' => $syncCounts,
            ];

            $auditLog->markRestored($result);
            $this->publishRecovery($auditLog, $result);

            $this->state->restoredCount++;

            return ['success' => true, 'items_restored' => 1, 'deleted' => $deletedCount, 'error' => null];
        } catch (\Exception $e) {
            $auditLog->markFailed($e->getMessage());
            $this->state->failedCount++;

            return ['success' => false, 'items_restored' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate violation report.
     */
    public function generateReport(string $runId): array
    {
        $logs = IntegrityViolationLog::forRun($runId)
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

    public function computeFieldDifferences(array $dbData, array $chainData): array
    {
        return $this->comparator->diff($dbData, $chainData);
    }

    // ----------------------------------------------------------------
    // BUILDERS (for extracted services)
    // ----------------------------------------------------------------

    private function buildRecorder(): IntegrityViolationRecorder
    {
        return new IntegrityViolationRecorder(
            $this->state,
            app(BlockchainAuditTrailService::class),
        );
    }

    private function buildVerifier(IntegrityViolationRecorder $recorder): IntegrityRecordVerifier
    {
        return new IntegrityRecordVerifier(
            recorder: $recorder,
            state: $this->state,
            blockchainIndex: $this->blockchainIndex,
            payloadProjector: $this->payloadProjector,
            comparator: $this->comparator,
            rpcClient: $this->blockchainRpcClient,
        );
    }

    private function buildDetector(IntegrityViolationRecorder $recorder): DeletedRecordDetector
    {
        return new DeletedRecordDetector(
            recorder: $recorder,
            state: $this->state,
            blockchainIndex: $this->blockchainIndex,
        );
    }

    private function buildRepairer(IntegrityViolationRecorder $recorder, IntegrityRecordVerifier $verifier): IntegrityAutoRepairer
    {
        return new IntegrityAutoRepairer(
            recorder: $recorder,
            state: $this->state,
            blockchainIndex: $this->blockchainIndex,
            syncService: $this->syncService,
            verifier: $verifier,
            blockchainAudit: app(BlockchainAuditTrailService::class),
        );
    }

    private function publishRecovery(IntegrityViolationLog $auditLog, array $result): void
    {
        try {
            app(BlockchainAuditTrailService::class)->publishRecovery($auditLog, $result);
        } catch (\Exception $e) {
            Log::debug('IntegrityVerificationService: failed to publish recovery to chain', [
                'audit_log_id' => $auditLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    private function refreshHashesAfterRepair(IntegrityRecordVerifier $verifier): void
    {
        $tables = [
            'procurements' => Procurement::class,
            'procurement_stages' => ProcurementStage::class,
            'procurement_documents' => ProcurementDocument::class,
            'procurement_events' => ProcurementEvent::class,
            'procurement_corrections' => ProcurementCorrection::class,
            'procurement_archives' => ProcurementArchive::class,
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::class,
            'Files' => File::class,
        ];

        foreach ($tables as $tableName => $modelClass) {
            foreach ($modelClass::query()->lazy() as $record) {
                $currentHash = $verifier->computeRecordHash($record, $tableName);
                if ($record->data_hash !== $currentHash) {
                    $record->forceFill([
                        'data_hash' => $currentHash,
                        'blockchain_hash' => $currentHash,
                    ])->save();
                }
            }
        }
    }

    private function violationIsResolved(IntegrityViolationLog $violation): bool
    {
        $recorder = $this->buildRecorder();
        $verifier = $this->buildVerifier($recorder);
        $repairer = $this->buildRepairer($recorder, $verifier);

        return $repairer->violationIsResolved($violation);
    }

    private function resolveStalePendingViolationsAfterCleanRun(): void
    {
        $resolved = 0;

        foreach (IntegrityViolationLog::where('recovery_status', 'pending')->lazy() as $violation) {
            $violation->markSkipped('Verifier completed a full clean run (run_id: '.$this->state->runId.') with no current blockchain/database breaches. This pending record is historical and no longer actionable.');
            $resolved++;
        }

        if ($resolved > 0) {
            Log::info('IntegrityVerification: resolved stale pending violations after clean run', [
                'run_id' => $this->state->runId,
                'count' => $resolved,
            ]);
        }
    }

    private function preloadBlockchainIndex(): void
    {
        $this->blockchainIndex->loadStreams(array_values(array_unique(array_map(
            fn (Stream $stream) => $stream->value,
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
        $this->state->reset($source);
        $this->blockchainIndex = app(BlockchainVerificationIndex::class);
    }
}
