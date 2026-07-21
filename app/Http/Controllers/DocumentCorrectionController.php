<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Http\Requests\Document\CorrectDocumentRequest;
use App\Jobs\BlockchainWriteJob;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Services\AuditLogService;
use App\Services\Publishers\CorrectionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        protected CorrectionPublisher $correctionPublisher,
        protected AuditLogService $auditLogService,
    ) {}

    public function correctDocument(CorrectDocumentRequest $request, string $txid): RedirectResponse
    {
        $this->authorize('correct-document', $txid);

        $validated = $request->validated();

        try {
            $originalDocument = ProcurementDocument::with('procurement')->where('txid', $txid)->first();

            if (! $originalDocument) {
                return back()->with('error', 'Document not found in blockchain.');
            }

            $pr_number = $originalDocument->procurement?->pr_number;
            $procurementTitle = $originalDocument->procurement?->title;

            if (! $pr_number || ! $procurementTitle) {
                return back()->with('error', 'Invalid document: missing procurement information.');
            }

            $userAddress = $request->user()->blockchain_address ?? '';
            $action = $validated['correction_type'] === 'replace' ? 'replace' : 'invalidate';
            $correctionType = match ($validated['correction_type']) {
                'replace' => 'document_correction',
                'invalidate' => 'status_correction',
                'hash' => 'hash_correction',
                default => 'metadata_correction',
            };

            // Store replacement File temporarily (if provided)
            $jobData = [
                'pr_number' => $pr_number,
                'procurement_title' => $procurementTitle,
                'original_txid' => $txid,
                'original_document_hash' => $originalDocument->hash ?? '',
                'correction_type' => $correctionType,
                'action' => $action,
                'reason' => $validated['correction_reason'],
                'corrected_by' => $request->user()->name ?? 'System',
                'user_address' => $userAddress,
                'original_stage' => $originalDocument->stage ?? null,
            ];

            if ($request->hasFile('corrected_File')) {
                $corrFile = $request->file('corrected_File');
                $jobData['temp_file_path'] = $corrFile->store('temp/blockchain-uploads');
                $jobData['original_filename'] = $corrFile->getClientOriginalName();
                $jobData['mime_type'] = $corrFile->getMimeType() ?? 'application/octet-stream';
            }

            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('correct_document', $jobData, $jobId, $request->user()->id);

            $this->auditLogService->log(
                'document.corrected',
                'document',
                $txid,
                [],
                ['pr_number' => $pr_number, 'correction_type' => $correctionType, 'reason' => $validated['correction_reason']],
            );

            return back()->with('success', 'Document correction submitted successfully. The blockchain update is being processed.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to submit document correction', [
                'txid' => $txid,
                'error' => 'An error occurred with the document correction.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return back()->with('error', 'Failed to submit correction. Please try again.');
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getCorrectionHistory(Request $request, string $procurement): JsonResponse
    {
        $this->authorize('correct-procurement', $procurement);

        try {
            // Fetch corrections for this procurement using Eloquent
            $corrections = ProcurementCorrection::with('procurement')
                ->whereHas('procurement', fn ($q) => $q->where('pr_number', $procurement))
                ->orderByDesc('corrected_at')
                ->get();

            // Fetch all documents for document metadata lookup
            $allDocuments = ProcurementDocument::with('procurement')->orderByDesc('uploaded_at')->take(5000)->get();

            // Create a map of document info keyed by pr_number for quick lookup
            $documentsMap = collect($allDocuments)
                ->keyBy(fn ($doc) => ($doc->procurement?->pr_number ?? '').'_'.$doc->file_key)
                ->mapWithKeys(function ($doc) {
                    $documentTypeEnum = DocumentTypeEnums::fromString($doc->document_type);

                    return [
                        $doc->file_key => [
                            'file_name' => $doc->filename,
                            'file_key' => $doc->file_key,
                            'document_type' => $doc->document_type,
                            'document_type_display' => $documentTypeEnum?->getDisplayName() ?? $doc->document_type,
                            'hash' => $doc->hash,
                            'file_size' => $doc->file_size,
                        ],
                    ];
                });

            // Map corrections to response format
            $corrections = $corrections
                ->map(function ($correction) use ($documentsMap) {
                    $originalDoc = null;
                    foreach ($documentsMap as $key => $doc) {
                        if ($doc['hash'] === $correction->original_document_hash) {
                            $originalDoc = $doc;
                            break;
                        }
                    }

                    $correctionTypeDisplay = match ($correction->correction_type) {
                        'replace' => 'Document Replacement',
                        'invalidate' => 'Document Invalidation',
                        'metadata' => 'Metadata Correction',
                        'document_correction' => 'Document Correction',
                        default => ucwords(str_replace('_', ' ', $correction->correction_type)),
                    };

                    return [
                        'txid' => $correction->txid,
                        'timestamp' => $correction->corrected_at?->toIso8601String(),
                        'original_txid' => $correction->original_txid,
                        'correction_txid' => $correction->txid,
                        'reason' => $correction->reason,
                        'corrected_by' => $correction->corrected_by,
                        'correction_type' => $correction->correction_type,
                        'correction_type_display' => $correctionTypeDisplay,
                        'original_document_hash' => $correction->original_document_hash,
                        'document_hash' => $correction->original_document_hash,
                        'file_name' => $originalDoc['file_name'] ?? null,
                        'file_key' => $originalDoc['file_key'] ?? null,
                        'document_type' => $originalDoc['document_type'] ?? '',
                        'document_type_display' => $originalDoc['document_type_display'] ?? null,
                        'pr_number' => $correction->procurement?->pr_number ?? '',
                        'procurement_title' => $correction->procurement?->title ?? '',
                        'action' => $correction->action,
                        'metadata' => $correction->toBlockchainArray(),
                        'corrected_metadata' => $correction->corrected_metadata,
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
            report($e);
            Log::error('Failed to retrieve correction history from blockchain', [
                'pr_number' => $procurement,
                'error' => 'An error occurred with the document correction.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve correction history. Please try again.',
                'corrections' => [],
            ], 500);
        }
    }

    /**
     * Check if a specific transaction has been corrected.
     */
    public function checkCorrection(CheckCorrectionRequest $request, string $txid): JsonResponse
    {
        $this->authorize('view-document', $txid);

        $validated = $request->validated();

        try {
            // Find correction by original txid using Eloquent
            $correction = ProcurementCorrection::where('original_txid', $txid)->first();

            return response()->json([
                'success' => true,
                'has_correction' => $correction !== null,
                'correction' => $correction?->toBlockchainArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check correction. Please try again.',
            ], 500);
        }
    }
}
