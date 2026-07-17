<?php

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\NormalizedTableSyncService;
use Illuminate\Support\Facades\Log;

class ProcurementStagePageService
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ModeAwareDocumentValidationService $modeAwareDocumentValidationService,
        private readonly NormalizedTableSyncService $normalizedTableSyncService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildStagePageData(string $prNumber, StageEnums $stage): array
    {
        if (! $this->procurementSupport->stageExistsInWorkflow($prNumber, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }

        $this->syncFromBlockchain($prNumber);

        $procurement = $this->procurementSupport->findProcurementById($prNumber);

        if ($stage->isPostProcurement() && $procurement !== null) {
            $this->procurementSupport->handleAutoStageTransition($prNumber, $procurement, $stage);
        }

        $procurementData = $stage === StageEnums::NOTICE_TO_PROCEED
            ? Procurement::where('pr_number', $prNumber)->first()
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
                'delivery_location' => $procurementData?->delivery_location,
                'delivery_date' => $procurementData?->delivery_date?->format('Y-m-d'),
                'delivery_date_formatted' => $procurementData?->getFormattedDeliveryDate(),
                'delivery_term_days' => $procurementData?->delivery_term_days,
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

    private function syncFromBlockchain(string $prNumber): void
    {
        try {
            $this->normalizedTableSyncService->syncPr($prNumber);
        } catch (\Throwable $e) {
            Log::warning('Stage page blockchain sync failed; using current DB mirror', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
