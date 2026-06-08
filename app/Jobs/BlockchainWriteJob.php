<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StreamEnums;
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
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles all asynchronous blockchain write operations via the Redis queue.
 *
 * Controllers dispatch this job instead of performing RPC writes synchronously
 * in the HTTP request cycle. The job stores its result (done/failed) in Redis
 * under the key "blockchain_job:{jobId}", which the status endpoint polls.
 *
 * Supported operations: upload_document | mark_stage_complete | initiate_procurement
 *                        correct_document | correct_procurement | skip_stage
 *                        repeat_stage | update_delivery_details | publish_decision
 */
class BlockchainWriteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * Two sync strategies are used:
     * 1. Transaction-based sync: when the handler returns a `transactions` array,
     *    each entry is synced via BlockchainRecordSyncService::upstream().
     * 2. Direct DB sync: when no `transactions` key is present (e.g., publish_decision,
     *    mark_stage_complete), the result's stage/status fields are written directly
     *    to the procurements and procurement_stages tables. This avoids the race
     *    condition where reading from blockchain immediately after publish returns
     *    stale data because MultiChain needs time to make new items queryable.
     *
     * After the direct DB write, a blockchain re-sync is also triggered as a
     * verification step to ensure the normalized tables eventually match the
     * blockchain's source of truth.
     *
     * Mirror sync failure MUST never fail the job — all errors are caught,
     * logged, and silently continued.
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
            'metadata' => StreamEnums::METADATA,
            'status' => StreamEnums::STATUS,
            'event' => StreamEnums::EVENTS,
            'documents' => StreamEnums::DOCUMENTS,
            'correction' => StreamEnums::CORRECTIONS,
            'procurement_correction' => StreamEnums::PROCUREMENTS_CORRECTIONS,
            'decision' => StreamEnums::EVENTS,
            'archive' => StreamEnums::ARCHIVE,
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
                $syncService->upstream($stream->value, $prNumber, $docTxid, $userAddress, null, $docEntry, true);
                $count++;
            }

            return $count;
        }

        $syncService->upstream($stream->value, $prNumber, $txid, $userAddress, null, is_array($txData) ? $txData : [], true);

        return 1;
    }

    /**
     * Directly update the normalized DB tables from the handler result.
     *
     * This avoids the race condition where reading from blockchain immediately
     * after publishing returns stale data (MultiChain needs time to index new
     * stream items). The handler result already contains the authoritative
     * stage/status that was just written to the blockchain, so we can use
     * that to update the DB immediately.
     *
     * After this direct write, the periodic blockchain sync (syncPr/syncAll)
     * will eventually verify and reconcile these records against the
     * blockchain's source of truth.
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
     * The ProcurementRepository::create() only writes to the blockchain — it does
     * NOT write to the DB. The DB is normally updated by syncPr() reading from
     * the blockchain, but that has a race condition (MultiChain needs time to
     * index new stream items). This method creates the records directly from the
     * data that was just published, ensuring the procurement is visible in the
     * list page immediately after the job completes.
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

                Procurement::create([
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
                    'initiated_at' => now(),
                    'last_updated_at' => now(),
                    'is_blockchain_verified' => true,
                    'last_verified_at' => now(),
                    'has_breach' => false,
                    'data_hash' => '',
                    'blockchain_hash' => '',
                ]);
                $syncedCount++;
            }

            // Create the initial procurement_stage record
            $statusTxid = $result['transactions']['status']['status_txid'] ?? null;
            $procurement = Procurement::where('pr_number', $prNumber)->first();

            if ($procurement && $statusTxid) {
                ProcurementStage::updateOrCreate(
                    ['txid' => $statusTxid],
                    [
                        'procurement_id' => $procurement->id,
                        'stage' => 'procurement_initiation',
                        'status' => 'procurement_initiated',
                        'entered_at' => now(),
                        'user_address' => $userAddress,
                        'is_blockchain_verified' => true,
                        'last_verified_at' => now(),
                        'has_breach' => false,
                    ]
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
     * This avoids the race condition where reading from blockchain immediately
     * after publishing returns stale data (MultiChain needs time to index new
     * stream items). The handler result already contains the authoritative
     * stage/status that was just written to the blockchain, so we can use
     * that to update the DB immediately.
     *
     * @return int Number of records directly updated
     */
    private function directDbSyncStageStatus(array $result, array $data, string $operation, string $prNumber, string $userAddress): int
    {
        // Stage and status come from either the result OR the original data (for mark_stage_complete)
        $stage = $data['stage'] ?? $result['stage'] ?? null;
        $status = $data['current_status'] ?? $result['status'] ?? $result['current_status'] ?? null;
        $nextStage = $data['next_stage'] ?? $result['next_stage'] ?? null;
        $statusTxid = $result['status_txid'] ?? null;

        // For mark_stage_complete, derive status from next_stage_status if available
        if ($operation === 'mark_stage_complete') {
            $status = $data['completion_status'] ?? $result['next_stage_status'] ?? $status;
            $nextStage = $data['next_stage'] ?? $result['next_stage'] ?? null;
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

                // For skipped decisions, the stage transitions to next_stage
                $effectiveStage = $nextStage ?? $stage;
                $procurement->update([
                    'current_stage' => $effectiveStage,
                    'current_status' => $status,
                    'previous_status' => $previousStatus,
                    'last_updated_at' => now(),
                ]);

                $syncedCount++;

                // Create or update the procurement_stage record for the current stage/status
                if ($statusTxid) {
                    ProcurementStage::updateOrCreate(
                        ['txid' => $statusTxid],
                        [
                            'procurement_id' => $procurement->id,
                            'stage' => $stage,
                            'status' => $status,
                            'previous_status' => $previousStatus,
                            'entered_at' => now(),
                            'user_address' => $userAddress,
                            'is_blockchain_verified' => true,
                            'last_verified_at' => now(),
                            'has_breach' => false,
                        ]
                    );
                    $syncedCount++;
                }

                // If there's a stage transition (skip decision), also create the next stage record
                if ($nextStage && $nextStage !== $stage) {
                    $nextStatusTxid = $result['transition_txid'] ?? null;

                    if ($nextStatusTxid) {
                        ProcurementStage::updateOrCreate(
                            ['txid' => $nextStatusTxid],
                            [
                                'procurement_id' => $procurement->id,
                                'stage' => $nextStage,
                                'status' => $status,
                                'previous_status' => $previousStatus,
                                'entered_at' => now(),
                                'user_address' => $userAddress,
                                'is_blockchain_verified' => true,
                                'last_verified_at' => now(),
                                'has_breach' => false,
                            ]
                        );
                        $syncedCount++;
                    }
                }

                Log::info("BlockchainWriteJob[{$operation}]: direct DB sync completed", [
                    'job_id' => $this->jobId,
                    'pr_number' => $prNumber,
                    'stage' => $effectiveStage,
                    'status' => $status,
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
            $syncService->upstream(
                StreamEnums::STATUS->value,
                $prNumber,
                $statusTxid ?? 'pending-verification',
                $userAddress,
                null,
                ['pr_number' => $prNumber, 'stage' => $stage, 'current_status' => $status],
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

        // Remove file paths that could expose server structure
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
}
