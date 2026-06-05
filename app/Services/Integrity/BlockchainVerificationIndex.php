<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Per-run in-memory blockchain index for integrity verification.
 *
 * MultiChain RPC is the slow part of verification. This class loads each
 * stream once, then provides O(1) lookups by stream/txid and stream/PR key.
 */
class BlockchainVerificationIndex
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $itemsByStream = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $itemsByTxid = [];

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private array $itemsByPrNumber = [];

    /** @var array<string, bool> */
    private array $loadedStreams = [];

    public function __construct(private Manager $manager) {}

    /**
     * @param  iterable<StreamEnums|string>  $streams
     */
    public function loadStreams(iterable $streams): void
    {
        foreach ($streams as $stream) {
            $this->loadStream($stream instanceof StreamEnums ? $stream->value : $stream);
        }
    }

    public function loadStream(string $stream): void
    {
        if ($this->isLoaded($stream)) {
            return;
        }

        try {
            $items = $this->manager->liststreamitems($stream, false, 10000);
            $items = is_array($items) ? $items : [];
        } catch (\Throwable $e) {
            Log::warning('BlockchainVerificationIndex: failed to load stream', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            $items = [];
        }

        $this->itemsByStream[$stream] = [];
        $this->itemsByTxid[$stream] = [];
        $this->itemsByPrNumber[$stream] = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $this->itemsByStream[$stream][] = $item;

            $txid = $item['txid'] ?? null;
            if (is_string($txid) && $txid !== '') {
                $this->itemsByTxid[$stream][$txid] = $item;
            }

            $prNumber = $this->streamKey($item);
            if ($prNumber !== null) {
                $this->itemsByPrNumber[$stream][$prNumber] ??= [];
                $this->itemsByPrNumber[$stream][$prNumber][] = $item;
            }
        }

        $this->loadedStreams[$stream] = true;
    }

    public function isLoaded(string $stream): bool
    {
        return $this->loadedStreams[$stream] ?? false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(string|StreamEnums $stream): array
    {
        $streamName = $stream instanceof StreamEnums ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByStream[$streamName] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemByTxid(string|StreamEnums $stream, ?string $txid): ?array
    {
        if (! $txid) {
            return null;
        }

        $streamName = $stream instanceof StreamEnums ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByTxid[$streamName][$txid] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonByTxid(string|StreamEnums $stream, ?string $txid): ?array
    {
        $item = $this->itemByTxid($stream, $txid);
        $json = $item['data']['json'] ?? null;

        return is_array($json) ? $json : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsByPrNumber(string|StreamEnums $stream, string $prNumber): array
    {
        $streamName = $stream instanceof StreamEnums ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByPrNumber[$streamName][$prNumber] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestJsonByPrNumber(string|StreamEnums $stream, string $prNumber): ?array
    {
        $items = $this->itemsByPrNumber($stream, $prNumber);
        $latest = end($items);

        if (! is_array($latest)) {
            return null;
        }

        $json = $latest['data']['json'] ?? null;

        return is_array($json) ? $json : null;
    }

    /**
     * @return list<string>
     */
    public function txids(string|StreamEnums $stream): array
    {
        $streamName = $stream instanceof StreamEnums ? $stream->value : $stream;
        $this->loadStream($streamName);

        return array_values(array_keys($this->itemsByTxid[$streamName] ?? []));
    }

    /**
     * @return list<string>
     */
    public function prNumbers(string|StreamEnums $stream = StreamEnums::METADATA): array
    {
        $streamName = $stream instanceof StreamEnums ? $stream->value : $stream;
        $this->loadStream($streamName);

        return array_values(array_keys($this->itemsByPrNumber[$streamName] ?? []));
    }

    public function hasTxid(string|StreamEnums $stream, ?string $txid): bool
    {
        return $this->itemByTxid($stream, $txid) !== null;
    }

    private function streamKey(array $item): ?string
    {
        $json = $item['data']['json'] ?? [];

        if (is_array($json)) {
            foreach (['pr_number', 'file_key', 'stream_key'] as $key) {
                $value = $json[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $key = data_get($item, 'keys.0');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
