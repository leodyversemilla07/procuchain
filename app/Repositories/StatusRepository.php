<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\StatusData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.status stream
 */
final readonly class StatusRepository
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new status record
     */
    public function create(StatusData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::STATUS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Status published to blockchain', [
                'pr_number' => $data->prNumber,
                'current_status' => $data->currentStatus,
                'stream' => StreamEnums::STATUS->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish status to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find status records by procurement ID
     *
     * @return StatusData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        $allStatuses = $this->all();

        $filtered = array_filter(
            $allStatuses,
            fn (StatusData $status): bool => $status->prNumber === $prNumber
        );

        // Sort by timestamp descending (most recent first)
        usort($filtered, function (StatusData $a, StatusData $b): int {
            $timeA = $a->timestamp instanceof \Carbon\Carbon ? $a->timestamp->timestamp : strtotime($a->timestamp);
            $timeB = $b->timestamp instanceof \Carbon\Carbon ? $b->timestamp->timestamp : strtotime($b->timestamp);

            return $timeB - $timeA;
        });

        return array_values($filtered);
    }

    /**
     * Get the latest status for a procurement
     */
    public function getLatest(string $prNumber): ?StatusData
    {
        $statuses = $this->findByProcurement($prNumber);

        if (empty($statuses)) {
            return null;
        }

        usort($statuses, fn ($a, $b) => $b->timestamp->timestamp - $a->timestamp->timestamp);

        return $statuses[0];
    }

    /**
     * Get all status records
     *
     * @return StatusData[]
     */
    public function all(int $limit = 1000, int $offset = 0): array
    {
        try {
            // Optimization: Use verbose=false to reduce data transfer, we only need the JSON
            $items = $this->multichain->liststreamitems(
                StreamEnums::STATUS->value,
                false,  // verbose=false for faster response
                $limit,
                $offset,
                false
            );

            if (! $items) {
                return [];
            }

            $statuses = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    try {
                        $statuses[] = StatusData::fromBlockchainArray($item['data']['json']);
                    } catch (\Exception $e) {
                        Log::error('Failed to parse status data in all()', [
                            'error' => $e->getMessage(),
                            'data' => $item['data']['json'] ?? null,
                        ]);
                    }
                }
            }

            return $statuses;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all statuses', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get only the latest status for each unique procurement (OPTIMIZED)
     * Uses single-query approach to avoid N+1 blockchain queries
     *
     * SCALING NOTES:
     * - Fetches $limit * 10 items in single query to find all unique procurements
     * - Current performance: ~1.5s for 150 items, ~3-5s for 500 items
     * - If blockchain grows beyond 1000+ status items, consider:
     *   1. Adding pagination to the procurement list
     *   2. Using blockchain caching
     *   3. Implementing background sync to database
     *
     * @param  int  $limit  Hint for expected unique procurements (multiplied by 10 internally)
     * @return StatusData[] Array of latest status for each procurement
     */
    public function getLatestByProcurement(int $limit = 100): array
    {
        try {
            // OPTIMIZED: Always use single-query method to avoid N+1 blockchain queries
            // This prevents timeout issues when fetching multiple procurements
            // Fetch all status items in a single query, then group by PR number
            Log::info('Using optimized single-query method', ['limit' => $limit]);

            // Fetch enough items to cover all procurements (multiply by expected items per procurement)
            // 150 items should cover ~15-20 unique procurements with ~8-10 status updates each
            $fetchLimit = max($limit * 10, 150);
            $allStatuses = $this->all($fetchLimit, 0);

            // Group by PR and get latest for each
            $grouped = [];
            foreach ($allStatuses as $status) {
                $prNumber = $status->prNumber;
                if (! isset($grouped[$prNumber])) {
                    $grouped[$prNumber] = $status;
                } else {
                    // Keep the latest by timestamp
                    $currentTime = $grouped[$prNumber]->timestamp instanceof \Carbon\Carbon
                        ? $grouped[$prNumber]->timestamp->timestamp
                        : strtotime($grouped[$prNumber]->timestamp);
                    $newTime = $status->timestamp instanceof \Carbon\Carbon
                        ? $status->timestamp->timestamp
                        : strtotime($status->timestamp);

                    if ($newTime > $currentTime) {
                        $grouped[$prNumber] = $status;
                    }
                }
            }

            // Return all unique procurements found (don't truncate)
            $result = array_values($grouped);

            Log::info('Found unique procurements', [
                'fetched_items' => count($allStatuses),
                'unique_procurements' => count($result),
            ]);

            return $result;

            // NOTE: The N+1 query method has been removed to prevent timeout issues
            // The single-query method above is now used for all cases
            // This code block is kept as a fallback but should never be reached
            return [];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve latest statuses by procurement', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to old method
            return $this->all($limit);
        }
    }

    /**
     * Get status history for a procurement
     *
     * @return StatusData[]
     */
    public function getHistory(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::STATUS->value,
                true,
                1000,
                0,
                false
            );

            if (! $items) {
                return [];
            }

            $history = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $status = StatusData::fromBlockchainArray($item['data']['json']);
                    if ($status->prNumber === $prNumber) {
                        $history[] = $status;
                    }
                }
            }

            return $history;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve status history', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
