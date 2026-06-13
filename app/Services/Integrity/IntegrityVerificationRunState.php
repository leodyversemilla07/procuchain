<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Models\IntegrityViolationLog;

/**
 * Mutable state bag shared across IntegrityVerificationService and its extracted sub-services.
 *
 * Passed by reference so mutations in sub-services are visible to the orchestrator.
 */
class IntegrityVerificationRunState
{
    public string $runId = '';

    public string $source = '';

    /** @var array<string, int> violation type => count */
    public array $violationCounts = [];

    public int $verifiedCount = 0;

    public int $restoredCount = 0;

    public int $failedCount = 0;

    public bool $verifyPublishers = false;

    public function reset(string $source): void
    {
        $this->runId = IntegrityViolationLog::newRunId();
        $this->source = $source;
        $this->violationCounts = [];
        $this->verifiedCount = 0;
        $this->restoredCount = 0;
        $this->failedCount = 0;
        $this->verifyPublishers = false;
    }

    public function result(): array
    {
        return [
            'run_id' => $this->runId,
            'verified' => $this->verifiedCount,
            'violations' => $this->violationCounts,
            'restored' => $this->restoredCount,
            'failed' => $this->failedCount,
        ];
    }
}
