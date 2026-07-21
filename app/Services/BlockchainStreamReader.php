<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BlockchainStreamReader
{
    public function __construct(
        private readonly BlockchainRpcClient $blockchainRpcClient,
    ) {}

    public function getStreamItems(string $stream): array
    {
        try {
            $items = $this->blockchainRpcClient->liststreamitems($stream, false, 10000);

            return is_array($items) ? $items : [];
        } catch (\Exception $e) {
            Log::error('BlockchainStreamReader: failed to read stream', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getStreamItemsForKey(string $stream, string $key): array
    {
        try {
            $items = $this->blockchainRpcClient->liststreamkeyitems($stream, $key, false, 1000);

            return is_array($items) ? $items : [];
        } catch (\Exception $e) {
            Log::error('BlockchainStreamReader: failed to read stream key items', [
                'stream' => $stream,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
