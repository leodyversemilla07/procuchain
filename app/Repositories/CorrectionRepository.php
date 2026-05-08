<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CorrectionRepositoryInterface;
use App\DataTransferObjects\CorrectionData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.corrections stream
 */
final readonly class CorrectionRepository implements CorrectionRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new correction record
     */
    public function create(CorrectionData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::CORRECTIONS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Correction published to blockchain', [
                'pr_number' => $data->prNumber,
                'correction_type' => $data->correctionType,
                'stream' => StreamEnums::CORRECTIONS->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish correction to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find corrections by procurement ID
     *
     * Uses liststreamkeyitems for efficient key-based lookup.
     *
     * @return CorrectionData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamkeyitems(
                StreamEnums::CORRECTIONS->value,
                $prNumber,
                true,
                1000
            );

            if (! $items) {
                return [];
            }

            $corrections = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $corrections[] = CorrectionData::fromBlockchainArray($item['data']['json'], $item['txid']);
                }
            }

            return $corrections;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corrections by procurement', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Find corrections by original transaction ID
     *
     * @return CorrectionData[]
     */
    public function findByOriginalTxid(string $originalTxid): array
    {
        $allCorrections = $this->all();

        return array_filter(
            $allCorrections,
            fn (CorrectionData $correction) => $correction->originalTxid === $originalTxid
        );
    }

    /**
     * Get all corrections
     *
     * @return CorrectionData[]
     */
    public function all(): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::CORRECTIONS->value,
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
                    $corrections[] = CorrectionData::fromBlockchainArray($item['data']['json'], $item['txid']);
                }
            }

            return $corrections;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all corrections', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get correction history for a procurement
     *
     * @return CorrectionData[]
     */
    public function getHistory(string $prNumber): array
    {
        return $this->findByProcurement($prNumber);
    }
}
