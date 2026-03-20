<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Http\Requests\Document\CorrectDocumentRequest;
use App\Jobs\BlockchainWriteJob;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Services\Publishers\CorrectionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        protected DocumentRepository $documentRepository,
        protected CorrectionRepository $correctionRepository,
        protected CorrectionPublisher $correctionPublisher
    ) {}

    public function correctDocument(CorrectDocumentRequest $request, string $txid): JsonResponse
    {
        $this->authorize('correct-document', $txid);

        $validated = $request->validated();

        try {
            $originalDocument = $this->documentRepository->findByTxid($txid);

            if (! $originalDocument) {
                return response()->json(['error' => 'Document not found in blockchain.'], 404);
            }

            $pr_number = $originalDocument->prNumber;
            $procurementTitle = $originalDocument->procurementTitle;

            if (! $pr_number || ! $procurementTitle) {
                return response()->json(['error' => 'Invalid document: missing procurement information.'], 422);
            }

            $userAddress = auth()->user()->blockchain_address ?? '';
            $action = $validated['correction_type'] === 'replace' ? 'replace' : 'invalidate';
            $correctionType = match ($validated['correction_type']) {
                'replace' => 'document_correction',
                'invalidate' => 'status_correction',
                'hash' => 'hash_correction',
                default => 'metadata_correction',
            };

            // Store replacement file temporarily (if provided)
            $jobData = [
                'pr_number' => $pr_number,
                'procurement_title' => $procurementTitle,
                'original_txid' => $txid,
                'original_document_hash' => $originalDocument->hash ?? '',
                'correction_type' => $correctionType,
                'action' => $action,
                'reason' => $validated['correction_reason'],
                'corrected_by' => auth()->user()->name ?? 'System',
                'user_address' => $userAddress,
                'original_stage' => $originalDocument->stage ?? null,
            ];

            if ($request->hasFile('corrected_file')) {
                $corrFile = $request->file('corrected_file');
                $jobData['temp_file_path'] = $corrFile->store('temp/blockchain-uploads');
                $jobData['original_filename'] = $corrFile->getClientOriginalName();
                $jobData['mime_type'] = $corrFile->getMimeType() ?? 'application/octet-stream';
            }

            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('correct_document', $jobData, $jobId, auth()->id());

            return response()->json([
                'job_id' => $jobId,
                'status' => 'pending',
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to submit document correction', [
                'txid' => $txid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to submit correction: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getCorrectionHistory(Request $request, string $procurement): JsonResponse
    {
        $this->authorize('view-procurement', $procurement);

        try {
            // Fetch corrections for this procurement using repository
            $correctionDtos = $this->correctionRepository->findByProcurement($procurement);

            // Fetch all documents using repository for document metadata lookup
            $allDocuments = $this->documentRepository->all();

            // Create a map of document info keyed by pr_number for quick lookup
            $documentsMap = collect($allDocuments)
                ->keyBy(fn ($doc) => $doc->prNumber.'_'.$doc->fileKey)
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
                        'txid' => $correctionDto->txid,
                        'timestamp' => $correctionDto->timestamp->toIso8601String(),
                        'original_txid' => $correctionDto->originalTxid,
                        'correction_txid' => $correctionDto->txid,
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
                        'pr_number' => $correctionDto->prNumber,
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
        $this->authorize('view-document', $txid);

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
}
