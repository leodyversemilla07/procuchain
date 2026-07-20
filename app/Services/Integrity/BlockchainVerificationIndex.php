<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
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

    /** @var array<string, string> */
    private array $failedStreams = [];

    public function __construct(private BlockchainRpcClient $blockchainRpcClient) {}

    /**
     * @param  iterable<Stream|string>  $streams
     */
    public function loadStreams(iterable $streams): void
    {
        foreach ($streams as $stream) {
            $this->loadStream($stream instanceof Stream ? $stream->value : $stream);
        }
    }

    public function loadStream(string $stream): void
    {
        if ($this->isLoaded($stream)) {
            return;
        }

        try {
            $items = $this->blockchainRpcClient->liststreamitems($stream, false, 10000);
            $items = is_array($items) ? $items : [];
        } catch (\Throwable $e) {
            Log::warning('BlockchainVerificationIndex: failed to load stream', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            $this->failedStreams[$stream] = $e->getMessage();
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

    public function reset(): void
    {
        $this->itemsByStream = [];
        $this->itemsByTxid = [];
        $this->itemsByPrNumber = [];
        $this->loadedStreams = [];
        $this->failedStreams = [];
    }

    public function isLoaded(string $stream): bool
    {
        return $this->loadedStreams[$stream] ?? false;
    }

    public function hasFailures(): bool
    {
        return $this->failedStreams !== [];
    }

    /**
     * @return array<string, string>
     */
    public function failedStreams(): array
    {
        return $this->failedStreams;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(string|Stream $stream): array
    {
        $streamName = $stream instanceof Stream ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByStream[$streamName] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemByTxid(string|Stream $stream, ?string $txid): ?array
    {
        if (! $txid) {
            return null;
        }

        $streamName = $stream instanceof Stream ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByTxid[$streamName][$txid] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonByTxid(string|Stream $stream, ?string $txid): ?array
    {
        $item = $this->itemByTxid($stream, $txid);
        $json = $item['data']['json'] ?? null;

        return is_array($json) ? $json : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsByPrNumber(string|Stream $stream, string $prNumber): array
    {
        $streamName = $stream instanceof Stream ? $stream->value : $stream;
        $this->loadStream($streamName);

        return $this->itemsByPrNumber[$streamName][$prNumber] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestJsonByPrNumber(string|Stream $stream, string $prNumber): ?array
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
    public function txids(string|Stream $stream): array
    {
        $streamName = $stream instanceof Stream ? $stream->value : $stream;
        $this->loadStream($streamName);

        return array_values(array_keys($this->itemsByTxid[$streamName] ?? []));
    }

    /**
     * @return list<string>
     */
    public function prNumbers(string|Stream $stream = Stream::METADATA): array
    {
        $streamName = $stream instanceof Stream ? $stream->value : $stream;
        $this->loadStream($streamName);

        return array_values(array_keys($this->itemsByPrNumber[$streamName] ?? []));
    }

    public function hasTxid(string|Stream $stream, ?string $txid): bool
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
