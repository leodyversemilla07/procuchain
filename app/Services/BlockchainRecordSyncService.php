<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
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
    ) {}

    /**
     * Sync a newly published blockchain record to normalized tables.
     *
     * Called AFTER a successful blockchain publish.
     */
    public function upstream(
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
     * This is intentionally destructive for DB-only records. Blockchain is the
     * source of truth; any normalized DB row whose PR/txid does not exist on
     * chain is an unauthorized injection and must be removed.
     */
    public function repairFromChain(string $prNumber, ?string $stream = null): int
    {
        Log::info('BlockchainRecordSync: repairFromChain', ['pr_number' => $prNumber, 'stream' => $stream]);

        $deleted = $this->removeUnauthorizedDbRecords();
        $counts = $this->normalizedSync->syncAll();

        Log::info('BlockchainRecordSync: repairFromChain completed', [
            'pr_number' => $prNumber,
            'deleted' => $deleted,
            'synced' => $counts,
        ]);

        return array_sum($deleted) + array_sum($counts);
    }

    /**
     * Remove normalized DB records that are absent from blockchain.
     *
     * @return array{procurements: int, stages: int, documents: int, events: int}
     */
    private function removeUnauthorizedDbRecords(): array
    {
        $blockchainPrNumbers = $this->getBlockchainPrNumbers();
        $stageTxids = $this->getBlockchainTxids(StreamEnums::STATUS);
        $documentTxids = $this->getBlockchainTxids(StreamEnums::DOCUMENTS);
        $eventTxids = $this->getBlockchainTxids(StreamEnums::EVENTS);

        $deleted = [
            'procurements' => 0,
            'stages' => 0,
            'documents' => 0,
            'events' => 0,
        ];

        if (! empty($blockchainPrNumbers)) {
            $deleted['procurements'] = Procurement::withTrashed()
                ->whereNotIn('pr_number', $blockchainPrNumbers)
                ->forceDelete();
        }

        if (! empty($stageTxids)) {
            $deleted['stages'] = ProcurementStage::whereNotNull('txid')
                ->whereNotIn('txid', $stageTxids)
                ->delete();
        }

        if (! empty($documentTxids)) {
            $deleted['documents'] = ProcurementDocument::withTrashed()
                ->whereNotNull('txid')
                ->whereNotIn('txid', $documentTxids)
                ->forceDelete();
        }

        if (! empty($eventTxids)) {
            $deleted['events'] = ProcurementEvent::whereNotNull('txid')
                ->whereNotIn('txid', $eventTxids)
                ->delete();
        }

        ProcurementStage::whereDoesntHave('procurement')->delete();
        ProcurementDocument::withTrashed()->whereDoesntHave('procurement')->forceDelete();
        ProcurementEvent::whereDoesntHave('procurement')->delete();

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function getBlockchainPrNumbers(): array
    {
        try {
            $items = app(Manager::class)->liststreamitems(StreamEnums::METADATA->value, false, 10000);

            return collect(is_array($items) ? $items : [])
                ->map(fn ($item) => $item['data']['json']['pr_number'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('BlockchainRecordSync: failed to read blockchain PR numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function getBlockchainTxids(StreamEnums $stream): array
    {
        try {
            $items = app(Manager::class)->liststreamitems($stream->value, false, 10000);

            return collect(is_array($items) ? $items : [])
                ->map(fn ($item) => $item['txid'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('BlockchainRecordSync: failed to read blockchain txids', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
