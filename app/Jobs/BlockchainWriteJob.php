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

            Cache::put("blockchain_job:{$this->jobId}", [
                'status' => 'done',
                'result' => $result,
                'user_id' => $this->userId,
            ], now()->addHour());

            $this->syncToMirror($result, $this->data, $this->operation);

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
     * Sync blockchain write results to the procurement_records table.
     *
     * Iterates through the result transactions and calls
     * BlockchainRecordSyncService::upstream() for each entry.
     * Mirror sync failure MUST never fail the job — all errors
     * are caught, logged, and silently continued.
     *
     * @param  array  $result  The handler result containing transactions
     * @param  array  $data  The original job data payload
     * @param  string  $operation  The blockchain operation type
     */
    private function syncToMirror(array $result, array $data, string $operation): void
    {
        try {
            $syncService = app(BlockchainRecordSyncService::class);

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

            $transactions = $result['transactions'] ?? [];
            $syncedCount = 0;

            foreach ($transactions as $type => $txData) {
                try {
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

                        continue;
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

                        continue;
                    }

                    // Documents is an array of individual document entries
                    if ($type === 'documents' && isset($txData[0]) && is_array($txData[0])) {
                        foreach ($txData as $docEntry) {
                            $docTxid = $docEntry['txid'] ?? '';

                            if (empty($docTxid)) {
                                continue;
                            }

                            $syncService->upstream(
                                $stream->value,
                                $prNumber,
                                $docTxid,
                                $userAddress,
                                null,
                                $docEntry,
                                true,
                            );
                            $syncedCount++;
                        }
                    } else {
                        $syncService->upstream(
                            $stream->value,
                            $prNumber,
                            $txid,
                            $userAddress,
                            null,
                            is_array($txData) ? $txData : [],
                            true,
                        );
                        $syncedCount++;
                    }
                } catch (Exception $e) {
                    Log::error("BlockchainWriteJob[{$operation}]: mirror sync failed for transaction type", [
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            Log::info("BlockchainWriteJob[{$operation}]: mirror sync completed", [
                'job_id' => $this->jobId,
                'pr_number' => $prNumber,
                'synced_count' => $syncedCount,
            ]);
        } catch (Exception $e) {
            Log::error("BlockchainWriteJob[{$operation}]: mirror sync failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle permanent job failure after all retries are exhausted.
     *
     * Notifies the submitting user so they can re-submit the action
     * rather than silently losing the blockchain write.
     */
    public function failed(Throwable $exception): void
    {
        Cache::put("blockchain_job:{$this->jobId}", [
            'status' => 'failed',
            'error' => $exception->getMessage(),
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
            errorMessage: $exception->getMessage(),
        ));
    }
}
