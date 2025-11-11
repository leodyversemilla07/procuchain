<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PostQualificationDocumentsRequest;
use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class PostQualificationController extends BaseController
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

        return Inertia::render('bac-secretariat/procurement-stage/post-qualification-upload', [
            'procurement' => [
                'id' => $id,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $procurement['stage'] ?? StageEnums::POST_QUALIFICATION->getDisplayName(),
            ],
        ]);
    }

    public function uploadDocuments(PostQualificationDocumentsRequest $request): RedirectResponse
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
}
