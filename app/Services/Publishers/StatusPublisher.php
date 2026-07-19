<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Enums\Stream;
use App\Models\ProcurementStage;
use App\Services\BlockchainRpcClient;
use App\Services\DashboardCacheService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Status Publisher Service
 *
 * Publishes status updates to the blockchain
 * - Handles stage transitions
 * - Publishes to procurement.status stream
 * - Validates status changes
 */
class StatusPublisher
{
    public function __construct(
        private readonly BlockchainRpcClient $rpcClient,
    ) {}

    /**
     * Publish a status update
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $stage  Stage identifier
     * @param  ProcurementStatus  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  ProcurementStatus|null  $previousStatus  Previous status
     * @param  array|null  $metadata  Additional metadata
     * @return array Status transaction information
     *
     * @throws Exception If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        ProcurementStatus $currentStatus,
        string $userAddress,
        ?ProcurementStatus $previousStatus = null,
        ?array $metadata = null
    ): array {
        try {
            Log::info('StatusPublisher: Publishing status', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'current_status' => $currentStatus->value,
                'previous_status' => $previousStatus?->value,
            ]);

            $status = new ProcurementStage;
            $status->stage = $stage->value;
            $status->status = $currentStatus->value;
            $status->user_address = $userAddress;
            $status->entered_at = now();
            $status->previous_status = $previousStatus?->value;
            $status->metadata = $metadata;

            $txid = $this->rpcClient->publish(
                Stream::STATUS->value,
                $prNumber,
                ['json' => $status->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new Exception('Blockchain status publish did not return a transaction id.');
            }

            // Invalidate ALL procurement list caches after status update
            // This includes versioned caches (v6), user-specific caches, and legacy caches
            $this->clearProcurementListCache();

            Log::info('StatusPublisher: Success', [
                'pr_number' => $prNumber,
                'status_txid' => $txid,
            ]);

            return [
                'success' => true,
                'status_txid' => $txid,
                'stage' => $stage->value,
                'current_status' => $currentStatus->value,
                'previous_status' => $previousStatus?->value,
            ];
        } catch (Exception $e) {
            Log::error('StatusPublisher: Failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish a stage transition (status + stage change)
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $fromStage  Previous stage
     * @param  StageEnums  $toStage  New stage
     * @param  ProcurementStatus  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  ProcurementStatus|null  $previousStatus  Previous status
     * @param  array|null  $metadata  Additional metadata
     * @return array Transition information
     *
     * @throws Exception If publication fails
     */
    public function publishTransition(
        string $prNumber,
        string $procurementTitle,
        StageEnums $fromStage,
        StageEnums $toStage,
        ProcurementStatus $currentStatus,
        string $userAddress,
        ?ProcurementStatus $previousStatus = null,
        ?array $metadata = null
    ): array {
        try {
            Log::info('StatusPublisher: Publishing transition', [
                'pr_number' => $prNumber,
                'from_stage' => $fromStage->value,
                'to_stage' => $toStage->value,
                'current_status' => $currentStatus->value,
            ]);

            $transitionMetadata = array_merge($metadata ?? [], [
                'transition' => true,
                'from_stage' => $fromStage->value,
                'to_stage' => $toStage->value,
                'transition_timestamp' => now()->toIso8601String(),
            ]);

            return $this->publish(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $toStage,
                currentStatus: $currentStatus,
                userAddress: $userAddress,
                previousStatus: $previousStatus,
                metadata: $transitionMetadata,
            );
        } catch (Exception $e) {
            Log::error('StatusPublisher: Transition failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish a completion status for a stage
     */
    public function publishCompletion(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        string $userAddress,
        ?array $metadata = null
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $stage,
            currentStatus: ProcurementStatus::COMPLETED,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: array_merge($metadata ?? [], [
                'completion_timestamp' => now()->toIso8601String(),
            ]),
        );
    }

    /**
     * Clear all procurement-related caches
     * Called after status updates to show fresh data
     */
    private function clearProcurementListCache(): void
    {
        DashboardCacheService::clearAllProcurementCaches();

        Log::info('Cleared all procurement caches after status update');
    }
}
