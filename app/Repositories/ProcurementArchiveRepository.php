<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Procurement Archive Repository
 *
 * Handles archiving and restoring procurements using the 'procurement.archive' stream.
 * 
 * Data Structure:
 * key: pr_number
 * data: {
 *   "action": "archive" | "restore",
 *   "timestamp": "ISO8601",
 *   "user_id": "user_id",
 *   "reason": "optional reason"
 * }
 */
class ProcurementArchiveRepository
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Archive a procurement
     */
    public function archive(string $prNumber, string $userId, ?string $reason = null): void
    {
        $data = [
            'action' => 'archive',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $userId,
            'reason' => $reason,
        ];

        $this->multichain->publish(
            StreamEnums::ARCHIVE->value,
            $prNumber,
            ['json' => $data]
        );

        Log::info('Procurement archived on blockchain', [
            'pr_number' => $prNumber,
            'user_id' => $userId,
        ]);
        
        // Clear caches
        \App\Services\DashboardCacheKeys::clearAllProcurementCaches();
    }

    /**
     * Restore a procurement
     */
    public function restore(string $prNumber, string $userId): void
    {
        $data = [
            'action' => 'restore',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $userId,
        ];

        $this->multichain->publish(
            StreamEnums::ARCHIVE->value,
            $prNumber,
            ['json' => $data]
        );

        Log::info('Procurement restored on blockchain', [
            'pr_number' => $prNumber,
            'user_id' => $userId,
        ]);

        // Clear caches
        \App\Services\DashboardCacheKeys::clearAllProcurementCaches();
    }

    /**
     * Get all archived procurement IDs
     * 
     * @return array<string> List of PR numbers that are currently archived
     */
    public function getArchivedPrNumbers(): array
    {
        try {
            // Fetch all items from archive stream
            $items = $this->multichain->liststreamitems(
                StreamEnums::ARCHIVE->value,
                false,  // verbose
                10000,  // limit - high number to catch all
                0,      // start
                false   // local-ordering
            );

            // Group by PR number (key) and get latest action
            $currentStatus = collect($items)
                ->groupBy('keys.0')
                ->map(function ($group) {
                    // Use the last item in the list, as liststreamitems returns in chronological order by default
                    // unless 'local-ordering' is true (which we set to false)
                    // The last item is the most recent one.
                    $latest = collect($group)->last();
                    return $latest['data']['json']['action'] ?? 'restore';
                });

            // Filter only those where latest action is 'archive'
            return $currentStatus
                ->filter(fn ($status) => $status === 'archive')
                ->keys()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to fetch archived procurements', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if a specific procurement is archived
     */
    public function isArchived(string $prNumber): bool
    {
        try {
            $items = $this->multichain->liststreamkeyitems(StreamEnums::ARCHIVE->value, $prNumber);
            
            if (empty($items)) {
                return false;
            }

            // Get latest item - liststreamkeyitems returns in chronological order
            $latest = collect($items)->last();
            $action = $latest['data']['json']['action'] ?? 'restore';

            return $action === 'archive';
        } catch (\Exception $e) {
            // If stream doesn't exist or error, assume not archived
            return false;
        }
    }
}
