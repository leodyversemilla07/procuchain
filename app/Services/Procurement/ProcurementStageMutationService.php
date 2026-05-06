<?php

namespace App\Services\Procurement;

use App\Enums\StageEnums;
use App\Jobs\BlockchainWriteJob;
use App\Models\User;
use Illuminate\Support\Str;

class ProcurementStageMutationService
{
    /**
     * @return array<string, mixed>
     */
    public function queueSkipStage(string $prNumber, StageEnums $stage, string $reason, User $user): array
    {
        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('skip_stage', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'reason' => $reason,
            'user_address' => $user->blockchain_address ?? $user->email,
        ], $jobId, $user->id);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueRepeatStage(string $prNumber, StageEnums $stage, string $reason, User $user): array
    {
        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('repeat_stage', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'reason' => $reason,
            'user_address' => $user->blockchain_address ?? $user->email,
        ], $jobId, $user->id);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueDecisionPublishing(
        string $decisionType,
        string $prNumber,
        string $procurementTitle,
        bool $wasHeld,
        User $user,
    ): array {
        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('publish_decision', [
            'decision_type' => $decisionType,
            'pr_number' => $prNumber,
            'procurement_title' => $procurementTitle,
            'was_held' => $wasHeld,
            'user_address' => $user->blockchain_address ?? $user->email,
        ], $jobId, $user->id);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
            'held' => $wasHeld,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueDeliveryDetails(
        string $prNumber,
        string $deliveryLocation,
        string $deliveryDate,
        int $deliveryTermDays,
        User $user,
    ): array {
        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('update_delivery_details', [
            'pr_number' => $prNumber,
            'delivery_location' => $deliveryLocation,
            'delivery_date' => $deliveryDate,
            'delivery_term_days' => $deliveryTermDays,
            'user_address' => $user->blockchain_address ?? $user->email,
        ], $jobId, $user->id);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
        ];
    }
}
