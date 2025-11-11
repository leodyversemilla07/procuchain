<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class PreProcurementConferenceController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/pre-procurement-conference-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
            ],
        ]);
    }

    public function publishDecision(PreProcurementConferenceDecisionRequest $request): RedirectResponse
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

    public function uploadDocuments(PreProcurementConferenceDocumentsRequest $request): RedirectResponse
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
}
