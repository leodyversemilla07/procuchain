<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\StatusData;
use App\Enums\Stream;
use App\Models\ProcurementStage;
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement status
 * Reads from DB (mirror of blockchain).
 */
final readonly class StatusRepository
{
    public function __construct(
        private BlockchainRpcClient $multichain
    ) {}

    /**
     * Create a new status record (writes to blockchain)
     */
    public function create(StatusData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                Stream::STATUS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain status publish did not return a transaction id.');
            }

            Log::info('Status published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish status', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Find status records by PR number from DB.
     */
    public function findByProcurement(string $prNumber): array
    {
        return ProcurementStage::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderBy('entered_at')
            ->get()
            ->map(fn ($stage) => StatusData::fromBlockchainArray([
                'pr_number' => $stage->procurement->pr_number ?? '',
                'procurement_title' => $stage->procurement->title ?? '',
                'stage' => $stage->stage,
                'current_status' => $stage->status,
                'user_address' => $stage->user_address ?? '',
                'timestamp' => $stage->entered_at->toIso8601String(),
                'previous_status' => $stage->previous_status,
                'metadata' => $stage->metadata,
            ]))
            ->toArray();
    }

    /**
     * Get latest status for each PR from DB.
     */
    public function getLatestStatusByProcurement(int $limit = 100): array
    {
        return ProcurementStage::orderByDesc('entered_at')
            ->take($limit)
            ->get()
            ->map(fn ($stage) => StatusData::fromBlockchainArray([
                'pr_number' => $stage->procurement->pr_number ?? '',
                'procurement_title' => $stage->procurement->title ?? '',
                'stage' => $stage->stage,
                'current_status' => $stage->status,
                'user_address' => $stage->user_address ?? '',
                'timestamp' => $stage->entered_at->toIso8601String(),
                'previous_status' => $stage->previous_status,
                'metadata' => $stage->metadata,
            ]))
            ->toArray();
    }
}
