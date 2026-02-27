<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Handlers\CorrectionHandler;
use App\Jobs\Handlers\DocumentUploadHandler;
use App\Jobs\Handlers\ProcurementInitiationHandler;
use App\Jobs\Handlers\ProcurementUpdateHandler;
use App\Jobs\Handlers\StageCompletionHandler;
use App\Jobs\Handlers\StageTransitionHandler;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    public function __construct(
        public readonly string $operation,
        public readonly array $data,
        public readonly string $jobId,
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
            ], now()->addHour());

            Log::info("BlockchainWriteJob[{$this->operation}]: completed", [
                'job_id' => $this->jobId,
                'pr_number' => $this->data['pr_number'] ?? 'N/A',
            ]);
        } catch (Exception $e) {
            Cache::put("blockchain_job:{$this->jobId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], now()->addHour());

            Log::error("BlockchainWriteJob[{$this->operation}]: failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
