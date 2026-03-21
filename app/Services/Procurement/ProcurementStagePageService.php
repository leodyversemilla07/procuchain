<?php

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Repositories\ProcurementRepository;
use App\Services\ModeAwareDocumentValidationService;

class ProcurementStagePageService
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ModeAwareDocumentValidationService $modeAwareDocumentValidationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildStagePageData(string $prNumber, StageEnums $stage): array
    {
        if (! $this->procurementSupport->stageExistsInWorkflow($prNumber, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }

        $procurement = $this->procurementSupport->findProcurementById($prNumber);

        if ($stage->isPostProcurement() && $procurement !== null) {
            $this->procurementSupport->handleAutoStageTransition($prNumber, $procurement, $stage);
        }

        $procurementData = $stage === StageEnums::NOTICE_TO_PROCEED
            ? $this->procurementRepository->findByProcurement($prNumber)
            : null;
        $mode = $this->procurementSupport->getProcurementMode($prNumber);

        return [
            'procurement' => [
                'pr_number' => $prNumber,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $stage->getDisplayName(),
                'stage_value' => $stage->value,
                'current_stage' => $procurement['stage'] ?? '',
                'delivery_location' => $procurementData?->deliveryLocation,
                'delivery_date' => $procurementData?->deliveryDate?->format('Y-m-d'),
                'delivery_date_formatted' => $procurementData?->getFormattedDeliveryDate(),
                'delivery_term_days' => $procurementData?->deliveryTermDays,
            ],
            'workflowInfo' => $this->procurementSupport->getWorkflowInfo($prNumber, $stage),
            'documentGuide' => $this->modeAwareDocumentValidationService->getStageDocumentGuide($stage, $mode),
            'uploadedDocuments' => $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocumentGuide(string $prNumber, StageEnums $stage): array
    {
        $this->procurementSupport->validateStageInWorkflow($prNumber, $stage);

        return $this->modeAwareDocumentValidationService->getStageDocumentGuide(
            $stage,
            $this->procurementSupport->getProcurementMode($prNumber),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompletionCheck(string $prNumber, StageEnums $stage): array
    {
        $this->procurementSupport->validateStageInWorkflow($prNumber, $stage);

        $uploadedDocumentEnums = array_values(array_filter(
            array_map(
                fn (string $documentType): ?DocumentTypeEnums => DocumentTypeEnums::tryFrom($documentType),
                $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage),
            ),
        ));

        return $this->modeAwareDocumentValidationService->validateStageCompletion(
            $stage,
            $uploadedDocumentEnums,
            $this->procurementSupport->getProcurementMode($prNumber),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function validateUpload(string $prNumber, StageEnums $stage, DocumentTypeEnums $documentType): array
    {
        $this->procurementSupport->validateStageInWorkflow($prNumber, $stage);

        $uploadedDocumentEnums = array_values(array_filter(
            array_map(
                fn (string $uploadedDocument): ?DocumentTypeEnums => DocumentTypeEnums::tryFrom($uploadedDocument),
                $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage),
            ),
        ));

        return $this->modeAwareDocumentValidationService->validateUpload(
            $stage,
            $documentType,
            $uploadedDocumentEnums,
            $this->procurementSupport->getProcurementMode($prNumber),
        );
    }
}
