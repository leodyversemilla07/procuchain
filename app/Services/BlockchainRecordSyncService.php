<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Record Sync Service
 *
 * Syncs data FROM blockchain to normalized tables.
 * Blockchain is source of truth.
 *
 * - upstream(): syncs a published record to normalized tables
 * - downstream(): reads all items from a stream and syncs to normalized tables
 * - syncAll(): syncs all procurement streams
 */
class BlockchainRecordSyncService
{
    public function __construct(
        private NormalizedTableSyncService $normalizedSync,
        private BlockchainRpcClient $blockchainRpc,
    ) {}

    /**
     * Sync a newly published blockchain record to normalized tables.
     *
     * Called AFTER a successful blockchain publish.
     */
    public function syncToMirror(
        string $stream,
        string $key,
        string $txid,
        string $publisherAddress,
        ?int $blocktime,
        array $data,
        bool $isAuthorized = true,
    ): void {
        Log::info('BlockchainRecordSync: upstream sync', ['stream' => $stream, 'key' => $key]);

        // Fast path: sync only this PR's data instead of all streams
        $this->normalizedSync->syncPr($key);
    }

    /**
     * Sync all procurement streams from blockchain to normalized tables.
     */
    public function syncAll(): array
    {
        return $this->normalizedSync->syncAll();
    }

    /**
     * Repair records from blockchain.
     *
     * Repair is scoped to the requested PR. Full-table destructive cleanup is
     * handled by dedicated integrity jobs, not by a single-PR repair action.
     */
    public function repairFromChain(string $prNumber, ?string $stream = null): int
    {
        Log::info('BlockchainRecordSync: repairFromChain', ['pr_number' => $prNumber, 'stream' => $stream]);

        $deleted = $this->removeUnauthorizedDbRecordsForPr($prNumber, $stream);
        $counts = $this->normalizedSync->syncPr($prNumber);

        Log::info('BlockchainRecordSync: repairFromChain completed', [
            'pr_number' => $prNumber,
            'deleted' => $deleted,
            'synced' => $counts,
        ]);

        return array_sum($deleted) + array_sum($counts);
    }

    /**
     * Remove normalized DB records for one PR when their txids are absent from blockchain.
     *
     * @return array{procurements: int, stages: int, documents: int, events: int}
     */
    private function removeUnauthorizedDbRecordsForPr(string $prNumber, ?string $stream = null): array
    {
        $deleted = [
            'procurements' => 0,
            'stages' => 0,
            'documents' => 0,
            'events' => 0,
        ];

        $procurementIds = Procurement::withTrashed()
            ->where('pr_number', $prNumber)
            ->pluck('id');

        if ($procurementIds->isEmpty()) {
            return $deleted;
        }

        $hasMetadataOnChain = $this->hasBlockchainMetadataForPr($prNumber);
        if ($hasMetadataOnChain === null) {
            return $deleted;
        }

        if (! $hasMetadataOnChain && $this->shouldRepairStream($stream, Stream::METADATA)) {
            $deleted['documents'] += ProcurementDocument::withTrashed()
                ->whereIn('procurement_id', $procurementIds)
                ->forceDelete();
            $deleted['stages'] += ProcurementStage::whereIn('procurement_id', $procurementIds)->delete();
            $deleted['events'] += ProcurementEvent::whereIn('procurement_id', $procurementIds)->delete();
            $deleted['procurements'] = Procurement::withTrashed()
                ->where('pr_number', $prNumber)
                ->forceDelete();

            return $deleted;
        }

        if ($this->shouldRepairStream($stream, Stream::STATUS)) {
            $stageTxids = $this->getBlockchainTxidsForPr(Stream::STATUS, $prNumber);
            if ($stageTxids !== null) {
                $deleted['stages'] = ProcurementStage::whereNotNull('txid')
                    ->whereIn('procurement_id', $procurementIds)
                    ->whereNotIn('txid', $stageTxids)
                    ->delete();
            }
        }

        if ($this->shouldRepairStream($stream, Stream::DOCUMENTS)) {
            $documentTxids = $this->getBlockchainTxidsForPr(Stream::DOCUMENTS, $prNumber);
            if ($documentTxids !== null) {
                $deleted['documents'] = ProcurementDocument::withTrashed()
                    ->whereNotNull('txid')
                    ->whereIn('procurement_id', $procurementIds)
                    ->whereNotIn('txid', $documentTxids)
                    ->forceDelete();
            }
        }

        if ($this->shouldRepairStream($stream, Stream::EVENTS)) {
            $eventTxids = $this->getBlockchainTxidsForPr(Stream::EVENTS, $prNumber);
            if ($eventTxids !== null) {
                $deleted['events'] = ProcurementEvent::whereNotNull('txid')
                    ->whereIn('procurement_id', $procurementIds)
                    ->whereNotIn('txid', $eventTxids)
                    ->delete();
            }
        }

        return $deleted;
    }

    private function hasBlockchainMetadataForPr(string $prNumber): ?bool
    {
        try {
            $items = $this->blockchainRpc->liststreamkeyitems(Stream::METADATA->value, $prNumber, false, 10000);

            return collect(is_array($items) ? $items : [])
                ->contains(fn ($item) => ($item['data']['json']['pr_number'] ?? null) === $prNumber);
        } catch (\Throwable $e) {
            Log::warning('BlockchainRecordSync: failed to read blockchain PR metadata', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<string>|null
     */
    private function getBlockchainTxidsForPr(Stream $stream, string $prNumber): ?array
    {
        try {
            $items = $this->blockchainRpc->liststreamkeyitems($stream->value, $prNumber, false, 10000);

            return collect(is_array($items) ? $items : [])
                ->map(fn ($item) => $item['txid'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('BlockchainRecordSync: failed to read blockchain txids', [
                'stream' => $stream->value,
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function shouldRepairStream(?string $stream, Stream $target): bool
    {
        return $stream === null || $stream === $target->value;
    }
}
