<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\NoticeToProceedDocumentRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class NoticeToProceedController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/ntp-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::NOTICE_TO_PROCEED->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocument(NoticeToProceedDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('ntp_file');
        $metadata = [
            'document_type' => 'Notice to Proceed',
            'issuance_date' => $validated['issuance_date'],
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StatusEnums::NTP_RECORDED,
            StatusEnums::NTP_RECORDED,
            [$file],
            [$metadata],
            'Notice to Proceed'
        );
    }
}
