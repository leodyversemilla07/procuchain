<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\CompletionDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class CompletionController extends BaseController
{
    use HasProcurementSupport;

    public function __construct(
        MultichainService $multiChain,
        ProcurementPublishingService $publishingService,
        ProcurementDataService $procurementDataService
    ) {
        $this->initializeProcurementSupport($multiChain, $publishingService, $procurementDataService);
        $this->applyProcurementMiddleware();
    }

    public function show($id): Response
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/completion-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::COMPLETED->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocuments(CompletionDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('completion_file');
        $metadata = [
            'document_type' => 'Certificate of Completion',
            'completion_date' => $validated['completion_date'],
            'notes' => $validated['completion_notes'] ?? null,
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
            StatusEnums::COMPLETED,
            StatusEnums::COMPLETED,
            [$file],
            [$metadata],
            'Completion Documents'
        );
    }
}
