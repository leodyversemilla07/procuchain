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

            // Get the latest version
            $latestItem = collect($items)->sortByDesc('blocktime')->first();

            return ProcurementData::fromBlockchainArray($latestItem['data']['json']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement from blockchain', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function all(): Collection
    {
        try {
            $items = $this->multichain->liststreamitems('procurement.metadata');

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

    public function update(ProcurementData $procurement): void
    {
        // Publish new version to blockchain (immutable append)
        $this->multichain->publish(
            StreamEnums::METADATA->value,
            $procurement->prNumber,
            ['json' => $procurement->toBlockchainArray()]
        );

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
