<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProcurementRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Procurement Repository
 *
 * Handles all blockchain CRUD operations for procurement metadata
 * Stream: procurement.metadata
 */
class ProcurementRepository implements ProcurementRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    public function create(ProcurementData $procurement): void
    {
        $this->multichain->publish(
            StreamEnums::METADATA->value,
            $procurement->prNumber,
            ['json' => $procurement->toBlockchainArray()]
        );

        // Clear caches to show new procurement
        \App\Services\DashboardCacheKeys::clearAllProcurementCaches();

        Log::info('Procurement published to blockchain', [
            'pr_number' => $procurement->prNumber,
            'stream' => StreamEnums::METADATA->value,
        ]);
    }

    public function findByProcurement(string $prNumber): ?ProcurementData
    {
        try {
            $items = $this->multichain->liststreamkeyitems(StreamEnums::METADATA->value, $prNumber);

            if (empty($items)) {
                return null;
            }

            // Get the latest version - use last item if blocktime is pending (unconfirmed)
            // Items are returned in chronological order, so last item is most recent
            $latestItem = collect($items)
                ->sortByDesc(fn ($item) => $item['blocktime'] ?? PHP_INT_MAX)
                ->first();

            // If all items are pending, get the last one (most recently published)
            if (($latestItem['blocktime'] ?? null) === null) {
                $latestItem = end($items);
            }

            return ProcurementData::fromBlockchainArray($latestItem['data']['json']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement from blockchain', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function all(int $limit = 1000, int $offset = 0): Collection
    {
        try {
            // OPTIMIZATION: Use verbose=false for faster response
            $items = $this->multichain->liststreamitems(
                'procurement.metadata',
                false,  // verbose=false
                $limit,
                $offset,
                false
            );

            // Group by procurement ID and get latest version of each
            return collect($items)
                ->groupBy('keys.0')
                ->map(fn ($group) => collect($group)->sortByDesc('blocktime')->first())
                ->map(fn ($item) => ProcurementData::fromBlockchainArray($item['data']['json']))
                ->values();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurements from blockchain', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Find multiple procurements by PR numbers (OPTIMIZED BATCH FETCH)
     *
     * @param  array<string>  $prNumbers
     * @return array<string, ProcurementData|null>
     */
    public function findManyByProcurement(array $prNumbers): array
    {
        if (empty($prNumbers)) {
            return [];
        }

        try {
            // Fetch all metadata items once (much faster than per-PR queries)
            $items = $this->multichain->liststreamitems(
                StreamEnums::METADATA->value,
                false,  // verbose=false
                count($prNumbers) * 2,  // Buffer for multiple versions
                0,
                false
            );

            $result = [];
            $grouped = collect($items)->groupBy('keys.0');

            foreach ($prNumbers as $prNumber) {
                if ($grouped->has($prNumber)) {
                    // Get latest version for this PR
                    $latest = $grouped->get($prNumber)
                        ->sortByDesc('blocktime')
                        ->first();

                    if ($latest && isset($latest['data']['json'])) {
                        $result[$prNumber] = ProcurementData::fromBlockchainArray($latest['data']['json']);
                    } else {
                        $result[$prNumber] = null;
                    }
                } else {
                    $result[$prNumber] = null;
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to batch fetch procurements', [
                'pr_count' => count($prNumbers),
                'error' => $e->getMessage(),
            ]);

            // Return nulls for all
            return array_fill_keys($prNumbers, null);
        }
    }

    public function update(ProcurementData $procurement): void
    {
        // Publish new version to blockchain (immutable append)
        $this->multichain->publish(
            StreamEnums::METADATA->value,
            $procurement->prNumber,
            ['json' => $procurement->toBlockchainArray()]
        );

        // Clear caches to show updated procurement data
        \App\Services\DashboardCacheKeys::clearAllProcurementCaches();

        Log::info('Procurement metadata updated on blockchain', [
            'pr_number' => $procurement->prNumber,
            'stream' => StreamEnums::METADATA->value,
        ]);
    }

    public function getHistory(string $prNumber): Collection
    {
        try {
            $items = $this->multichain->liststreamkeyitems(StreamEnums::METADATA->value, $prNumber);

            return collect($items)
                ->sortBy('blocktime')
                ->map(fn ($item) => [
                    'data' => ProcurementData::fromBlockchainArray($item['data']['json']),
                    'txid' => $item['txid'],
                    'blocktime' => $item['blocktime'],
                    'blockheight' => $item['blockheight'],
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement history', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Check if a procurement exists
     */
    public function exists(string $prNumber): bool
    {
        return $this->findByProcurement($prNumber) !== null;
    }
}
