<?php

namespace App\Http\Controllers;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Enums\StreamEnums;
use App\Http\Requests\Procurement\BacResolutionDocumentRequest;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Http\Requests\Procurement\BidEvaluationDocumentsRequest;
use App\Http\Requests\Procurement\BidOpeningDocumentsRequest;
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
use App\Services\EventTypeLabelMapper;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends BaseController
{
    protected $multiChain;

    protected $eventTypeLabelMapper;

    protected $publishingService;

    public function __construct(
        MultichainService $multiChain,
        EventTypeLabelMapper $eventTypeLabelMapper,
        ProcurementPublishingService $publishingService
    ) {
        $this->multiChain = $multiChain;
        $this->eventTypeLabelMapper = $eventTypeLabelMapper;
        $this->publishingService = $publishingService;
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

    public function showPreProcurementConferenceUpload($id): Response
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/pre-procurement-conference-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
            ],
        ]);
    }

    public function showPreBidConferenceUpload($id): Response
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/pre-bid-conference-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::PRE_BID_CONFERENCE->getDisplayName(),
            ],
        ]);
    }

    public function showBiddingDocumentsUpload($id)
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/bidding-documents-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            ],
        ]);
    }

    public function showSupplementalBidBulletinUpload($id)
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

    public function showBidOpeningUpload($id)
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

    public function showBidEvaluationUpload($id)
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/bid-evaluation-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::BID_EVALUATION->getDisplayName(),
            ],
        ]);
    }

    public function showPostQualificationUpload($id)
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/post-qualification-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::POST_QUALIFICATION->getDisplayName(),
            ],
        ]);
    }

    public function showBacResolutionUpload($id)
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

    public function showNoaUpload($id)
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

    public function showPerformanceBondContactAndPoUpload($id)
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

    public function showNTPUpload($id)
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

    public function showMonitoringUpload($id)
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

    public function showCompletionUpload($id)
    {
        $procurement = $this->findProcurementById($id);

        return Inertia::render('bac-secretariat/procurement-stage/completion-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::COMPLETED->getDisplayName(),
            ],
        ]);
    }

    /**
     * Helper to find procurement by id from the STATUS stream.
     *
     * @param  string|int  $id
     * @return array|null
     */
    private function findProcurementById($id)
    {
        $statusItems = $this->multiChain->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            1000,
            0
        );
        if (! empty($statusItems)) {
            foreach ($statusItems as $item) {
                if (
                    isset($item['data']['json']) &&
                    isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $id
                ) {
                    return $item['data']['json'];
                }
            }
        }

        return null;
    }

    public function publishProcurementInitiation(ProcurementInitiationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        return $this->publishingService->publishDocuments(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PROCUREMENT_INITIATION,
            StatusEnums::PROCUREMENT_SUBMITTED,
            $validated['files'],
            $validated['metadata'],
            'Procurement Initiation'
        );
    }

    public function publishPreProcurementConferenceDecision(PreProcurementConferenceDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['conference_held']) {
            return $this->publishingService->updateStatus(
                $validated['procurement_id'],
                $validated['procurement_title'],
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
                'Pre-procurement conference held - documents pending',
                'Pre-Procurement Conference Decision'
            );
        }

        return $this->publishingService->updateStatus(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
            'Pre-procurement conference skipped - proceeding to '.StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            'Pre-Procurement Conference Decision'
        );
    }

    public function uploadPreProcurementConferenceDocuments(PreProcurementConferenceDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $meetingDate = date('Y-m-d', strtotime($validated['meeting_date']));
        $participants = $validated['participants'];

        $files = [];
        $metadata = [];

        if ($minutesFile = request()->file('minutes_file')) {
            $files[] = $minutesFile;
            $metadata[] = [
                'document_type' => 'Minutes',
                'meeting_date' => $meetingDate,
                'participants' => $participants,
            ];
        }

        if ($attendanceFile = request()->file('attendance_file')) {
            $files[] = $attendanceFile;
            $metadata[] = [
                'document_type' => 'Attendance',
                'meeting_date' => $meetingDate,
                'participants' => $participants,
            ];
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            $files,
            $metadata,
            'Pre-Procurement Conference Documents'
        );
    }

    public function publishPreBidConferenceDecision(PreBidConferenceDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['conference_held']) {
            return $this->publishingService->handleTransitionOnly(
                $validated['procurement_id'],
                $validated['procurement_title'],
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::PRE_BID_CONFERENCE,
                StatusEnums::PRE_BID_CONFERENCE_HELD,
                StatusEnums::PRE_BID_CONFERENCE_HELD,
                'Pre-bid conference held',
                'Pre-Bid Conference Decision'
            );
        }

        return $this->publishingService->handleTransitionOnly(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
            StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
            'Pre-bid conference skipped',
            'Pre-Bid Conference Decision'
        );
    }

    public function uploadPreBidConferenceDocuments(PreBidConferenceDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $meetingDate = $validated['meeting_date'];
        $participants = $validated['participants'];

        $files = [];
        $metadata = [];

        if ($minutesFile = request()->file('minutes_file')) {
            $files[] = $minutesFile;
            $metadata[] = [
                'document_type' => 'Pre-Bid Minutes',
                'meeting_date' => $meetingDate,
                'participants' => $participants,
            ];
        }

        if ($attendanceFile = request()->file('attendance_file')) {
            $files[] = $attendanceFile;
            $metadata[] = [
                'document_type' => 'Pre-Bid Attendance',
                'meeting_date' => $meetingDate,
                'participants' => $participants,
            ];
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StatusEnums::PRE_BID_CONFERENCE_HELD,
            StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            $files,
            $metadata,
            'Pre-Bid Conference Documents'
        );
    }

    public function publishSupplementalBidBulletinDecision(SupplementalBidBulletinDecisionRequest $request): RedirectResponse
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

    public function uploadSupplementalBidBulletinDocuments(SupplementalBidBulletinDocumentsRequest $request): RedirectResponse
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

    public function uploadBiddingDocuments(BiddingDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('bidding_documents_file');
        $metadata = array_merge($validated['metadata'] ?? [], [
            'document_type' => StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            'issuance_date' => $validated['issuance_date'],
            'validity_period' => [
                'start_date' => $validated['validity_period_start'],
                'end_date' => $validated['validity_period_end'],
            ],
        ]);

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
            [$file],
            [$metadata],
            'Bidding Documents'
        );
    }

    public function uploadBidOpeningDocuments(BidOpeningDocumentsRequest $request): RedirectResponse
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

    public function uploadBidEvaluationDocuments(BidEvaluationDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $evaluationDate = $validated['evaluation_date'];
        $evaluatorNames = $validated['evaluator_names'];

        $files = [];
        $metadata = [];

        if ($summaryFile = request()->file('summary_file')) {
            $files[] = $summaryFile;
            $metadata[] = [
                'document_type' => 'Evaluation Summary',
                'evaluation_date' => $evaluationDate,
                'evaluator_names' => $evaluatorNames,
            ];
        }

        if ($abstractFile = request()->file('abstract_file')) {
            $files[] = $abstractFile;
            $metadata[] = [
                'document_type' => 'Abstract',
                'evaluation_date' => $evaluationDate,
                'evaluator_names' => $evaluatorNames,
            ];
        }

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
            StatusEnums::BIDS_EVALUATED,
            StatusEnums::BIDS_EVALUATED,
            $files,
            $metadata,
            'Bid Evaluation Documents'
        );
    }

    public function uploadPostQualificationDocuments(PostQualificationDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $outcome = $validated['outcome'] ?? false;
        $baseMetadata = [
            'submission_date' => $validated['submission_date'],
            'outcome' => $outcome ? 'Verified' : 'Failed',
            'remarks' => $validated['remarks'] ?? null,
        ];

        $files = [];
        $metadata = [];

        if ($reportFile = request()->file('post_qualification_report')) {
            $files[] = $reportFile;
            $metadata[] = array_merge(['document_type' => 'Post Qualification Report'], $baseMetadata);
        }

        if ($twgFile = request()->file('twg_certification')) {
            $files[] = $twgFile;
            $metadata[] = array_merge(['document_type' => 'TWG Certification'], $baseMetadata);
        }

        if ($noticeFile = request()->file('notice_of_post_qualification')) {
            $files[] = $noticeFile;
            $metadata[] = array_merge(['document_type' => 'Notice of Post Qualification'], $baseMetadata);
        }

        if ($outcome) {
            return $this->publishingService->publishWithTransition(
                $validated['procurement_id'],
                $validated['procurement_title'],
                StageEnums::POST_QUALIFICATION,
                StageEnums::BAC_RESOLUTION,
                StatusEnums::POST_QUALIFICATION_VERIFIED,
                StatusEnums::POST_QUALIFICATION_VERIFIED,
                $files,
                $metadata,
                'Post Qualification Documents'
            );
        }

        return $this->publishingService->publishDocuments(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::POST_QUALIFICATION,
            StatusEnums::POST_QUALIFICATION_FAILED,
            $files,
            $metadata,
            'Post Qualification Documents'
        );
    }

    public function uploadBacResolutionDocument(BacResolutionDocumentRequest $request): RedirectResponse
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

    public function uploadNoaDocument(NoticeOfAwardDocumentRequest $request): RedirectResponse
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

    public function uploadPerformanceBondContractAndPoDocuments(PerformanceBondContractAndPoDocumentsRequest $request): RedirectResponse
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

    public function uploadNTPDocument(NoticeToProceedDocumentRequest $request): RedirectResponse
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

    public function uploadMonitoringDocument(MonitoringDocumentRequest $request): RedirectResponse
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

    public function uploadCompletionDocuments(CompletionDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = request()->file('completion_file');
        $metadata = [
            'document_type' => 'Certificate of Completion',
            'completion_date' => $validated['completion_date'],
            'notes' => $validated['completion_notes'] ?? null,
        ];

        return $this->publishingService->publishWithTransition(
            $validated['procurement_id'],
            $validated['procurement_title'],
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
            StatusEnums::COMPLETED,
            StatusEnums::COMPLETED,
            [$file],
            [$metadata],
            'Completion Documents'
        );
    }

    /**
     * Get blockchain publication status for procurement documents
     */
    public function getBlockchainStatus(string $id): \Illuminate\Http\JsonResponse
    {
        $documents = \App\Models\ProcurementDocument::where('procurement_id', $id)
            ->latest('created_at')
            ->limit(50) // Recent documents
            ->get(['id', 'file_name', 'blockchain_status', 'blockchain_error', 'blockchain_txid', 'blockchain_status_updated_at', 'created_at']);

        $summary = [
            'pending' => $documents->where('blockchain_status', 'pending')->count(),
            'confirmed' => $documents->where('blockchain_status', 'confirmed')->count(),
            'failed' => $documents->where('blockchain_status', 'failed')->count(),
            'total' => $documents->count(),
        ];

        // Determine overall status
        $allConfirmed = $summary['pending'] === 0 && $summary['failed'] === 0 && $summary['total'] > 0;
        $hasFailed = $summary['failed'] > 0;
        $status = $allConfirmed ? 'confirmed' : ($hasFailed ? 'failed' : 'pending');

        return response()->json([
            'status' => $status,
            'summary' => $summary,
            'documents' => $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'file_name' => $doc->file_name,
                    'blockchain_status' => $doc->blockchain_status,
                    'blockchain_error' => $doc->blockchain_error,
                    'blockchain_txid' => $doc->blockchain_txid,
                    'updated_at' => $doc->blockchain_status_updated_at?->diffForHumans() ?? $doc->created_at->diffForHumans(),
                ];
            }),
        ]);
    }
}
