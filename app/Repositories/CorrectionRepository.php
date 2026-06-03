<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CorrectionRepositoryInterface;
use App\DataTransferObjects\CorrectionData;
use App\Enums\StreamEnums;
use App\Models\ProcurementCorrection;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement corrections
 * Reads from DB (mirror of blockchain).
 */
final readonly class CorrectionRepository implements CorrectionRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new correction record (writes to blockchain)
     */
    public function create(CorrectionData $data): ?string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::CORRECTIONS->value,
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
     * Returns CorrectionData objects.
     */
    public function findByProcurement(string $prNumber): array
    {
        return ProcurementCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('corrected_at')
            ->get()
            ->map(fn ($c) => CorrectionData::fromBlockchainArray([
                'pr_number' => $c->procurement->pr_number ?? '',
                'procurement_title' => $c->procurement->title ?? '',
                'original_txid' => $c->original_txid,
                'original_document_hash' => $c->original_document_hash,
                'correction_type' => $c->correction_type,
                'action' => $c->action,
                'reason' => $c->reason,
                'corrected_by' => $c->corrected_by,
                'user_address' => $c->user_address ?? '',
                'timestamp' => $c->corrected_at->toIso8601String(),
            ], $c->txid ?? ''))
            ->toArray();
    }

    /**
     * Find corrections by original TXID from DB.
     */
    public function findByOriginalTxid(string $originalTxid): array
    {
        return ProcurementCorrection::where('original_txid', $originalTxid)
            ->orderByDesc('corrected_at')
            ->get()
            ->map(fn ($c) => CorrectionData::fromBlockchainArray([
                'pr_number' => $c->procurement->pr_number ?? '',
                'procurement_title' => $c->procurement->title ?? '',
                'original_txid' => $c->original_txid,
                'original_document_hash' => $c->original_document_hash,
                'correction_type' => $c->correction_type,
                'action' => $c->action,
                'reason' => $c->reason,
                'corrected_by' => $c->corrected_by,
                'user_address' => $c->user_address ?? '',
                'timestamp' => $c->corrected_at->toIso8601String(),
            ], $c->txid ?? ''))
            ->toArray();
    }

    /**
     * Get all corrections from DB.
     */
    public function all(): array
    {
        return ProcurementCorrection::orderByDesc('corrected_at')
            ->get()
            ->map(fn ($c) => CorrectionData::fromBlockchainArray([
                'pr_number' => $c->procurement->pr_number ?? '',
                'procurement_title' => $c->procurement->title ?? '',
                'original_txid' => $c->original_txid,
                'original_document_hash' => $c->original_document_hash,
                'correction_type' => $c->correction_type,
                'action' => $c->action,
                'reason' => $c->reason,
                'corrected_by' => $c->corrected_by,
                'user_address' => $c->user_address ?? '',
                'timestamp' => $c->corrected_at->toIso8601String(),
            ], $c->txid ?? ''))
            ->toArray();
    }

    /**
     * Get correction history for a PR from DB.
     */
    public function getHistory(string $prNumber): array
    {
        return $this->findByProcurement($prNumber);
    }
}
