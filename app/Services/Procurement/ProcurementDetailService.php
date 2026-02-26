<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use Illuminate\Support\Facades\Log;

/**
 * Composes the full procurement detail view data.
 *
 * Extracted from ProcurementListController::show() to follow SRP.
 * Orchestrates fetching of status, documents, events, workflow info,
 * procurement details, and corrections into a single response payload.
 */
final class ProcurementDetailService
{
    public function __construct(
        private readonly ProcurementDataService $dataService,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementCorrectionRepository $correctionRepository,
    ) {}

    /**
     * Build the complete procurement detail payload for the show page.
     *
     * @return array{procurement: array<string, mixed>, workflow: array<string, mixed>|null}|null
     */
    public function getDetail(string $prNumber): ?array
    {
        $statusItems = $this->dataService->fetchStatusItems($prNumber);
        $currentStatus = $statusItems->first();

        if (! $currentStatus) {
            return null;
        }

        $documents = $this->dataService->fetchAndProcessAllDocuments($prNumber);
        $events = $this->dataService->fetchAndProcessEvents($prNumber);

        $this->dataService->preloadUserNames(collect($events));

        $procurementDetails = $this->procurementRepository->findByProcurement($prNumber);

        $procurementData = $this->dataService->buildProcurementData(
            $prNumber,
            $currentStatus,
            $documents,
            $events,
            $statusItems
        );

        $workflowInfo = $this->buildWorkflowInfo($procurementDetails, $currentStatus['stage']);

        if ($procurementDetails) {
            $procurementData['details'] = $this->buildDetailsArray($procurementDetails);
            $procurementData['details'] = array_merge(
                $procurementData['details'],
                $this->buildCorrectionsArray($prNumber),
            );
        }

        Log::debug('Current status data', [
            'current_status' => $currentStatus,
            'procurement_data_status' => $procurementData['status'] ?? null,
        ]);

        return [
            'procurement' => $procurementData,
            'workflow' => $workflowInfo,
        ];
    }

    /**
     * Build workflow visualization info from procurement details.
     *
     * @return array<string, mixed>|null
     */
    private function buildWorkflowInfo(?ProcurementData $procurementDetails, string $currentStageValue): ?array
    {
        if (! $procurementDetails) {
            return null;
        }

        $procurementMode = $procurementDetails->procurementMode;

        $workflowConfig = ProcurementWorkflowConfig::forMode($procurementMode)->active()->first();

        $stages = $workflowConfig
            ? $workflowConfig->getStagesAsEnums()
            : StageEnums::getStagesForMode($procurementMode);

        $stagesList = array_values($stages);
        $currentStageIndex = -1;
        foreach ($stagesList as $index => $stage) {
            if ($stage->value === $currentStageValue) {
                $currentStageIndex = $index;
                break;
            }
        }

        $workflowStages = collect($stagesList)->map(function ($stage, $index) use ($currentStageIndex, $currentStageValue, $workflowConfig) {
            return [
                'value' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'url' => '#',
                'is_completed' => $index < $currentStageIndex,
                'is_current' => $stage->value === $currentStageValue,
                'is_optional' => $workflowConfig ? $workflowConfig->isStageOptional($stage) : false,
            ];
        })->toArray();

        return [
            'mode' => $procurementMode->value,
            'name' => $procurementMode->getDisplayName(),
            'stages' => $workflowStages,
        ];
    }

    /**
     * Build the procurement details array from the DTO.
     *
     * @return array<string, mixed>
     */
    private function buildDetailsArray(ProcurementData $details): array
    {
        return [
            'pr_number' => $details->prNumber,
            'app_reference' => $details->appReference,
            'title' => $details->title,
            'description' => $details->description,
            'abc_amount' => $details->abcAmount,
            'abc_amount_formatted' => $details->getFormattedAbcAmount(),
            'funding_source' => $details->fundingSource,
            'category' => $details->category->value,
            'category_label' => $details->category->label(),
            'procurement_mode' => $details->procurementMode->value,
            'procurement_mode_label' => $details->procurementMode->label(),
            'office' => $details->office,
            'end_user' => $details->endUser,
            'delivery_location' => $details->deliveryLocation,
            'delivery_date' => $details->deliveryDate?->toIso8601String(),
            'delivery_date_formatted' => $details->getFormattedDeliveryDate(),
            'delivery_term_days' => $details->deliveryTermDays,
            'prepared_by' => $details->preparedBy,
            'bac_resolution_number' => $details->bacResolutionNumber,
            'bac_resolution_date' => $details->bacResolutionDate?->toIso8601String(),
            'bac_resolution_date_formatted' => $details->getFormattedBacResolutionDate(),
            'philgeps_reference' => $details->philgepsReference,
            'philgeps_posting_date' => $details->philgepsPostingDate?->toIso8601String(),
            'philgeps_posting_date_formatted' => $details->getFormattedPhilgepsPostingDate(),
            'approved_by' => $details->approvedBy,
            'approval_date' => $details->approvalDate?->toIso8601String(),
            'approval_date_formatted' => $details->getFormattedApprovalDate(),
            'created_at' => $details->createdAt->toIso8601String(),
            'created_at_formatted' => $details->getFormattedCreatedAt(),
        ];
    }

    /**
     * Build correction information for a procurement.
     *
     * @return array<string, mixed>
     */
    private function buildCorrectionsArray(string $prNumber): array
    {
        $hasCorrections = $this->correctionRepository->hasCorrections($prNumber);
        $latestCorrection = $hasCorrections ? $this->correctionRepository->getLatest($prNumber) : null;
        $allCorrections = $hasCorrections ? $this->correctionRepository->findByProcurement($prNumber) : [];

        return [
            'has_corrections' => $hasCorrections,
            'latest_correction' => $latestCorrection ? [
                'timestamp' => $latestCorrection->timestamp->toIso8601String(),
                'corrected_by' => $latestCorrection->correctedBy,
                'reason' => $latestCorrection->reason,
                'changed_fields' => $latestCorrection->getChangedFields(),
            ] : null,
            'corrections' => array_map(function ($correction) {
                return [
                    'pr_number' => $correction->prNumber,
                    'timestamp' => $correction->timestamp->toIso8601String(),
                    'reason' => $correction->reason,
                    'corrected_by' => $correction->correctedBy,
                    'correction_type' => $correction->correctionType,
                    'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
                    'changed_fields' => $correction->getChangedFields(),
                    'metadata' => $correction->toBlockchainArray(),
                ];
            }, $allCorrections),
        ];
    }
}
