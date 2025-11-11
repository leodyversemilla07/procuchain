<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PerformanceBondContractAndPoDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceBondContractPoController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/performance-bond-contract-po-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocuments(PerformanceBondContractAndPoDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $files = [];
        $metadata = [];

        if ($bondFile = request()->file('performance_bond_file')) {
            $files[] = $bondFile;
            $metadata[] = [
                'document_type' => 'Performance Bond',
                'submission_date' => $validated['submission_date'],
                'bond_amount' => $validated['bond_amount'],
            ];
        }

        if ($contractFile = request()->file('contract_file')) {
            $files[] = $contractFile;
            $metadata[] = [
                'document_type' => 'Contract',
                'signing_date' => $validated['signing_date'],
            ];
        }

        if ($poFile = request()->file('po_file')) {
            $files[] = $poFile;
            $metadata[] = [
                'document_type' => 'Purchase Order',
                'signing_date' => $validated['signing_date'],
            ];
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            $files,
            $metadata,
            'Performance Bond, Contract & PO'
        );
    }
}
