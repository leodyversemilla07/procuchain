<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Requests\Document\CorrectDocumentRequest;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        private readonly ProcurementPublishingService $procurementPublishing,
        private readonly MultichainService $multiChainService
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
            // Fetch the original document from blockchain
            $documentItems = $this->multiChainService->listStreamItems(
                'procurement.documents',
                true,
                10000,
                0,
                false
            );

            $originalDocument = collect($documentItems)->firstWhere('txid', $txid);

            if (! $originalDocument) {
                return redirect()->back()->withErrors(['error' => 'Document not found in blockchain.']);
            }

            $documentData = $originalDocument['data']['json'];
            $procurementId = $documentData['procurement_id'] ?? null;
            $procurementTitle = $documentData['procurement_title'] ?? null;

            if (! $procurementId || ! $procurementTitle) {
                return redirect()->back()->withErrors(['error' => 'Invalid document: missing procurement information.']);
            }

            // Process corrected file if replacing
            $correctedMetadata = null;
            if ($validated['correction_type'] === 'replace' && $request->hasFile('corrected_file')) {
                $file = $request->file('corrected_file');

                try {
                    $uploadResult = $this->fileStorageService->uploadFile(
                        file: $file,
                        path: 'procurement_documents/'.$procurementId,
                        suffix: 'corrected_'.time(),
                        metadata: [
                            'procurement_id' => $procurementId,
                            'correction_type' => 'replace',
                            'original_txid' => $txid,
                            'corrected_by' => auth()->user()->name,
                        ]
                    );

                    $correctedMetadata = [
                        'file_name' => $uploadResult['filename'],
                        'file_size' => $uploadResult['size'],
                        'mime_type' => $file->getMimeType(),
                        'file_key' => $uploadResult['file_key'],
                        'hash' => $uploadResult['hash'],
                        'data_txid' => $uploadResult['data_txid'],
                        'metadata_txid' => $uploadResult['metadata_txid'],
                    ];
                } catch (\Exception $e) {
                    return redirect()->back()->withErrors(['error' => 'Failed to upload corrected file: '.$e->getMessage()]);
                }
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

            // Use ProcurementPublishingService for atomic correction publishing
            $result = $this->procurementPublishing->publishCorrection(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                originalTxid: $txid,
                originalDocumentHash: $documentData['hash'] ?? '',
                correctionType: $correctionType,
                action: $action,
                reason: $validated['correction_reason'],
                userAddress: $userAddress,
                replacementFile: $request->hasFile('corrected_file') ? $request->file('corrected_file') : null,
                correctedMetadata: $correctedMetadata,
                eventData: [
                    'stage' => $documentData['stage'] ?? 'unknown',
                    'event_type' => 'document_corrected',
                    'category' => 'correction',
                    'severity' => 'warning',
                    'details' => "Document corrected: {$validated['correction_reason']}",
                    'document_count' => 1,
                ]
            );

            if (! $result['success']) {
                Log::error('Failed to publish correction', [
                    'procurement_id' => $procurementId,
                    'original_txid' => $txid,
                    'error' => $result['error'],
                    'completed_steps' => $result['completed_steps'] ?? [],
                ]);

                return redirect()->back()->withErrors([
                    'error' => 'Failed to submit correction: '.$result['error'],
                ]);
            }

            Log::info('Document correction published to blockchain', [
                'procurement_id' => $procurementId,
                'original_txid' => $txid,
                'correction_txid' => $result['correction_txid'],
                'event_txid' => $result['event_txid'],
                'file_txids' => $result['file_txids'] ?? [],
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
            // Fetch corrections from blockchain corrections stream
            $correctionItems = $this->multiChainService->listStreamItems(
                'procurement.corrections',
                true,
                10000,
                0,
                false
            );

            // Fetch documents to get file information
            $documentItems = $this->multiChainService->listStreamItems(
                'procurement.documents',
                true,
                10000,
                0,
                false
            );

            // Create a map of txid -> document info for quick lookup
            $documentsMap = collect($documentItems)
                ->keyBy(function ($item) {
                    return $item['txid'];
                })
                ->map(function ($item) {
                    $data = $item['data']['json'];

                    // Extract filename from file_key or use file_name
                    $fileName = $data['file_name'] ?? null;
                    if (! $fileName && isset($data['file_key'])) {
                        $fileName = basename($data['file_key']);
                    }

                    // Get formatted document type
                    $documentTypeEnum = DocumentTypeEnums::fromString($data['document_type'] ?? 'unknown');

                    return [
                        'file_name' => $fileName,
                        'file_key' => $data['file_key'] ?? null,
                        'document_type' => $data['document_type'] ?? '',
                        'document_type_display' => $documentTypeEnum?->getDisplayName() ?? ($data['document_type'] ?? 'Unknown'),
                        'hash' => $data['hash'] ?? '',
                        'file_size' => $data['file_size'] ?? 0,
                    ];
                });

            // Filter corrections for this procurement
            $corrections = collect($correctionItems)
                ->filter(function ($item) use ($procurement) {
                    $data = $item['data']['json'] ?? [];

                    return ($data['procurement_id'] ?? '') === $procurement;
                })
                ->map(function ($item) use ($documentsMap) {
                    $data = $item['data']['json'];
                    $originalTxid = $data['original_txid'] ?? '';

                    // Get original document info
                    $originalDoc = $documentsMap->get($originalTxid);

                    // Format correction type for display
                    $correctionType = $data['correction_type'] ?? 'metadata';
                    $correctionTypeDisplay = match ($correctionType) {
                        'replace' => 'Document Replacement',
                        'invalidate' => 'Document Invalidation',
                        'metadata' => 'Metadata Correction',
                        'document_correction' => 'Document Correction',
                        default => ucwords(str_replace('_', ' ', $correctionType)),
                    };

                    return [
                        'txid' => $item['txid'],
                        'timestamp' => $data['timestamp'] ?? '',
                        'original_txid' => $originalTxid,
                        'correction_txid' => $item['txid'],
                        'reason' => $data['reason'] ?? $data['correction_reason'] ?? '',
                        'corrected_by' => $data['corrected_by'] ?? '',
                        'correction_type' => $correctionType,
                        'correction_type_display' => $correctionTypeDisplay,
                        'original_document_hash' => $data['original_document_hash'] ?? $data['document_hash'] ?? $data['hash'] ?? '',
                        'document_hash' => $data['original_document_hash'] ?? $data['document_hash'] ?? $data['hash'] ?? '',
                        'file_name' => $originalDoc['file_name'] ?? null,
                        'file_key' => $originalDoc['file_key'] ?? null,
                        'document_type' => $originalDoc['document_type'] ?? '',
                        'document_type_display' => $originalDoc['document_type_display'] ?? null,
                        'procurement_id' => $data['procurement_id'] ?? '',
                        'procurement_title' => $data['procurement_title'] ?? '',
                        'action' => $data['action'] ?? 'replace',
                        'metadata' => $data,
                        'corrected_metadata' => isset($data['corrected_metadata']) ? $data['corrected_metadata'] : null,
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
                'procurement_id' => $procurement,
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
            $correction = $this->correctionService->findCorrectionForTransaction(
                txid: $txid,
                multiChain: $this->multiChainService
            );

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
            // Fetch procurement data from blockchain
            $statusItems = $this->multiChainService->listStreamItems(
                'procurement.status',
                true,
                1000,
                0,
                false
            );

            // Find the latest status for this procurement
            $procurementStatus = collect($statusItems)
                ->filter(function ($item) use ($id) {
                    $data = $item['data']['json'] ?? [];

                    return ($data['procurement_id'] ?? '') === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['json']['timestamp'] ?? '';
                })
                ->first();

            if (! $procurementStatus) {
                abort(404, 'Procurement not found in blockchain');
            }

            $statusData = $procurementStatus['data']['json'];

            // Fetch documents from blockchain
            $documentItems = $this->multiChainService->listStreamItems(
                'procurement.documents',
                true,
                10000,
                0,
                false
            );

            $documents = collect($documentItems)
                ->filter(function ($item) use ($id) {
                    $data = $item['data']['json'] ?? [];

                    return ($data['procurement_id'] ?? '') === $id;
                })
                ->map(function ($item) {
                    $data = $item['data']['json'];

                    // Extract filename from file_key or use file_name if available
                    $fileName = $data['file_name'] ?? null;
                    if (! $fileName && isset($data['file_key'])) {
                        // Extract filename from file_key (e.g., "test/final-document.pdf" -> "final-document.pdf")
                        $fileName = basename($data['file_key']);
                    }
                    $fileName = $fileName ?: 'Unknown';

                    // Get formatted document type
                    $documentTypeEnum = DocumentTypeEnums::fromString($data['document_type'] ?? 'unknown');
                    $documentTypeDisplay = $documentTypeEnum?->getDisplayName() ?? ($data['document_type'] ?? 'Unknown');

                    return [
                        'id' => $item['txid'], // Use txid as unique identifier
                        'file_name' => $fileName,
                        'file_key' => $data['file_key'] ?? null,
                        'document_type' => $data['document_type'] ?? '',
                        'document_type_display' => $documentTypeDisplay,
                        'hash' => $data['hash'] ?? '',
                        'file_size' => $data['file_size'] ?? 0,
                        'uploaded_at' => $data['timestamp'] ?? $data['uploaded_at'] ?? now()->toIso8601String(),
                        'blockchain_txid' => $item['txid'],
                        'is_corrected' => $data['is_corrected'] ?? false,
                        'correction_reason' => $data['correction_reason'] ?? null,
                        'corrected_by' => $data['corrected_by'] ?? null,
                        'corrected_at' => $data['corrected_at'] ?? null,
                        'correction_txid' => $data['correction_txid'] ?? null,
                    ];
                })
                ->values()
                ->all();

            // Get formatted status and stage
            $statusEnum = StatusEnums::tryFrom($statusData['current_status'] ?? 'unknown');
            $stageEnum = StageEnums::tryFrom($statusData['stage'] ?? 'unknown');

            return Inertia::render('documents/document-corrections', [
                'procurement' => [
                    'id' => $id,
                    'title' => $statusData['procurement_title'] ?? 'Unknown',
                    'reference_number' => $id,
                    'status' => $statusData['current_status'] ?? 'unknown',
                    'status_display' => $statusEnum?->getDisplayName() ?? ($statusData['current_status'] ?? 'Unknown'),
                    'stage' => $statusData['stage'] ?? 'unknown',
                    'stage_display' => $stageEnum?->getDisplayName() ?? ($statusData['stage'] ?? 'Unknown'),
                    'documents' => $documents,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch procurement correction data from blockchain', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load procurement data from blockchain');
        }
    }
}
