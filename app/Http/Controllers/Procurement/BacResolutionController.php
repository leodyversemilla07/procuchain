<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\BacResolutionDocumentRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class BacResolutionController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/bac-resolution-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BAC_RESOLUTION->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocument(BacResolutionDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('bac_resolution_file');
        $metadata = [
            'document_type' => 'BAC Resolution',
            'issuance_date' => $validated['issuance_date'],
            'signatory_details' => $validated['signatory_details'],
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BAC_RESOLUTION,
            StageEnums::NOTICE_OF_AWARD,
            StatusEnums::RESOLUTION_RECORDED,
            StatusEnums::RESOLUTION_RECORDED,
            [$file],
            [$metadata],
            'BAC Resolution'
        );
    }
}
