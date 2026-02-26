<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\Verification\ComplianceResult;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use App\Services\StageDocumentRequirements;
use Exception;
use Illuminate\Support\Facades\Log;

final class DocumentComplianceVerifier
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly StageDocumentRequirements $requirements,
    ) {}

    /**
     * Verify compliance with RA 9184/RA 12009 requirements.
     */
    public function verify(string $prNumber, StageEnums $stage, ?iterable $documents = null): ComplianceResult
    {
        try {
            $documents = $documents ?? $this->documentRepository->findByProcurement($prNumber);

            $documentTypeChecks = [];
            $timelineChecks = [];
            $procurementModeChecks = [];
            $errors = [];
            $warnings = [];

            $stageDocuments = array_filter(
                $documents,
                fn (DocumentData $doc): bool => $doc->stage === $stage->value
            );

            $requiredDocs = $this->requirements->getRequiredDocuments($stage);
            $optionalDocs = $this->requirements->getOptionalDocuments($stage);
            $allValidDocs = array_merge($requiredDocs, $optionalDocs);

            foreach ($stageDocuments as $doc) {
                $docType = DocumentTypeEnums::tryFrom($doc->documentType);
                $isValidForStage = $docType !== null && in_array($docType, $allValidDocs, true);

                $documentTypeChecks[] = [
                    'document_type' => $doc->documentType,
                    'file_key' => $doc->fileKey,
                    'valid' => $isValidForStage,
                    'is_required' => $docType !== null && in_array($docType, $requiredDocs, true),
                    'stage' => $stage->value,
                ];

                if (! $isValidForStage) {
                    $warnings[] = sprintf(
                        'Document type "%s" may not be appropriate for stage "%s"',
                        $doc->documentType,
                        $stage->getDisplayName()
                    );
                }
            }

            foreach ($stageDocuments as $doc) {
                if ($doc->mimeType !== 'application/pdf') {
                    $errors[] = sprintf(
                        'Document %s has invalid format: %s. Only PDF files are allowed per RA 9184.',
                        $doc->documentType,
                        $doc->mimeType
                    );
                }
            }

            if (in_array($stage, [StageEnums::BIDDING_DOCUMENTS, StageEnums::SUPPLEMENTAL_BID_BULLETIN], true)) {
                $timelineChecks[] = [
                    'check_type' => 'posting_period',
                    'stage' => $stage->value,
                    'compliant' => true,
                    'note' => 'Per RA 9184, minimum posting period of 7 calendar days required',
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
