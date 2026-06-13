<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Stream;
use App\Jobs\Handlers\CorrectionHandler;
use App\Jobs\Handlers\DocumentUploadHandler;
use App\Jobs\Handlers\ProcurementInitiationHandler;
use App\Jobs\Handlers\ProcurementUpdateHandler;
use App\Jobs\Handlers\StageCompletionHandler;
use App\Jobs\Handlers\StageTransitionHandler;
use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Models\User;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\BlockchainRecordSyncService;
use App\Services\Concerns\HashesData;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Handles all asynchronous blockchain write operations via the Redis queue.
 */
class BlockchainWriteJob implements ShouldQueue
{
    use Dispatchable, HashesData, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum attempts before the job is marked failed */
    public int $tries = 3;

    /** @var int Seconds the job may run before timing out */
    public int $timeout = 90;

    /** @var array Seconds to wait between retry attempts */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $operation,
        public readonly array $data,
        public readonly string $jobId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(): void
    {
        try {
            $result = match ($this->operation) {
                'upload_document' => app(DocumentUploadHandler::class)->execute($this->data),
                'initiate_procurement' => app(ProcurementInitiationHandler::class)->execute($this->data),
                'mark_stage_complete' => app(StageCompletionHandler::class)->execute($this->data),
                'skip_stage' => app(StageTransitionHandler::class)->executeSkip($this->data),
                'repeat_stage' => app(StageTransitionHandler::class)->executeRepeat($this->data),
                'correct_document' => app(CorrectionHandler::class)->executeDocumentCorrection($this->data),
                'correct_procurement' => app(CorrectionHandler::class)->executeProcurementCorrection($this->data),
                'update_delivery_details' => app(ProcurementUpdateHandler::class)->executeDeliveryDetails($this->data),
                'publish_decision' => app(ProcurementUpdateHandler::class)->executeDecision($this->data),
                default => throw new Exception("Unknown blockchain operation: {$this->operation}"),
            };

            // Sync to DB BEFORE marking job as done, so the frontend
            // only sees "done" after the normalized tables are updated.
            // This prevents the race condition where router.reload() fires
            // before the DB reflects the blockchain state.
            $this->syncToMirror($result, $this->data, $this->operation);

            Cache::put("blockchain_job:{$this->jobId}", [
                'status' => 'done',
                'result' => $result,
                'user_id' => $this->userId,
            ], now()->addHour());

            Log::info("BlockchainWriteJob[{$this->operation}]: completed", [
                'job_id' => $this->jobId,
                'pr_number' => $this->data['pr_number'] ?? 'N/A',
            ]);
        } catch (Exception $e) {
            // Only log — do NOT write 'failed' to cache yet, because this
            // attempt may still be retried. The cache should only reflect
            // permanent failure once all retries are exhausted (see failed()).
            // Update cache to 'retrying' so the polling frontend can show progress.
            if ($this->attempts() < $this->tries) {
                Cache::put("blockchain_job:{$this->jobId}", [
                    'status' => 'retrying',
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'user_id' => $this->userId,
                ], now()->addHour());
            }

            Log::warning("BlockchainWriteJob[{$this->operation}]: attempt {$this->attempts()}/{$this->tries} failed", [
                'job_id' => $this->jobId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync blockchain write results to the normalized tables.
     *
     * Uses either transaction-based or direct DB sync to avoid race conditions
     * with MultiChain indexing. Failures are logged but never fail the job.
     *
     * @param  array  $result  The handler result containing transactions or stage/status info
     * @param  array  $data  The original job data payload
     * @param  string  $operation  The blockchain operation type
     */
    private function syncToMirror(array $result, array $data, string $operation): void
    {
        try {
            $userAddress = $data['user_address']
                ?? $data['procurement_data']['user_address']
                ?? '';

            $prNumber = $result['pr_number']
                ?? $data['pr_number']
                ?? '';

            if (empty($userAddress) || empty($prNumber)) {
                Log::warning("BlockchainWriteJob[{$operation}]: mirror sync skipped — missing user_address or pr_number", [
                    'job_id' => $this->jobId,
                    'user_address' => $userAddress,
                    'pr_number' => $prNumber,
                ]);

                return;
            }

            // Strategy 1: Transaction-based sync (when handler returns transactions)
            $transactions = $result['transactions'] ?? [];
            $syncedCount = 0;

            if (! empty($transactions)) {
                $syncService = app(BlockchainRecordSyncService::class);

                foreach ($transactions as $type => $txData) {
                    try {
                        $syncedCount += $this->syncTransactionEntry(
                            $syncService, $type, $txData, $prNumber, $userAddress, $operation
                        );
                    } catch (Exception $e) {
                        Log::error("BlockchainWriteJob[{$operation}]: mirror sync failed for transaction type", [
                            'type' => $type,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Strategy 2: Direct DB sync from result data
            // For operations like publish_decision, mark_stage_complete, skip_stage,
            // repeat_stage — the handler returns stage/status in the result but no
            // `transactions` array. We write directly to the DB to avoid the
            // blockchain-read race condition.
            $directSyncCount = $this->directDbSync($result, $data, $operation, $prNumber, $userAddress);

            Log::info("BlockchainWriteJob[{$operation}]: mirror sync completed", [
                'job_id' => $this->jobId,
                'pr_number' => $prNumber,
                'transaction_synced_count' => $syncedCount,
                'direct_synced_count' => $directSyncCount,
            ]);
        } catch (Exception $e) {
            Log::error("BlockchainWriteJob[{$operation}]: mirror sync failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync a single transaction entry via BlockchainRecordSyncService.
     *
     * @return int Number of entries synced
     */
    private function syncTransactionEntry(
        BlockchainRecordSyncService $syncService,
        string $type,
        array $txData,
        string $prNumber,
        string $userAddress,
        string $operation
    ): int {
        $stream = match ($type) {
            'metadata' => Stream::METADATA,
            'status' => Stream::STATUS,
            'event' => Stream::EVENTS,
            'documents' => Stream::DOCUMENTS,
            'correction' => Stream::CORRECTIONS,
            'procurement_correction' => Stream::PROCUREMENTS_CORRECTIONS,
            'decision' => Stream::EVENTS,
            'archive' => Stream::ARCHIVE,
            default => null,
        };

        if ($stream === null) {
            Log::debug("BlockchainWriteJob[{$operation}]: skipping unknown transaction type in mirror sync", [
                'type' => $type,
            ]);

            return 0;
        }

        $txid = match ($type) {
            'status' => $txData['status_txid'] ?? '',
            'event' => $txData['event_txid'] ?? '',
            default => $txData['txid'] ?? '',
        };

        if (empty($txid)) {
            Log::debug("BlockchainWriteJob[{$operation}]: skipping mirror sync — missing txid", [
                'type' => $type,
            ]);

            return 0;
        }

        // Documents is an array of individual document entries
        if ($type === 'documents' && isset($txData[0]) && is_array($txData[0])) {
            $count = 0;
            foreach ($txData as $docEntry) {
                $docTxid = $docEntry['txid'] ?? '';
                if (empty($docTxid)) {
                    continue;
                }
                $syncService->syncToMirror($stream->value, $prNumber, $docTxid, $userAddress, null, $docEntry, true);
                $count++;
            }

            return $count;
        }

        $syncService->syncToMirror($stream->value, $prNumber, $txid, $userAddress, null, is_array($txData) ? $txData : [], true);

        return 1;
    }

    /**
     * Directly update the normalized DB tables from the handler result.
     *
     * Avoids race condition where blockchain reads return stale data before
     * MultiChain indexes new stream items. Reconciled later by syncPr/syncAll.
     *
     * @return int Number of records directly updated
     */
    private function directDbSync(array $result, array $data, string $operation, string $prNumber, string $userAddress): int
    {
        // Handle procurement initiation separately — it creates the procurement record
        if ($operation === 'initiate_procurement') {
            return $this->directDbSyncInitiation($result, $data, $prNumber, $userAddress);
        }

        // Only apply direct sync for operations that update stage/status
        $stageStatusOperations = ['publish_decision', 'mark_stage_complete', 'skip_stage', 'repeat_stage'];

        if (! in_array($operation, $stageStatusOperations, true)) {
            return 0;
        }

        return $this->directDbSyncStageStatus($result, $data, $operation, $prNumber, $userAddress);
    }

    /**
     * Directly create the procurement and stage records in the DB after initiation.
     *
     * @return int Number of records directly created
     */
    private function directDbSyncInitiation(array $result, array $data, string $prNumber, string $userAddress): int
    {
        $syncedCount = 0;
        $procurementData = $data['procurement_data'] ?? [];

        if (empty($procurementData)) {
            return 0;
        }

        try {
            // Create the procurement record directly in the DB
            $existing = Procurement::withTrashed()->where('pr_number', $prNumber)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
            } else {
                $userId = $procurementData['user_id'] ?? null;
                if (is_array($userId)) {
                    $userId = $userId['id'] ?? null;
                }

                $initiatedAt = now();
                $procurementAttributes = [
                    'pr_number' => $prNumber,
                    'app_reference' => $procurementData['app_reference'] ?? null,
                    'title' => $procurementData['title'] ?? '',
                    'description' => $procurementData['description'] ?? null,
                    'category' => $procurementData['category'] ?? 'goods',
                    'procurement_mode' => $procurementData['procurement_mode'] ?? 'competitive_bidding',
                    'office' => $procurementData['office'] ?? null,
                    'end_user' => $procurementData['end_user'] ?? null,
                    'fund_source' => $procurementData['funding_source'] ?? null,
                    'prepared_by' => $procurementData['prepared_by'] ?? null,
                    'abc_amount' => (float) ($procurementData['abc_amount'] ?? 0),
                    'current_stage' => 'procurement_initiation',
                    'current_status' => 'procurement_initiated',
                    'user_address' => $userAddress,
                    'user_id' => $userId !== null ? (string) $userId : null,
                    'initiated_at' => $initiatedAt,
                    'last_updated_at' => $initiatedAt,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => $initiatedAt,
                    'has_breach' => false,
                ];
                $procurementHash = $this->computeHash($this->extractHashableFields($procurementAttributes, Procurement::getHashableFields()));

                Procurement::create([
                    ...$procurementAttributes,
                    'data_hash' => $procurementHash,
                    'blockchain_hash' => $procurementHash,
                ]);
                $syncedCount++;
            }

            // Create the initial procurement_stage record
            $statusTxid = $result['transactions']['status']['status_txid'] ?? null;
            $procurement = Procurement::where('pr_number', $prNumber)->first();

            if ($procurement && $statusTxid) {
                $enteredAt = now();
                $stageAttributes = [
                    'procurement_id' => $procurement->id,
                    'stage' => 'procurement_initiation',
                    'status' => 'procurement_initiated',
                    'entered_at' => $enteredAt,
                    'user_address' => $userAddress,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => $enteredAt,
                    'has_breach' => false,
                ];
                $stageHash = $this->computeHash($this->extractHashableFields($stageAttributes, ProcurementStage::getHashableFields()));

                ProcurementStage::updateOrCreate(
                    ['txid' => $statusTxid],
                    [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                );
                $syncedCount++;
            }

            Log::info('BlockchainWriteJob[initiate_procurement]: direct DB sync completed', [
                'job_id' => $this->jobId,
                'pr_number' => $prNumber,
                'records_created' => $syncedCount,
            ]);
        } catch (Exception $e) {
            Log::error('BlockchainWriteJob[initiate_procurement]: direct DB sync failed', [
                'job_id' => $this->jobId,
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncedCount;
    }

    /**
     * Directly update the procurement's stage/status in the DB from the handler result.
     *
     * @return int Number of records directly updated
     */
    private function directDbSyncStageStatus(array $result, array $data, string $operation, string $prNumber, string $userAddress): int
    {
        // Stage completion jobs use current_stage / next_stage_status, while
        // decision/skip jobs return stage/status directly from their handlers.
        $stage = $data['stage'] ?? $data['current_stage'] ?? $result['stage'] ?? null;
        $status = $data['current_status'] ?? $data['completion_status'] ?? $result['status'] ?? $result['current_status'] ?? null;
        $nextStage = $data['next_stage'] ?? $result['next_stage'] ?? null;
        $nextStageStatus = $data['next_stage_status'] ?? $result['next_stage_status'] ?? null;
        $statusTxid = $result['status_txid'] ?? null;

        if ($operation === 'mark_stage_complete') {
            if (($data['operation_variant'] ?? null) === 'initiation_complete') {
                $stage = $nextStage ?? $stage;
                $status = $nextStageStatus ?? $status;
            }
        }

        if (empty($stage) || empty($status)) {
            Log::debug("BlockchainWriteJob[{$operation}]: direct DB sync skipped — no stage/status in result", [
                'job_id' => $this->jobId,
                'result_keys' => array_keys($result),
            ]);

            return 0;
        }

        $syncedCount = 0;

        try {
            // Update the procurement's current stage and status
            $procurement = Procurement::withTrashed()->where('pr_number', $prNumber)->first();

            if ($procurement) {
                if ($procurement->trashed()) {
                    $procurement->restore();
                }

                $previousStatus = $procurement->current_status;

                // For completed stages with a transition, the procurement should
                // reflect the stage/status it just entered, not the old stage.
                $effectiveStage = $nextStage ?? $stage;
                $effectiveStatus = $nextStage && $operation === 'mark_stage_complete'
                    ? ($nextStageStatus ?? $status)
                    : $status;

                $procurement->update([
                    'current_stage' => $effectiveStage,
                    'current_status' => $effectiveStatus,
                    'previous_status' => $previousStatus,
                    'last_updated_at' => now(),
                ]);

                $syncedCount++;

                // Create or update the procurement_stage record for the current stage/status
                if ($statusTxid) {
                    $enteredAt = now();
                    $stageAttributes = [
                        'procurement_id' => $procurement->id,
                        'stage' => $stage,
                        'status' => $status,
                        'previous_status' => $previousStatus,
                        'entered_at' => $enteredAt,
                        'user_address' => $userAddress,
                        'is_blockchain_verified' => true,
                        'last_verified_at' => $enteredAt,
                        'has_breach' => false,
                    ];
                    $stageHash = $this->computeHash($this->extractHashableFields($stageAttributes, ProcurementStage::getHashableFields()));

                    ProcurementStage::updateOrCreate(
                        ['txid' => $statusTxid],
                        [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                    );
                    $syncedCount++;
                }

                // If there's a stage transition (skip decision), also create the next stage record
                if ($nextStage && $nextStage !== $stage) {
                    $nextStatusTxid = $result['transition_txid'] ?? null;

                    if ($nextStatusTxid) {
                        $enteredAt = now();
                        $stageAttributes = [
                            'procurement_id' => $procurement->id,
                            'stage' => $nextStage,
                            'status' => $effectiveStatus,
                            'previous_status' => $previousStatus,
                            'entered_at' => $enteredAt,
                            'user_address' => $userAddress,
                            'is_blockchain_verified' => true,
                            'last_verified_at' => $enteredAt,
                            'has_breach' => false,
                        ];
                        $stageHash = $this->computeHash($this->extractHashableFields($stageAttributes, ProcurementStage::getHashableFields()));

                        ProcurementStage::updateOrCreate(
                            ['txid' => $nextStatusTxid],
                            [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                        );
                        $syncedCount++;
                    }
                }

                Log::info("BlockchainWriteJob[{$operation}]: direct DB sync completed", [
                    'job_id' => $this->jobId,
                    'pr_number' => $prNumber,
                    'stage' => $effectiveStage,
                    'status' => $effectiveStatus,
                    'next_stage' => $nextStage,
                    'records_updated' => $syncedCount,
                ]);
            } else {
                Log::warning("BlockchainWriteJob[{$operation}]: direct DB sync skipped — procurement not found", [
                    'job_id' => $this->jobId,
                    'pr_number' => $prNumber,
                ]);
            }
        } catch (Exception $e) {
            Log::error("BlockchainWriteJob[{$operation}]: direct DB sync failed", [
                'job_id' => $this->jobId,
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }

        // Schedule a delayed blockchain re-sync to verify and reconcile
        // This ensures the DB eventually matches the blockchain source of truth,
        // even if the direct write above was incomplete.
        try {
            $syncService = app(BlockchainRecordSyncService::class);
            $syncService->syncToMirror(
                Stream::STATUS->value,
                $prNumber,
                $statusTxid ?? 'pending-verification',
                $userAddress,
                null,
                ['pr_number' => $prNumber, 'stage' => $effectiveStage ?? $stage, 'current_status' => $effectiveStatus ?? $status],
                true,
            );
        } catch (Exception $e) {
            Log::debug("BlockchainWriteJob[{$operation}]: post-sync verification deferred", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncedCount;
    }

    /**
     * Handle permanent job failure after all retries are exhausted.
     *
     * Notifies the submitting user so they can re-submit the action
     * rather than silently losing the blockchain write.
     */
    public function failed(Throwable $exception): void
    {
        // Sanitize error message to avoid exposing sensitive information
        $errorMessage = $this->sanitizeErrorMessage($exception->getMessage());

        Cache::put("blockchain_job:{$this->jobId}", [
            'status' => 'failed',
            'error' => $errorMessage,
            'user_id' => $this->userId,
        ], now()->addHour());

        Log::error("BlockchainWriteJob[{$this->operation}]: permanently failed after all retries", [
            'job_id' => $this->jobId,
            'pr_number' => $this->data['pr_number'] ?? 'N/A',
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        $this->cleanupFailedUploadTempFile();

        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $user->notify(new BlockchainJobFailedNotification(
            operation: $this->operation,
            prNumber: $this->data['pr_number'] ?? 'N/A',
            jobId: $this->jobId,
            errorMessage: $errorMessage,
        ));
    }

    /**
     * Sanitize error message to avoid exposing sensitive information.
     *
     * @param  string  $message  The original error message
     * @return string The sanitized message
     */
    private function sanitizeErrorMessage(string $message): string
    {
        $sanitized = $message;

        // Remove File paths that could expose server structure
        $sanitized = preg_replace('/in .*?\.php:\d+/', '', $sanitized) ?? $sanitized;

        // Remove database credentials or connection details
        $sanitized = preg_replace('/SQLSTATE\[[^\]]+\][^\n]*/', 'Database error occurred', $sanitized) ?? $sanitized;

        // Remove stack traces
        $sanitized = preg_replace('/Stack trace:.*/s', '', $sanitized) ?? $sanitized;

        // Remove any JSON data that might contain sensitive info
        $sanitized = preg_replace('/\{.*?\}/', '[details omitted]', $sanitized) ?? $sanitized;

        if (strlen($sanitized) > 200) {
            $sanitized = substr($sanitized, 0, 200).'...';
        }

        $sanitized = trim($sanitized);

        return $sanitized !== '' ? $sanitized : 'Blockchain write failed. Please try again or contact support.';
    }

    private function cleanupFailedUploadTempFile(): void
    {
        if ($this->operation !== 'upload_document') {
            return;
        }

        $tempPath = $this->data['temp_file_path'] ?? null;

        if (! is_string($tempPath) || $tempPath === '') {
            return;
        }

        try {
            if (Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }
        } catch (Throwable $e) {
            Log::warning('BlockchainWriteJob: failed to cleanup temp upload after permanent failure', [
                'job_id' => $this->jobId,
                'temp_path' => $tempPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function extractHashableFields(array $data, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field] = $data[$field] ?? null;
        }

        return $result;
    }
}
