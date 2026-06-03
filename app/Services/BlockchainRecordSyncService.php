<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\ProcurementRecord;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Blockchain Mirror Sync Service
 *
 * Handles bidirectional synchronization between the MultiChain blockchain
 * and the procurement_records database table. Supports:
 *
 * - upstream(): mirrors data to the DB after a successful blockchain publish
 * - downstream(): reads all items from a stream on chain and upserts to mirror
 * - syncAll(): syncs all procurement streams from chain to mirror
 * - repairFromChain(): repairs a specific PR's mirror data from chain
 */
class BlockchainRecordSyncService
{
    /**
     * Mirror data to the database after a successful blockchain publish.
     *
     * Called AFTER a successful blockchain publish to reflect the on-chain
     * data in the procurement_records table.
     *
     * @param  string  $stream  The blockchain stream name
     * @param  string  $key  The stream key (e.g. PR number)
     * @param  string  $txid  The transaction ID from the publish
     * @param  string  $publisherAddress  The blockchain address of the publisher
     * @param  int|null  $blocktime  The block timestamp from the chain
     * @param  array  $data  The JSON payload that was published
     * @param  bool  $isAuthorized  Whether the publisher is an authorized user
     * @return ProcurementRecord The upserted mirror record
     */
    public function upstream(
        string $stream,
        string $key,
        string $txid,
        string $publisherAddress,
        ?int $blocktime,
        array $data,
        bool $isAuthorized = true,
    ): ProcurementRecord {
        $dataHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        Log::info('BlockchainRecordSync: upstream sync started', [
            'stream' => $stream,
            'key' => $key,
            'txid' => $txid,
            'publisher' => $publisherAddress,
            'data_hash' => $dataHash,
        ]);

        $existing = ProcurementRecord::where('stream', $stream)
            ->where('stream_key', $key)
            ->where('txid', $txid)
            ->first();

        // ─── Revision Tracking ──────────────────────────────────────────
        // For a new record (txid not yet in mirror), compute revision lineage:
        // - Find the previous latest revision for this stream+key
        // - revision_number = previous.revision_number + 1 (or 1 if root)
        // - parent_txid = previous.txid (or null if root)
        // - Demote the previous latest revision
        $revisionNumber = 1;
        $parentTxid = null;
        $isLatest = true;

        if ($existing === null) {
            $previousLatest = ProcurementRecord::where('stream', $stream)
                ->where('stream_key', $key)
                ->where('is_latest_revision', true)
                ->first();

            if ($previousLatest !== null) {
                $revisionNumber = $previousLatest->revision_number + 1;
                $parentTxid = $previousLatest->txid;

                // Demote previous latest — this record is now the latest
                $previousLatest->demoteAsLatest();
                $previousLatest->save();

                Log::info('BlockchainRecordSync: demoted previous latest revision', [
                    'stream' => $stream,
                    'key' => $key,
                    'previous_txid' => $previousLatest->txid,
                    'previous_revision' => $previousLatest->revision_number,
                    'new_revision' => $revisionNumber,
                ]);
            }
        } else {
            // Existing record: preserve its revision metadata
            $revisionNumber = $existing->revision_number;
            $parentTxid = $existing->parent_txid;
            $isLatest = $existing->is_latest_revision;
        }

        $mirror = ProcurementRecord::updateOrCreate(
            [
                'stream' => $stream,
                'stream_key' => $key,
                'txid' => $txid,
            ],
            [
                'revision_number' => $revisionNumber,
                'parent_txid' => $parentTxid,
                'is_latest_revision' => $isLatest,
                'data_json' => $data,
                'data_hash' => $dataHash,
                'publisher_address' => $publisherAddress,
                'blocktime' => $blocktime ? Carbon::createFromTimestamp($blocktime) : null,
                'is_authorized' => $isAuthorized,
                'synced_at' => Carbon::now(),
            ],
        );

        // If the existing row had a breach that is now resolved
        // (we're overwriting with confirmed chain data), mark it repaired
        if ($existing && $existing->isBreached()) {
            $mirror->markAsRepaired();

            Log::info('BlockchainRecordSync: breach resolved during upstream sync', [
                'stream' => $stream,
                'key' => $key,
                'txid' => $txid,
            ]);
        }

        // Notify if unauthorized publisher detected during upstream
        if (! $isAuthorized) {
            $this->notifyBreach(
                'unauthorized_publisher',
                $stream,
                $key,
                $txid,
                ['publisher_address' => $publisherAddress],
                $mirror->id,
            );
        }

        Log::info('BlockchainRecordSync: upstream sync completed', [
            'stream' => $stream,
            'key' => $key,
            'txid' => $txid,
            'mirror_id' => $mirror->id,
            'revision_number' => $revisionNumber,
            'parent_txid' => $parentTxid,
        ]);

        return $mirror;
    }

