<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDocumentsRequest;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
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

use App\Handlers\PreProcurementConference\PreProcurementConferenceShowUploadHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsShowUploadHandler;
use App\Handlers\BidOpening\BidOpeningShowUploadHandler;
use App\Handlers\BidEvaluation\BidEvaluationShowUploadHandler;
use App\Handlers\PostQualification\PostQualificationShowUploadHandler;
use App\Handlers\BacResolution\BacResolutionShowUploadHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardShowUploadHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoShowUploadHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedShowUploadHandler;
use App\Handlers\Monitoring\MonitoringShowUploadHandler;
use App\Handlers\Completion\CompletionDocumentsShowUploadHandler;

use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\BidOpening\BidOpeningHandler;
use App\Handlers\BidEvaluation\BidEvaluationHandler;
use App\Handlers\PostQualification\PostQualificationHandler;
use App\Handlers\BacResolution\BacResolutionHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedHandler;
use App\Handlers\Monitoring\MonitoringHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;
use App\Handlers\Completion\CompletionProcessHandler;

class ProcurementController extends BaseController
{
    public function __construct()
    {
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
        return Inertia::render('bac-secretariat/procurement-phase/procurement-initiation');
    }

    public function showBiddingDocumentsUpload($id, BiddingDocumentsShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showBidOpeningUpload($id, BidOpeningShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showBidEvaluationUpload($id, BidEvaluationShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showPostQualificationUpload($id, PostQualificationShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showBacResolutionUpload($id, BacResolutionShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showNoaUpload($id, NoticeOfAwardShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showPerformanceBondContactAndPoUpload($id, PerformanceBondContractAndPoShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showNTPUpload($id, NoticeToProceedShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showMonitoringUpload($id, MonitoringShowUploadHandler $handler)
    {
        return $handler->handle($id);
    }

    public function showCompleteStatus($id, CompletionDocumentsShowUploadHandler $handler)

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

    public function showPreProcurementUpload($id, PreProcurementConferenceShowUploadHandler $handler)
    {
        return $handler->handle($id);
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
}
