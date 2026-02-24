<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Requests\Procurement\CorrectProcurementRequest;
use App\Jobs\BlockchainWriteJob;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use App\Services\Publishers\ProcurementCorrectionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProcurementCorrectionController extends Controller
{
    public function __construct(
        private readonly ProcurementCorrectionPublisher $procurementCorrectionPublisher,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementCorrectionRepository $procurementCorrectionRepository,
        private readonly CorrectionRepository $correctionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ProcurementDataService $procurementDataService
    ) {}

    public function correctProcurement(CorrectProcurementRequest $request, string $prNumber): JsonResponse
    {
        $validated = $request->validated();

        try {
            $originalProcurement = $this->procurementRepository->findByProcurement($prNumber);

            if (! $originalProcurement) {
                Log::warning('Procurement not found in METADATA stream for correction, attempting fallback to STATUS stream', [
                    'pr_number' => $prNumber,
                    'user'      => auth()->user()->email,
                ]);

                $statusData = $this->procurementDataService->fetchStatusItems($prNumber)->first();
                if (! $statusData) {
                    return response()->json(['error' => 'Procurement not found in blockchain.'], 404);
                }

                $originalProcurement = new ProcurementData(
                    prNumber:        $prNumber,
                    title:           $statusData['procurement_title'] ?? 'N/A',
                    status:          StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? StatusEnums::PROCUREMENT_SUBMITTED,
                    stage:           StageEnums::tryFrom($statusData['stage'] ?? '') ?? StageEnums::PROCUREMENT_INITIATION,
                    procurementMode: ProcurementModeEnums::PUBLIC_BIDDING,
                    timestamp:       $statusData['timestamp'] ?? now()->toIso8601String(),
                    userAddress:     $statusData['user_address'] ?? auth()->user()->blockchain_address ?? '',
                );
            }

            $correctedData = $this->extractCorrectedData($validated);
            $jobId         = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('correct_procurement', [
                'original_procurement' => $originalProcurement->toBlockchainArray(),
                'corrected_data'       => $correctedData,
                'reason'               => $validated['correction_reason'],
                'corrected_by'         => auth()->user()->name ?? 'System',
                'user_address'         => auth()->user()->blockchain_address ?? '',
                'pr_number'            => $prNumber,
            ], $jobId);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'pending',
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to submit procurement correction', [
                'pr_number' => $prNumber,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to submit correction: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getProcurementCorrectionHistory(Request $request, string $pr_number): JsonResponse
    {
        try {
            Log::info('Fetching correction history', ['pr_number' => $pr_number, 'user' => auth()->id()]);

            // Fetch both procurement corrections and document corrections
            $procurementCorrections = $this->procurementCorrectionRepository->findByProcurement($pr_number);
            $documentCorrections = $this->correctionRepository->findByProcurement($pr_number);

            Log::info('Found corrections', [
                'pr_number' => $pr_number,
                'procurement_corrections' => count($procurementCorrections),
                'document_corrections' => count($documentCorrections),
            ]);

            // Combine and format all corrections
            $allCorrections = collect([...$procurementCorrections, ...$documentCorrections])
                ->map(function ($correction) {
                    // Handle both CorrectionData and ProcurementCorrectionData
                    if (method_exists($correction, 'getChangedFields')) {
                        // ProcurementCorrectionData
                        return [
                            'pr_number' => $correction->prNumber,
                            'timestamp' => $correction->timestamp->toIso8601String(),
                            'reason' => $correction->reason,
                            'corrected_by' => $correction->correctedBy,
                            'correction_type' => $correction->correctionType ?? 'procurement_metadata',
                            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType ?? 'procurement_metadata')),
                            'action' => $correction->action ?? 'replace', // Add action field for consistency
                            'txid' => $correction->txid ?? '', // Add txid field
                            'original_txid' => $correction->originalTxid ?? '',
                            'original_document_hash' => $correction->originalDocumentHash ?? '',
                            'document_hash' => $correction->documentHash ?? '',
                            'file_name' => $correction->fileName ?? '',
                            'file_key' => $correction->fileKey ?? '',
                            'document_type' => $correction->documentType ?? '',
                            'document_type_display' => $correction->documentTypeDisplay ?? '',
                            'changed_fields' => $correction->getChangedFields(),
                            'corrected_metadata' => $correction->correctedMetadata ?? null,
                            'metadata' => $correction->toBlockchainArray(),
                        ];
                    } else {
                        // CorrectionData (document corrections)
                        return [
                            'pr_number' => $correction->prNumber,
                            'timestamp' => $correction->timestamp->toIso8601String(),
                            'reason' => $correction->reason,
                            'corrected_by' => $correction->correctedBy,
                            'correction_type' => $correction->correctionType,
                            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
                            'action' => $correction->action ?? 'replace', // Add action field
                            'txid' => $correction->txid ?? '', // Add txid field
                            'original_txid' => $correction->originalTxid ?? '',
                            'original_document_hash' => $correction->originalDocumentHash ?? '',
                            'document_hash' => $correction->documentHash ?? '',
                            'file_name' => $correction->fileName ?? '',
                            'file_key' => $correction->fileKey ?? '',
                            'document_type' => $correction->documentType ?? '',
                            'document_type_display' => $correction->documentTypeDisplay ?? '',
                            'changed_fields' => [], // Document corrections don't have changed fields in the same way
                            'corrected_metadata' => $correction->correctedMetadata ?? null,
                            'metadata' => $correction->toBlockchainArray(),
                        ];
                    }
                })
                ->sortByDesc('timestamp')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'corrections' => $allCorrections,
                'count' => count($allCorrections),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement correction history from blockchain', [
                'pr_number' => $pr_number,
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

            // Load all corrections (both procurement metadata and document corrections)
            $procurementCorrections = $this->procurementCorrectionRepository->findByProcurement($prNumber);
            $documentCorrections = $this->correctionRepository->findByProcurement($prNumber);

            // Combine and format all corrections
            $allCorrections = collect([...$procurementCorrections, ...$documentCorrections])
                ->map(function ($correction) {
                    // Handle both CorrectionData and ProcurementCorrectionData
                    if (method_exists($correction, 'getChangedFields')) {
                        // ProcurementCorrectionData
                        return [
                            'pr_number' => $correction->prNumber,
                            'timestamp' => $correction->timestamp->toIso8601String(),
                            'reason' => $correction->reason,
                            'corrected_by' => $correction->correctedBy,
                            'correction_type' => $correction->correctionType ?? 'procurement_metadata',
                            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType ?? 'procurement_metadata')),
                            'changed_fields' => $correction->getChangedFields(),
                            'metadata' => $correction->toBlockchainArray(),
                        ];
                    } else {
                        // CorrectionData (document corrections)
                        return [
                            'pr_number' => $correction->prNumber,
                            'timestamp' => $correction->timestamp->toIso8601String(),
                            'reason' => $correction->reason,
                            'corrected_by' => $correction->correctedBy,
                            'correction_type' => $correction->correctionType,
                            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
                            'changed_fields' => [], // Document corrections don't have changed fields in the same way
                            'metadata' => $correction->toBlockchainArray(),
                        ];
                    }
                })
                ->sortByDesc('timestamp')
                ->values()
                ->all();

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
                'corrections' => $allCorrections,
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
