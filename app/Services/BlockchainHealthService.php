<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Health Monitoring Service
 *
 * Provides health checks and status monitoring for the blockchain connection.
 * Implements circuit breaker pattern to prevent hammering a dead blockchain node.
 */
class BlockchainHealthService
{
    private const CIRCUIT_BREAKER_KEY = 'blockchain:circuit_breaker';

    private const HEALTH_CHECK_KEY = 'blockchain:health_check';

    private const FAILURE_THRESHOLD = 5; // Open circuit after 5 failures

    private const RECOVERY_TIME = 300; // 5 minutes before attempting recovery

    private const HEALTH_CHECK_TTL = 60; // Cache health check for 1 minute

    public function __construct(
        private MultichainService $multichainService
    ) {}

    /**
     * Check if blockchain is healthy and available
     */
    public function isHealthy(): bool
    {
        // Check circuit breaker first
        if ($this->isCircuitOpen()) {
            Log::warning('Blockchain circuit breaker is OPEN - blocking requests');

            return false;
        }

        // Try to get cached health status
        return Cache::remember(self::HEALTH_CHECK_KEY, self::HEALTH_CHECK_TTL, function () {
            return $this->performHealthCheck();
        });
    }

    /**
     * Perform actual health check against blockchain
     */
    private function performHealthCheck(): bool
    {
        try {
            // Simple getInfo call to check connectivity
            $info = $this->multichainService->getInfo();

            if (isset($info['nodeaddress'])) {
                $this->recordSuccess();

                return true;
            }

            $this->recordFailure();

            return false;

        } catch (\Exception $e) {
            Log::error('Blockchain health check failed', [
                'error' => $e->getMessage(),
            ]);

            $this->recordFailure();

            return false;
        }
    }

    /**
     * Check if circuit breaker is open (blocking requests)
     */
    public function isCircuitOpen(): bool
    {
        $circuitState = Cache::get(self::CIRCUIT_BREAKER_KEY);

        if (! $circuitState) {
            return false;
        }

        // Check if recovery time has passed
        if (time() >= $circuitState['recovery_time']) {
            Log::info('Circuit breaker attempting recovery');
            $this->closeCircuit();

            return false;
        }

        return true;
    }

    /**
     * Record a successful blockchain operation
     */
    public function recordSuccess(): void
    {
        $this->closeCircuit();
        Cache::forget(self::HEALTH_CHECK_KEY); // Force fresh check next time
    }

    /**
     * Record a failed blockchain operation
     */
    public function recordFailure(): void
    {
        $circuitState = Cache::get(self::CIRCUIT_BREAKER_KEY, [
            'failures' => 0,
            'opened_at' => null,
            'recovery_time' => null,
        ]);

        $circuitState['failures']++;

        // Open circuit if threshold reached
        if ($circuitState['failures'] >= self::FAILURE_THRESHOLD && ! $circuitState['opened_at']) {
            $circuitState['opened_at'] = time();
            $circuitState['recovery_time'] = time() + self::RECOVERY_TIME;

            Log::error('CIRCUIT BREAKER OPENED - Blockchain appears down', [
                'consecutive_failures' => $circuitState['failures'],
                'recovery_time' => date('Y-m-d H:i:s', $circuitState['recovery_time']),
            ]);
        }

        Cache::put(self::CIRCUIT_BREAKER_KEY, $circuitState, self::RECOVERY_TIME + 60);
        Cache::forget(self::HEALTH_CHECK_KEY);
    }

    /**
     * Close the circuit breaker (allow requests)
     */
    private function closeCircuit(): void
    {
        $circuitState = Cache::get(self::CIRCUIT_BREAKER_KEY);

        if ($circuitState && $circuitState['opened_at']) {
            Log::info('CIRCUIT BREAKER CLOSED - Blockchain recovered');
        }

        Cache::forget(self::CIRCUIT_BREAKER_KEY);
    }

    /**
     * Get comprehensive health status
     */
    public function getHealthStatus(): array
    {
        $isHealthy = $this->isHealthy();
        $circuitState = Cache::get(self::CIRCUIT_BREAKER_KEY);

        // Get database stats
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $pendingDocuments = DB::table('procurement_documents')
            ->where('blockchain_status', 'pending')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $failedDocuments = DB::table('procurement_documents')
            ->where('blockchain_status', 'failed')
            ->where('blockchain_status_updated_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'circuit_breaker' => [
                'is_open' => $this->isCircuitOpen(),
                'failures' => $circuitState['failures'] ?? 0,
                'recovery_time' => isset($circuitState['recovery_time']) && $circuitState['recovery_time']
                    ? date('Y-m-d H:i:s', $circuitState['recovery_time'])
                    : null,
            ],
            'queue' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs_24h' => $failedJobs,
            ],
            'documents' => [
                'pending_1h' => $pendingDocuments,
                'failed_24h' => $failedDocuments,
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Reset circuit breaker (admin override)
     */
    public function resetCircuitBreaker(): void
    {
        Cache::forget(self::CIRCUIT_BREAKER_KEY);
        Cache::forget(self::HEALTH_CHECK_KEY);

        Log::info('Circuit breaker manually reset by administrator');
    }
}
