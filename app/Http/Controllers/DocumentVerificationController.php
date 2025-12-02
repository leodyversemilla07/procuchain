<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StageEnums;
use App\Http\Requests\Document\VerifyProcurementRequest;
use App\Http\Requests\Document\VerifySingleDocumentRequest;
use App\Services\DocumentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Document Verification Controller
 *
 * Handles all document verification endpoints for ProcuChain
 */
final class DocumentVerificationController extends Controller
{
    public function __construct(
        private readonly DocumentVerificationService $verificationService
    ) {}

    /**
     * Full verification for a procurement
     *
     * POST /procurement/{pr_number}/verify
     */
    public function verify(VerifyProcurementRequest $request, string $prNumber): JsonResponse
    {
        Log::info('Starting procurement verification', [
            'pr_number' => $prNumber,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        $stage = $request->getStageEnum();
        $verificationTypes = $request->getVerificationTypes();

        $results = [];

        // Run requested verification types
        if (in_array('integrity', $verificationTypes, true)) {
            $batchResults = $this->verificationService->batchVerifyDocuments($prNumber);
            $results['integrity'] = $batchResults;
        }

        if (in_array('completeness', $verificationTypes, true)) {
            $stage = $stage ?? StageEnums::PROCUREMENT_INITIATION;
            $completenessResult = $this->verificationService->verifyCompleteness($prNumber, $stage);
            $results['completeness'] = $completenessResult->toArray();
        }

        if (in_array('cross_reference', $verificationTypes, true)) {
            $crossRefResult = $this->verificationService->verifyCrossReferences($prNumber);
            $results['cross_reference'] = $crossRefResult->toArray();
        }

        if (in_array('compliance', $verificationTypes, true)) {
            $stage = $stage ?? StageEnums::PROCUREMENT_INITIATION;
            $complianceResult = $this->verificationService->verifyCompliance($prNumber, $stage);
            $results['compliance'] = $complianceResult->toArray();
        }

        Log::info('Procurement verification completed', [
            'pr_number' => $prNumber,
            'verification_types' => $verificationTypes,
        ]);

        return response()->json([
            'success' => true,
            'pr_number' => $prNumber,
            'verification_types' => $verificationTypes,
            'results' => $results,
            'verified_at' => now()->toIso8601String(),
            'verified_by' => auth()->id(),
        ]);
    }

    /**
     * Hash integrity verification only
     *
     * POST /procurement/{pr_number}/verify/integrity
     */
    public function verifyIntegrity(string $prNumber): JsonResponse
    {
        Log::info('Starting integrity verification', [
            'pr_number' => $prNumber,
            'user_id' => auth()->id(),
        ]);

        $results = $this->verificationService->batchVerifyDocuments($prNumber);

        // Calculate summary
        $totalDocuments = count($results);
        $validDocuments = count(array_filter($results, fn ($r) => $r['verification']['is_valid'] ?? false));
        $allValid = $totalDocuments > 0 && $validDocuments === $totalDocuments;

        return response()->json([
            'success' => true,
            'pr_number' => $prNumber,
            'all_valid' => $allValid,
            'summary' => [
                'total_documents' => $totalDocuments,
                'valid_documents' => $validDocuments,
                'invalid_documents' => $totalDocuments - $validDocuments,
            ],
            'results' => $results,
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get verification report for a procurement
     *
     * GET /procurement/{pr_number}/verification-report
     */
    public function getReport(VerifyProcurementRequest $request, string $prNumber): JsonResponse
    {
        Log::info('Generating verification report', [
            'pr_number' => $prNumber,
            'user_id' => auth()->id(),
        ]);

        $stage = $request->getStageEnum();
        $report = $this->verificationService->generateVerificationReport($prNumber, $stage);

        return response()->json([
            'success' => true,
            'report' => $report->toArray(),
        ]);
    }

    /**
     * Verify a single document by file key
     *
     * POST /documents/{fileKey}/verify
     */
    public function verifyDocument(VerifySingleDocumentRequest $request, string $fileKey): JsonResponse
    {
        // Decode the file key (may be URL encoded)
        $decodedFileKey = urldecode($fileKey);

        Log::info('Verifying single document', [
            'file_key' => $decodedFileKey,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        $result = $this->verificationService->verifySingleDocument($decodedFileKey);

        return response()->json([
            'success' => $result->isValid,
            'result' => $result->toArray(),
        ]);
    }

    /**
     * Show verification report page
     *
     * GET /procurement/{pr_number}/verification
     */
    public function showVerificationPage(string $prNumber): Response
    {
        $report = $this->verificationService->generateVerificationReport($prNumber);

        return Inertia::render('procurement/verification', [
            'prNumber' => $prNumber,
            'report' => $report->toArray(),
        ]);
    }
}
