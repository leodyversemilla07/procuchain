<?php

declare(strict_types=1);

namespace App\Services\Integrity;

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
use App\Services\BlockchainAuditTrailService;
use App\Services\NormalizedTableSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Auto-repairs pending violations by deleting unauthorized DB records and re-syncing from blockchain.
 */
class IntegrityAutoRepairer
{
    public function __construct(
        private readonly IntegrityViolationRecorder $recorder,
        private readonly IntegrityVerificationRunState $state,
        private readonly BlockchainVerificationIndex $blockchainIndex,
        private readonly NormalizedTableSyncService $syncService,
        private readonly IntegrityRecordVerifier $verifier,
        private readonly BlockchainAuditTrailService $blockchainAudit,
    ) {}

    public function repair(): void
    {
        $pending = IntegrityViolationLog::forRun($this->state->runId)
            ->where('recovery_status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            Log::info('IntegrityVerification: auto-repair skipped - no pending violations', [
                'run_id' => $this->state->runId,
            ]);

            return;
        }

        $count = $pending->count();
        Log::info('IntegrityVerification: auto-repair starting', [
            'run_id' => $this->state->runId,
            'pending_violations' => $count,
        ]);

        try {
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();
            $stageTxids = $this->getBlockchainTxids(Stream::STATUS);
            $documentTxids = $this->getBlockchainTxids(Stream::DOCUMENTS);
            $eventTxids = $this->getBlockchainTxids(Stream::EVENTS);
            $correctionTxids = $this->getBlockchainTxids(Stream::CORRECTIONS);
            $archiveTxids = $this->getBlockchainTxids(Stream::ARCHIVE);
            $metadataCorrectionTxids = $this->getBlockchainTxids(Stream::PROCUREMENTS_CORRECTIONS);
            $blockchainFileTxids = $this->getBlockchainTxids(Stream::FILE_METADATA);

            if (! empty($blockchainPrNumbers)) {
                $dbCount = Procurement::withTrashed()->count();
                $chainCount = count($blockchainPrNumbers);

                if ($dbCount > 0 && $chainCount < max(1, intdiv($dbCount, 2))) {
                    Log::warning('IntegrityVerification: safety guard triggered - skipping forceDelete (partial blockchain read)', [
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
                    $deletedBlockchainFiles = ! empty($blockchainFileTxids)
                        ? File::withTrashed()->whereNotNull('txid')->whereNotIn('txid', $blockchainFileTxids)->forceDelete()
                        : 0;

                    ProcurementStage::whereDoesntHave('procurement')->delete();
                    ProcurementDocument::withTrashed()->whereDoesntHave('procurement')->forceDelete();
                    ProcurementEvent::whereDoesntHave('procurement')->delete();
                    ProcurementCorrection::whereDoesntHave('procurement')->delete();
                    ProcurementArchive::whereDoesntHave('procurement')->delete();
                    ProcurementMetadataCorrection::whereDoesntHave('procurement')->delete();

                    if ($deletedCount > 0 || $deletedStages > 0 || $deletedDocuments > 0 || $deletedEvents > 0 || $deletedCorrections > 0 || $deletedArchives > 0 || $deletedMetadataCorrections > 0 || $deletedBlockchainFiles > 0) {
                        Log::info('IntegrityVerification: removed unauthorized DB records', [
                            'deleted_procurements' => $deletedCount,
                            'deleted_stages' => $deletedStages,
                            'deleted_documents' => $deletedDocuments,
                            'deleted_events' => $deletedEvents,
                            'deleted_corrections' => $deletedCorrections,
                            'deleted_archives' => $deletedArchives,
                            'deleted_metadata_corrections' => $deletedMetadataCorrections,
                            'deleted_BlockchainFiles' => $deletedBlockchainFiles,
                        ]);
                    }
                }
            }

            $syncCounts = $this->syncService->syncAll();

            $this->refreshHashesAfterRepair();

            foreach ($pending as $violation) {
                if ($this->violationIsResolved($violation)) {
                    $result = [
                        'restored_by' => 'system_auto_repair',
                        'restored_at' => now()->toIso8601String(),
                        'verification_run_id' => $this->state->runId,
                        'sync_counts' => $syncCounts,
                    ];

                    $violation->markRestored($result);
                    $this->publishRecovery($violation, $result);

                    $this->state->restoredCount++;
                } else {
                    $violation->markFailed('Auto-repair completed sync, but post-repair verification still reproduces this violation.');
                    $this->state->failedCount++;
                }
            }

            Log::info('IntegrityVerification: auto-repair completed', [
                'run_id' => $this->state->runId,
                'restored' => $this->state->restoredCount,
                'failed' => $this->state->failedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('IntegrityVerification: auto-repair failed', [
                'run_id' => $this->state->runId,
                'error' => $e->getMessage(),
            ]);

            foreach ($pending as $violation) {
                $violation->markFailed($e->getMessage());
                $this->state->failedCount++;
            }
        }
    }

    public function violationIsResolved(IntegrityViolationLog $violation): bool
    {
        $modelClass = $this->modelClassForStream($violation->stream);

        return match ($violation->violation_type) {
            BreachType::ROW_DELETED->value => $this->rowDeletedViolationIsResolved($violation, $modelClass),
            BreachType::UNAUTHORIZED_RECORD->value => $this->unauthorizedRecordViolationIsResolved($violation, $modelClass),
            BreachType::HASH_MISMATCH->value,
            BreachType::CONTENT_MISMATCH->value,
            BreachType::USER_ADDRESS_TAMPERED->value => $this->contentViolationIsResolved($violation, $modelClass),
            default => false,
        };
    }

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
            'Files' => File::class,
        ];

        foreach ($tables as $tableName => $modelClass) {
            foreach ($modelClass::query()->lazy() as $record) {
                $currentHash = $this->verifier->computeRecordHash($record, $tableName);
                if ($record->data_hash !== $currentHash) {
                    $record->forceFill([
                        'data_hash' => $currentHash,
                        'blockchain_hash' => $currentHash,
                    ])->save();
                }
            }
        }
    }

    private function rowDeletedViolationIsResolved(IntegrityViolationLog $violation, ?string $modelClass): bool
    {
        if (! $modelClass) {
            return false;
        }

        if ($violation->stream === Stream::METADATA->value) {
            return $modelClass::where('pr_number', $violation->stream_key)->exists();
        }

        return is_string($violation->txid) && $violation->txid !== ''
            ? $modelClass::where('txid', $violation->txid)->exists()
            : false;
    }

    private function unauthorizedRecordViolationIsResolved(IntegrityViolationLog $violation, ?string $modelClass): bool
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

    private function contentViolationIsResolved(IntegrityViolationLog $violation, ?string $modelClass): bool
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

        $stream = Stream::from($violation->stream);
        if (! $this->recordReferencesLatestChainRevision($record, $tableName, $prNumber, $stream)) {
            return false;
        }

        $fieldDiffs = $this->verifier->computeFieldDifferences(
            $this->verifier->recordToArray($record, $tableName),
            app(BlockchainPayloadProjector::class)->projectForTable($chainData, $tableName, $record),
        );

        if (! empty($fieldDiffs)) {
            return false;
        }

        if ($tableName === 'procurements' && ! empty($this->verifier->procurementStatusDifferencesFromLatestStatusStream($record, $prNumber))) {
            return false;
        }

        $currentHash = $this->verifier->computeRecordHash($record, $tableName);

        return ! $record->data_hash || $currentHash === $record->data_hash;
    }

    private function recordReferencesLatestChainRevision($record, string $tableName, string $prNumber, Stream $stream): bool
    {
        if ($tableName !== 'procurements') {
            return true;
        }

        $items = $this->blockchainIndex->itemsByPrNumber($stream, $prNumber);
        $latest = end($items);

        if (! is_array($latest)) {
            return true;
        }

        $latestTxid = $latest['txid'] ?? null;

        return ! is_string($latestTxid) || $latestTxid === '' || $latestTxid === ($record->txid ?? null);
    }

    private function findMirrorRecordForViolation(IntegrityViolationLog $violation, ?string $modelClass): ?Model
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

        if ($violation->stream === Stream::METADATA->value) {
            return $modelClass::where('pr_number', $violation->stream_key)->first();
        }

        return null;
    }

    private function modelClassForStream(string $stream): ?string
    {
        return match ($stream) {
            Stream::METADATA->value => Procurement::class,
            Stream::STATUS->value => ProcurementStage::class,
            Stream::DOCUMENTS->value => ProcurementDocument::class,
            Stream::EVENTS->value => ProcurementEvent::class,
            Stream::CORRECTIONS->value => ProcurementCorrection::class,
            Stream::ARCHIVE->value => ProcurementArchive::class,
            Stream::PROCUREMENTS_CORRECTIONS->value => ProcurementMetadataCorrection::class,
            Stream::FILE_METADATA->value => File::class,
            default => null,
        };
    }

    private function tableNameForStream(string $stream): ?string
    {
        return match ($stream) {
            Stream::METADATA->value => 'procurements',
            Stream::STATUS->value => 'procurement_stages',
            Stream::DOCUMENTS->value => 'procurement_documents',
            Stream::EVENTS->value => 'procurement_events',
            Stream::CORRECTIONS->value => 'procurement_corrections',
            Stream::ARCHIVE->value => 'procurement_archives',
            Stream::PROCUREMENTS_CORRECTIONS->value => 'procurement_metadata_corrections',
            Stream::FILE_METADATA->value => 'Files',
            default => null,
        };
    }

    private function getBlockchainPrNumbers(): array
    {
        try {
            return $this->blockchainIndex->prNumbers(Stream::METADATA);
        } catch (\Exception $e) {
            Log::warning('Failed to get blockchain PR numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getBlockchainTxids(Stream $stream): array
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

            $rpcClient = app(BlockchainRpcClient::class);
            $items = $rpcClient->liststreamkeyitems($stream, $prNumber);
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

    private function publishRecovery(IntegrityViolationLog $violation, array $result): void
    {
        try {
            $this->blockchainAudit->publishRecovery($violation, $result);
        } catch (\Exception $e) {
            Log::debug('IntegrityAutoRepairer: failed to publish recovery to chain', [
                'audit_log_id' => $violation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
