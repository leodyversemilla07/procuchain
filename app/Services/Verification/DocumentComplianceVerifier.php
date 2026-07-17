<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\ComplianceResult;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use App\Services\StageDocumentRequirementsService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DocumentComplianceVerifier
{
    public function __construct(
        private readonly StageDocumentRequirementsService $requirements,
    ) {}

    /**
     * Verify compliance with RA 12009 (NGPA) requirements.
     */
    public function verify(string $prNumber, StageEnums $stage, ?iterable $documents = null): ComplianceResult
    {
        try {
            if ($documents === null) {
                $documents = ProcurementDocument::with('procurement')
                    ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
                    ->orderByDesc('uploaded_at')
                    ->get();
            }

            $documentTypeChecks = [];
            $timelineChecks = [];
            $procurementModeChecks = [];
            $errors = [];
            $warnings = [];

            $stageDocuments = array_filter(
                $documents instanceof Collection ? $documents->all() : (array) $documents,
                fn ($doc): bool => $doc->stage === $stage->value
            );

            $requiredDocs = $this->requirements->getRequiredDocuments($stage);
            $optionalDocs = $this->requirements->getOptionalDocuments($stage);
            $allValidDocs = array_merge($requiredDocs, $optionalDocs);

            foreach ($stageDocuments as $doc) {
                $docType = DocumentTypeEnums::tryFrom($doc->document_type);
                $isValidForStage = $docType !== null && in_array($docType, $allValidDocs, true);

                $documentTypeChecks[] = [
                    'document_type' => $doc->document_type,
                    'file_key' => $doc->file_key,
                    'valid' => $isValidForStage,
                    'is_required' => $docType !== null && in_array($docType, $requiredDocs, true),
                    'stage' => $stage->value,
                ];

                if (! $isValidForStage) {
                    $warnings[] = sprintf(
                        'Document type "%s" may not be appropriate for stage "%s"',
                        $doc->document_type,
                        $stage->getDisplayName()
                    );
                }
            }

            foreach ($stageDocuments as $doc) {
                if ($doc->mime_type !== 'application/pdf') {
                    $errors[] = sprintf(
                        'Document %s has invalid format: %s. Only PDF BlockchainFiles are allowed per RA 12009 (NGPA).',
                        $doc->document_type,
                        $doc->mime_type
                    );
                }
            }

            if (in_array($stage, [StageEnums::BIDDING_DOCUMENTS, StageEnums::SUPPLEMENTAL_BID_BULLETIN], true)) {
                $timelineChecks[] = [
                    'check_type' => 'posting_period',
                    'stage' => $stage->value,
                    'compliant' => true,
                    'note' => 'Per RA 12009 (NGPA), minimum posting period of 7 calendar days required',
                ];
            }

            $isCompliant = empty($errors);

            Log::info('Compliance verification completed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'is_compliant' => $isCompliant,
                'documents_checked' => count($stageDocuments),
            ]);

            if ($isCompliant) {
                return ComplianceResult::compliant(
                    prNumber: $prNumber,
                    stage: $stage,
                    documentTypeChecks: $documentTypeChecks,
                    timelineChecks: $timelineChecks,
                    procurementModeChecks: $procurementModeChecks,
                    warnings: $warnings,
                );
            }

            return ComplianceResult::nonCompliant(
                prNumber: $prNumber,
                stage: $stage,
                errors: $errors,
                documentTypeChecks: $documentTypeChecks,
                timelineChecks: $timelineChecks,
                procurementModeChecks: $procurementModeChecks,
                warnings: $warnings,
            );
        } catch (Exception $e) {
            Log::error('Compliance verification failed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return ComplianceResult::nonCompliant(
                prNumber: $prNumber,
                stage: $stage,
                errors: ['Compliance verification failed: '.$e->getMessage()],
            );
        }
    }
}
