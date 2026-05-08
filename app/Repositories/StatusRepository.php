<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\StatusData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Carbon\Carbon;
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
     * Uses liststreamkeyitems for efficient key-based lookup
     * instead of scanning all stream items.
     *
     * @return StatusData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamkeyitems(
                StreamEnums::STATUS->value,
                $prNumber,
                false,
                1000
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
                        Log::warning('Failed to parse status data in findByProcurement', [
                            'pr_number' => $prNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Sort by timestamp descending (most recent first)
            usort($statuses, fn (StatusData $a, StatusData $b): int => $b->timestamp->timestamp - $a->timestamp->timestamp
            );

            return array_values($statuses);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve status by procurement', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
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
            $items = $this->multichain->liststreamitems(
                StreamEnums::STATUS->value,
                false,
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
     */
    public function getLatestByProcurement(int $limit = 100): array
    {
        try {
            Log::info('Using optimized single-query method', ['limit' => $limit]);

            $fetchLimit = max($limit * 10, 150);
            $allStatuses = $this->all($fetchLimit, 0);

            $grouped = [];
            foreach ($allStatuses as $status) {
                $prNumber = $status->prNumber;
                if (! isset($grouped[$prNumber])) {
                    $grouped[$prNumber] = $status;
                } else {
                    $currentTime = $grouped[$prNumber]->timestamp instanceof Carbon
                        ? $grouped[$prNumber]->timestamp->timestamp
                        : strtotime($grouped[$prNumber]->timestamp);
                    $newTime = $status->timestamp instanceof Carbon
                        ? $status->timestamp->timestamp
                        : strtotime($status->timestamp);

                    if ($newTime > $currentTime) {
                        $grouped[$prNumber] = $status;
                    }
                }
            }

            $result = array_values($grouped);

            Log::info('Found unique procurements', [
                'fetched_items' => count($allStatuses),
                'unique_procurements' => count($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve latest statuses by procurement', [
                'error' => $e->getMessage(),
            ]);

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
        return $this->findByProcurement($prNumber);
    }
}
