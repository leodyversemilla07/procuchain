<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class SupplementalBidBulletinController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::SUPPLEMENTAL_BID_BULLETIN->getDisplayName(),
            ],
        ]);
    }

    public function publishDecision(SupplementalBidBulletinDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['supplemental_bid_needed']) {
            return $this->publishingService->handleTransitionOnly(
                $validated['procurement_id'],
                $validated['procurement_title'],
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
                StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
                'Additional supplemental bid bulletins required',
                'Supplemental Bid Bulletin Decision'
            );
        }

        return $this->publishingService->handleTransitionOnly(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'No additional supplemental bid bulletins needed',
            'Supplemental Bid Bulletin Decision'
        );
    }

    public function uploadDocuments(SupplementalBidBulletinDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('bulletin_file');
        $metadata = [
            'document_type' => 'Supplemental Bid Bulletin',
            'bulletin_number' => $validated['bulletin_number'],
            'bulletin_title' => $validated['bulletin_title'],
            'issue_date' => $validated['issue_date'],
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
            StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            [$file],
            [$metadata],
            'Supplemental Bid Bulletin'
        );
    }
}
