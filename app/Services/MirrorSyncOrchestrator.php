<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates transaction-based mirror sync from blockchain results to normalized tables.
 */
class MirrorSyncOrchestrator
{
    public function __construct(
        private readonly BlockchainRecordSyncService $syncService,
    ) {}

    /**
     * Sync blockchain write results to normalized tables via transaction-based sync.
     *
     * @return int Number of entries synced
     */
    public function syncTransactionResults(array $transactions, string $prNumber, string $userAddress, string $operation): int
    {
        if (empty($transactions)) {
            return 0;
        }

        $syncService = $this->syncService;
        $syncedCount = 0;

        foreach ($transactions as $type => $txData) {
            try {
                $syncedCount += $this->syncTransactionEntry(
                    $syncService, $type, $txData, $prNumber, $userAddress, $operation
                );
            } catch (Exception $e) {
                Log::error("MirrorSyncOrchestrator[{$operation}]: sync failed for transaction type", [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $syncedCount;
    }

    private function syncTransactionEntry(
        BlockchainRecordSyncService $syncService,
        string $type,
        array $txData,
        string $prNumber,
        string $userAddress,
        string $operation,
    ): int {
        $stream = match ($type) {
            'metadata' => Stream::METADATA,
            'status' => Stream::STATUS,
            'event' => Stream::EVENTS,
            'documents' => Stream::DOCUMENTS,
            'correction' => Stream::CORRECTIONS,
            'procurement_correction' => Stream::PROCUREMENTS_CORRECTIONS,
            'decision' => Stream::EVENTS,
            'archive' => Stream::ARCHIVE,
            default => null,
        };

        if ($stream === null) {
            Log::debug("MirrorSyncOrchestrator[{$operation}]: skipping unknown transaction type", [
                'type' => $type,
            ]);

            return 0;
        }

        $txid = match ($type) {
            'status' => $txData['status_txid'] ?? '',
            'event' => $txData['event_txid'] ?? '',
            default => $txData['txid'] ?? '',
        };

        if (empty($txid)) {
            Log::debug("MirrorSyncOrchestrator[{$operation}]: skipping — missing txid", [
                'type' => $type,
            ]);

            return 0;
        }

        // Documents is an array of individual document entries
        if ($type === 'documents' && isset($txData[0]) && is_array($txData[0])) {
            $count = 0;
            foreach ($txData as $docEntry) {
                $docTxid = $docEntry['txid'] ?? '';
                if (empty($docTxid)) {
                    continue;
                }
                $syncService->syncToMirror($stream->value, $prNumber, $docTxid, $userAddress, null, $docEntry, true);
                $count++;
            }

            return $count;
        }

        $syncService->syncToMirror($stream->value, $prNumber, $txid, $userAddress, null, is_array($txData) ? $txData : [], true);

        return 1;
    }
}
