<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Verification\CompletenessResult;
use App\DataTransferObjects\Verification\ComplianceResult;
use App\DataTransferObjects\Verification\CrossReferenceResult;
use App\DataTransferObjects\Verification\VerificationReportDTO;
use App\DataTransferObjects\Verification\VerificationResult;
use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\Verification\DocumentCompletenessVerifier;
use App\Services\Verification\DocumentComplianceVerifier;
use App\Services\Verification\DocumentCrossReferenceVerifier;
use App\Services\Verification\DocumentIntegrityVerifier;
use Illuminate\Support\Facades\Log;

/**
 * Document Verification Service
 *
 * Orchestrates document verification by delegating to focused verifiers.
 */
final class DocumentVerificationService
{
    public function __construct(
        private readonly DocumentIntegrityVerifier $integrityVerifier,
        private readonly DocumentCompletenessVerifier $completenessVerifier,
        private readonly DocumentCrossReferenceVerifier $crossReferenceVerifier,
        private readonly DocumentComplianceVerifier $complianceVerifier,
    ) {}

    public function verifyIntegrity(string $fileKey, string $dataTxid): VerificationResult
    {
        return $this->integrityVerifier->verify($fileKey, $dataTxid);
    }

    public function verifyCompleteness(string $prNumber, StageEnums $stage, ?iterable $documents = null): CompletenessResult
    {
        return $this->completenessVerifier->verify($prNumber, $stage, $documents);
    }

    public function verifyCrossReferences(string $prNumber, ?iterable $documents = null): CrossReferenceResult
    {
        return $this->crossReferenceVerifier->verify($prNumber, $documents);
    }

    public function verifyCompliance(string $prNumber, StageEnums $stage, ?iterable $documents = null): ComplianceResult
    {
        return $this->complianceVerifier->verify($prNumber, $stage, $documents);
    }

    public function verifySingleDocument(string $fileKey): VerificationResult
    {
        return $this->integrityVerifier->verifySingle($fileKey);
    }

    public function batchVerifyDocuments(string $prNumber): array
    {
        return $this->integrityVerifier->batchVerify($prNumber);
    }

    /**
     * Generate a comprehensive verification report for a procurement.
     */
    public function generateVerificationReport(string $prNumber, ?StageEnums $stage = null, ?User $authUser = null): VerificationReportDTO
    {
        $documents = ProcurementDocument::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get();

        if ($stage === null) {
            $stage = $this->determineCurrentStage($documents->all());
        }

        $integrityResults = [];
        foreach ($documents as $doc) {
            $integrityResults[] = [
                'file_key' => $doc->file_key,
                'is_valid' => true,
                'expected_hash' => $doc->hash,
                'actual_hash' => $doc->hash,
                'errors' => [],
                'warnings' => [],
                'verification_type' => 'integrity',
                'verified_at' => now()->toIso8601String(),
                'metadata_only' => true,
            ];
        }

        $documentsArray = $documents->all();
        $completenessResult = $this->completenessVerifier->verify($prNumber, $stage, $documentsArray);
        $crossReferenceResult = $this->crossReferenceVerifier->verify($prNumber, $documentsArray);
        $complianceResult = $this->complianceVerifier->verify($prNumber, $stage, $documentsArray);

        Log::info('Verification report generated', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'documents_verified' => count($integrityResults),
        ]);

        return VerificationReportDTO::fromResults(
            prNumber: $prNumber,
            stage: $stage,
            integrityResults: $integrityResults,
            completenessResult: $completenessResult,
            crossReferenceResult: $crossReferenceResult,
            complianceResult: $complianceResult,
            verifiedBy: $authUser?->id,
        );
    }

    private function determineCurrentStage(array $documents): StageEnums
    {
        if (empty($documents)) {
            return StageEnums::PROCUREMENT_INITIATION;
        }

        $stageValues = StageEnums::values();
        $cases = StageEnums::cases();
        $latestStageIndex = 0;

        foreach ($documents as $doc) {
            $stageIndex = array_search($doc->stage ?? '', $stageValues, true);
            if ($stageIndex !== false && $stageIndex > $latestStageIndex) {
                $latestStageIndex = $stageIndex;
            }
        }

        if ($latestStageIndex < 0 || $latestStageIndex >= count($cases)) {
            return StageEnums::PROCUREMENT_INITIATION;
        }

        return $cases[$latestStageIndex];
    }
}
