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
use App\Services\BlockchainEventLoggerService;
use App\Services\BlockchainOrchestratorService;
use App\Services\DocumentUploadService;
use App\Services\EventTypeLabelMapper;
use App\Services\MultichainService;
use App\Services\NotificationService;
use App\Services\StatusUpdaterService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends BaseController
{
    protected $documentUploadService;

    protected $blockchainOrchestrator;

    protected $statusUpdater;

    protected $eventLogger;

    protected $multiChain;

    protected $eventTypeLabelMapper;

    protected $notificationService;

    public function __construct(
        DocumentUploadService $documentUploadService,
        BlockchainOrchestratorService $blockchainOrchestrator,
        StatusUpdaterService $statusUpdater,
        BlockchainEventLoggerService $eventLogger,
        MultichainService $multiChain,
        EventTypeLabelMapper $eventTypeLabelMapper,
        NotificationService $notificationService
    ) {
        $this->documentUploadService = $documentUploadService;
        $this->blockchainOrchestrator = $blockchainOrchestrator;
        $this->statusUpdater = $statusUpdater;
        $this->eventLogger = $eventLogger;
        $this->multiChain = $multiChain;
        $this->eventTypeLabelMapper = $eventTypeLabelMapper;
        $this->notificationService = $notificationService;
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
        $data = [];
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'files' => $validated['files'] ?? [],
                'metadata' => $validated['metadata'] ?? [],
                'timestamp' => now()->toIso8601String(),
                'userAddress' => Auth::user()->blockchain_address,
                'stage' => StageEnums::PROCUREMENT_INITIATION,
                'status' => StatusEnums::PROCUREMENT_SUBMITTED,
            ];

            $stageFolder = $data['stage']->getStoragePathSegment();
            $metadataArray = $this->documentUploadService->uploadAndPrepare(
                $data['files'],
                $data['metadata'],
                $data['procurementId'],
                $data['procurementTitle'],
                $stageFolder
            );

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['stage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['stage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                count($metadataArray),
                true
            );

            $result = [
                'success' => true,
                'message' => $data['stage']->getDisplayName().' documents published successfully',
            ];
        } catch (Exception $e) {
            Log::error('Error in publishProcurementInitiation', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to publish '.($data['stage'] ?? StageEnums::PROCUREMENT_INITIATION)->getDisplayName().' documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with(['success' => true, 'message' => $result['message']]);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function publishPreProcurementConferenceDecision(PreProcurementConferenceDecisionRequest $request): RedirectResponse
    {
        $data = [];
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'conferenceHeld' => $validated['conference_held'] ?? false,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
                'initialStage' => StageEnums::PROCUREMENT_INITIATION,
                'nextStage' => StageEnums::BIDDING_DOCUMENTS,
            ];

            if ($data['conferenceHeld']) {
                $status = StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD;

                $this->statusUpdater->updateStatus(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $status->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['userAddress'],
                    $data['timestamp']
                );

                $this->eventLogger->logEvent(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    'Pre-procurement conference held - documents pending',
                    0,
                    $data['userAddress'],
                    'decision',
                    'workflow',
                    'info',
                    $data['timestamp']
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $status->getDisplayName(),
                    $data['timestamp'],
                    'held',
                    false,
                    ''
                );

                $result = [
                    'success' => true,
                    'message' => $status->getDisplayName().'. Please upload documents.',
                ];
            } else {
                try {
                    $status = StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED;

                    Log::info('Attempting to skip pre-procurement conference', [
                        'procurement_id' => $data['procurementId'],
                        'procurement_title' => $data['procurementTitle'],
                    ]);

                    $this->statusUpdater->updateStatus(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $status->getDisplayName(),
                        $data['nextStage']->getDisplayName(),
                        $data['userAddress'],
                        $data['timestamp']
                    );

                    $this->eventLogger->logEvent(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getDisplayName(),
                        'Pre-procurement conference skipped - proceeding to '.$data['nextStage']->getDisplayName(),
                        0,
                        $data['userAddress'],
                        'decision',
                        'workflow',
                        'info',
                        $data['timestamp']
                    );

                    $this->notificationService->notifyStageUpdate(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getDisplayName(),
                        $status->getDisplayName(),
                        $data['timestamp'],
                        'skipped',
                        true,
                        $data['nextStage']->getDisplayName()
                    );

                    Log::info('Successfully skipped pre-procurement conference', [
                        'procurement_id' => $data['procurementId'],
                        'next_stage' => $data['nextStage']->getDisplayName(),
                    ]);

                    $result = [
                        'success' => true,
                        'message' => $status->getDisplayName().'. Proceeding to '.$data['nextStage']->getDisplayName().'.',
                        'nextPhase' => $data['nextStage']->getDisplayName(),
                    ];
                } catch (Exception $e) {
                    Log::error('Failed to handle conference skipped', [
                        'procurement_id' => $data['procurementId'],
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        } catch (Exception $e) {
            Log::error('Error in publishPreProcurementConferenceDecision', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to process '.($data['currentStage']->getDisplayName() ?? 'Pre-Procurement Conference').' decision: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            if (! empty($result['nextPhase'])) {
                return redirect()
                    ->route('bac-secretariat.procurements-list.index')
                    ->with(['success' => true, 'message' => $result['message'], 'nextPhase' => $result['nextPhase']]);
            }

            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with(['success' => true, 'message' => $result['message']]);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadPreProcurementConferenceDocuments(PreProcurementConferenceDocumentsRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'meetingDate' => date('Y-m-d', strtotime($validated['meeting_date'] ?? '')),
                'participants' => $validated['participants'] ?? [],
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
                'nextStage' => StageEnums::BIDDING_DOCUMENTS,
                'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            ];

            $files = [];
            $metadata = [];
            $stageFolder = $data['currentStage']->getStoragePathSegment();

            $minutesFile = request()->file('minutes_file');
            $attendanceFile = request()->file('attendance_file');

            if ($minutesFile) {
                $files[] = $minutesFile;
                $metadata[] = [
                    'document_type' => 'Minutes',
                    'meeting_date' => $data['meetingDate'],
                    'participants' => $data['participants'],
                ];
            }
            if ($attendanceFile) {
                $files[] = $attendanceFile;
                $metadata[] = [
                    'document_type' => 'Attendance',
                    'meeting_date' => $data['meetingDate'],
                    'participants' => $data['participants'],
                ];
            }

            $metadataArray = $this->documentUploadService->uploadAndPrepare(
                $files,
                $metadata,
                $data['procurementId'],
                $data['procurementTitle'],
                $stageFolder
            );

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' after completing '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'completed',
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadPreProcurementConferenceDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.(isset($data['currentStage']) ? $data['currentStage']->getDisplayName() : 'Pre-Procurement Conference').' documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with(['success' => true, 'message' => $result['message']]);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function publishPreBidConferenceDecision(PreBidConferenceDecisionRequest $request): RedirectResponse
    {
        $data = [];
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'conferenceHeld' => $validated['conference_held'] ?? false,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::PRE_BID_CONFERENCE,
                'nextStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            ];

            if ($data['conferenceHeld']) {
                $status = StatusEnums::PRE_BID_CONFERENCE_HELD;

                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $status->getDisplayName(),
                    $status->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['currentStage']->getDisplayName(), // Stay in pre-bid conference
                    $data['userAddress'],
                    'Pre-bid conference held'
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $status->getDisplayName(),
                    $data['timestamp'],
                    'conference_held',
                    false,
                    ''
                );

                $result = [
                    'success' => true,
                    'message' => $status->getDisplayName().'. Pre-bid conference is in progress.',
                ];
            } else {
                $status = StatusEnums::PRE_BID_CONFERENCE_SKIPPED;

                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $status->getDisplayName(),
                    $status->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['nextStage']->getDisplayName(),
                    $data['userAddress'],
                    'Pre-bid conference skipped'
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $status->getDisplayName(),
                    $data['timestamp'],
                    'conference_skipped',
                    true,
                    $data['nextStage']->getDisplayName()
                );

                $result = [
                    'success' => true,
                    'message' => $status->getDisplayName().'. Proceeding to '.$data['nextStage']->getDisplayName().'.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in publishPreBidConferenceDecision', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to process '.(isset($data['currentStage']) ? $data['currentStage']->getDisplayName() : 'Pre-Bid Conference').' decision: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadPreBidConferenceDocuments(PreBidConferenceDocumentsRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'minutesFile' => request()->file('minutes_file'),
                'attendanceFile' => request()->file('attendance_file'),
                'meetingDate' => $validated['meeting_date'] ?? null,
                'participants' => $validated['participants'] ?? [],
                'needsBulletins' => $validated['needs_bulletins'] ?? false,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::PRE_BID_CONFERENCE,
                'nextStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                'status' => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            ];

            $files = [];
            $metadata = [];
            $stageFolder = $data['currentStage']->getStoragePathSegment();

            if ($data['minutesFile']) {
                $files[] = $data['minutesFile'];
                $metadata[] = [
                    'document_type' => 'Pre-Bid Minutes',
                    'meeting_date' => $data['meetingDate'],
                    'participants' => $data['participants'],
                ];
            }
            if ($data['attendanceFile']) {
                $files[] = $data['attendanceFile'];
                $metadata[] = [
                    'document_type' => 'Pre-Bid Attendance',
                    'meeting_date' => $data['meetingDate'],
                    'participants' => $data['participants'],
                ];
            }

            $metadataArray = $this->documentUploadService->uploadAndPrepare(
                $files,
                $metadata,
                $data['procurementId'],
                $data['procurementTitle'],
                $stageFolder
            );

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                StatusEnums::PRE_BID_CONFERENCE_HELD->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' after completing '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'completed',
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadPreBidConferenceDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload Pre-Bid Conference documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function publishSupplementalBidBulletinDecision(SupplementalBidBulletinDecisionRequest $request): RedirectResponse
    {

        $data = [];
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'supplementalBidNeeded' => $validated['supplemental_bid_needed'] ?? false,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                'nextStage' => StageEnums::BID_OPENING,
            ];

            if ($data['supplementalBidNeeded']) {
                $status = StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING;

                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $status->getDisplayName(),
                    $status->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['currentStage']->getDisplayName(), // Stay in supplemental bid bulletin stage
                    $data['userAddress'],
                    'Additional supplemental bid bulletins required'
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $status->getDisplayName(),
                    $data['timestamp'],
                    'more_bulletins_required',
                    false,
                    ''
                );

                $result = [
                    'success' => true,
                    'message' => $status->getDisplayName().'. Additional supplemental bid bulletins are required.',
                ];
            } else {
                $status = StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED;

                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $status->getDisplayName(),
                    $status->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['nextStage']->getDisplayName(), // Move to bid opening
                    $data['userAddress'],
                    'No additional supplemental bid bulletins needed'
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $status->getDisplayName(),
                    $data['timestamp'],
                    'bulletins_completed',
                    true,
                    $data['nextStage']->getDisplayName()
                );

                $result = [
                    'success' => true,
                    'message' => $status->getDisplayName().'. Proceeding to '.$data['nextStage']->getDisplayName().'.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in publishSupplementalBidBulletinDecision', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to process '.($data['currentStage']->getDisplayName() ?? 'Supplemental Bid Bulletin').' decision: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadSupplementalBidBulletinDocuments(SupplementalBidBulletinDocumentsRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'bulletinFile' => request()->file('bulletin_file'),
                'bulletinNumber' => $validated['bulletin_number'] ?? null,
                'bulletinTitle' => $validated['bulletin_title'] ?? null,
                'issueDate' => $validated['issue_date'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                'nextStage' => StageEnums::BID_OPENING,
                'completedStatus' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
            ];

            // Prepare metadata for bulletin file
            $metadataArray = [];
            if ($data['bulletinFile']) {
                $metadataArray = $this->documentUploadService->uploadAndPrepare(
                    [$data['bulletinFile']],
                    [
                        [
                            'document_type' => 'Supplemental Bid Bulletin',
                            'bulletin_number' => $data['bulletinNumber'],
                            'bulletin_title' => $data['bulletinTitle'],
                            'issue_date' => $data['issueDate'],
                        ],
                    ],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                );
            }

            if (empty($metadataArray)) {
                $result = [
                    'success' => false,
                    'message' => 'No bulletin file uploaded',
                ];
            } else {
                // Publish bulletin document asynchronously
                $this->blockchainOrchestrator->publishDocuments(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $data['status']->getDisplayName(),
                    $metadataArray,
                    $data['userAddress']
                );

                // Transition stage from ongoing to completed and proceed to Bid Opening
                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['status']->getDisplayName(),
                    $data['completedStatus']->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['nextStage']->getDisplayName(),
                    $data['userAddress'],
                    'Proceeding to '.$data['nextStage']->getDisplayName().' after '.$data['currentStage']->getDisplayName()
                );

                // Notify users of completion and next stage
                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $data['completedStatus']->getDisplayName(),
                    $data['timestamp'],
                    'completed',
                    true,
                    $data['nextStage']->getDisplayName()
                );

                $result = [
                    'success' => true,
                    'message' => $data['currentStage']->getDisplayName().' uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error uploading supplemental bid bulletin', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload supplemental bid bulletin: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadBiddingDocuments(BiddingDocumentsRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'biddingDocumentsFile' => request()->file('bidding_documents_file'),
                'issuanceDate' => $validated['issuance_date'] ?? null,
                'validityPeriodStart' => $validated['validity_period_start'] ?? null,
                'validityPeriodEnd' => $validated['validity_period_end'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::BIDDING_DOCUMENTS,
                'nextStage' => StageEnums::PRE_BID_CONFERENCE,
                'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
            ];

            $metadataArray = [];
            if ($data['biddingDocumentsFile']) {
                $baseMetadata = [
                    'document_type' => $data['currentStage']->getDisplayName(),
                    'issuance_date' => $data['issuanceDate'],
                    'validity_period' => [
                        'start_date' => $data['validityPeriodStart'],
                        'end_date' => $data['validityPeriodEnd'],
                    ],
                ];
                $metadataArray = $this->documentUploadService->uploadAndPrepare(
                    [$data['biddingDocumentsFile']],
                    [$data['metadata'] + $baseMetadata],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                );
            }

            // First publish documents with the published status asynchronously
            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            // Then handle stage transition - this is crucial for advancing stages!
            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->getDisplayName(), // From PRE_PROCUREMENT_CONFERENCE_COMPLETED
                $data['status']->getDisplayName(),                                    // To BIDDING_DOCUMENTS_PUBLISHED
                $data['currentStage']->getDisplayName(),                              // From BIDDING_DOCUMENTS
                $data['nextStage']->getDisplayName(),                                 // To PRE_BID_CONFERENCE
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' after publishing bidding documents'
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'published',
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' published successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadBiddingDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to publish '.StageEnums::BIDDING_DOCUMENTS->getDisplayName().': '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadBidOpeningDocuments(BidOpeningDocumentsRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'bidDocuments' => request()->file('bid_documents', []),
                'biddersData' => $validated['bidders_data'] ?? [],
                'openingDateTime' => $validated['opening_date_time'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::BID_OPENING,
                'nextStage' => StageEnums::BID_EVALUATION,
                'status' => StatusEnums::BIDS_OPENED,
            ];

            $metadataArray = [];
            foreach ($data['bidDocuments'] as $index => $file) {
                if ($file && isset($data['biddersData'][$index])) {
                    $bidderName = $data['biddersData'][$index]['bidder_name'] ?? 'Unknown Bidder';
                    $bidValue = floatval($data['biddersData'][$index]['bid_value'] ?? '0');

                    $metadataInfo = [
                        'document_type' => 'Bid Document',
                        'bidder_name' => $bidderName,
                        'bid_value' => number_format($bidValue, 2, '.', ''),
                        'opening_date_time' => $data['openingDateTime'],
                    ];

                    $fileMetadata = $this->documentUploadService->uploadAndPrepare(
                        [$file],
                        [$metadataInfo],
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getStoragePathSegment()
                    );
                    $metadataArray = array_merge($metadataArray, $fileMetadata);
                }
            }

            if (count($metadataArray) > 0) {
                try {
                    $this->blockchainOrchestrator->publishDocuments(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getDisplayName(),
                        $data['status']->getDisplayName(),
                        $metadataArray,
                        $data['userAddress']
                    );

                    $this->blockchainOrchestrator->handleStageTransition(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['status']->getDisplayName(),
                        $data['status']->getDisplayName(),
                        $data['currentStage']->getDisplayName(),
                        $data['nextStage']->getDisplayName(),
                        $data['userAddress'],
                        'Proceeding to '.$data['nextStage']->getDisplayName().' stage after opening bids'
                    );

                    $this->notificationService->notifyStageUpdate(
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getDisplayName(),
                        $data['status']->getDisplayName(),
                        $data['timestamp'],
                        count($metadataArray),
                        'opened',
                        true,
                        $data['nextStage']->getDisplayName()
                    );

                    $result = [
                        'success' => true,
                        'message' => count($metadataArray).' bid documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().' stage.',
                    ];
                } catch (Exception $e) {
                    Log::error('Error in processBidDocuments', [
                        'error' => $e->getMessage(),
                        'procurement_id' => $data['procurementId'],
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $result = [
                        'success' => false,
                        'message' => 'Failed to process bid documents. Please try again.',
                        'errors' => [
                            'bid_documents' => 'Failed to upload bid documents: '.$e->getMessage(),
                        ],
                    ];
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'No valid bid documents were provided.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in uploadBidOpeningDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload bid documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadBidEvaluationDocuments(BidEvaluationDocumentsRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'summaryFile' => request()->file('summary_file'),
                'abstractFile' => request()->file('abstract_file'),
                'evaluationDate' => $validated['evaluation_date'] ?? null,
                'evaluatorNames' => $validated['evaluator_names'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::BID_EVALUATION,
                'nextStage' => StageEnums::POST_QUALIFICATION,
                'status' => StatusEnums::BIDS_EVALUATED,
            ];

            $metadataArray = [];
            if ($data['summaryFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['summaryFile']],
                    [[
                        'document_type' => 'Evaluation Summary',
                        'evaluation_date' => $data['evaluationDate'],
                        'evaluator_names' => $data['evaluatorNames'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            if ($data['abstractFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['abstractFile']],
                    [[
                        'document_type' => 'Abstract',
                        'evaluation_date' => $data['evaluationDate'],
                        'evaluator_names' => $data['evaluatorNames'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' after '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'evaluated',
                count($metadataArray),
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadBidEvaluationDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.StageEnums::BID_EVALUATION->getDisplayName().' documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadPostQualificationDocuments(PostQualificationDocumentsRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'postQualificationReport' => request()->file('post_qualification_report'),
                'twgCertification' => request()->file('twg_certification'),
                'noticeOfPostQualification' => request()->file('notice_of_post_qualification'),
                'submissionDate' => $validated['submission_date'] ?? null,
                'outcome' => $validated['outcome'] ?? false,
                'remarks' => $validated['remarks'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::POST_QUALIFICATION,
                'nextStage' => StageEnums::BAC_RESOLUTION,
                'status' => ($validated['outcome'] ?? false) ? StatusEnums::POST_QUALIFICATION_VERIFIED : StatusEnums::POST_QUALIFICATION_FAILED,
            ];

            $metadataArray = [];
            $baseMetadata = [
                'submission_date' => $data['submissionDate'],
                'outcome' => $data['outcome'] ? 'Verified' : 'Failed',
                'remarks' => $data['remarks'],
            ];
            $files = [
                [
                    'file' => $data['postQualificationReport'],
                    'documentType' => 'Post Qualification Report',
                    'required' => true,
                ],
                [
                    'file' => $data['twgCertification'],
                    'documentType' => 'TWG Certification',
                    'required' => false,
                ],
                [
                    'file' => $data['noticeOfPostQualification'],
                    'documentType' => 'Notice of Post Qualification',
                    'required' => true,
                ],
            ];

            foreach ($files as $fileInfo) {
                if ($fileInfo['file']) {
                    $fileMetadata = array_merge(
                        ['document_type' => $fileInfo['documentType']],
                        $baseMetadata
                    );
                    $metadataArray = array_merge(
                        $metadataArray,
                        $this->documentUploadService->uploadAndPrepare(
                            [$fileInfo['file']],
                            [$fileMetadata],
                            $data['procurementId'],
                            $data['procurementTitle'],
                            $data['currentStage']->getStoragePathSegment()
                        )
                    );
                } elseif ($fileInfo['required']) {
                    throw new \Exception("Required document {$fileInfo['documentType']} is missing");
                }
            }

            // First publish the documents asynchronously
            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            if ($data['outcome']) {
                // If verification passed, proceed to next stage
                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['status']->getDisplayName(),
                    $data['status']->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['nextStage']->getDisplayName(),
                    $data['userAddress'],
                    'Proceeding to '.$data['nextStage']->getDisplayName().' after successful post-qualification'
                );
            } else {
                // If verification failed, log the failure event
                $this->eventLogger->logEvent(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    'Post-qualification failed - procurement process halted',
                    0,
                    $data['userAddress'],
                    'status_update',
                    'workflow',
                    'warning',
                    now()->addSecond()->toIso8601String()
                );
            }

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                count($metadataArray),
                $data['outcome'],
                $data['outcome']
            );

            $message = $data['currentStage']->getDisplayName().' documents uploaded successfully';
            if ($data['outcome']) {
                $message .= '. Proceeding to '.$data['nextStage']->getDisplayName().'.';
            } else {
                $message .= '. Post-qualification failed - procurement process halted.';
            }

            $result = [
                'success' => true,
                'message' => $message,
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadPostQualificationDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.StageEnums::POST_QUALIFICATION->getDisplayName().' documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadBacResolutionDocument(BacResolutionDocumentRequest $request): RedirectResponse
    {

        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'bacResolutionFile' => request()->file('bac_resolution_file'),
                'issuanceDate' => $validated['issuance_date'] ?? null,
                'signatoryDetails' => $validated['signatory_details'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::BAC_RESOLUTION,
                'nextStage' => StageEnums::NOTICE_OF_AWARD,
                'status' => StatusEnums::RESOLUTION_RECORDED,
            ];

            $metadataArray = [];
            if ($data['bacResolutionFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['bacResolutionFile']],
                    [[
                        'document_type' => 'BAC Resolution',
                        'issuance_date' => $data['issuanceDate'],
                        'signatory_details' => $data['signatoryDetails'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' after recording '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'recorded',
                count($metadataArray),
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' document uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadBacResolutionDocument', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.StageEnums::BAC_RESOLUTION->getDisplayName().' document: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadNoaDocument(NoticeOfAwardDocumentRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'noaFile' => request()->file('noa_file'),
                'issuanceDate' => $validated['issuance_date'] ?? null,
                'signatoryDetails' => $validated['signatory_details'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::NOTICE_OF_AWARD,
                'nextStage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                'status' => StatusEnums::AWARDED,
            ];

            $metadataArray = [];
            if ($data['noaFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['noaFile']],
                    [[
                        'document_type' => 'Notice of Award',
                        'issuance_date' => $data['issuanceDate'],
                        'signatory_details' => $data['signatoryDetails'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            // Log publication to PhilGEPS
            $publicationTimestamp = now()->addSecond()->toIso8601String();
            $this->eventLogger->logEvent(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                'Published Notice of Award to PhilGEPS',
                1,
                $data['userAddress'],
                'publication',
                'workflow',
                'info',
                $publicationTimestamp
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' stage after recording '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'awarded',
                count($metadataArray),
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' document uploaded and published successfully. Proceeding to '.$data['nextStage']->getDisplayName().' stage.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadNoaDocument', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.StageEnums::NOTICE_OF_AWARD->getDisplayName().' document: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadPerformanceBondContractAndPoDocuments(PerformanceBondContractAndPoDocumentsRequest $request): RedirectResponse
    {
        $processedDocumentsCount = 0;
        $errors = [];
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'performanceBondFile' => request()->file('performance_bond_file'),
                'contractFile' => request()->file('contract_file'),
                'poFile' => request()->file('po_file'),
                'submissionDate' => $validated['submission_date'] ?? null,
                'bondAmount' => $validated['bond_amount'] ?? null,
                'signingDate' => $validated['signing_date'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                'nextStage' => StageEnums::NOTICE_TO_PROCEED,
                'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            ];

            // Helper for single document metadata
            $prepareSingleDocumentMetadata = function ($file, $documentType, $specificMetadata, $commonData) {
                $metadataResult = $this->documentUploadService->uploadAndPrepare(
                    [$file],
                    [['document_type' => $documentType] + $specificMetadata],
                    $commonData['procurementId'],
                    $commonData['procurementTitle'],
                    $documentType
                );
                if (empty($metadataResult) || ! isset($metadataResult[0])) {
                    throw new \Exception("Failed to prepare metadata for document type: {$documentType}");
                }

                return $metadataResult[0];
            };

            // Helper for publishing a single document
            $publishSingleDocument = function ($documentMetadata, $commonData) {
                $this->blockchainOrchestrator->publishDocuments(
                    $commonData['procurementId'],
                    $commonData['procurementTitle'],
                    $commonData['currentStage']->getDisplayName(),
                    $commonData['status']->getDisplayName(),
                    [$documentMetadata],
                    $commonData['userAddress']
                );
            };

            // Process Performance Bond
            if ($data['performanceBondFile']) {
                try {
                    $bondMetadata = $prepareSingleDocumentMetadata(
                        $data['performanceBondFile'],
                        'Performance Bond',
                        ['submission_date' => $data['submissionDate'], 'bond_amount' => $data['bondAmount']],
                        $data
                    );
                    $publishSingleDocument($bondMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Performance Bond: '.$e->getMessage();
                    Log::error('Error processing Performance Bond', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Process Contract
            if ($data['contractFile']) {
                try {
                    $contractMetadata = $prepareSingleDocumentMetadata(
                        $data['contractFile'],
                        'Contract',
                        ['signing_date' => $data['signingDate']],
                        $data
                    );
                    $publishSingleDocument($contractMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Contract: '.$e->getMessage();
                    Log::error('Error processing Contract', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Process Purchase Order (PO)
            if ($data['poFile']) {
                try {
                    $poMetadata = $prepareSingleDocumentMetadata(
                        $data['poFile'],
                        'Purchase Order',
                        ['signing_date' => $data['signingDate']],
                        $data
                    );
                    $publishSingleDocument($poMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Purchase Order: '.$e->getMessage();
                    Log::error('Error processing Purchase Order', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Check if at least one document was processed successfully
            if ($processedDocumentsCount === 0 && ! empty($errors)) {
                throw new \Exception(implode('; ', $errors));
            } elseif ($processedDocumentsCount === 0) {
                throw new \Exception('No document files were provided for upload.');
            }

            // Finalize stage processing
            if ($processedDocumentsCount > 0) {
                $this->blockchainOrchestrator->handleStageTransition(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['status']->getDisplayName(),
                    $data['status']->getDisplayName(),
                    $data['currentStage']->getDisplayName(),
                    $data['nextStage']->getDisplayName(),
                    $data['userAddress'],
                    'Proceeding to '.$data['nextStage']->getDisplayName().' stage after recording '.$processedDocumentsCount.' document(s)'
                );

                $this->notificationService->notifyStageUpdate(
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getDisplayName(),
                    $data['status']->getDisplayName(),
                    $data['timestamp'],
                    'recorded',
                    $processedDocumentsCount,
                    true,
                    $data['nextStage']->getDisplayName()
                );
            }

            $successMessage = $data['currentStage']->getDisplayName().' documents processed successfully ('.$processedDocumentsCount.' files). Proceeding to '.$data['nextStage']->getDisplayName().' stage.';
            if (! empty($errors)) {
                $successMessage .= ' Some files failed: '.implode('; ', $errors);
            }

            $result = [
                'success' => true,
                'message' => $successMessage,
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadPerformanceBondContractAndPoDocuments', ['error' => $e->getMessage()]);
            $errorMessage = 'Failed to upload '.StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName().' documents: '.$e->getMessage();
            if (! empty($errors)) {
                $errorMessage .= ' Individual errors: '.implode('; ', $errors);
            }
            $result = [
                'success' => false,
                'message' => $errorMessage,
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadNTPDocument(NoticeToProceedDocumentRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'ntpFile' => request()->file('ntp_file'),
                'issuanceDate' => $validated['issuance_date'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(\Illuminate\Support\Facades\Auth::user())->blockchain_address,
                'currentStage' => StageEnums::NOTICE_TO_PROCEED,
                'nextStage' => StageEnums::MONITORING,
                'status' => StatusEnums::NTP_RECORDED,
            ];

            $metadataArray = [];
            if ($data['ntpFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['ntpFile']],
                    [[
                        'document_type' => 'Notice to Proceed',
                        'issuance_date' => $data['issuanceDate'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            // Log publication to PhilGEPS
            $publicationTimestamp = now()->addSecond()->toIso8601String();
            $this->eventLogger->logEvent(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                'Published NTP to PhilGEPS',
                1,
                $data['userAddress'],
                'publication',
                'workflow',
                'info',
                $publicationTimestamp
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' stage after recording '.$data['currentStage']->getDisplayName()
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                'recorded',
                count($metadataArray),
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' document uploaded and published successfully. Proceeding to '.$data['nextStage']->getDisplayName().' stage.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadNTPDocument', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload '.StageEnums::NOTICE_TO_PROCEED->getDisplayName().' document: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadMonitoringDocument(MonitoringDocumentRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'complianceFile' => request()->file('compliance_file'),
                'reportDate' => $validated['report_date'] ?? null,
                'reportNotes' => $validated['report_notes'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::MONITORING,
                'status' => StatusEnums::MONITORING_COMPLETED,
                'nextStage' => StageEnums::COMPLETION,
            ];

            $metadataArray = [];
            if ($data['complianceFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['complianceFile']],
                    [[
                        'document_type' => 'Compliance Report',
                        'report_date' => $data['reportDate'],
                        'report_notes' => $data['reportNotes'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            // --- Handle Transition to Completion Stage ---
            $transitionTimestamp = now()->toIso8601String();
            $transitionStatus = StatusEnums::COMPLETED;

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $transitionStatus->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Transitioning to '.$data['nextStage']->getDisplayName().' after recording '.$data['currentStage']->getDisplayName().' documents.'
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $transitionStatus->getDisplayName(),
                $transitionTimestamp,
                'transitioned',
                count($metadataArray),
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' documents uploaded and process transitioned to '.$data['nextStage']->getDisplayName().' stage successfully.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadMonitoringDocument', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload compliance report: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }

    public function uploadCompletionDocuments(CompletionDocumentsRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $data = [
                'procurementId' => $validated['procurement_id'] ?? null,
                'procurementTitle' => $validated['procurement_title'] ?? null,
                'completionFile' => request()->file('completion_file'),
                'completionDate' => $validated['completion_date'] ?? null,
                'completionNotes' => $validated['completion_notes'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'userAddress' => optional(Auth::user())->blockchain_address,
                'currentStage' => StageEnums::COMPLETION,
                'nextStage' => StageEnums::COMPLETED,
                'status' => StatusEnums::COMPLETED,
            ];

            $metadataArray = [];
            if ($data['completionFile']) {
                $metadataArray = array_merge($metadataArray, $this->documentUploadService->uploadAndPrepare(
                    [$data['completionFile']],
                    [[
                        'document_type' => 'Certificate of Completion',
                        'completion_date' => $data['completionDate'],
                        'notes' => $data['completionNotes'],
                    ]],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment()
                ));
            }

            $this->blockchainOrchestrator->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            $this->blockchainOrchestrator->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Marking procurement as '.$data['nextStage']->getDisplayName().' after uploading '.$data['currentStage']->getDisplayName().' documents.'
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'],
                count($metadataArray),
                'completed',
                true,
                $data['nextStage']->getDisplayName()
            );

            $result = [
                'success' => true,
                'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Procurement process is now '.$data['status']->getDisplayName().'.',
            ];
        } catch (Exception $e) {
            Log::error('Error in uploadCompletionDocuments', ['error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Failed to upload completion documents: '.$e->getMessage(),
            ];
        }

        if ($result['success']) {
            return redirect()
                ->route('bac-secretariat.procurements-list.index')
                ->with('success', $result['message']);
        }

        return redirect()->back()->withErrors(['error' => $result['message']]);
    }
}
