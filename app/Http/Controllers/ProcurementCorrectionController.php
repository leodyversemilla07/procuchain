<?php

namespace App\Http\Controllers;

use App\Http\Requests\Procurement\CorrectProcurementRequest;
use App\Jobs\BlockchainWriteJob;
use App\Services\AuditLogger;
use App\Services\Procurement\ProcurementCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProcurementCorrectionController extends Controller
{
    public function __construct(
        private readonly ProcurementCorrectionService $correctionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function correctProcurement(CorrectProcurementRequest $request, string $prNumber)
    {
        $this->authorize('correct-procurement', $prNumber);

        $validated = $request->validated();

        try {
            $originalProcurement = $this->correctionService->findProcurementForCorrection($prNumber, $request->user());
            $correctedData = $this->correctionService->extractCorrectedData($validated);
            $jobId = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('correct_procurement', [
                'original_procurement' => $originalProcurement->toBlockchainArray(),
                'corrected_data' => $correctedData,
                'reason' => $validated['correction_reason'],
                'corrected_by' => $request->user()->name ?? 'System',
                'user_address' => $request->user()->blockchain_address ?? '',
                'pr_number' => $prNumber,
            ], $jobId, $request->user()->id);

            $this->auditLogger->log(
                'procurement.corrected',
                'procurement',
                $prNumber,
                [],
                ['reason' => $validated['correction_reason']],
            );

            return back()->with('success', 'Procurement correction submitted successfully. The blockchain write will complete in the background.');
        } catch (\RuntimeException $e) {
            return back()->with('error', 'An error occurred with the procurement correction.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to submit procurement correction', [
                'pr_number' => $prNumber,
                'error' => 'An error occurred with the procurement correction.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return back()->with('error', 'Failed to submit correction. Please try again.');
        }
    }

    /**
     * Get correction history for a procurement.
     */
    public function getProcurementCorrectionHistory(Request $request, string $pr_number): JsonResponse
    {
        $this->authorize('correct-procurement', $pr_number);

        try {
            $corrections = $this->correctionService->getCorrectionHistory($pr_number, $request->user());

            return response()->json([
                'success' => true,
                'corrections' => $corrections,
                'count' => count($corrections),
            ]);
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to retrieve procurement correction history from blockchain', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred with the procurement correction.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve correction history. Please try again.',
                'corrections' => [],
            ], 500);
        }
    }

    /**
     * Check if a procurement has any corrections.
     */
    public function checkProcurementCorrection(Request $request, string $prNumber): JsonResponse
    {
        $this->authorize('correct-procurement', $prNumber);

        $request->validate([
            'pr_number' => 'required|string',
        ]);

        try {
            $result = $this->correctionService->checkCorrections($prNumber);

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check procurement corrections. Please try again.',
            ], 500);
        }
    }

    /**
     * Show the procurement corrections page for a procurement.
     */
    public function showProcurementCorrectionsPage(string $prNumber): InertiaResponse
    {
        $this->authorize('correct-procurement', $prNumber);

        try {
            $pageData = $this->correctionService->getCorrectionPageData($prNumber);

            return Inertia::render('procurements/procurement-corrections', $pageData);
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to fetch procurement correction data from blockchain', [
                'pr_number' => $prNumber,
                'error' => 'An error occurred with the procurement correction.',
            ]);

            abort(500, 'Failed to load procurement data from blockchain');
        }
    }
}
