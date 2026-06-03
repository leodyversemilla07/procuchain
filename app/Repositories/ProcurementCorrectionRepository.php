<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementCorrectionData;
use App\Enums\StreamEnums;
use App\Models\ProcurementMetadataCorrection;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement metadata corrections
 * Reads from DB (mirror of blockchain).
 */
final readonly class ProcurementCorrectionRepository implements ProcurementCorrectionRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new correction record (writes to blockchain)
     */
    public function create(ProcurementCorrectionData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::PROCUREMENTS_CORRECTIONS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Correction published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish correction', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Find corrections by PR number from DB.
     */
    public function findByProcurement(string $prNumber): array
    {
        return ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('corrected_at')
            ->get()
            ->toArray();
    }

    /**
     * Get all corrections from DB.
     */
    public function all(): array
    {
        return ProcurementMetadataCorrection::orderByDesc('corrected_at')->get()->toArray();
    }

    /**
     * Get correction history for a PR from DB.
     */
    public function getHistory(string $prNumber): array
    {
        return $this->findByProcurement($prNumber);
    }

    /**
     * Check if a PR has corrections in DB.
     */
    public function hasCorrections(string $prNumber): bool
    {
        return ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->exists();
    }

    /**
     * Get the latest correction for a PR from DB.
     */
    public function getLatest(string $prNumber): ?ProcurementCorrectionData
    {
        $correction = ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('corrected_at')
            ->first();

        if (! $correction) {
            return null;
        }

        return ProcurementCorrectionData::fromBlockchainArray([
            'pr_number' => $correction->procurement->pr_number ?? '',
            'procurement_title' => $correction->procurement->title ?? '',
            'correction_type' => $correction->correction_type,
            'reason' => $correction->reason,
            'corrected_by' => $correction->corrected_by,
            'user_address' => $correction->user_address ?? '',
            'timestamp' => $correction->corrected_at->toIso8601String(),
            'original_title' => $correction->original_title,
            'corrected_title' => $correction->corrected_title,
        ], $correction->txid ?? '');
    }
}
