<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class BiddingDocumentsController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/bidding-documents-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            ],
        ]);
    }

    public function upload(BiddingDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StatusEnums::BIDDING_DOCUMENTS_SUBMITTED,
            StatusEnums::BIDDING_DOCUMENTS_SUBMITTED,
            $validated['files'],
            $validated['metadata'],
            'Bidding Documents'
        );
    }
}
