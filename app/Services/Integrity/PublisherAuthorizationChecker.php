<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\BreachType;
use App\Services\BlockchainRpcClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PublisherAuthorizationChecker
{
    public function __construct(
        private readonly BlockchainRpcClient $rpcClient,
        private readonly IntegrityViolationRecorder $recorder,
    ) {}

    public function check(Model $record, string $tableName, string $prNumber, string $stream): bool
    {
        try {
            $txid = $record->txid;
            if (! $txid) {
                return false;
            }

            $txData = $this->rpcClient->getrawtransaction($txid, 1);
            if (! $txData || ! is_array($txData)) {
                return false;
            }

            $publishers = [];
            $dataSection = $txData['data'] ?? [];

            if (is_array($dataSection)) {
                foreach ($dataSection as $keyData) {
                    if (is_array($keyData) && isset($keyData['publishers'])) {
                        $publishers = array_merge($publishers, (array) $keyData['publishers']);
                    }
                }
            }

            $publishers = array_unique($publishers);

            if (empty($publishers)) {
                return false;
            }

            $authorizedAddress = $record->user_address ?? null;

            if (! $authorizedAddress) {
                return false;
            }

            foreach ($publishers as $publisher) {
                if ($publisher !== $authorizedAddress) {
                    $this->recorder->record(
                        type: BreachType::UNAUTHORIZED_PUBLISHER->value,
                        tableName: $tableName,
                        record: $record,
                        prNumber: $prNumber,
                        message: "Unauthorized publisher {$publisher} - expected {$authorizedAddress}",
                        chainData: ['publishers' => $publishers, 'authorized_address' => $authorizedAddress],
                    );

                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::debug('IntegrityVerification: publisher check failed', [
                'txid' => $record->txid,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
