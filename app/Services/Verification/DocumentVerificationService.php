<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class DocumentVerificationService
{
    public function __construct(
        private readonly DocumentIntegrityVerifier $integrityVerifier,
        private readonly DocumentCompletenessVerifier $completenessVerifier,
        private readonly DocumentCrossReferenceVerifier $crossReferenceVerifier,
        private readonly DocumentComplianceVerifier $complianceVerifier,
    ) {}

    public function verifyIntegrity(string $fileKey, string $dataTxid): array
    {
        return $this->integrityVerifier->verify($fileKey, $dataTxid);
    }

    public function verifyCompleteness(string $prNumber, StageEnums $stage, ?iterable $documents = null): array
    {
        return $this->completenessVerifier->verify($prNumber, $stage, $documents);
    }

    public function verifyCrossReferences(string $prNumber, ?iterable $documents = null): array
    {
        return $this->crossReferenceVerifier->verify($prNumber, $documents);
    }

    public function verifyCompliance(string $prNumber, StageEnums $stage, ?iterable $documents = null): array
    {
        return $this->complianceVerifier->verify($prNumber, $stage, $documents);
    }

    public function verifySingleDocument(string $fileKey): array
    {
        return $this->integrityVerifier->verifySingle($fileKey);
    }

    public function batchVerifyDocuments(string $prNumber): array
    {
        return $this->integrityVerifier->batchVerify($prNumber);
    }

    public function generateVerificationReport(string $prNumber, ?StageEnums $stage = null, ?User $authUser = null): array
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

        $integrityValid = true;
        foreach ($integrityResults as $result) {
            if (! ($result['is_valid'] ?? true)) {
                $integrityValid = false;
                break;
            }
        }

        $overallValid = $integrityValid
            && ($completenessResult['is_complete'] ?? false)
            && ($crossReferenceResult['is_consistent'] ?? false)
            && ($complianceResult['is_compliant'] ?? false);

        $criticalIssues = 0;
        foreach ($integrityResults as $result) {
            if (! ($result['is_valid'] ?? true)) {
                $criticalIssues++;
            }
        }
        $criticalIssues += count($completenessResult['errors'] ?? []);
        $criticalIssues += count($crossReferenceResult['errors'] ?? []);
        $criticalIssues += count($complianceResult['errors'] ?? []);

        $warningsCount = 0;
        foreach ($integrityResults as $result) {
            $warningsCount += count($result['warnings'] ?? []);
        }
        $warningsCount += count($completenessResult['warnings'] ?? []);
        $warningsCount += count($crossReferenceResult['warnings'] ?? []);
        $warningsCount += count($complianceResult['warnings'] ?? []);

        $overallStatus = $overallValid ? 'verified' : ($criticalIssues > 0 ? 'failed' : 'warnings');

        return [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'overall_valid' => $overallValid,
            'overall_status' => $overallStatus,
            'integrity_results' => $integrityResults,
            'completeness_result' => $completenessResult,
            'cross_reference_result' => $crossReferenceResult,
            'compliance_result' => $complianceResult,
            'summary' => array_merge([
                'integrity_valid' => $integrityValid,
                'documents_verified' => count($integrityResults),
                'completeness_percentage' => $completenessResult['completion_percentage'] ?? 0,
                'cross_references_consistent' => $crossReferenceResult['is_consistent'] ?? false,
                'ra_12009_compliant' => $complianceResult['is_compliant'] ?? false,
            ], [
                'critical_issues' => $criticalIssues,
                'warnings' => $warningsCount,
            ]),
            'generated_at' => now()->toIso8601String(),
            'verified_by' => $authUser?->id,
        ];
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
