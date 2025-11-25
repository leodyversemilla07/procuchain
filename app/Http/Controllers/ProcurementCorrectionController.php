<?php

namespace App\Http\Controllers;

use App\Http\Requests\Procurement\CorrectProcurementRequest;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Publishers\ProcurementCorrectionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProcurementCorrectionController extends Controller
{
    public function __construct(
        private readonly ProcurementCorrectionPublisher $procurementCorrectionPublisher,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementCorrectionRepository $procurementCorrectionRepository,
        private readonly DocumentRepository $documentRepository
    ) {}

    /**
     * Submit a correction for procurement metadata.
     */
    public function correctProcurement(CorrectProcurementRequest $request, string $prNumber): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Fetch the original procurement from blockchain
            $originalProcurement = $this->procurementRepository->findByProcurement($prNumber);

            if (! $originalProcurement) {
                return redirect()->back()->withErrors(['error' => 'Procurement not found in blockchain.']);
            }

            // Get user's blockchain address
            $userAddress = auth()->user()->blockchain_address ?? '';

            // Extract corrected data from request
            $correctedData = $this->extractCorrectedData($validated);

            // Use ProcurementCorrectionPublisher for atomic correction publishing
            $result = $this->procurementCorrectionPublisher->publishCorrection(
                originalProcurement: $originalProcurement,
                correctedData: $correctedData,
                reason: $validated['correction_reason'],
                correctedBy: auth()->user()->name ?? 'System',
                userAddress: $userAddress
            );

            Log::info('Procurement correction published to blockchain', [
                'pr_number' => $prNumber,
                'correction_txid' => $result['correction_txid'],
                'changed_fields' => array_keys($result['changed_fields']),
            ]);

            // Send notification to relevant stakeholders
            $this->sendCorrectionNotification($originalProcurement, $result, $validated['correction_reason']);

            return redirect()->back()->with('success', 'Procurement correction submitted successfully. Correction TX: '.$result['correction_txid']);
        } catch (\Exception $e) {
            Log::error('Failed to submit procurement correction', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to submit correction: '.$e->getMessage()]);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getProcurementCorrectionHistory(Request $request, string $procurement): JsonResponse
    {
        try {
            // Fetch corrections for this procurement using repository
            $correctionDtos = $this->procurementCorrectionRepository->findByProcurement($procurement);

            // Map corrections to response format
            $corrections = collect($correctionDtos)
                ->map(function ($correctionDto) {
                    return [
                        'pr_number' => $correctionDto->prNumber,
                        'timestamp' => $correctionDto->timestamp->toIso8601String(),
                        'reason' => $correctionDto->reason,
                        'corrected_by' => $correctionDto->correctedBy,
                        'correction_type' => $correctionDto->correctionType,
                        'correction_type_display' => ucwords(str_replace('_', ' ', $correctionDto->correctionType)),
                        'changed_fields' => $correctionDto->getChangedFields(),
                        'metadata' => $correctionDto->toBlockchainArray(),
                    ];
                })
                ->sortByDesc('timestamp')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'corrections' => $corrections,
                'count' => count($corrections),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement correction history from blockchain', [
                'pr_number' => $procurement,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve correction history: '.$e->getMessage(),
                'corrections' => [],
            ], 500);
        }
    }

    /**
     * Check if a procurement has any corrections.
     */
    public function checkProcurementCorrection(Request $request, string $prNumber): JsonResponse
    {
        $validated = $request->validate([
            'pr_number' => 'required|string',
        ]);

        try {
            $hasCorrections = $this->procurementCorrectionRepository->hasCorrections($prNumber);
            $latestCorrection = $hasCorrections ? $this->procurementCorrectionRepository->getLatest($prNumber) : null;

            return response()->json([
                'success' => true,
                'has_corrections' => $hasCorrections,
                'latest_correction' => $latestCorrection,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check procurement corrections: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the procurement corrections page for a procurement.
     */
    public function showProcurementCorrectionsPage(string $prNumber): InertiaResponse
    {
        try {
            // Fetch the procurement from blockchain
            $procurement = $this->procurementRepository->findByProcurement($prNumber);

            if (! $procurement) {
                abort(404, 'Procurement not found in blockchain');
            }

            // Check if procurement has corrections
            $hasCorrections = $this->procurementCorrectionRepository->hasCorrections($prNumber);
            $latestCorrection = $hasCorrections ? $this->procurementCorrectionRepository->getLatest($prNumber) : null;

            // Fetch documents for this procurement
            $documents = $this->documentRepository->findByProcurement($prNumber);
            $formattedDocuments = [];
            foreach ($documents as $index => $doc) {
                $formattedDocuments[] = [
                    'id' => $index,
                    'pr_number' => $doc->prNumber,
                    'file_key' => $doc->fileKey,
                    'document_type' => $doc->documentType,
                    'document_type_display' => $this->formatDocumentType($doc->documentType),
                    'stage' => $doc->stage,
                    'stage_display' => $this->formatStage($doc->stage),
                    'file_size' => $doc->fileSize,
                    'hash' => $doc->hash,
                    'timestamp' => $doc->timestamp->toIso8601String(),
                    'blockchain_txid' => $doc->dataTxid,
                    'uploaded_by' => $doc->uploadedBy,
                    'metadata' => [
                        'file_name' => $doc->fileName,
                        'mime_type' => $doc->mimeType,
                        'description' => $doc->description,
                        'metadata_txid' => $doc->metadataTxid,
                        'stage_metadata' => $doc->stageMetadata,
                    ],
                ];
            }

            return Inertia::render('procurements/procurement-corrections', [
                'procurement' => [
                    'pr_number' => $procurement->prNumber,
                    'title' => $procurement->title,
                    'description' => $procurement->description,
                    'abc_amount' => $procurement->abcAmount,
                    'formatted_abc_amount' => $procurement->getFormattedAbcAmount(),
                    'funding_source' => $procurement->fundingSource,
                    'category' => $procurement->category->value,
                    'category_display' => $procurement->category->getDisplayName(),
                    'procurement_mode' => $procurement->procurementMode->value,
                    'procurement_mode_display' => $procurement->procurementMode->getDisplayName(),
                    'office' => $procurement->office,
                    'end_user' => $procurement->endUser,
                    'purpose' => $procurement->purpose,
                    'delivery_location' => $procurement->deliveryLocation,
                    'delivery_date' => $procurement->getFormattedDeliveryDate(),
                    'delivery_term_days' => $procurement->deliveryTermDays,
                    'bac_resolution_number' => $procurement->bacResolutionNumber,
                    'bac_resolution_date' => $procurement->getFormattedBacResolutionDate(),
                    'philgeps_reference' => $procurement->philgepsReference,
                    'philgeps_posting_date' => $procurement->getFormattedPhilgepsPostingDate(),
                    'approved_by' => $procurement->approvedBy,
                    'approval_date' => $procurement->getFormattedApprovalDate(),
                    'status' => $procurement->status,
                    'has_corrections' => $hasCorrections,
                    'latest_correction' => $latestCorrection ? [
                        'timestamp' => $latestCorrection->timestamp->toIso8601String(),
                        'corrected_by' => $latestCorrection->correctedBy,
                        'reason' => $latestCorrection->reason,
                        'changed_fields' => $latestCorrection->getChangedFields(),
                    ] : null,
                ],
                'documents' => $formattedDocuments,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch procurement correction data from blockchain', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load procurement data from blockchain');
        }
    }

    /**
     * Extract corrected data from validated request
     */
    private function extractCorrectedData(array $validated): array
    {
        $correctedData = [];

        // Basic information
        if (isset($validated['title'])) {
            $correctedData['title'] = $validated['title'];
        }
        if (isset($validated['description'])) {
            $correctedData['description'] = $validated['description'];
        }
        if (isset($validated['abc_amount'])) {
            $correctedData['abc_amount'] = (float) $validated['abc_amount'];
        }
        if (isset($validated['funding_source'])) {
            $correctedData['funding_source'] = $validated['funding_source'];
        }
        if (isset($validated['category'])) {
            $correctedData['category'] = $validated['category'];
        }
        if (isset($validated['procurement_mode'])) {
            $correctedData['procurement_mode'] = $validated['procurement_mode'];
        }

        // Office and organizational details
        if (isset($validated['office'])) {
            $correctedData['office'] = $validated['office'];
        }
        if (isset($validated['end_user'])) {
            $correctedData['end_user'] = $validated['end_user'];
        }
        if (isset($validated['purpose'])) {
            $correctedData['purpose'] = $validated['purpose'];
        }

        // Delivery information
        if (isset($validated['delivery_location'])) {
            $correctedData['delivery_location'] = $validated['delivery_location'];
        }
        if (isset($validated['delivery_date'])) {
            $correctedData['delivery_date'] = $validated['delivery_date'];
        }
        if (isset($validated['delivery_term_days'])) {
            $correctedData['delivery_term_days'] = (int) $validated['delivery_term_days'];
        }

        // BAC Resolution
        if (isset($validated['bac_resolution_number'])) {
            $correctedData['bac_resolution_number'] = $validated['bac_resolution_number'];
        }
        if (isset($validated['bac_resolution_date'])) {
            $correctedData['bac_resolution_date'] = $validated['bac_resolution_date'];
        }

        // PhilGEPS
        if (isset($validated['philgeps_reference'])) {
            $correctedData['philgeps_reference'] = $validated['philgeps_reference'];
        }
        if (isset($validated['philgeps_posting_date'])) {
            $correctedData['philgeps_posting_date'] = $validated['philgeps_posting_date'];
        }

        // Approval
        if (isset($validated['approved_by'])) {
            $correctedData['approved_by'] = $validated['approved_by'];
        }
        if (isset($validated['approval_date'])) {
            $correctedData['approval_date'] = $validated['approval_date'];
        }

        return $correctedData;
    }

    /**
     * Format stage enum to display name
     */
    private function formatStage(?string $stage): string
    {
        if (! $stage || $stage === 'Unknown') {
            return 'Unknown';
        }

        // Try to match the stage with StageEnums
        try {
            $stageEnum = \App\Enums\StageEnums::tryFrom($stage);
            if ($stageEnum) {
                return $stageEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to format stage', ['stage' => $stage, 'error' => $e->getMessage()]);
        }

        // Fallback: return as is if no enum match
        return $stage;
    }

    /**
     * Format document type to display name
     */
    private function formatDocumentType(?string $documentType): string
    {
        if (! $documentType || $documentType === 'Unknown') {
            return 'Unknown Document';
        }

        // Try to match the document type with DocumentTypeEnums
        try {
            $documentTypeEnum = \App\Enums\DocumentTypeEnums::fromString($documentType);
            if ($documentTypeEnum) {
                return $documentTypeEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to format document type', ['document_type' => $documentType, 'error' => $e->getMessage()]);
        }

        // Fallback: Convert snake_case or kebab-case to Title Case if no enum match
        if (! preg_match('/[A-Z\s]/', $documentType)) {
            return ucwords(str_replace(['_', '-'], ' ', $documentType));
        }

        // Already formatted, return as is
        return $documentType;
    }
}
