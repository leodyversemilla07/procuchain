<?php

namespace App\Services\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Jobs\BlockchainWriteJob;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ModeAwareDocumentValidationService;
use Illuminate\Support\Str;

class ProcurementStageCompletionService
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ModeAwareDocumentValidationService $modeAwareDocumentValidationService,
    ) {}

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function queueStageCompletion(string $prNumber, StageEnums $stage, User $user): array
    {
        $uploadedDocuments = $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage);
        $documentGuide = $this->modeAwareDocumentValidationService->getStageDocumentGuide(
            $stage,
            $this->procurementSupport->getProcurementMode($prNumber),
        );

        if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
            return [
                'status' => 422,
                'data' => ['error' => 'Cannot mark stage as complete. Please upload all required documents first.'],
            ];
        }

        $procurement = $this->procurementRepository->findByProcurement($prNumber);
        if ($procurement === null) {
            return [
                'status' => 404,
                'data' => ['error' => 'Procurement not found.'],
            ];
        }

        $nextStage = $this->procurementSupport->getNextStageForProcurement($prNumber, $stage);
        if ($stage === StageEnums::PROCUREMENT_INITIATION && $nextStage === null) {
            return [
                'status' => 422,
                'data' => ['error' => 'Unable to determine next stage for this procurement mode.'],
            ];
        }

        $jobId = Str::uuid()->toString();

        if ($stage === StageEnums::PROCUREMENT_INITIATION) {
            BlockchainWriteJob::dispatch('mark_stage_complete', [
                'operation_variant' => 'initiation_complete',
                'pr_number' => $prNumber,
                'procurement_title' => $procurement->title,
                'user_address' => $user->blockchain_address ?? $user->email,
                'current_stage' => $stage->value,
                'next_stage' => $nextStage?->value,
                'next_stage_status' => $nextStage ? $this->procurementSupport->getInitialStatusForStage($prNumber, $nextStage)->value : null,
                'document_count' => count($uploadedDocuments),
            ], $jobId, $user->id);

            return [
                'status' => 202,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'pending',
                    'next_stage' => $nextStage?->value,
                    'next_stage_name' => $nextStage?->getDisplayName(),
                ],
            ];
        }

        $previousStatus = StatusEnums::tryFrom($procurement->status);
        $completionStatus = $this->procurementSupport->getCompletionStatusForStage($stage);

        BlockchainWriteJob::dispatch('mark_stage_complete', [
            'pr_number' => $prNumber,
            'procurement_title' => $procurement->title,
            'user_address' => $user->blockchain_address ?? $user->email,
            'current_stage' => $stage->value,
            'completion_status' => $completionStatus->value,
            'previous_status' => $previousStatus?->value,
            'next_stage' => $nextStage?->value,
            'next_stage_status' => $nextStage ? $this->procurementSupport->getInitialStatusForStage($prNumber, $nextStage)->value : null,
            'procurement_mode' => $procurement->procurementMode->value,
            'document_count' => count($uploadedDocuments),
            'is_pre_procurement' => $stage->isPreProcurement(),
        ], $jobId, $user->id);

        return [
            'status' => 202,
            'data' => [
                'job_id' => $jobId,
                'status' => 'pending',
                'next_stage' => $nextStage?->value,
                'next_stage_name' => $nextStage?->getDisplayName(),
            ],
        ];
    }
}
