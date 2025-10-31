<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\MonitoringDocumentRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/monitoring-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::MONITORING->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocument(MonitoringDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('compliance_file');
        $metadata = [
            'document_type' => 'Compliance Report',
            'report_date' => $validated['report_date'],
            'report_notes' => $validated['report_notes'] ?? null,
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StatusEnums::MONITORING_COMPLETED,
            StatusEnums::COMPLETED,
            [$file],
            [$metadata],
            'Monitoring Documents'
        );
    }
}
