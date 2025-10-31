<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\NoticeOfAwardDocumentRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class NoticeOfAwardController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/noa-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::NOTICE_OF_AWARD->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocument(NoticeOfAwardDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('noa_file');
        $metadata = [
            'document_type' => 'Notice of Award',
            'issuance_date' => $validated['issuance_date'],
            'signatory_details' => $validated['signatory_details'],
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StatusEnums::AWARDED,
            StatusEnums::AWARDED,
            [$file],
            [$metadata],
            'Notice of Award'
        );
    }
}
