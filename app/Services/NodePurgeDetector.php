<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Libraries\MultiChain\Client;
use App\Support\NodeClientFactory;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NodePurgeDetector
{
    private const PURGE_CHECK_STREAM = Stream::FILE_METADATA->value;

    public function __construct(
        private BlockchainRpcClient $multichain,
    ) {}

    public function checkPurgeStateFromPrimary(string $nodeId): array
    {
        $default = [
            'is_purged' => false,
            'was_explicitly_purged' => false,
            'partially_purged' => false,
            'unsubscribed_streams' => [],
            'purge_reason' => null,
            'purge_timestamp' => null,
            'connection_error' => false,
            'connection_error_message' => null,
        ];

        try {
            $purgeKey = 'node_'.$nodeId.'_full_purge';
            $purgeItems = $this->multichain->liststreamkeyitems(
                self::PURGE_CHECK_STREAM,
                $purgeKey,
                false,
                1,
                -1,
                false
            );

            if (! $this->multichain->success() || ! is_array($purgeItems) || count($purgeItems) === 0) {
                return $default;
            }

            $resyncKey = 'node_'.$nodeId.'_resync';
            $resyncItems = $this->multichain->liststreamkeyitems(
                self::PURGE_CHECK_STREAM,
                $resyncKey,
                false,
                1,
                -1,
                false
            );

            $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
            $resyncBlock = ($this->multichain->success() && is_array($resyncItems) && count($resyncItems) > 0)
                ? ($resyncItems[0]['blocktime'] ?? 0)
                : 0;

            $isPurged = $purgeBlock > $resyncBlock;

            if (! $isPurged) {
                return $default;
            }

            $purgeData = $purgeItems[0]['data']['json'] ?? [];

            return [
                'is_purged' => true,
                'was_explicitly_purged' => true,
                'partially_purged' => false,
                'unsubscribed_streams' => [],
                'purge_reason' => $purgeData['reason'] ?? 'Node data physically deleted (SSM purge)',
                'purge_timestamp' => $purgeBlock,
                'connection_error' => false,
                'connection_error_message' => null,
            ];
        } catch (Exception $e) {
            Log::warning("SharedLedger: Failed to check purge state from primary for node {$nodeId}", [
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    public function isNodePurged(string $nodeId, ?Client $client = null): bool
    {
        try {
            $purgeKey = 'node_'.$nodeId.'_full_purge';

            if ($client) {
                $purgeItems = $client->liststreamkeyitems(self::PURGE_CHECK_STREAM, $purgeKey, false, 1, -1, false);
                $success = $client->success();
            } else {
                $purgeItems = $this->multichain->liststreamkeyitems(self::PURGE_CHECK_STREAM, $purgeKey, false, 1, -1, false);
                $success = $this->multichain->success();
            }

            if ($success && is_array($purgeItems) && count($purgeItems) > 0) {
                $resyncKey = 'node_'.$nodeId.'_resync';

                if ($client) {
                    $resyncItems = $client->liststreamkeyitems(self::PURGE_CHECK_STREAM, $resyncKey, false, 1, -1, false);
                    $resyncSuccess = $client->success();
                } else {
                    $resyncItems = $this->multichain->liststreamkeyitems(self::PURGE_CHECK_STREAM, $resyncKey, false, 1, -1, false);
                    $resyncSuccess = $this->multichain->success();
                }

                if ($resyncSuccess && is_array($resyncItems) && count($resyncItems) > 0) {
                    $purgeBlock = $purgeItems[0]['blocktime'] ?? 0;
                    $resyncBlock = $resyncItems[0]['blocktime'] ?? 0;

                    return $purgeBlock > $resyncBlock;
                }

                return true;
            }
        } catch (Exception $e) {
            Log::warning("SharedLedger: Failed to check purge status for node {$nodeId}", [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function buildAvailableNodesList(): array
    {
        return Cache::remember('shared_ledger:available_nodes', 60, function () {
            return $this->buildAvailableNodesListUncached();
        });
    }

    public function buildAvailableNodesListUncached(): array
    {
        $purgeStates = [];
        foreach (NodeClientFactory::getNodes() as $node) {
            $purgeStates[$node['id']] = $this->checkPurgeStateFromPrimary($node['id'])['is_purged'];
        }

        return collect(NodeClientFactory::getNodes())->map(function ($node) use ($purgeStates) {
            return [
                'id' => $node['id'],
                'name' => $node['name'],
                'role' => $node['role'],
                'is_purged' => $purgeStates[$node['id']] ?? false,
            ];
        })->values()->toArray();
    }
}
