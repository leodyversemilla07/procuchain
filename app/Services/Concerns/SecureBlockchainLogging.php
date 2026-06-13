<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Trait for secure blockchain operation logging
 *
 * Implements Issue #17 fix: Reduces logging of sensitive data
 * while maintaining useful debugging information.
 */
trait SecureBlockchainLogging
{
    /**
     * Log procurement operation with masked sensitive data
     */
    protected function logProcurementOperation(string $level, string $message, string $prNumber, array $context = []): void
    {
        $context['pr_number'] = $this->maskPrNumber($prNumber);

        Log::log($level, $message, $context);
    }

    /**
     * Log with user information (ID only, not full object)
     */
    protected function logWithUser(string $level, string $message, $user, array $context = []): void
    {
        if ($user) {
            $context['user_id'] = is_object($user) ? $user->id : $user;
            // Never log full user object or sensitive fields
        }

        Log::log($level, $message, $context);
    }

    /**
     * Log document operation with minimal sensitive data
     */
    protected function logDocumentOperation(string $level, string $message, string $prNumber, string $filename, array $context = []): void
    {
        $context['pr_number'] = $this->maskPrNumber($prNumber);
        $context['file_name'] = basename($filename); // filename only, no path
        $context['BlockchainFile_extension'] = pathinfo($filename, PATHINFO_EXTENSION);

        // Don't log File contents, full paths, or large metadata

        Log::log($level, $message, $context);
    }

    /**
     * Mask PR number to show only prefix for privacy
     *
     * Examples:
     * - Full: PR-2025-001-0001
     * - Masked: PR-2025-...
     */
    protected function maskPrNumber(string $prNumber): string
    {
        if (config('blockchain.logging.log_full_pr_numbers', false)) {
            return $prNumber; // Production should set to false
        }

        $prefixLength = config('blockchain.logging.pr_number_prefix_length', 11);

        if (strlen($prNumber) <= $prefixLength) {
            return $prNumber;
        }

        return substr($prNumber, 0, $prefixLength).'...';
    }

    /**
     * Hash sensitive identifier for correlation without exposure
     */
    protected function hashIdentifier(string $identifier): string
    {
        return substr(hash('sha256', $identifier), 0, 12);
    }

    /**
     * Log blockchain health check with minimal data
     */
    protected function logHealthCheck(string $level, string $message, array $context = []): void
    {
        // Health checks don't need sensitive data
        // Only log status and timestamps
        $safeContext = [
            'timestamp' => now()->toIso8601String(),
            'status' => $context['status'] ?? 'unknown',
        ];

        if (isset($context['error'])) {
            $safeContext['error'] = $context['error'];
        }

        Log::log($level, $message, $safeContext);
    }

    /**
     * Sanitize context array by removing sensitive keys
     */
    protected function sanitizeContext(array $context): array
    {
        $sensitiveKeys = [
            'password',
            'token',
            'secret',
            'api_key',
            'private_key',
            'blockchain_address',
            'user',
            'users',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($context[$key])) {
                $context[$key] = '[REDACTED]';
            }
        }

        return $context;
    }
}
