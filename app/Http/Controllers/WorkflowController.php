<?php

namespace App\Http\Controllers;

use App\Enums\ProcurementModeEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    /**
     * Display the dynamic procurement workflow page.
     */
    public function __invoke(Request $request): Response
    {
        $workflowConfigs = ProcurementWorkflowConfig::active()->get();
        $documentConfigs = StageDocumentConfig::active()->get();

        $workflows = $workflowConfigs->map(function ($config) use ($documentConfigs) {
            $mode = $config->procurement_mode;
            $stages = $config->getStagesAsEnums();

            $stageDetails = collect($stages)->map(function ($stageEnum) use ($mode, $config, $documentConfigs) {
                $docConfig = $documentConfigs->first(function ($doc) use ($stageEnum, $mode) {
                    return $doc->stage === $stageEnum->value && $doc->procurement_mode === $mode;
                });

                return [
                    'id' => $stageEnum->value,
                    'name' => $stageEnum->getDisplayName(),
                    'phase' => $stageEnum->getPhase(),
                    'description' => $stageEnum->getDescription(),
                    'optional' => $config->isStageOptional($stageEnum),
                    'repeatable' => $stageEnum->isRepeatable(),
                    'details' => $stageEnum->getKeyActivities(),
                    'documents' => $docConfig ? array_merge($docConfig->required_documents ?? [], $docConfig->optional_documents ?? []) : [],
                ];
            });

            return [
                'mode' => $mode,
                'name' => ProcurementModeEnums::tryFrom($mode)?->getDisplayName() ?? $mode,
                'stages' => $stageDetails,
            ];
        });

        return Inertia::render('workflow', [
            'workflows' => $workflows,
        ]);
    }
}
