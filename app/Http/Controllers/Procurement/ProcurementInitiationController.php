<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementInitiationController extends BaseController
{
    use HasProcurementSupport;

    public function __construct(
        MultichainService $multiChain,
        ProcurementPublishingService $publishingService
    ) {
        $this->initializeProcurementSupport($multiChain, $publishingService);
        $this->applyProcurementMiddleware();
    }

    public function show(): Response
    {
        return Inertia::render('bac-secretariat/procurement-stage/procurement-initiation');
    }

    public function publish(ProcurementInitiationRequest $request): RedirectResponse
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
}
