<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Handlers\CorrectionHandler;
use App\Jobs\Handlers\DocumentUploadHandler;
use App\Jobs\Handlers\ProcurementInitiationHandler;
use App\Jobs\Handlers\ProcurementUpdateHandler;
use App\Jobs\Handlers\StageCompletionHandler;
use App\Jobs\Handlers\StageTransitionHandler;
use App\Models\User;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\DirectDbSyncService;
use App\Services\MirrorSyncOrchestrator;
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

            $syncedCount = app(MirrorSyncOrchestrator::class)->syncTransactionResults(
                $result['transactions'] ?? [],
                $prNumber,
                $userAddress,
                $operation,
            );

            $directSyncCount = 0;
            $syncMethod = $this->getDirectSyncMethod($operation);
            if ($syncMethod !== null) {
                $directSyncCount = app(DirectDbSyncService::class)->{$syncMethod}(
                    $result,
                    $data,
                    $operation,
                    $prNumber,
                    $userAddress,
                );
            }

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

    private function getDirectSyncMethod(string $operation): ?string
    {
        return match ($operation) {
            'initiate_procurement' => 'syncInitiation',
            'publish_decision', 'mark_stage_complete', 'skip_stage', 'repeat_stage' => 'syncStageStatus',
            default => null,
        };
    }

    public function failed(Throwable $exception): void
    {
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

    private function sanitizeErrorMessage(string $message): string
    {
        $sanitized = $message;
        $sanitized = preg_replace('/in .*?\.php:\d+/', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/SQLSTATE\[[^\]]+\][^\n]*/', 'Database error occurred', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/Stack trace:.*/s', '', $sanitized) ?? $sanitized;
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
}
