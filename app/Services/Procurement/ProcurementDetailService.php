<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementWorkflowConfig;
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

        $procurementDetails = Procurement::where('pr_number', $prNumber)->first();

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
    private function buildWorkflowInfo(?Procurement $procurementDetails, string $currentStageValue): ?array
    {
        if (! $procurementDetails) {
            return null;
        }

        $procurementModeRaw = $procurementDetails->procurement_mode;
        $procurementMode = ProcurementMode::tryFrom($procurementModeRaw);

        $workflowConfig = ProcurementWorkflowConfig::forMode($procurementModeRaw)->active()->first();

        $stages = $workflowConfig
            ? $workflowConfig->getStagesAsEnums()
            : ($procurementMode ? StageEnums::getStagesForMode($procurementMode) : []);

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
            'mode' => $procurementMode?->value,
            'name' => $procurementMode?->getDisplayName() ?? $procurementModeRaw,
            'stages' => $workflowStages,
        ];
    }

    /**
     * Build the procurement details array from the model.
     *
     * @return array<string, mixed>
     */
    private function buildDetailsArray(Procurement $details): array
    {
        return [
            'pr_number' => $details->pr_number,
            'app_reference' => $details->app_reference,
            'title' => $details->title,
            'description' => $details->description,
            'abc_amount' => $details->abc_amount,
            'abc_amount_formatted' => $details->getFormattedAbcAmount(),
            'funding_source' => $details->fund_source,
            'category' => $details->category,
            'category_label' => ProcurementCategory::tryFrom($details->category)?->label() ?? $details->category,
            'procurement_mode' => $details->procurement_mode,
            'procurement_mode_label' => ProcurementMode::tryFrom($details->procurement_mode)?->label() ?? $details->procurement_mode,
            'office' => $details->office,
            'end_user' => $details->end_user,
            'delivery_location' => $details->delivery_location,
            'delivery_date' => $details->delivery_date?->toIso8601String(),
            'delivery_date_formatted' => $details->getFormattedDeliveryDate(),
            'delivery_term_days' => $details->delivery_term_days,
            'prepared_by' => $details->prepared_by,
            'bac_resolution_number' => $details->bac_resolution_number,
            'bac_resolution_date' => $details->bac_resolution_date?->toIso8601String(),
            'bac_resolution_date_formatted' => $details->getFormattedBacResolutionDate(),
            'philgeps_reference' => $details->philgeps_reference,
            'philgeps_posting_date' => $details->philgeps_posting_date?->toIso8601String(),
            'philgeps_posting_date_formatted' => $details->getFormattedPhilgepsPostingDate(),
            'approved_by' => $details->approved_by,
            'approval_date' => $details->approval_date?->toIso8601String(),
            'approval_date_formatted' => $details->getFormattedApprovalDate(),
            'created_at' => ($details->initiated_at ?? $details->created_at)->toIso8601String(),
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
        $correctionsQuery = ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber));
        $hasCorrections = $correctionsQuery->exists();
        $latestCorrection = $hasCorrections ? $correctionsQuery->latest('corrected_at')->first() : null;
        $allCorrections = $hasCorrections ? $correctionsQuery->get() : collect();

        return [
            'has_corrections' => $hasCorrections,
            'latest_correction' => $latestCorrection ? [
                'timestamp' => $latestCorrection->corrected_at?->toIso8601String(),
                'corrected_by' => $latestCorrection->corrected_by,
                'reason' => $latestCorrection->reason,
                'changed_fields' => $latestCorrection->getChangedFields(),
            ] : null,
            'corrections' => $allCorrections->map(function ($correction) {
                return [
                    'pr_number' => $correction->procurement?->pr_number,
                    'timestamp' => $correction->corrected_at?->toIso8601String(),
                    'reason' => $correction->reason,
                    'corrected_by' => $correction->corrected_by,
                    'correction_type' => $correction->correction_type,
                    'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correction_type)),
                    'changed_fields' => $correction->getChangedFields(),
                    'metadata' => $correction->toBlockchainArray(),
                ];
            })->toArray(),
        ];
    }
}
