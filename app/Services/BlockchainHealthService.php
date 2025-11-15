<?php

namespace App\Services;

use App\Libraries\MultiChain\Manager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Health Monitoring Service
 *
 * Provides health checks and status monitoring for the blockchain connection.
 * Implements circuit breaker pattern to prevent hammering a dead blockchain node.
 * Used by BlockchainExplorerController to display health metrics.
 */
class BlockchainHealthService
{
    private const CIRCUIT_BREAKER_KEY = 'blockchain:circuit_breaker';

    private const HEALTH_CHECK_KEY = 'blockchain:health_check';

    // Issue #20 fix: Load from config instead of hardcoded constants
    private int $failureThreshold;

    private int $recoveryTime;

    private int $healthCheckTtl;

    public function __construct(
        private Manager $multichain
    ) {
        // Load configuration values (Issue #20 fix)
        $this->failureThreshold = config('blockchain.health_check.failure_threshold', 5);
        $this->recoveryTime = config('blockchain.health_check.recovery_time', 300);
        $this->healthCheckTtl = config('blockchain.health_check.health_check_ttl', 60);
    }

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

        // Try to get cached health status (Issue #20 fix: use instance property)
        return Cache::remember(self::HEALTH_CHECK_KEY, $this->healthCheckTtl, function () {
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
            $info = $this->multichain->getinfo();

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
            Log::info('Circuit breaker attempting recovery - entering half-open state');

            // Try a test request before fully closing circuit (Issue #6 fix)
            try {
                $info = $this->multichain->getinfo();

                if (isset($info['nodeaddress'])) {
                    Log::info('Circuit breaker recovery successful - closing circuit');
                    $this->closeCircuit();

                    return false; // Circuit is now closed (healthy)
                }

                // Test failed, extend recovery time
                Log::warning('Circuit breaker recovery test failed - staying open');
                $this->extendRecoveryTime();

                return true; // Circuit stays open
            } catch (\Exception $e) {
                Log::warning('Circuit breaker recovery failed - staying open', [
                    'error' => $e->getMessage(),
                ]);

                // Extend recovery time for next attempt
                $this->extendRecoveryTime();

                return true; // Circuit stays open
            }
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

        // Open circuit if threshold reached (Issue #20 fix: use instance property)
        if ($circuitState['failures'] >= $this->failureThreshold && ! $circuitState['opened_at']) {
            $circuitState['opened_at'] = time();
            $circuitState['recovery_time'] = time() + $this->recoveryTime;

            Log::error('CIRCUIT BREAKER OPENED - Blockchain appears down', [
                'consecutive_failures' => $circuitState['failures'],
                'recovery_time' => date('Y-m-d H:i:s', $circuitState['recovery_time']),
            ]);
        }

        Cache::put(self::CIRCUIT_BREAKER_KEY, $circuitState, $this->recoveryTime + 60);
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
     * Extend recovery time for failed recovery attempts (Issue #6 fix)
     */
    private function extendRecoveryTime(): void
    {
        $circuitState = Cache::get(self::CIRCUIT_BREAKER_KEY);

        if ($circuitState) {
            // Extend by another recovery period (Issue #20 fix: use instance property)
            $circuitState['recovery_time'] = time() + $this->recoveryTime;

            Cache::put(self::CIRCUIT_BREAKER_KEY, $circuitState, $this->recoveryTime + 60);

            Log::info('Circuit breaker recovery time extended', [
                'next_attempt' => date('Y-m-d H:i:s', $circuitState['recovery_time']),
            ]);
        }
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
