<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Controller;
use App\Models\ProcurementWorkflowConfig;
use App\Services\AuditLogger;
use App\Services\ProcurementWorkflowService;
use App\Services\StageDocumentConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementWorkflowConfigController extends Controller
{
    public function __construct(
        private readonly ProcurementWorkflowService $workflowService,
        private readonly StageDocumentConfigService $documentConfigService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of workflow configurations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('manage-workflow-config');

        $configs = [];

        foreach (ProcurementModeEnums::cases() as $mode) {
            $dbConfig = ProcurementWorkflowConfig::forMode($mode)->first();
            $workflowConfig = $this->workflowService->getWorkflowConfig($mode);

            $configs[] = [
                'mode' => $mode->value,
                'display_name' => $mode->getDisplayName(),
                'description' => $mode->getDescription(),
                'irr_section' => $mode->getIrrSection(),
                'is_alternative_mode' => $mode->isAlternativeMode(),
                'stage_count' => $workflowConfig['stage_count'],
                'optional_stage_count' => $workflowConfig['optional_stage_count'],
                'required_stage_count' => $workflowConfig['required_stage_count'],
                'is_customized' => $dbConfig !== null,
                'updated_at' => $dbConfig?->updated_at?->toISOString(),
                'updated_by' => $dbConfig?->updatedByUser?->name,
            ];
        }

        // Group by competitive vs alternative modes
        $competitiveModes = array_filter($configs, fn ($c) => ! $c['is_alternative_mode']);
        $alternativeModes = array_filter($configs, fn ($c) => $c['is_alternative_mode']);

        return Inertia::render('admin/workflow-configs', [
            'competitiveModes' => array_values($competitiveModes),
            'alternativeModes' => array_values($alternativeModes),
        ]);
    }

    /**
     * Show the form for editing a workflow configuration.
     */
    public function edit(Request $request, string|ProcurementModeEnums $mode): Response
    {
        $this->authorize('manage-workflow-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);

        if (! $modeEnum) {
            abort(404, 'Invalid procurement mode');
        }

        $workflowConfig = $this->workflowService->getWorkflowConfig($modeEnum);
        $currentStages = $this->workflowService->getStagesForMode($modeEnum);
        $currentOptional = $this->workflowService->getOptionalStagesForMode($modeEnum);

        // Get all available stages
        $allStages = array_map(fn (StageEnums $stage) => [
            'value' => $stage->value,
            'display_name' => $stage->getDisplayName(),
            'description' => $stage->getDescription(),
            'phase' => $stage->getPhase(),
            'phase_display_name' => $stage->getPhaseDisplayName(),
        ], StageEnums::cases());

        // Get default stages for comparison
        $defaultStages = StageEnums::getStagesForMode($modeEnum);
        $defaultOptional = StageEnums::getOptionalStagesForMode($modeEnum);

        return Inertia::render('admin/workflow-config-edit', [
            'mode' => [
                'value' => $modeEnum->value,
                'display_name' => $modeEnum->getDisplayName(),
                'description' => $modeEnum->getDescription(),
                'irr_section' => $modeEnum->getIrrSection(),
            ],
            'currentStages' => array_map(fn (StageEnums $s) => $s->value, $currentStages),
            'currentOptionalStages' => array_map(fn (StageEnums $s) => $s->value, $currentOptional),
            'defaultStages' => array_map(fn (StageEnums $s) => $s->value, $defaultStages),
            'defaultOptionalStages' => array_map(fn (StageEnums $s) => $s->value, $defaultOptional),
            'allStages' => $allStages,
        ]);
    }

    /**
     * Update the workflow configuration.
     */
    public function update(Request $request, string|ProcurementModeEnums $mode): RedirectResponse
    {
        $this->authorize('manage-workflow-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);

        if (! $modeEnum) {
            abort(404, 'Invalid procurement mode');
        }

        $validated = $request->validate([
            'stages' => 'required|array|min:1',
            'stages.*' => 'required|string',
            'optional_stages' => 'nullable|array',
            'optional_stages.*' => 'string',
        ]);

        // Validate that all stage values are valid enum values
        $stages = [];
        foreach ($validated['stages'] as $stageValue) {
            $stageEnum = StageEnums::tryFrom($stageValue);
            if (! $stageEnum) {
                return back()->withErrors(['stages' => "Invalid stage: {$stageValue}"]);
            }
            $stages[] = $stageEnum;
        }

        $optionalStages = [];
        foreach ($validated['optional_stages'] ?? [] as $stageValue) {
            $stageEnum = StageEnums::tryFrom($stageValue);
            if (! $stageEnum) {
                return back()->withErrors(['optional_stages' => "Invalid optional stage: {$stageValue}"]);
            }
            // Ensure optional stage is in the stages list
            if (! in_array($stageEnum, $stages, true)) {
                return back()->withErrors(['optional_stages' => "Optional stage {$stageValue} must be in the stages list"]);
            }
            $optionalStages[] = $stageEnum;
        }

        $this->workflowService->saveWorkflowConfig(
            $modeEnum,
            $stages,
            $optionalStages,
            $request->user()->id
        );

        $this->auditLogger->log(
            'admin.workflow_config_updated',
            'workflow_config',
            $modeEnum->value,
            [],
            ['stages_count' => count($stages), 'optional_stages_count' => count($optionalStages)],
        );

        return redirect()
            ->route('admin.workflow-config.index')
            ->with('success', "Workflow configuration for {$modeEnum->getDisplayName()} updated successfully.");
    }

    /**
     * Reset workflow configuration to defaults.
     */
    public function resetToDefaults(Request $request, string|ProcurementModeEnums $mode): RedirectResponse
    {
        $this->authorize('manage-workflow-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);

        if (! $modeEnum) {
            abort(404, 'Invalid procurement mode');
        }

        $this->workflowService->resetToDefaults($modeEnum, $request->user()->id);

        $this->auditLogger->log(
            'admin.workflow_config_reset',
            'workflow_config',
            $modeEnum->value,
        );

        return redirect()
            ->route('admin.workflow-config.index')
            ->with('success', "Workflow configuration for {$modeEnum->getDisplayName()} reset to defaults.");
    }

    /**
     * Preview the complete workflow and document configuration for a mode.
     * Shows admins what users will see for a given procurement mode.
     */
    public function preview(Request $request, string|ProcurementModeEnums $mode): Response
    {
        $this->authorize('manage-workflow-config');

        $modeEnum = $mode instanceof ProcurementModeEnums ? $mode : ProcurementModeEnums::tryFrom($mode);

        if (! $modeEnum) {
            abort(404, 'Invalid procurement mode');
        }

        // Get all stages for this mode from the database-backed service
        $stages = $this->workflowService->getStagesForMode($modeEnum);
        $optionalStages = $this->workflowService->getOptionalStagesForMode($modeEnum);

        // Build complete workflow preview with documents for each stage
        $workflowPreview = [];
        foreach ($stages as $stage) {
            $documentGuide = $this->documentConfigService->getStageDocumentGuide($stage, $modeEnum);

            $workflowPreview[] = [
                'stage' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'description' => $stage->getDescription(),
                'phase' => $stage->getPhase(),
                'phase_display_name' => $stage->getPhaseDisplayName(),
                'is_optional' => in_array($stage, $optionalStages, true),
                'required_documents' => $documentGuide['required_documents'],
                'optional_documents' => $documentGuide['optional_documents'],
                'document_counts' => $documentGuide['counts'],
            ];
        }

        // Group stages by phase for better visualization
        $phases = [
            'pre_procurement' => [
                'name' => 'Pre-Procurement Phase',
                'stages' => array_filter($workflowPreview, fn ($s) => $s['phase'] === 'pre_procurement'),
            ],
            'procurement' => [
                'name' => 'Procurement Phase',
                'stages' => array_filter($workflowPreview, fn ($s) => $s['phase'] === 'procurement'),
            ],
            'post_procurement' => [
                'name' => 'Post-Procurement Phase',
                'stages' => array_filter($workflowPreview, fn ($s) => $s['phase'] === 'post_procurement'),
            ],
        ];

        // Re-index arrays
        foreach ($phases as $key => $phase) {
            $phases[$key]['stages'] = array_values($phase['stages']);
        }

        // Get all modes for the dropdown
        $allModes = array_map(fn (ProcurementModeEnums $m) => [
            'value' => $m->value,
            'display_name' => $m->getDisplayName(),
            'description' => $m->getDescription(),
            'irr_section' => $m->getIrrSection(),
            'is_alternative_mode' => $m->isAlternativeMode(),
        ], ProcurementModeEnums::cases());

        return Inertia::render('admin/workflow-preview', [
            'mode' => [
                'value' => $modeEnum->value,
                'display_name' => $modeEnum->getDisplayName(),
                'description' => $modeEnum->getDescription(),
                'irr_section' => $modeEnum->getIrrSection(),
                'is_alternative_mode' => $modeEnum->isAlternativeMode(),
            ],
            'phases' => $phases,
            'summary' => [
                'total_stages' => count($stages),
                'optional_stages' => count($optionalStages),
                'required_stages' => count($stages) - count($optionalStages),
                'total_required_documents' => array_sum(array_map(fn ($s) => $s['document_counts']['required_count'], $workflowPreview)),
                'total_optional_documents' => array_sum(array_map(fn ($s) => $s['document_counts']['optional_count'], $workflowPreview)),
            ],
            'allModes' => $allModes,
        ]);
    }
}
