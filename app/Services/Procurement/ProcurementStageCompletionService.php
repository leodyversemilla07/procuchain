<?php

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
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
        // Convert uploaded document type strings to enums
        $uploadedDocumentStrings = $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage);
        $uploadedDocumentEnums = array_values(array_filter(
            array_map(
                fn (string $documentType): ?DocumentTypeEnums => DocumentTypeEnums::tryFrom($documentType),
                $uploadedDocumentStrings,
            ),
        ));

        // Use validateStageCompletion() which checks each specific required document type
        // instead of just comparing raw counts (which allows wrong doc types to pass)
        $mode = $this->procurementSupport->getProcurementMode($prNumber);
        $completionCheck = $this->modeAwareDocumentValidationService->validateStageCompletion(
            $stage,
            $uploadedDocumentEnums,
            $mode,
        );

        if (! $completionCheck['can_complete']) {
            $missingDisplayNames = array_map(function (string $docValue): string {
                $docEnum = DocumentTypeEnums::tryFrom($docValue);

                return $docEnum ? $docEnum->getDisplayName() : $docValue;
            }, $completionCheck['missing_documents']);

            return [
                'status' => 422,
                'data' => [
                    'error' => 'Cannot mark stage as complete. Missing required documents: '.implode(', ', $missingDisplayNames),
                    'missing_documents' => $completionCheck['missing_documents'],
                    'completion_percentage' => $completionCheck['completion_percentage'],
                ],
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
                'document_count' => count($uploadedDocumentEnums),
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
            'document_count' => count($uploadedDocumentEnums),
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
