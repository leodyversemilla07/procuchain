<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementCorrectionData;
use App\Enums\Stream;
use App\Models\ProcurementMetadataCorrection;
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement metadata corrections
 * Reads from DB (mirror of blockchain).
 */
final readonly class ProcurementCorrectionRepository implements ProcurementCorrectionRepositoryInterface
{
    public function __construct(
        private BlockchainRpcClient $multichain
    ) {}

    /**
     * Create a new correction record (writes to blockchain)
     */
    public function create(ProcurementCorrectionData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                Stream::PROCUREMENTS_CORRECTIONS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain procurement correction publish did not return a transaction id.');
            }

            Log::info('Correction published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish correction', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Find corrections by PR number from DB.
     */
    public function findByProcurement(string $prNumber): array
    {
        return ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->with('procurement')
            ->orderByDesc('corrected_at')
            ->get()
            ->map(fn (ProcurementMetadataCorrection $correction) => $this->toData($correction))
            ->all();
    }

    /**
     * Get all corrections from DB.
     */
    public function all(): array
    {
        return ProcurementMetadataCorrection::with('procurement')
            ->orderByDesc('corrected_at')
            ->get()
            ->map(fn (ProcurementMetadataCorrection $correction) => $this->toData($correction))
            ->all();
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

        return $this->toData($correction);
    }

    private function toData(ProcurementMetadataCorrection $correction): ProcurementCorrectionData
    {
        return ProcurementCorrectionData::fromBlockchainArray([
            'pr_number' => $correction->procurement->pr_number ?? '',
            'procurement_title' => $correction->procurement->title ?? '',
            'correction_type' => $correction->correction_type,
            'reason' => $correction->reason,
            'corrected_by' => $correction->corrected_by,
            'user_address' => $correction->user_address ?? '',
            'timestamp' => $correction->corrected_at->toIso8601String(),
            'original_title' => $correction->original_title,
            'original_description' => $correction->original_description,
            'original_abc_amount' => $correction->original_abc_amount,
            'original_funding_source' => $correction->original_funding_source,
            'original_category' => $correction->original_category,
            'original_procurement_mode' => $correction->original_procurement_mode,
            'original_office' => $correction->original_office,
            'original_end_user' => $correction->original_end_user,
            'original_delivery_date' => $correction->original_delivery_date?->toIso8601String(),
            'original_bac_resolution_number' => $correction->original_bac_resolution_number,
            'original_bac_resolution_date' => $correction->original_bac_resolution_date?->toIso8601String(),
            'original_approved_by' => $correction->original_approved_by,
            'original_approval_date' => $correction->original_approval_date?->toIso8601String(),
            'corrected_title' => $correction->corrected_title,
            'corrected_description' => $correction->corrected_description,
            'corrected_abc_amount' => $correction->corrected_abc_amount,
            'corrected_funding_source' => $correction->corrected_funding_source,
            'corrected_category' => $correction->corrected_category,
            'corrected_procurement_mode' => $correction->corrected_procurement_mode,
            'corrected_office' => $correction->corrected_office,
            'corrected_end_user' => $correction->corrected_end_user,
            'corrected_delivery_date' => $correction->corrected_delivery_date?->toIso8601String(),
            'corrected_bac_resolution_number' => $correction->corrected_bac_resolution_number,
            'corrected_bac_resolution_date' => $correction->corrected_bac_resolution_date?->toIso8601String(),
            'corrected_approved_by' => $correction->corrected_approved_by,
            'corrected_approval_date' => $correction->corrected_approval_date?->toIso8601String(),
        ], $correction->txid ?? '');
    }
}
