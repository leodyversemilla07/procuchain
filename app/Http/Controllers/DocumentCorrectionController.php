<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\CorrectDocumentRequest;
use App\Models\ProcurementDocument;
use App\Services\BlockchainCorrectionService;
use App\Services\FileStorageService;
use App\Services\MultichainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        private readonly BlockchainCorrectionService $correctionService,
        private readonly MultichainService $multiChainService,
        private readonly FileStorageService $fileStorageService
    ) {}

    /**
     * Submit a correction for a document.
     */
    public function correctDocument(CorrectDocumentRequest $request, ProcurementDocument $document): RedirectResponse
    {
        $this->authorize('correct', $document);

        $validated = $request->validated();

        // Process corrected file if replacing
        $correctedMetadata = null;
        if ($validated['correction_type'] === 'replace' && $request->hasFile('corrected_file')) {
            $file = $request->file('corrected_file');

            // Upload file to blockchain (on-chain storage)
            try {
                $uploadResult = $this->fileStorageService->uploadFile(
                    file: $file,
                    path: 'procurement_documents/'.$document->procurement_id,
                    suffix: 'corrected_'.time(),
                    metadata: [
                        'document_id' => $document->id,
                        'procurement_id' => $document->procurement_id,
                        'correction_type' => 'replace',
                        'original_txid' => $document->txid,
                        'corrected_by' => Auth::user()->name,
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
                return redirect()->back()->withErrors(['error' => 'Failed to upload corrected file to blockchain: '.$e->getMessage()]);
            }
        }

        // Get user's blockchain address
        $userAddress = Auth::user()->blockchain_address ?? config('multichain.admin_address');

        try {
            // Submit correction
            $correctionTxid = $this->correctionService->correctDocument(
                document: $document,
                reason: $validated['correction_reason'],
                correctedMetadata: $correctedMetadata,
                correctedBy: Auth::user()->name,
                userAddress: $userAddress
            );

            return redirect()->back()->with('success', 'Document correction submitted successfully. Transaction ID: '.$correctionTxid);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to submit correction: '.$e->getMessage()]);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getCorrectionHistory(Request $request, int $procurementId): JsonResponse
    {
        $this->authorize('viewCorrectionHistory', ProcurementDocument::class);

        try {
            $corrections = $this->correctionService->getCorrections(
                procurementId: (string) $procurementId,
                multiChain: $this->multiChainService
            );

            return response()->json([
                'success' => true,
                'corrections' => $corrections,
                'count' => count($corrections),
            ]);
        } catch (\Exception $e) {
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
        // For now, render with minimal data - the page will fetch documents and corrections via API
        return Inertia::render('documents/document-corrections', [
            'procurement' => [
                'id' => (int) $id,
                'title' => 'Loading...',
                'reference_number' => '',
                'status' => '',
                'stage' => '',
                'documents' => [],
            ],
        ]);
    }
}
