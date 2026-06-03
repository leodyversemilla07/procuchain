<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
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
 *   // Dual-write on model create
 *   BlockchainSyncService::publish($model, StreamEnums::AUDIT_TRAIL);
 *
 *   // Recovery: rebuild MySQL from blockchain
 *   BlockchainSyncService::restoreTable('audit_logs', StreamEnums::AUDIT_TRAIL);
 */
class BlockchainSyncService
{
    /**
     * Publish a model's data to the blockchain and update its blockchain columns.
     *
     * Called after every write to a blockchain-backed table.
     * The model MUST have txid, data_hash, and blockchain_synced_at columns.
     *
     * @param  Model  $model  The Eloquent model to publish
     * @param  StreamEnums  $stream  The blockchain stream to write to
     * @param  string|null  $key  Stream key (defaults to model ID)
     * @return string|null The blockchain transaction ID
     */
    public static function publish(Model $model, StreamEnums $stream, ?string $key = null): ?string
    {
        try {
            $manager = app(Manager::class);

            $data = static::buildPayload($model);
            $dataHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $streamKey = $key ?? (string) $model->getKey();

            $result = $manager->publish(
                $stream->value,
                $streamKey,
                ['json' => $data],
            );

            if ($result === null || $result === false) {
                Log::error('BlockchainSync: publish failed', [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                    'stream' => $stream->value,
                ]);

                return null;
            }

            $txid = is_string($result) ? $result : ($result['txid'] ?? null);

            // Update the model's blockchain columns
            $model->updateQuietly([
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_synced_at' => now(),
            ]);

            Log::debug('BlockchainSync: published', [
                'model' => class_basename($model),
                'id' => $model->getKey(),
                'stream' => $stream->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('BlockchainSync: exception', [
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
     * @param  string  $tableName  The MySQL table name
     * @param  StreamEnums  $stream  The blockchain stream to read from
     * @param  string  $modelClass  The Eloquent model class
     * @param  callable|null  $mapData  Optional callback to transform chain data before insert
     * @return array{imported: int, skipped: int, errors: int}
     */
    public static function restoreTable(
        string $tableName,
        StreamEnums $stream,
        string $modelClass,
        ?callable $mapData = null,
    ): array {
        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems($stream->value, true, 100000);
        } catch (\Exception $e) {
            Log::error('BlockchainSync: failed to read stream', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }

        if (! is_array($items) || empty($items)) {
            Log::info('BlockchainSync: no items in stream', ['stream' => $stream->value]);

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

                // Check if already exists (dedup by txid)
                $existing = DB::table($tableName)->where('txid', $txid)->first();
                if ($existing) {
                    $skipped++;

                    continue;
                }

                // Transform data if callback provided
                $rowData = $mapData ? $mapData($data) : $data;

                // Add blockchain metadata
                $rowData['txid'] = $txid;
                $rowData['data_hash'] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $rowData['blockchain_synced_at'] = now();

                DB::table($tableName)->insert($rowData);
                $imported++;
            } catch (\Exception $e) {
                Log::error('BlockchainSync: failed to import item', [
                    'stream' => $stream->value,
                    'txid' => $item['txid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $errors++;
            }
        }

        Log::info('BlockchainSync: restore completed', [
            'stream' => $stream->value,
            'table' => $tableName,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Verify a model's data_hash against the blockchain.
     *
     * @return array{valid: bool, computed_hash: string, stored_hash: string|null}
     */
    public static function verify(Model $model, StreamEnums $stream): array
    {
        $computedHash = hash('sha256', json_encode(
            static::buildPayload($model),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return [
            'valid' => $computedHash === $model->data_hash,
            'computed_hash' => $computedHash,
            'stored_hash' => $model->data_hash,
        ];
    }

    /**
     * Build the JSON payload for blockchain from a model.
     * Excludes blockchain metadata columns (txid, data_hash, blockchain_synced_at).
     */
    private static function buildPayload(Model $model): array
    {
        $data = $model->toArray();

        // Remove blockchain metadata columns — they're not part of the data
        unset($data['txid'], $data['data_hash'], $data['blockchain_synced_at']);

        // Remove timestamps if they exist
        unset($data['created_at'], $data['updated_at']);

        return $data;
    }
}
