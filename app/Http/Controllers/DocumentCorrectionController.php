<?php

namespace App\Http\Controllers;

use App\Models\ProcurementDocument;
use App\Services\BlockchainCorrectionService;
use App\Services\MultichainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCorrectionController extends Controller
{
    public function __construct(
        private readonly BlockchainCorrectionService $correctionService,
        private readonly MultichainService $multiChainService
    ) {}

    /**
     * Submit a correction for a document.
     */
    public function correctDocument(Request $request, ProcurementDocument $document): RedirectResponse
    {
        // Validate permissions
        if (! Auth::user()->hasAnyRole(['admin', 'bac_chairman', 'bac_secretariat'])) {
            abort(403, 'You do not have permission to correct documents.');
        }

        // Validate request
        $validated = $request->validate([
            'correction_reason' => 'required|string|min:10|max:1000',
            'correction_type' => 'required|in:replace,invalidate',
            'corrected_file' => 'required_if:correction_type,replace|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'procurement_id' => 'required|integer|exists:procurements,id',
            'procurement_title' => 'required|string',
            'original_document_hash' => 'required|string',
            'original_txid' => 'nullable|string',
        ]);

        // Process corrected file if replacing
        $correctedMetadata = null;
        if ($validated['correction_type'] === 'replace' && $request->hasFile('corrected_file')) {
            $file = $request->file('corrected_file');
            $filePath = $file->store('procurement_documents', 'local');

            $correctedMetadata = [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_key' => $filePath,
                'hash' => hash_file('sha256', $file->getRealPath()),
            ];
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
            // Clean up uploaded file if correction failed
            if (isset($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to submit correction: '.$e->getMessage()]);
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getCorrectionHistory(Request $request, int $procurementId): JsonResponse
    {
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
