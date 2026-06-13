<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Services\Concerns\HashesData;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Directly syncs handler results to normalized DB tables.
 *
 * Avoids race conditions where blockchain reads return stale data before
 * MultiChain indexes new stream items. Reconciled later by syncPr/syncAll.
 */
class DirectDbSyncService
{
    use HashesData;

    public function syncInitiation(array $result, array $data, string $operation, string $prNumber, string $userAddress): int
    {
        $syncedCount = 0;
        $procurementData = $data['procurement_data'] ?? [];

        if (empty($procurementData)) {
            return 0;
        }

        try {
            $existing = Procurement::withTrashed()->where('pr_number', $prNumber)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
            } else {
                $userId = $procurementData['user_id'] ?? null;
                if (is_array($userId)) {
                    $userId = $userId['id'] ?? null;
                }

                $initiatedAt = now();
                $procurementAttributes = [
                    'pr_number' => $prNumber,
                    'app_reference' => $procurementData['app_reference'] ?? null,
                    'title' => $procurementData['title'] ?? '',
                    'description' => $procurementData['description'] ?? null,
                    'category' => $procurementData['category'] ?? 'goods',
                    'procurement_mode' => $procurementData['procurement_mode'] ?? 'competitive_bidding',
                    'office' => $procurementData['office'] ?? null,
                    'end_user' => $procurementData['end_user'] ?? null,
                    'fund_source' => $procurementData['funding_source'] ?? null,
                    'prepared_by' => $procurementData['prepared_by'] ?? null,
                    'abc_amount' => (float) ($procurementData['abc_amount'] ?? 0),
                    'current_stage' => 'procurement_initiation',
                    'current_status' => 'procurement_initiated',
                    'user_address' => $userAddress,
                    'user_id' => $userId !== null ? (string) $userId : null,
                    'initiated_at' => $initiatedAt,
                    'last_updated_at' => $initiatedAt,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => $initiatedAt,
                    'has_breach' => false,
                ];
                $procurementHash = $this->computeHash($this->extractFields($procurementAttributes, Procurement::getHashableFields()));

                Procurement::create([
                    ...$procurementAttributes,
                    'data_hash' => $procurementHash,
                    'blockchain_hash' => $procurementHash,
                ]);
                $syncedCount++;
            }

            $statusTxid = $result['transactions']['status']['status_txid'] ?? null;
            $procurement = Procurement::where('pr_number', $prNumber)->first();

            if ($procurement && $statusTxid) {
                $enteredAt = now();
                $stageAttributes = [
                    'procurement_id' => $procurement->id,
                    'stage' => 'procurement_initiation',
                    'status' => 'procurement_initiated',
                    'entered_at' => $enteredAt,
                    'user_address' => $userAddress,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => $enteredAt,
                    'has_breach' => false,
                ];
                $stageHash = $this->computeHash($this->extractFields($stageAttributes, ProcurementStage::getHashableFields()));

                ProcurementStage::updateOrCreate(
                    ['txid' => $statusTxid],
                    [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                );
                $syncedCount++;
            }
        } catch (Exception $e) {
            Log::error('DirectDbSyncService: initiation sync failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncedCount;
    }

    public function syncStageStatus(array $result, array $data, string $operation, string $prNumber, string $userAddress): int
    {
        $stage = $data['stage'] ?? $data['current_stage'] ?? $result['stage'] ?? null;
        $status = $data['current_status'] ?? $data['completion_status'] ?? $result['status'] ?? $result['current_status'] ?? null;
        $nextStage = $data['next_stage'] ?? $result['next_stage'] ?? null;
        $nextStageStatus = $data['next_stage_status'] ?? $result['next_stage_status'] ?? null;
        $statusTxid = $result['status_txid'] ?? null;

        if ($operation === 'mark_stage_complete') {
            if (($data['operation_variant'] ?? null) === 'initiation_complete') {
                $stage = $nextStage ?? $stage;
                $status = $nextStageStatus ?? $status;
            }
        }

        if (empty($stage) || empty($status)) {
            Log::debug("DirectDbSyncService[{$operation}]: skipped — no stage/status in result", [
                'result_keys' => array_keys($result),
            ]);

            return 0;
        }

        $syncedCount = 0;

        try {
            $procurement = Procurement::withTrashed()->where('pr_number', $prNumber)->first();

            if (! $procurement) {
                Log::warning("DirectDbSyncService[{$operation}]: skipped — procurement not found", [
                    'pr_number' => $prNumber,
                ]);

                return 0;
            }

            if ($procurement->trashed()) {
                $procurement->restore();
            }

            $previousStatus = $procurement->current_status;

            $effectiveStage = $nextStage ?? $stage;
            $effectiveStatus = $nextStage && $operation === 'mark_stage_complete'
                ? ($nextStageStatus ?? $status)
                : $status;

            $procurement->update([
                'current_stage' => $effectiveStage,
                'current_status' => $effectiveStatus,
                'previous_status' => $previousStatus,
                'last_updated_at' => now(),
            ]);

            $syncedCount++;

            if ($statusTxid) {
                $enteredAt = now();
                $stageAttributes = [
                    'procurement_id' => $procurement->id,
                    'stage' => $stage,
                    'status' => $status,
                    'previous_status' => $previousStatus,
                    'entered_at' => $enteredAt,
                    'user_address' => $userAddress,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => $enteredAt,
                    'has_breach' => false,
                ];
                $stageHash = $this->computeHash($this->extractFields($stageAttributes, ProcurementStage::getHashableFields()));

                ProcurementStage::updateOrCreate(
                    ['txid' => $statusTxid],
                    [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                );
                $syncedCount++;
            }

            if ($nextStage && $nextStage !== $stage) {
                $nextStatusTxid = $result['transition_txid'] ?? null;

                if ($nextStatusTxid) {
                    $enteredAt = now();
                    $stageAttributes = [
                        'procurement_id' => $procurement->id,
                        'stage' => $nextStage,
                        'status' => $effectiveStatus,
                        'previous_status' => $previousStatus,
                        'entered_at' => $enteredAt,
                        'user_address' => $userAddress,
                        'is_blockchain_verified' => true,
                        'last_verified_at' => $enteredAt,
                        'has_breach' => false,
                    ];
                    $stageHash = $this->computeHash($this->extractFields($stageAttributes, ProcurementStage::getHashableFields()));

                    ProcurementStage::updateOrCreate(
                        ['txid' => $nextStatusTxid],
                        [...$stageAttributes, 'data_hash' => $stageHash, 'blockchain_hash' => $stageHash]
                    );
                    $syncedCount++;
                }
            }

            // Schedule post-sync verification via BlockchainRecordSyncService
            try {
                $syncService = app(BlockchainRecordSyncService::class);
                $syncService->syncToMirror(
                    Stream::STATUS->value,
                    $prNumber,
                    $statusTxid ?? 'pending-verification',
                    $userAddress,
                    null,
                    ['pr_number' => $prNumber, 'stage' => $effectiveStage ?? $stage, 'current_status' => $effectiveStatus ?? $status],
                    true,
                );
            } catch (Exception $e) {
                Log::debug("DirectDbSyncService[{$operation}]: post-sync verification deferred", [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (Exception $e) {
            Log::error("DirectDbSyncService[{$operation}]: sync failed", [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncedCount;
    }

    private function extractFields(array $data, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $data[$field] ?? null;
        }

        return $result;
    }
}
