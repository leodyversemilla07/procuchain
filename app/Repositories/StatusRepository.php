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
    public function all(): array
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

            $statuses = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $statuses[] = StatusData::fromBlockchainArray($item['data']['json']);
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
