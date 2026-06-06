<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\StreamEnums;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Services\DashboardCacheKeys;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Procurement Archive Repository
 *
 * Handles archiving and restoring procurements.
 * Reads from DB, writes to blockchain.
 */
class ProcurementArchiveRepository
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Archive a procurement (writes to blockchain)
     */
    public function archive(string $prNumber, string $userId, ?string $reason = null): void
    {
        $data = [
            'action' => 'archive',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $userId,
            'reason' => $reason,
        ];

        $txid = $this->multichain->publish(
            StreamEnums::ARCHIVE->value,
            $prNumber,
            ['json' => $data]
        );

        if (! is_string($txid) || $txid === '') {
            throw new \RuntimeException('Blockchain archive publish did not return a transaction id.');
        }

        Log::info('Procurement archived', ['pr_number' => $prNumber, 'txid' => $txid]);
        DashboardCacheKeys::clearAllProcurementCaches();
    }

    /**
     * Restore a procurement (writes to blockchain)
     */
    public function restore(string $prNumber, string $userId): void
    {
        $data = [
            'action' => 'restore',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $userId,
        ];

        $txid = $this->multichain->publish(
            StreamEnums::ARCHIVE->value,
            $prNumber,
            ['json' => $data]
        );

        if (! is_string($txid) || $txid === '') {
            throw new \RuntimeException('Blockchain archive restore publish did not return a transaction id.');
        }

        Log::info('Procurement restored', ['pr_number' => $prNumber, 'txid' => $txid]);
        DashboardCacheKeys::clearAllProcurementCaches();
    }

    /**
     * Get archived PR numbers from DB.
     */
    public function getArchivedPrNumbers(): array
    {
        return ProcurementArchive::where('action', 'archive')
            ->pluck('procurement_id')
            ->map(fn ($id) => Procurement::find($id)?->pr_number)
            ->filter()
            ->toArray();
    }

    /**
     * Check if a PR is archived in DB.
     */
    public function isArchived(string $prNumber): bool
    {
        return ProcurementArchive::where('action', 'archive')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->exists();
    }
}
