<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Services\WorkflowDefinitionService;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowDefinitionService $workflowDefinitionService,
    ) {}

    /**
     * Display the dynamic procurement workflow page.
     */
    public function __invoke(): Response
    {
        $this->authorize('view-workflow');

        $workflows = collect(ProcurementModeEnums::cases())->map(function (ProcurementModeEnums $mode): array {
            $optionalStages = $this->workflowDefinitionService->getOptionalStagesForMode($mode);
            $stages = $this->workflowDefinitionService->getStagesForMode($mode);

            $stageDetails = collect($stages)->map(function (StageEnums $stageEnum) use ($mode, $optionalStages): array {
                $requiredDocuments = $this->workflowDefinitionService->getRequiredDocuments($stageEnum, $mode);
                $optionalDocuments = $this->workflowDefinitionService->getOptionalDocuments($stageEnum, $mode);

                return [
                    'id' => $stageEnum->value,
                    'name' => $stageEnum->getDisplayName(),
                    'phase' => $stageEnum->getPhase(),
                    'description' => $stageEnum->getDescription(),
                    'optional' => in_array($stageEnum, $optionalStages, true),
                    'repeatable' => $stageEnum->isRepeatable(),
                    'details' => $stageEnum->getKeyActivities(),
                    'documents' => array_map(
                        fn (DocumentTypeEnums $document): string => $document->getDisplayName(),
                        array_merge($requiredDocuments, $optionalDocuments),
                    ),
                ];
            });

            return [
                'mode' => $mode->value,
                'name' => $mode->getDisplayName(),
                'stages' => $stageDetails,
            ];
        });

        return Inertia::render('workflow', [
            'workflows' => $workflows,
        ]);
    }
}
