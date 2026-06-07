<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\IntegrityVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunIntegrityVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $cacheKey,
        private readonly string $userId,
        private readonly string $userName,
        private readonly bool $autoRepair,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(IntegrityVerificationService $service): void
    {
        Cache::put("{$this->cacheKey}_status", 'running', 3600);
        Cache::put("{$this->cacheKey}_started_at", now()->toIso8601String(), 3600);

        Log::info('RunIntegrityVerification: job started', [
            'cache_key' => $this->cacheKey,
            'auto_repair' => $this->autoRepair,
            'user' => $this->userName,
        ]);

        try {
            $result = $service->verifyAndRepair(
                autoRepair: $this->autoRepair,
                source: 'manual',
            );

            Cache::put("{$this->cacheKey}_status", 'completed', 3600);
            Cache::put("{$this->cacheKey}_result", $result, 3600);

            Log::info('RunIntegrityVerification: job completed', $result);
        } catch (\Throwable $e) {
            Cache::put("{$this->cacheKey}_status", 'failed', 3600);
            Cache::put("{$this->cacheKey}_error", 'Verification failed. Please try again or contact support.', 3600);

            Log::error('RunIntegrityVerification: job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
