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
     * Uses MultiChain's key-based indexing for faster queries
     * Per MultiChain docs: local-ordering provides faster execution
     *
     * @return StatusData[]
     */
    public function getLatestByProcurement(int $limit = 100): array
    {
        try {
            // EMERGENCY FALLBACK: If limit is very small, use simple all() method
            // to avoid N+1 blockchain queries
            if ($limit <= 10) {
                Log::info('Using fallback all() method due to small limit', ['limit' => $limit]);
                $allStatuses = $this->all($limit * 5, 0); // Fetch more items to account for multiple versions

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

                return array_slice(array_values($grouped), 0, $limit);
            }

            // Get stream keys (PR numbers) first - this is very fast
            // OPTIMIZATION: local-ordering=true for faster query execution
            $keys = $this->multichain->liststreamkeys(
                StreamEnums::STATUS->value,
                '*',
                false,  // verbose=false for speed
                $limit,
                0,
                true    // local-ordering for faster queries
            );

            if (! $keys) {
                return [];
            }

            $latestStatuses = [];

            // For each PR, get only the latest item (count=1, start=-1 for most recent)
            foreach ($keys as $key) {
                if (empty($key['key'])) {
                    continue;
                }

                $prNumber = $key['key'];

                // Get all items for this key and take the latest by timestamp
                // Note: MultiChain returns items in chronological order (oldest first)
                $items = $this->multichain->liststreamkeyitems(
                    StreamEnums::STATUS->value,
                    $prNumber,
                    false,  // verbose=false for speed
                    10,     // Get last 10 to find the latest
                    0,      // start from beginning
                    true    // local-ordering for faster queries
                );

                if (! empty($items)) {
                    // Items are in chronological order, so last item is the latest
                    $latestItem = end($items);
                    if (isset($latestItem['data']['json'])) {
                        try {
                            $latestStatuses[] = StatusData::fromBlockchainArray($latestItem['data']['json']);
                        } catch (\Exception $e) {
                            Log::error('Failed to parse status data for PR', [
                                'pr_number' => $prNumber,
                                'error' => $e->getMessage(),
                                'data' => $latestItem['data']['json'] ?? null,
                            ]);
                        }
                    }
                }
            }

            return $latestStatuses;
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