    /**
     * Read all items from a stream on chain and upsert to mirror.
     *
     * Called during blockchain:sync command to pull all items from a
     * given stream and mirror them to the procurement_records table.
     *
     * @param  string  $stream  The blockchain stream name
     * @param  callable|null  $progressCallback  Optional callback(count, total) for progress reporting
     * @return int Count of synced items
     */
    public function downstream(string $stream, ?callable $progressCallback = null): int
    {
        Log::info('BlockchainRecordSync: downstream sync started', ['stream' => $stream]);

        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems($stream, true, 10000);
        } catch (\Exception $e) {
            Log::error('BlockchainRecordSync: failed to list stream items', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        if (! is_array($items) || empty($items)) {
            Log::info('BlockchainRecordSync: no items found in stream', ['stream' => $stream]);

            return 0;
        }

        $total = count($items);
        $count = 0;

        Log::info('BlockchainRecordSync: processing stream items', [
            'stream' => $stream,
            'total_items' => $total,
        ]);

        foreach ($items as $item) {
            try {
                $key = $item['key'] ?? ($item['publishers'][0] ?? null);
                $txid = $item['txid'] ?? null;
                $publisher = $item['publishers'][0] ?? null;
                $blocktime = $item['blocktime'] ?? null;
                $data = $item['data']['json'] ?? [];

                if (! $key || ! $txid || ! $publisher) {
                    Log::warning('BlockchainRecordSync: skipping item with missing fields', [
                        'stream' => $stream,
                        'txid' => $txid,
                        'key' => $key,
                        'publisher' => $publisher,
                    ]);

                    continue;
                }

                $isAuthorized = User::where('blockchain_address', $publisher)
                    ->where('account_locked', false)
                    ->exists();

                $this->upstream(
                    $stream,
                    $key,
                    $txid,
                    $publisher,
                    $blocktime,
                    is_array($data) ? $data : [],
                    $isAuthorized,
                );

                $count++;
            } catch (\Exception $e) {
                Log::error('BlockchainRecordSync: error processing item', [
                    'stream' => $stream,
                    'txid' => $item['txid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($progressCallback !== null) {
                $progressCallback($count, $total);
            }
        }

        Log::info('BlockchainRecordSync: downstream sync completed', [
            'stream' => $stream,
            'synced_count' => $count,
            'total_items' => $total,
        ]);

        return $count;
    }

    /**
     * Sync all procurement streams from chain to mirror.
     *
     * Iterates through all StreamEnums cases that are procurement streams
     * and calls downstream() for each.
     *
     * @param  callable|null  $progressCallback  Optional callback(stream, count, total) for progress reporting
     * @return array<string, int> Array of [stream => synced_count]
     */
    public function syncAll(?callable $progressCallback = null): array
    {
        Log::info('BlockchainRecordSync: syncAll started');

        $results = [];

        foreach (StreamEnums::cases() as $case) {
            if (! $case->isProcurementStream()) {
                continue;
            }

            $stream = $case->value;

            Log::info('BlockchainRecordSync: syncing procurement stream', ['stream' => $stream]);

            $count = $this->downstream($stream);
            $results[$stream] = $count;

            if ($progressCallback !== null) {
                $progressCallback($stream, $count, count($results));
            }
        }

        Log::info('BlockchainRecordSync: syncAll completed', [
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Repair a specific PR's mirror data from the blockchain.
     *
     * Reads the chain data for a given PR number and upserts each item
     * to the mirror table, marking any existing breaches as repaired.
     *
     * @param  string  $prNumber  The PR number to repair
     * @param  string|null  $stream  If specified, only repair that stream. Otherwise repair all procurement streams.
     * @return int Count of repaired items
     */
    public function repairFromChain(string $prNumber, ?string $stream = null): int
    {
        Log::info('BlockchainRecordSync: repairFromChain started', [
            'pr_number' => $prNumber,
            'stream' => $stream,
        ]);

        $streams = [];

        if ($stream !== null) {
            $streams[] = $stream;
        } else {
            foreach (StreamEnums::cases() as $case) {
                if ($case->isProcurementStream()) {
                    $streams[] = $case->value;
                }
            }
        }

        $count = 0;

        foreach ($streams as $currentStream) {
            try {
                Log::info('BlockchainRecordSync: repairing stream for PR', [
                    'pr_number' => $prNumber,
                    'stream' => $currentStream,
                ]);

                $manager = app(Manager::class);
                $items = $manager->liststreamkeyitems($currentStream, $prNumber);
            } catch (\Exception $e) {
                Log::error('BlockchainRecordSync: failed to list stream key items for repair', [
                    'pr_number' => $prNumber,
                    'stream' => $currentStream,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! is_array($items) || empty($items)) {
                Log::info('BlockchainRecordSync: no items found for PR in stream', [
                    'pr_number' => $prNumber,
                    'stream' => $currentStream,
                ]);

                continue;
            }

            foreach ($items as $item) {
                try {
                    $key = $item['key'] ?? ($item['publishers'][0] ?? null);
                    $txid = $item['txid'] ?? null;
                    $publisher = $item['publishers'][0] ?? null;
                    $blocktime = $item['blocktime'] ?? null;
                    $data = $item['data']['json'] ?? [];

                    if (! $key || ! $txid || ! $publisher) {
                        Log::warning('BlockchainRecordSync: skipping repair item with missing fields', [
                            'pr_number' => $prNumber,
                            'stream' => $currentStream,
                            'txid' => $txid,
                        ]);

                        continue;
                    }

                    $isAuthorized = User::where('blockchain_address', $publisher)
                        ->where('account_locked', false)
                        ->exists();

                    $this->upstream(
                        $currentStream,
                        $key,
                        $txid,
                        $publisher,
                        $blocktime,
                        is_array($data) ? $data : [],
                        $isAuthorized,
                    );

                    // Mark any existing breaches as repaired for this item
                    $existingBreaches = ProcurementRecord::where('stream', $currentStream)
                        ->where('stream_key', $key)
                        ->where('txid', $txid)
                        ->whereNotNull('breach_detected_at')
                        ->whereNull('repaired_at')
                        ->get();

                    foreach ($existingBreaches as $breach) {
                        $breach->markAsRepaired();

                        Log::info('BlockchainRecordSync: breach repaired during repairFromChain', [
                            'pr_number' => $prNumber,
                            'stream' => $currentStream,
                            'txid' => $txid,
                            'mirror_id' => $breach->id,
                        ]);
                    }

                    $count++;
                } catch (\Exception $e) {
                    Log::error('BlockchainRecordSync: error repairing item', [
                        'pr_number' => $prNumber,
                        'stream' => $currentStream,
                        'txid' => $item['txid'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
        }

        Log::info('BlockchainRecordSync: repairFromChain completed', [
            'pr_number' => $prNumber,
            'stream' => $stream,
            'repaired_count' => $count,
        ]);

        return $count;
    }

    /**
     * Send breach notifications to relevant authorities.
     *
     * Notifies BAC Chairman, HOPE, and admins about detected integrity breaches.
     * Failures in notification are caught and logged — they MUST never block sync.
     *
     * @param  string  $breachType  The BreachTypeEnums value
     * @param  string  $stream  The blockchain stream
     * @param  string  $key  The stream key
     * @param  string  $txid  The transaction ID
     * @param  array  $breachData  Additional breach context
     * @param  int|null  $mirrorId  The mirror record ID
     */
    private function notifyBreach(
        string $breachType,
        string $stream,
        string $key,
        string $txid,
        array $breachData = [],
        ?int $mirrorId = null,
    ): void {
        try {
            $recipients = User::whereHas('roles', function ($query): void {
                $query->whereIn('name', ['bac_chairman', 'hope', 'admin']);
            })->get();

            if ($recipients->isEmpty()) {
                Log::warning('BlockchainRecordSync: no recipients for breach notification', [
                    'breach_type' => $breachType,
                    'stream' => $stream,
                    'key' => $key,
                ]);

                return;
            }

            Notification::send($recipients, new IntegrityBreachNotification(
                breachType: $breachType,
                stream: $stream,
                streamKey: $key,
                txid: $txid,
                breachData: $breachData,
                mirrorId: $mirrorId,
            ));

            Log::info('BlockchainRecordSync: breach notification sent', [
                'breach_type' => $breachType,
                'stream' => $stream,
                'key' => $key,
                'txid' => $txid,
                'recipient_count' => $recipients->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('BlockchainRecordSync: failed to send breach notification', [
                'breach_type' => $breachType,
                'stream' => $stream,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
