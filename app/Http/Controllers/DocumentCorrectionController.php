<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Requests\Document\CorrectDocumentRequest;
use App\Libraries\MultiChain\Manager;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\StatusRepository;
use App\Services\BlockchainStorageService;
use App\Services\Publishers\CorrectionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        private readonly CorrectionPublisher $correctionPublisher,
        private readonly Manager $multichain,
        private readonly BlockchainStorageService $fileStorageService,
        private readonly DocumentRepository $documentRepository,
        private readonly CorrectionRepository $correctionRepository,
        private readonly StatusRepository $statusRepository
    ) {}

    /**
     * Submit a correction for a document (Pure Blockchain Implementation).
     *
     * This method works directly with blockchain data without database models.
     * It fetches the document from blockchain, validates it, and publishes a correction record.
     */
    public function correctDocument(CorrectDocumentRequest $request, string $txid): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Fetch the original document from blockchain using repository
            $originalDocument = $this->documentRepository->findByTxid($txid);

            if (! $originalDocument) {
                return redirect()->back()->withErrors(['error' => 'Document not found in blockchain.']);
            }

            // Convert DTO to array for compatibility
            $documentData = [
                'pr_number' => $originalDocument->pr_number,
                'procurement_title' => $originalDocument->procurementTitle,
                'stage' => $originalDocument->stage,
                'hash' => $originalDocument->hash,
            ];
            $pr_number = $documentData['pr_number'] ?? null;
            $procurementTitle = $documentData['procurement_title'] ?? null;

            if (! $pr_number || ! $procurementTitle) {
                return redirect()->back()->withErrors(['error' => 'Invalid document: missing procurement information.']);
            }

            // Get user's blockchain address
            $userAddress = auth()->user()->blockchain_address ?? config('multichain.admin_address');

            // Determine action and correction type based on request
            $action = $validated['correction_type'] === 'replace' ? 'replace' : 'invalidate';
            $correctionType = match ($validated['correction_type']) {
                'replace' => 'document_correction',
                'invalidate' => 'status_correction',
                'hash' => 'hash_correction',
                default => 'metadata_correction',
            };

            // Use CorrectionPublisher for atomic correction publishing
            $result = $this->correctionPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurementTitle,
                originalTxid: $txid,
                originalDocumentHash: $documentData['hash'] ?? '',
                correctionType: $correctionType,
                action: $action,
                reason: $validated['correction_reason'],
                correctedBy: auth()->user()->name ?? 'System',
                userAddress: $userAddress,
                correctedFile: $request->hasFile('corrected_file') ? $request->file('corrected_file') : null
            );

            Log::info('Document correction published to blockchain', [
                'pr_number' => $pr_number,
                'original_txid' => $txid,
                'correction_txid' => $result['correction_txid'],
                'correction_type' => $validated['correction_type'],
            ]);

            return redirect()->back()->with('success', 'Document correction submitted successfully. Correction TX: '.$result['correction_txid']);
        } catch (\Exception $e) {
            Log::error('Failed to submit document correction', [
                'txid' => $txid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to submit correction: '.$e->getMessage()]);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getCorrectionHistory(Request $request, string $procurement): JsonResponse
    {
        try {
            // Fetch corrections for this procurement using repository
            $correctionDtos = $this->correctionRepository->findByProcurement($procurement);

            // Fetch all documents using repository for document metadata lookup
            $allDocuments = $this->documentRepository->all();

            // Create a map of document info keyed by pr_number for quick lookup
            $documentsMap = collect($allDocuments)
                ->keyBy(fn ($doc) => $doc->pr_number.'_'.$doc->fileKey)
                ->mapWithKeys(function ($doc) {
                    // Get formatted document type
                    $documentTypeEnum = DocumentTypeEnums::fromString($doc->documentType);

                    return [
                        $doc->fileKey => [
                            'file_name' => $doc->fileName,
                            'file_key' => $doc->fileKey,
                            'document_type' => $doc->documentType,
                            'document_type_display' => $documentTypeEnum?->getDisplayName() ?? $doc->documentType,
                            'hash' => $doc->hash,
                            'file_size' => $doc->fileSize,
                        ],
                    ];
                });

            // Map corrections to response format
            $corrections = collect($correctionDtos)
                ->map(function ($correctionDto) use ($documentsMap) {
                    // Try to find the original document info
                    $originalDoc = null;
                    foreach ($documentsMap as $key => $doc) {
                        if ($doc['hash'] === $correctionDto->originalDocumentHash) {
                            $originalDoc = $doc;
                            break;
                        }
                    }

                    // Format correction type for display
                    $correctionTypeDisplay = match ($correctionDto->correctionType) {
                        'replace' => 'Document Replacement',
                        'invalidate' => 'Document Invalidation',
                        'metadata' => 'Metadata Correction',
                        'document_correction' => 'Document Correction',
                        default => ucwords(str_replace('_', ' ', $correctionDto->correctionType)),
                    };

                    return [
                        'txid' => $correctionDto->originalTxid,
                        'timestamp' => $correctionDto->timestamp->toIso8601String(),
                        'original_txid' => $correctionDto->originalTxid,
                        'correction_txid' => $correctionDto->originalTxid,
                        'reason' => $correctionDto->reason,
                        'corrected_by' => $correctionDto->correctedBy,
                        'correction_type' => $correctionDto->correctionType,
                        'correction_type_display' => $correctionTypeDisplay,
                        'original_document_hash' => $correctionDto->originalDocumentHash,
                        'document_hash' => $correctionDto->originalDocumentHash,
                        'file_name' => $originalDoc['file_name'] ?? null,
                        'file_key' => $originalDoc['file_key'] ?? null,
                        'document_type' => $originalDoc['document_type'] ?? '',
                        'document_type_display' => $originalDoc['document_type_display'] ?? null,
                        'pr_number' => $correctionDto->pr_number,
                        'procurement_title' => $correctionDto->procurementTitle,
                        'action' => $correctionDto->action,
                        'metadata' => $correctionDto->toBlockchainArray(),
                        'corrected_metadata' => $correctionDto->correctedMetadata,
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
            Log::error('Failed to retrieve correction history from blockchain', [
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
     * Check if a specific transaction has been corrected.
     */
    public function checkCorrection(Request $request, string $txid): JsonResponse
    {
        $validated = $request->validate([
            'txid' => 'required|string|size:64',
        ]);

        try {
            // Find correction by original txid using repository
            $corrections = $this->correctionRepository->findByOriginalTxid($txid);
            $correction = ! empty($corrections) ? $corrections[0] : null;

            return response()->json([
                'success' => true,
                'has_correction' => $correction !== null,
                'correction' => $correction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check correction: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the document corrections page for a procurement.
     */
    public function showCorrectionsPage(string $id): Response
    {
        try {
            // Fetch the latest status for this procurement using repository
            $latestStatus = $this->statusRepository->getLatest($id);

            if (! $latestStatus) {
                abort(404, 'Procurement not found in blockchain');
            }

            // Fetch documents for this procurement using repository
            $documentDtos = $this->documentRepository->findByProcurement($id);

            $documents = collect($documentDtos)
                ->map(function ($doc) {
                    // Get formatted document type
                    $documentTypeEnum = DocumentTypeEnums::fromString($doc->documentType);
                    $documentTypeDisplay = $documentTypeEnum?->getDisplayName() ?? $doc->documentType;

                    return [
                        'id' => $doc->dataTxid, // Use data txid as unique identifier
                        'file_name' => $doc->fileName,
                        'file_key' => $doc->fileKey,
                        'document_type' => $doc->documentType,
                        'document_type_display' => $documentTypeDisplay,
                        'hash' => $doc->hash,
                        'file_size' => $doc->fileSize,
                        'uploaded_at' => $doc->timestamp->toIso8601String(),
                        'blockchain_txid' => $doc->dataTxid,
                        'is_corrected' => false, // Would need to check corrections repository
                        'correction_reason' => null,
                        'corrected_by' => null,
                        'corrected_at' => null,
                        'correction_txid' => null,
                    ];
                })
                ->values()
                ->all();

            // Get formatted status and stage
            $statusEnum = StatusEnums::tryFrom($latestStatus->currentStatus);
            $stageEnum = StageEnums::tryFrom($latestStatus->stage);

            return Inertia::render('documents/document-corrections', [
                'procurement' => [
                    'id' => $id,
                    'title' => $latestStatus->procurementTitle,
                    'reference_number' => $id,
                    'status' => $latestStatus->currentStatus,
                    'status_display' => $statusEnum?->getDisplayName() ?? $latestStatus->currentStatus,
                    'stage' => $latestStatus->stage,
                    'stage_display' => $stageEnum?->getDisplayName() ?? $latestStatus->stage,
                    'documents' => $documents,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch procurement correction data from blockchain', [
                'pr_number' => $id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load procurement data from blockchain');
        }
    }
}
