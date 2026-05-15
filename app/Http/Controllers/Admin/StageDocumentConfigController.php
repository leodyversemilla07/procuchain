<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Controller;
use App\Models\StageDocumentConfig;
use App\Services\AuditLogger;
use App\Services\ProcurementWorkflowService;
use App\Services\StageDocumentConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StageDocumentConfigController extends Controller
{
    public function __construct(
        private readonly StageDocumentConfigService $documentConfigService,
        private readonly ProcurementWorkflowService $workflowService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of stage document configurations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('manage-stage-document-config');

        $selectedMode = $request->query('mode', ProcurementModeEnums::COMPETITIVE_BIDDING->value);
        $modeEnum = ProcurementModeEnums::tryFrom($selectedMode) ?? ProcurementModeEnums::COMPETITIVE_BIDDING;

        // Get stages for the selected mode from workflow service
        $workflowStages = $this->workflowService->getStagesForMode($modeEnum);

        $configs = [];
        foreach ($workflowStages as $stage) {
            $dbConfig = StageDocumentConfig::forStage($stage)->forMode($modeEnum)->first();
            $counts = $this->documentConfigService->getDocumentCounts($stage, $modeEnum);

            $configs[] = [
                'stage' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'description' => $stage->getDescription(),
                'phase' => $stage->getPhase(),
                'phase_display_name' => $stage->getPhaseDisplayName(),
                'required_count' => $counts['required_count'],
                'optional_count' => $counts['optional_count'],
                'total_count' => $counts['total_count'],
                'is_customized' => $dbConfig !== null,
                'updated_at' => $dbConfig?->updated_at?->toISOString(),
                'updated_by' => $dbConfig?->updatedByUser?->name,
            ];
        }

        // Group by phase
        $preProcurement = array_filter($configs, fn ($c) => $c['phase'] === 'pre_procurement');
        $procurement = array_filter($configs, fn ($c) => $c['phase'] === 'procurement');
        $postProcurement = array_filter($configs, fn ($c) => $c['phase'] === 'post_procurement');

        // Get all modes for dropdown
        $modes = array_map(fn (ProcurementModeEnums $m) => [
            'value' => $m->value,
            'display_name' => $m->getDisplayName(),
            'is_alternative' => $m->isAlternativeMode(),
        ], ProcurementModeEnums::cases());

        return Inertia::render('admin/stage-document-configs', [
            'selectedMode' => $modeEnum->value,
            'selectedModeDisplayName' => $modeEnum->getDisplayName(),
            'modes' => $modes,
            'preProcurement' => array_values($preProcurement),
            'procurement' => array_values($procurement),
            'postProcurement' => array_values($postProcurement),
        ]);
    }

    /**
     * Show the form for editing a stage document configuration.
     */
    public function edit(Request $request, string|ProcurementModeEnums $mode, string|StageEnums $stage): Response
    {
        $this->authorize('manage-stage-document-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);
        $stageEnum = $stage instanceof StageEnums ? $stage : StageEnums::tryFrom($stage);

        if (! $modeEnum || ! $stageEnum) {
            abort(404, 'Invalid mode or stage');
        }

        $currentRequired = $this->documentConfigService->getRequiredDocuments($stageEnum, $modeEnum);
        $currentOptional = $this->documentConfigService->getOptionalDocuments($stageEnum, $modeEnum);

        // Get all available document types
        $allDocuments = $this->documentConfigService->getAllDocumentTypes();

        // Get default documents for comparison (from ModeAwareDocumentRequirements)
        $guide = $this->documentConfigService->getStageDocumentGuide($stageEnum, $modeEnum);

        return Inertia::render('admin/stage-document-config-edit', [
            'mode' => [
                'value' => $modeEnum->value,
                'display_name' => $modeEnum->getDisplayName(),
            ],
            'stage' => [
                'value' => $stageEnum->value,
                'display_name' => $stageEnum->getDisplayName(),
                'description' => $stageEnum->getDescription(),
                'phase' => $stageEnum->getPhase(),
                'phase_display_name' => $stageEnum->getPhaseDisplayName(),
            ],
            'currentRequiredDocuments' => array_map(fn (DocumentTypeEnums $d) => $d->value, $currentRequired),
            'currentOptionalDocuments' => array_map(fn (DocumentTypeEnums $d) => $d->value, $currentOptional),
            'allDocuments' => $allDocuments,
        ]);
    }

    /**
     * Update the stage document configuration.
     */
    public function update(Request $request, string|ProcurementModeEnums $mode, string|StageEnums $stage): RedirectResponse
    {
        $this->authorize('manage-stage-document-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);
        $stageEnum = $stage instanceof StageEnums ? $stage : StageEnums::tryFrom($stage);

        if (! $modeEnum || ! $stageEnum) {
            abort(404, 'Invalid mode or stage');
        }

        $validated = $request->validate([
            'required_documents' => 'nullable|array',
            'required_documents.*' => 'string',
            'optional_documents' => 'nullable|array',
            'optional_documents.*' => 'string',
        ]);

        // Validate that all document values are valid enum values
        $requiredDocuments = [];
        foreach ($validated['required_documents'] ?? [] as $docValue) {
            $docEnum = DocumentTypeEnums::tryFrom($docValue);
            if (! $docEnum) {
                return back()->withErrors(['required_documents' => "Invalid document type: {$docValue}"]);
            }
            $requiredDocuments[] = $docEnum;
        }

        $optionalDocuments = [];
        foreach ($validated['optional_documents'] ?? [] as $docValue) {
            $docEnum = DocumentTypeEnums::tryFrom($docValue);
            if (! $docEnum) {
                return back()->withErrors(['optional_documents' => "Invalid document type: {$docValue}"]);
            }
            // Ensure no overlap between required and optional
            if (in_array($docEnum, $requiredDocuments, true)) {
                return back()->withErrors(['optional_documents' => "Document {$docValue} cannot be both required and optional"]);
            }
            $optionalDocuments[] = $docEnum;
        }

        $this->documentConfigService->saveDocumentConfig(
            $stageEnum,
            $modeEnum,
            $requiredDocuments,
            $optionalDocuments,
            $request->user()->id
        );

        $this->auditLogger->log(
            'admin.stage_document_config_updated',
            'stage_document_config',
            "{$modeEnum->value}:{$stageEnum->value}",
            [],
            ['required_count' => count($requiredDocuments), 'optional_count' => count($optionalDocuments)],
        );

        return redirect()
            ->route('admin.stage-documents.index', ['mode' => $modeEnum->value])
            ->with('success', "Document configuration for {$stageEnum->getDisplayName()} updated successfully.");
    }

    /**
     * Reset stage document configuration to defaults.
     */
    public function resetToDefaults(Request $request, string|ProcurementModeEnums $mode, string|StageEnums $stage): RedirectResponse
    {
        $this->authorize('manage-stage-document-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);
        $stageEnum = $stage instanceof StageEnums ? $stage : StageEnums::tryFrom($stage);

        if (! $modeEnum || ! $stageEnum) {
            abort(404, 'Invalid mode or stage');
        }

        $this->documentConfigService->resetToDefaults($stageEnum, $modeEnum, $request->user()->id);

        $this->auditLogger->log(
            'admin.stage_document_config_reset',
            'stage_document_config',
            "{$modeEnum->value}:{$stageEnum->value}",
        );

        return redirect()
            ->route('admin.stage-documents.index', ['mode' => $modeEnum->value])
            ->with('success', "Document configuration for {$stageEnum->getDisplayName()} reset to defaults.");
    }
}
