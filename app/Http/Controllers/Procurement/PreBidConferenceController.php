<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class PreBidConferenceController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/pre-bid-conference-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::PRE_BID_CONFERENCE->getDisplayName(),
            ],
        ]);
    }

    public function publishDecision(PreBidConferenceDecisionRequest $request): RedirectResponse
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

    public function uploadDocuments(PreBidConferenceDocumentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $meetingDate = $validated['meeting_date'];
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
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            $files,
            $metadata,
            'Pre-Bid Conference Documents'
        );
    }
}
