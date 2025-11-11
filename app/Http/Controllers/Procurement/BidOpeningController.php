<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\BidOpeningDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class BidOpeningController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/bid-opening-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BID_OPENING->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocuments(BidOpeningDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $bidDocuments = request()->file('bid_documents', []);
        $biddersData = $validated['bidders_data'] ?? [];
        $openingDateTime = $validated['opening_date_time'];

        $files = [];
        $metadata = [];

        foreach ($bidDocuments as $index => $file) {
            if ($file && isset($biddersData[$index])) {
                $bidderName = $biddersData[$index]['bidder_name'] ?? 'Unknown Bidder';
                $bidValue = floatval($biddersData[$index]['bid_value'] ?? '0');

                $files[] = $file;
                $metadata[] = [
                    'document_type' => 'Bid Document',
                    'bidder_name' => $bidderName,
                    'bid_value' => number_format($bidValue, 2, '.', ''),
                    'opening_date_time' => $openingDateTime,
                ];
            }
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StatusEnums::BIDS_OPENED,
            StatusEnums::BIDS_OPENED,
            $files,
            $metadata,
            'Bid Opening Documents'
        );
    }
}
