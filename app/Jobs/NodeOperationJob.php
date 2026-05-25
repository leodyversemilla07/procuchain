<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Services\AuditLogger;
use App\Services\BlockchainStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Async job for long-running SSM node operations (purge / resync).
 *
 * These operations can take 60-180s via AWS SSM, which exceeds nginx's
 * fastcgi_read_timeout. Dispatching to a queue lets the HTTP response
 * return instantly while the SSM command runs in the background.
 *
 * Progress is stored in Cache under "node_operation:{jobId}" and
 * polled by the frontend via /admin/recoverable-data/node-operation/{jobId}/status.
 */
class NodeOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $operation,   // 'purge' or 'resync'
        public readonly string $nodeId,
        public readonly string $reason,
        public readonly string $jobId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(BlockchainStorageService $storage): void
    {
        // Mark as running
        Cache::put("node_operation:{$this->jobId}", [
            'status' => 'running',
            'operation' => $this->operation,
            'node_id' => $this->nodeId,
            'user_id' => $this->userId,
 ], now()->addMinutes(20));

 try {
 $result = $this->operation === 'purge'
                ? $storage->purgeAllFromNode($this->nodeId, $this->reason)
                : $storage->resyncNode($this->nodeId, $this->reason);

            Cache::put("node_operation:{$this->jobId}", [
                'status' => $result['success'] ? 'done' : 'failed',
                'operation' => $this->operation,
                'node_id' => $this->nodeId,
                'message' => $result['message'] ?? '',
                'user_id' => $this->userId,
            ], now()->addMinutes(20));

            if (!$result['success']) {
                Log::warning('NodeOperationJob returned failure', [
                    'job_id' => $this->jobId,
                    'operation' => $this->operation,
                    'node_id' => $this->nodeId,
                    'message' => $result['message'] ?? '',
                ]);
            }

            // Release the node lock regardless of success/failure
            Cache::forget("node_operation_lock:{$this->nodeId}");
        } catch (Throwable $e) {
            Cache::put("node_operation:{$this->jobId}", [
                'status' => 'failed',
                'operation' => $this->operation,
                'node_id' => $this->nodeId,
                'message' => 'Job exception: ' . $e->getMessage(),
                'user_id' => $this->userId,
            ], now()->addMinutes(20));

            // Release the node lock on exception too
            Cache::forget("node_operation_lock:{$this->nodeId}");

            Log::error('NodeOperationJob threw exception', [
                'job_id' => $this->jobId,
                'operation' => $this->operation,
                'node_id' => $this->nodeId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Let the queue handle retries
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put("node_operation:{$this->jobId}", [
            'status' => 'failed',
            'operation' => $this->operation,
            'node_id' => $this->nodeId,
            'message' => 'Job failed after all retries: ' . ($exception?->getMessage() ?? 'Unknown error'),
            'user_id' => $this->userId,
        ], now()->addMinutes(20));

        // Release the node lock on final failure
        Cache::forget("node_operation_lock:{$this->nodeId}");
    }
}
