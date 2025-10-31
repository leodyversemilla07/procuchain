<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\BidEvaluationDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class BidEvaluationController extends BaseController
{
    use HasProcurementSupport;

    public function __construct(
        MultichainService $multiChain,
        ProcurementPublishingService $publishingService
    ) {
        $this->initializeProcurementSupport($multiChain, $publishingService);
        $this->applyProcurementMiddleware();
    }

    public function show($id): Response
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/bid-evaluation-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BID_EVALUATION->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocuments(BidEvaluationDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $evaluationDate = $validated['evaluation_date'];
        $evaluatorNames = $validated['evaluator_names'];

        $files = [];
        $metadata = [];

        if ($summaryFile = request()->file('summary_file')) {
            $files[] = $summaryFile;
            $metadata[] = [
                'document_type' => 'Evaluation Summary',
                'evaluation_date' => $evaluationDate,
                'evaluator_names' => $evaluatorNames,
            ];
        }

        if ($abstractFile = request()->file('abstract_file')) {
            $files[] = $abstractFile;
            $metadata[] = [
                'document_type' => 'Abstract',
                'evaluation_date' => $evaluationDate,
                'evaluator_names' => $evaluatorNames,
            ];
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StatusEnums::BIDS_EVALUATED,
            StatusEnums::BIDS_EVALUATED,
            $files,
            $metadata,
            'Bid Evaluation Documents'
        );
    }
}
