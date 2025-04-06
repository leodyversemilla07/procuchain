<?php

namespace App\Http\Controllers;

use App\Handlers\BacResolution\BacResolutionHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\BidEvaluation\BidEvaluationHandler;
use App\Handlers\BidOpening\BidOpeningHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;
use App\Handlers\Completion\CompletionProcessHandler;
use App\Handlers\Monitoring\MonitoringHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\PostQualification\PostQualificationHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Http\Requests\Procurement\BacResolutionDocumentRequest;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Http\Requests\Procurement\BidEvaluationDocumentsRequest;
use App\Http\Requests\Procurement\BidOpeningDocumentsRequest;
use App\Http\Requests\Procurement\CompleteProcessRequest;
use App\Http\Requests\Procurement\CompletionDocumentsRequest;
use App\Http\Requests\Procurement\MonitoringDocumentRequest;
use App\Http\Requests\Procurement\NoticeOfAwardDocumentRequest;
use App\Http\Requests\Procurement\NoticeToProceedDocumentRequest;
use App\Http\Requests\Procurement\PerformanceBondContractAndPoDocumentsRequest;
use App\Http\Requests\Procurement\PostQualificationDocumentsRequest;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;
use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDocumentsRequest;
use App\Enums\StreamEnums;
use App\Enums\StageEnums;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\BlockchainService;
use App\Services\Multichain\StreamQueryOptions;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;

class ProcurementController extends BaseController
{
    protected $multiChain;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->multiChain = $blockchainService->getClient();
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');

        $this->middleware(function ($request, $next) {
            $response = $next($request);
            if ($response instanceof RedirectResponse) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time()).' GMT');

                $response->headers->set('X-Frame-Options', 'DENY');
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
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
        try {
            $allStatuses = collect([]);
            
            try {
                $statusOptions = new StreamQueryOptions(
                    StreamEnums::STATUS->value,
                    true,
                    1000,
                    -1000
                );
                
                $blockchainStatuses = $this->multiChain->listStreamItems($statusOptions);
                if ($blockchainStatuses && is_array($blockchainStatuses)) {
                    $allStatuses = collect($blockchainStatuses);
                }
            } catch (Exception $e) {
                Log::warning('Could not fetch blockchain statuses', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $procurement = $allStatuses
                ->map(function ($item) {
                    $data = $item['data'] ?? [];
                    return [
                        'id' => $data['procurement_id'] ?? '',
                        'title' => $data['title'] ?? $data['procurement_title'] ?? 'Unknown',
                        'status' => $data['current_status'] ?? $data['status']['current_status'] ?? 'Unknown',
                        'stage' => $data['stage'] ?? $data['status']['stage'] ?? 'Unknown',
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                })
                ->filter(function ($item) use ($id) {
                    return $item['id'] == $id;
                })
                ->sortByDesc('timestamp')
                ->first();
            
            if (!$procurement) {
                $procurement = [
                    'id' => $id,
                    'title' => 'Procurement #' . $id,
                    'status' => 'Unknown',
                    'stage' => $stageName
                ];
            }
            
            return Inertia::render($viewPath, [
                'procurement' => $procurement
            ]);
            
        } catch (Exception $e) {
            Log::error('Error showing ' . strtolower($stageName) . ' upload page', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render($viewPath, [
                'procurement' => [
                    'id' => $id,
                    'title' => 'Procurement #' . $id,
                    'status' => 'Unknown',
                    'stage' => $stageName
                ],
                'error' => 'Could not retrieve full procurement details'
            ]);
        }
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
            'bac-secretariat/procurement-stage/contract-po-upload'
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

    public function showCompleteStatus($id, $handler)
    {
        return $handler->handle($id);
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

    public function uploadBidOpeningDocuments(BidOpeningDocumentsRequest $request, BidOpeningHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBidEvaluationDocuments(BidEvaluationDocumentsRequest $request, BidEvaluationHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPostQualificationDocuments(PostQualificationDocumentsRequest $request, PostQualificationHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadBacResolutionDocument(BacResolutionDocumentRequest $request, BacResolutionHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadNoaDocument(NoticeofAwardDocumentRequest $request, NoticeOfAwardHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadPerformanceBondContractAndPoDocuments(PerformanceBondContractAndPoDocumentsRequest $request, PerformanceBondContractAndPoHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadNTPDocument(NoticeToProceedDocumentRequest $request, NoticeToProceedHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function uploadMonitoringDocument(MonitoringDocumentRequest $request, MonitoringHandler $handler): RedirectResponse
    {
        return $this->handleProcurementAction($request, $handler);
    }

    public function publishCompleteProcess(CompleteProcessRequest $request, CompletionProcessHandler $handler): RedirectResponse
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
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to save draft'], 500);
            }
            
            return back()->withErrors(['error' => 'Failed to save draft']);
        }
    }
}
