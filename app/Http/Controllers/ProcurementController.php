<?php

namespace App\Http\Controllers;

use App\Enums\StageEnums;
use App\Enums\StreamEnums;
use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Handlers\BidOpening\BidOpeningDocumentsHandler;
use App\Handlers\BidEvaluation\BidEvaluationDocumentsHandler;
use App\Handlers\PostQualification\PostQualificationDocumentsHandler;
use App\Handlers\BacResolution\BacResolutionDocumentHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardDocumentHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedDocumentHandler;
use App\Handlers\Monitoring\MonitoringDocumentHandler;
use App\Handlers\Completion\CompletionProcessHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;
use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDocumentsRequest;
use App\Http\Requests\Procurement\BidOpeningDocumentsRequest;
use App\Http\Requests\Procurement\BidEvaluationDocumentsRequest;
use App\Http\Requests\Procurement\PostQualificationDocumentsRequest;
use App\Http\Requests\Procurement\BacResolutionDocumentRequest;
use App\Http\Requests\Procurement\NoticeOfAwardDocumentRequest;
use App\Http\Requests\Procurement\PerformanceBondContractAndPoDocumentsRequest;
use App\Http\Requests\Procurement\NoticeToProceedDocumentRequest;
use App\Http\Requests\Procurement\MonitoringDocumentRequest;
use App\Http\Requests\Procurement\CompleteProcessRequest;
use App\Http\Requests\Procurement\CompletionDocumentsRequest;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends BaseController
{
    protected $services;

    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');

        $this->middleware(function ($request, $next) {
            $response = $next($request);
            if ($response instanceof RedirectResponse) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time()) . ' GMT');

                $response->headers->set('X-Frame-Options', 'DENY');
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
            }

            return $response;
        });
    }

    public function showProcurementInitiation(): Response
    {
        return Inertia::render('bac-secretariat/procurement-stage/procurement-initiation');
    }

    private function handleProcurementStageUpload(string $id, string $stageName, string $viewPath)
    {
        // First try to find procurement in the STATUS stream
        $statusItems = $this->services->getMultiChain()->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            50,  // Reasonable number to search through
            -50  // Get recent items first
        );

        $procurement = null;
        $procurementTitle = '';

        // Look for the procurement ID in the status stream
        if (!empty($statusItems)) {
            foreach ($statusItems as $item) {
                if (
                    isset($item['data']['json']) &&
                    isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $id
                ) {
                    $procurementData = $item['data']['json'];
                    $procurement = $procurementData;
                    $procurementTitle = $procurementData['procurement_title'] ?? '';

                    // Once found, break out of the loop
                    if (!empty($procurementTitle)) {
                        break;
                    }
                }
            }
        }

        return Inertia::render($viewPath, [
            'procurement' => [
                'id' => $id,
                'title' => $procurementTitle,
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? $stageName,
            ],
        ]);
    }

    public function showPreProcurementConferenceUpload($id): Response
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
            'bac-secretariat/procurement-stage/pre-procurement-conference-upload'
        );
    }

    public function showPreBidConferenceUpload($id): Response
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::PRE_BID_CONFERENCE->getDisplayName(),
            'bac-secretariat/procurement-stage/pre-bid-conference-upload'
        );
    }

    public function showBiddingDocumentsUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            'bac-secretariat/procurement-stage/bidding-documents-upload'
        );
    }

    public function showSupplementalBidBulletinUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN->getDisplayName(),
            'bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload'
        );
    }

    public function showBidOpeningUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::BID_OPENING->getDisplayName(),
            'bac-secretariat/procurement-stage/bid-opening-upload'
        );
    }

    public function showBidEvaluationUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::BID_EVALUATION->getDisplayName(),
            'bac-secretariat/procurement-stage/bid-evaluation-upload'
        );
    }

    public function showPostQualificationUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::POST_QUALIFICATION->getDisplayName(),
            'bac-secretariat/procurement-stage/post-qualification-upload'
        );
    }

    public function showBacResolutionUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::BAC_RESOLUTION->getDisplayName(),
            'bac-secretariat/procurement-stage/bac-resolution-upload'
        );
    }

    public function showNoaUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::NOTICE_OF_AWARD->getDisplayName(),
            'bac-secretariat/procurement-stage/noa-upload'
        );
    }

    public function showPerformanceBondContactAndPoUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName(),
            // Update the view path to the combined component
            'bac-secretariat/procurement-stage/performance-bond-contract-po-upload'
        );
    }

    public function showNTPUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::NOTICE_TO_PROCEED->getDisplayName(),
            'bac-secretariat/procurement-stage/ntp-upload'
        );
    }

    public function showMonitoringUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::MONITORING->getDisplayName(),
            'bac-secretariat/procurement-stage/monitoring-upload'
        );
    }

    public function showCompletionUpload($id)
    {
        return $this->handleProcurementStageUpload(
            $id,
            StageEnums::COMPLETED->getDisplayName(),
            'bac-secretariat/procurement-stage/completion-upload'
        );
    }

    private function processHandlerResult(array $result): RedirectResponse
    {
        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with(['success' => true, 'message' => $result['message']]);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    protected function handleProcurementAction($request, $handler): RedirectResponse
    {
        return $this->processHandlerResult($handler->handle($request));
    }

    public function publishProcurementInitiation(ProcurementInitiationRequest $request, ProcurementInitiationHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function publishPreProcurementConferenceDecision(PreProcurementConferenceDecisionRequest $request, PreProcurementConferenceDecisionHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPreProcurementConferenceDocuments(PreProcurementConferenceDocumentsRequest $request, PreProcurementConferenceDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function publishPreBidConferenceDecision(PreBidConferenceDecisionRequest $request, PreBidConferenceDecisionHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPreBidConferenceDocuments(PreBidConferenceDocumentsRequest $request, PreBidConferenceDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function publishSupplementalBidBulletinDecision(SupplementalBidBulletinDecisionRequest $request, SupplementalBidBulletinDecisionHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadSupplementalBidBulletinDocuments(SupplementalBidBulletinDocumentsRequest $request, SupplementalBidBulletinDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBiddingDocuments(BiddingDocumentsRequest $request, BiddingDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBidOpeningDocuments(BidOpeningDocumentsRequest $request, BidOpeningDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBidEvaluationDocuments(BidEvaluationDocumentsRequest $request, BidEvaluationDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPostQualificationDocuments(PostQualificationDocumentsRequest $request, PostQualificationDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBacResolutionDocument(BacResolutionDocumentRequest $request, BacResolutionDocumentHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadNoaDocument(NoticeOfAwardDocumentRequest $request, NoticeOfAwardDocumentHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPerformanceBondContractAndPoDocuments(PerformanceBondContractAndPoDocumentsRequest $request, PerformanceBondContractAndPoHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadNTPDocument(NoticeToProceedDocumentRequest $request, NoticeToProceedDocumentHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadMonitoringDocument(MonitoringDocumentRequest $request, MonitoringDocumentHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadCompletionDocuments(CompletionDocumentsRequest $request, CompletionDocumentsHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function saveProcurementDraft(Request $request)
    {
        try {
            // Store draft data in session for now
            session(['procurement_draft' => $request->all()]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Draft saved successfully']);
            }

            return back()->with('success', 'Draft saved successfully');
        } catch (Exception $e) {
            Log::error('Failed to save procurement draft:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to save draft'], 500);
            }

            return back()->withErrors(['error' => 'Failed to save draft']);
        }
    }
}
