<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\InitiateProcurementRequest;
use App\Libraries\MultiChain\Manager;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use App\Services\Publishers\ProcurementOrchestrator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementInitiationController extends BaseController
{
    use HasProcurementSupport;

    // Issue #3 & #13 Fix: Inject orchestrator for atomic workflow operations
    protected Manager $multichain;

    protected ProcurementDataService $procurementDataService;

    public function __construct(
        Manager $multichain,
        ProcurementDataService $procurementDataService,
        private readonly ProcurementRepository $procurements,
        private readonly ProcurementOrchestrator $orchestrator
    ) {
        // Note: Orchestrator handles publishers internally for atomic operations (Issue #3)
        // We still need multichain/dataService for read operations
        $this->multichain = $multichain;
        $this->procurementDataService = $procurementDataService;
        $this->applyProcurementMiddleware();
    }

    public function index(): Response
    {
        return Inertia::render('bac-secretariat/procurement/procurement-initiation-list', [
            'procurements' => $this->procurements->all(),
        ]);
    }

    public function show(?string $id = null): Response
    {
        if ($id) {
            $procurement = $this->procurements->find($id);

            if (! $procurement) {
                abort(404);
            }

            return Inertia::render('bac-secretariat/procurement-stage/procurement-initiation-show', [
                'procurement' => $procurement,
                'history' => $this->procurements->getHistory($id),
            ]);
        }

        return Inertia::render('bac-secretariat/procurement-initiation', [
            'categories' => collect(ProcurementCategoryEnums::cases())
                ->map(fn ($category) => [
                    'value' => $category->value,
                    'label' => $category->getDisplayName(),
                    'description' => $category->getDescription(),
                ])
                ->toArray(),
            'procurementModes' => collect(ProcurementModeEnums::cases())
                ->map(fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->getDisplayName(),
                    'description' => $case->getDescription(),
                    'threshold' => $case->thresholdAmount(),
                    'requires_philgeps' => $case->requiresPhilGEPS(),
                    'requires_bac_resolution' => $case->requiresBACResolution(),
                ])
                ->values(),
            'documentTypes' => collect(DocumentTypeEnums::getInitiationDocuments())
                ->map(fn ($docType) => [
                    'value' => $docType->value,
                    'label' => $docType->getDisplayName(),
                    'description' => $docType->getDescription(),
                    'is_mandatory' => $docType->isMandatory(),
                    'requirement_summary' => $docType->getRequirementSummary(),
                ])
                ->values(),
        ]);
    }

    /**
     * Initiate procurement with complete metadata and publish to blockchain
     */
    public function initiate(InitiateProcurementRequest $request): RedirectResponse
    {
        $prNumber = $request->input('pr_number');
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        // Check if PR number already exists (Issue #5: Idempotency)
        $existing = $this->procurements->findByProcurement($prNumber);
        if ($existing) {
            return back()->withErrors([
                'pr_number' => "PR Number {$prNumber} already exists. Please use a different PR number.",
            ])->withInput();
        }

        $procurement = new ProcurementData(
            prNumber: $prNumber,
            ppmpReference: $request->input('ppmp_reference'),
            title: $request->input('title'),
            description: $request->input('description'),
            abcAmount: (float) $request->input('abc_amount'),
            fundingSource: $request->input('funding_source'),
            category: ProcurementCategoryEnums::from($request->input('category')),
            procurementMode: ProcurementModeEnums::from($request->input('procurement_mode')),
            office: $request->input('office'),
            endUser: $request->input('end_user'),
            purpose: $request->input('purpose'),
            deliveryLocation: $request->input('delivery_location'),
            deliveryDate: Carbon::parse($request->input('delivery_date')),
            deliveryTermDays: $request->input('delivery_term_days') ? (int) $request->input('delivery_term_days') : null,
            preparedBy: $request->input('prepared_by') ?? $user->name,
            bacResolutionNumber: null,
            bacResolutionDate: null,
            philgepsReference: null,
            philgepsPostingDate: null,
            approvedBy: null,
            approvalDate: null,
            status: 'draft',
            userId: (string) $user->id,
            createdAt: now(),
        );

        // Prepare files array for orchestrator
        $filesData = [];
        $requestFiles = $request->file('files', []);
        $documentTypes = $request->input('document_types', []);
        $documentDescriptions = $request->input('document_descriptions', []);

        foreach ($requestFiles as $index => $file) {
            $docTypeValue = $documentTypes[$index] ?? null;
            $docType = $docTypeValue ? DocumentTypeEnums::tryFrom($docTypeValue) : null;

            // Skip invalid document types
            if (! $docType) {
                continue;
            }

            // Check file size (skip files larger than 2MB to avoid blockchain transaction limits)
            if ($file->getSize() > 2 * 1024 * 1024) {
                Log::warning('File too large for blockchain', [
                    'pr_number' => $prNumber,
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);

                continue;
            }

            $filesData[] = [
                'file' => $file,
                'document_type' => DocumentTypeEnums::from($docType->value),
                'description' => $documentDescriptions[$index] ?? $docType->getDescription(),
                'metadata' => [
                    'is_mandatory' => $docType->isMandatory(),
                    'requirement_summary' => $docType->getRequirementSummary(),
                ],
            ];
        }

        try {
            // Issue #3 Fix: Use orchestrator for atomic workflow
            // Blockchain is single source of truth - all operations coordinated
            $result = $this->orchestrator->initiateProcurement(
                procurementData: [
                    'pr_number' => $prNumber,
                    'ppmp_reference' => $procurement->ppmpReference,
                    'title' => $procurement->title,
                    'description' => $procurement->description,
                    'abc_amount' => $procurement->abcAmount,
                    'funding_source' => $procurement->fundingSource,
                    'category' => $procurement->category->value,
                    'procurement_mode' => $procurement->procurementMode->value,
                    'office' => $procurement->office,
                    'end_user' => $procurement->endUser,
                    'purpose' => $procurement->purpose,
                    'delivery_location' => $procurement->deliveryLocation,
                    'delivery_date' => $procurement->deliveryDate->toDateString(),
                    'delivery_term_days' => $procurement->deliveryTermDays,
                    'prepared_by' => $procurement->preparedBy,
                    'status' => $procurement->status,
                    'user_id' => $procurement->userId,
                    'user_address' => $userAddress,
                    'created_at' => $procurement->createdAt->toIso8601String(),
                ],
                files: $filesData,
                userName: $user->name
            );

            // Check result and handle accordingly
            if (! $result['success']) {
                Log::error('Orchestrator returned failure', [
                    'pr_number' => $prNumber,
                    'result' => $result,
                ]);

                return redirect()->back()->withErrors([
                    'error' => $result['message'] ?? 'Failed to initiate procurement. Please try again.',
                ])->withInput();
            }

            // Success - redirect to publishing status page
            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $prNumber,
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'return_url' => route('bac-secretariat.procurements.show', $prNumber),
            ])->with('success', $result['message']);
        } catch (\Exception $e) {
            \Log::error('Failed to initiate procurement', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to initiate procurement. Please try again.',
            ]);
        }
    }
}
