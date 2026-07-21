<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Illuminate\Database\Eloquent\Model;

class ChainRecordComparator
{
    public function __construct(
        private readonly BlockchainVerificationIndex $blockchainIndex,
        private readonly BlockchainPayloadProjector $payloadProjector,
        private readonly IntegrityComparator $comparator,
        private readonly BlockchainRpcClient $rpcClient,
    ) {}

    public function fetchChainData(string $stream, string $prNumber, ?string $txid): ?array
    {
        try {
            if ($this->blockchainIndex->isLoaded($stream)) {
                if ($txid) {
                    $json = $this->blockchainIndex->jsonByTxid($stream, $txid);
                    if ($json) {
                        return $json;
                    }
                } else {
                    $json = $this->blockchainIndex->latestJsonByPrNumber($stream, $prNumber);
                    if ($json) {
                        return $json;
                    }
                }
            }

            $items = $this->rpcClient->liststreamkeyitems($stream, $prNumber);
            $items = is_array($items) ? $items : [];

            if ($txid) {
                foreach ($items as $item) {
                    if (($item['txid'] ?? null) === $txid) {
                        $json = $item['data']['json'] ?? null;

                        return is_array($json) ? $json : null;
                    }
                }

                $this->blockchainIndex->loadStream($stream);

                return $this->blockchainIndex->jsonByTxid($stream, $txid);
            }

            if (empty($items)) {
                return null;
            }

            $latest = end($items);
            $json = is_array($latest) ? ($latest['data']['json'] ?? null) : null;

            return is_array($json) ? $json : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function procurementStatusDifferencesFromLatestStatusStream(Model $record, string $prNumber): array
    {
        $latestStatus = $this->latestStatusItemForPrNumber($prNumber);

        if (! $latestStatus) {
            return [];
        }

        $chainData = $latestStatus['data']['json'] ?? null;
        if (! is_array($chainData)) {
            return [];
        }

        $diffs = [];
        $chainStatus = $chainData['current_status'] ?? null;
        $chainStage = $chainData['stage'] ?? null;

        if ($chainStatus !== null && ! $this->valuesMatch($chainStatus, $record->current_status ?? null)) {
            $diffs[] = ['field' => 'current_status', 'old_value' => $chainStatus, 'new_value' => $record->current_status ?? null];
        }

        if ($chainStage !== null && ! $this->valuesMatch($chainStage, $record->current_stage ?? null)) {
            $diffs[] = ['field' => 'current_stage', 'old_value' => $chainStage, 'new_value' => $record->current_stage ?? null];
        }

        return $diffs;
    }

    public function supersededRevisionViolationData(Model $record, string $tableName, string $prNumber, Stream $stream): ?array
    {
        if ($this->recordReferencesLatestChainRevision($record, $tableName, $prNumber, $stream)) {
            return null;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        $latestTxid = $latest['txid'] ?? null;
        $recordTxid = $record->txid ?? null;
        $fieldDiffs = [[
            'field' => 'txid',
            'old_value' => $latestTxid,
            'new_value' => $recordTxid,
        ]];

        $recordTxidChainData = is_string($recordTxid) && $recordTxid !== ''
            ? $this->blockchainIndex->jsonByTxid($stream, $recordTxid)
            : null;
        $recordTxidPrNumber = $recordTxidChainData['pr_number'] ?? null;
        if (is_string($recordTxidPrNumber) && $recordTxidPrNumber !== '' && $recordTxidPrNumber !== $prNumber) {
            $fieldDiffs[] = [
                'field' => 'pr_number',
                'old_value' => $recordTxidPrNumber,
                'new_value' => $prNumber,
            ];
        }

        return [
            'fieldDiffs' => $fieldDiffs,
            'chainData' => is_array($latest['data']['json'] ?? null) ? $latest['data']['json'] : null,
        ];
    }

    public function recordReferencesLatestChainRevision(Model $record, string $tableName, string $prNumber, Stream $stream): bool
    {
        if ($tableName !== 'procurements') {
            return true;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        if (! is_array($latest)) {
            return true;
        }

        $latestTxid = $latest['txid'] ?? null;

        return ! is_string($latestTxid) || $latestTxid === '' || $latestTxid === ($record->txid ?? null);
    }

    public function computeProjectedDifferences(array $dbData, Model $record, string $tableName, array $chainData): array
    {
        return $this->comparator->diff(
            $dbData,
            $this->payloadProjector->projectForTable($chainData, $tableName, $record),
        );
    }

    private function latestStatusItemForPrNumber(string $prNumber): ?array
    {
        $items = $this->blockchainIndex->itemsByPrNumber(Stream::STATUS, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }

    private function valuesMatch(mixed $chainValue, mixed $dbValue): bool
    {
        if ($chainValue === $dbValue) {
            return true;
        }

        if (is_numeric($chainValue) && is_numeric($dbValue)) {
            return (float) $chainValue === (float) $dbValue;
        }

        return (string) $chainValue === (string) $dbValue;
    }

    private function latestChainItemForRecord(string $tableName, string $prNumber, Stream $stream): ?array
    {
        if ($tableName !== 'procurements') {
            return null;
        }

        $items = $this->blockchainIndex->itemsByPrNumber($stream, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }
}
