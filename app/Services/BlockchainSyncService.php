<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Services\Concerns\HashesData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic Blockchain Sync Service
 *
 * Provides dual-write and recovery for ALL blockchain-backed tables.
 * Follows the pattern: MySQL = query cache, Blockchain = source of truth.
 *
 * Usage:
 *   $sync = app(BlockchainSyncService::class);
 *   $sync->publish($model, Stream::AUDIT_TRAIL);
 *   $sync->restoreTable('audit_logs', Stream::AUDIT_TRAIL, AuditLog::class);
 */
class BlockchainSyncService
{
    use HashesData;

    public function __construct(
        private readonly BlockchainRpcClient $rpc,
    ) {}

    /**
     * Publish a model's data to the blockchain and update its blockchain columns.
     *
     * Called after every write to a blockchain-backed table.
     * The model MUST have txid, data_hash, and blockchain_synced_at columns.
     *
     * @return string|null The blockchain transaction ID
     */
    public function publish(Model $model, Stream $stream, ?string $key = null): ?string
    {
        try {
            $data = $model->toArray();

            // Remove blockchain metadata columns — they're not part of the data
            unset($data['txid'], $data['data_hash'], $data['blockchain_synced_at']);
            unset($data['created_at'], $data['updated_at']);

            $dataHash = $this->computeHash($data);
            $streamKey = $key ?? (string) $model->getKey();

            $result = $this->rpc->publish(
                $stream->value,
                $streamKey,
                ['json' => $data],
            );

            if ($result === null || $result === false) {
                Log::error('BlockchainSyncService: publish failed', [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                    'stream' => $stream->value,
                ]);

                return null;
            }

            $txid = is_string($result) ? $result : ($result['txid'] ?? null);

            $model->updateQuietly([
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_synced_at' => now(),
            ]);

            Log::debug('BlockchainSyncService: published', [
                'model' => class_basename($model),
                'id' => $model->getKey(),
                'stream' => $stream->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('BlockchainSyncService: exception', [
                'model' => class_basename($model),
                'id' => $model->getKey(),
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Restore a MySQL table from blockchain data.
     *
     * Reads all items from the specified stream and upserts them
     * into the MySQL table. Used after MySQL destruction.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function restoreTable(
        string $tableName,
        Stream $stream,
        string $modelClass,
        ?callable $mapData = null,
    ): array {
        try {
            $items = $this->rpc->liststreamitems($stream->value, true, 100000);
        } catch (\Exception $e) {
            Log::error('BlockchainSyncService: failed to read stream', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }

        if (! is_array($items) || empty($items)) {
            Log::info('BlockchainSyncService: no items in stream', ['stream' => $stream->value]);

            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($items as $item) {
            try {
                $data = $item['data']['json'] ?? [];
                $txid = $item['txid'] ?? null;

                if (empty($data) || ! $txid) {
                    $skipped++;

                    continue;
                }

                $existing = DB::table($tableName)->where('txid', $txid)->first();
                if ($existing) {
                    $skipped++;

                    continue;
                }

                $rowData = $mapData ? $mapData($data) : $data;
                $rowData['txid'] = $txid;
                $rowData['data_hash'] = $this->computeHash($data);
                $rowData['blockchain_synced_at'] = now();

                DB::table($tableName)->insert($rowData);
                $imported++;
            } catch (\Exception $e) {
                Log::error('BlockchainSyncService: failed to import item', [
                    'stream' => $stream->value,
                    'txid' => $item['txid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $errors++;
            }
        }

        Log::info('BlockchainSyncService: restore completed', [
            'stream' => $stream->value,
            'table' => $tableName,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }
}
