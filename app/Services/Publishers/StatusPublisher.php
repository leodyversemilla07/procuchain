<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Contracts\StatusPublisherInterface;
use App\DataTransferObjects\StatusData;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\StatusRepository;
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
class StatusPublisher implements StatusPublisherInterface
{
    public function __construct(
        private StatusRepository $statuses
    ) {}

    /**
     * Publish a status update
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $stage  Stage identifier
     * @param  StatusEnums  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  StatusEnums|null  $previousStatus  Previous status
     * @param  array|null  $metadata  Additional metadata
     * @return array Status transaction information
     *
     * @throws Exception If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $currentStatus,
        string $userAddress,
        ?StatusEnums $previousStatus = null,
        ?array $metadata = null
    ): array {
        try {
            Log::info('StatusPublisher: Publishing status', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'current_status' => $currentStatus->value,
                'previous_status' => $previousStatus?->value,
            ]);

            $status = new StatusData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $stage->value,
                currentStatus: $currentStatus->value,
                userAddress: $userAddress,
                timestamp: now(),
                previousStatus: $previousStatus?->value,
                metadata: $metadata,
            );

            $txid = $this->statuses->create($status);

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
     * @param  StatusEnums  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  StatusEnums|null  $previousStatus  Previous status
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
        StatusEnums $currentStatus,
        string $userAddress,
        ?StatusEnums $previousStatus = null,
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
            currentStatus: StatusEnums::COMPLETED,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: array_merge($metadata ?? [], [
                'completion_timestamp' => now()->toIso8601String(),
            ]),
        );
    }
}
