<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementCorrectionData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.metadata.corrections stream
 */
final readonly class ProcurementCorrectionRepository implements ProcurementCorrectionRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new procurement correction record
     */
    public function create(ProcurementCorrectionData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::PROCUREMENTS_CORRECTIONS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Procurement correction published to blockchain', [
                'pr_number' => $data->prNumber,
                'correction_type' => $data->correctionType,
                'stream' => StreamEnums::PROCUREMENT_CORRECTIONS->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish procurement correction to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find corrections by procurement ID
     *
     * @return ProcurementCorrectionData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        $allCorrections = $this->all();

        return array_filter(
            $allCorrections,
            fn (ProcurementCorrectionData $correction) => $correction->prNumber === $prNumber
        );
    }

    /**
     * Get all procurement corrections
     *
     * @return ProcurementCorrectionData[]
     */
    public function all(): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::PROCUREMENTS_CORRECTIONS->value,
                true,
                1000,
                0,
                false
            );

            if (! $items) {
                return [];
            }

            $corrections = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $corrections[] = ProcurementCorrectionData::fromBlockchainArray($item['data']['json'], $item['txid']);
                }
            }

            return $corrections;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all procurement corrections', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get correction history for a procurement
     *
     * @return ProcurementCorrectionData[]
     */
    public function getHistory(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::PROCUREMENTS_CORRECTIONS->value,
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
                    $correction = ProcurementCorrectionData::fromBlockchainArray($item['data']['json'], $item['txid']);
                    if ($correction->prNumber === $prNumber) {
                        $history[] = $correction;
                    }
                }
            }

            return $history;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement correction history', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if a procurement has any corrections
     */
    public function hasCorrections(string $prNumber): bool
    {
        $corrections = $this->findByProcurement($prNumber);

        return ! empty($corrections);
    }

    /**
     * Get the latest correction for a procurement
     */
    public function getLatest(string $prNumber): ?ProcurementCorrectionData
    {
        $corrections = $this->findByProcurement($prNumber);

        if (empty($corrections)) {
            return null;
        }

        // Sort by timestamp descending and return the first (latest)
        usort($corrections, fn ($a, $b) => $b->timestamp <=> $a->timestamp);

        return $corrections[0];
    }
}
