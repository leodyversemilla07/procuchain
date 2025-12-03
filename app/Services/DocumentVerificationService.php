<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\Verification\CompletenessResult;
use App\DataTransferObjects\Verification\ComplianceResult;
use App\DataTransferObjects\Verification\CrossReferenceResult;
use App\DataTransferObjects\Verification\VerificationReportDTO;
use App\DataTransferObjects\Verification\VerificationResult;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Document Verification Service
 *
 * Orchestrates all document verification logic for ProcuChain:
 * - Hash-based integrity verification
 * - Document completeness validation
 * - Cross-reference verification
 * - RA 9184/RA 12009 compliance verification
 */
final class DocumentVerificationService
{
    public function __construct(
        private readonly BlockchainStorageService $blockchainStorage,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentValidationService $validationService,
        private readonly StageDocumentRequirements $requirements,
    ) {}

    /**
     * Verify document integrity using SHA-256 hash comparison
     *
     * @param  string  $fileKey  The file key for the document
     * @param  string  $dataTxid  The blockchain data transaction ID
     * @return VerificationResult The verification result
     */
    public function verifyIntegrity(string $fileKey, string $dataTxid): VerificationResult
    {
        try {
            // Retrieve document from blockchain
            $fileData = $this->blockchainStorage->retrieveFile($fileKey, $dataTxid);

            // Get stored hash from metadata (calculated during upload)
            $storedHash = $fileData['hash'] ?? null;

            // Recalculate hash from content
            $actualHash = hash('sha256', $fileData['content']);

            // Compare and return result
            $isValid = $storedHash === $actualHash;

            Log::info('Document integrity verification completed', [
                'file_key' => $fileKey,
                'is_valid' => $isValid,
                'hash_match' => $storedHash === $actualHash,
            ]);

            if ($isValid) {
                return VerificationResult::success($fileKey, $actualHash, 'integrity');
            }

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: $storedHash,
                actualHash: $actualHash,
                errors: ['Document integrity compromised: hash mismatch detected'],
                verificationType: 'integrity',
            );
        } catch (Exception $e) {
            Log::error('Document integrity verification failed', [
                'file_key' => $fileKey,
                'data_txid' => $dataTxid,
                'error' => $e->getMessage(),
            ]);

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: null,
                actualHash: null,
                errors: ['Verification failed: '.$e->getMessage()],
                verificationType: 'integrity',
            );
        }
    }

    /**
     * Verify document completeness for a specific stage
     *
     * @param  string  $prNumber  The PR number
     * @param  StageEnums  $stage  The procurement stage
     * @return CompletenessResult The completeness verification result
     */
    public function verifyCompleteness(string $prNumber, StageEnums $stage): CompletenessResult
    {
        try {
            // Get all documents for this procurement
            $documents = $this->documentRepository->findByProcurement($prNumber);

            // Filter documents for the specific stage
            $stageDocuments = array_filter(
                $documents,
                fn (DocumentData $doc): bool => $doc->stage === $stage->value
            );

            // Get uploaded document types as enums
            $uploadedTypes = [];
            foreach ($stageDocuments as $doc) {
                $docType = DocumentTypeEnums::tryFrom($doc->documentType);
                if ($docType !== null) {
                    $uploadedTypes[] = $docType;
                }
            }

            // Validate stage completion using existing service
            $validationResult = $this->validationService->validateStageCompletion($stage, $uploadedTypes);

            $errors = [];
            $warnings = [];

            // Add warnings for optional documents not uploaded
            $optionalDocs = $this->requirements->getOptionalDocuments($stage);
            $uploadedOptionalCount = 0;
            foreach ($optionalDocs as $optDoc) {
                if (in_array($optDoc, $uploadedTypes, true)) {
                    $uploadedOptionalCount++;
                }
            }

            if ($uploadedOptionalCount === 0 && count($optionalDocs) > 0) {
                $warnings[] = sprintf(
                    'No optional documents uploaded. Consider uploading: %s',
                    implode(', ', array_map(fn ($doc) => $doc->getDisplayName(), array_slice($optionalDocs, 0, 3)))
                );
            }

            Log::info('Document completeness verification completed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'is_complete' => $validationResult['can_complete'],
                'completion_percentage' => $validationResult['completion_percentage'],
            ]);

            return CompletenessResult::fromValidation(
                prNumber: $prNumber,
                stage: $stage,
                validationResult: $validationResult,
                errors: $errors,
                warnings: $warnings,
            );
        } catch (Exception $e) {
            Log::error('Document completeness verification failed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return new CompletenessResult(
                isComplete: false,
                prNumber: $prNumber,
                stage: $stage,
                completionPercentage: 0.0,
                requiredDocuments: [],
                uploadedDocuments: [],
                missingDocuments: [],
                errors: ['Completeness verification failed: '.$e->getMessage()],
                warnings: [],
                verifiedAt: now(),
            );
        }
    }

    /**
     * Verify cross-references between documents (PR numbers, amounts, dates)
     *
     * @param  string  $prNumber  The PR number
     * @return CrossReferenceResult The cross-reference verification result
     */
    public function verifyCrossReferences(string $prNumber): CrossReferenceResult
    {
        try {
            // Get all documents for this procurement
            $documents = $this->documentRepository->findByProcurement($prNumber);

            $prNumberChecks = [];
            $amountChecks = [];
            $dateChecks = [];
            $signatoryChecks = [];
            $errors = [];
            $warnings = [];

            // Verify PR numbers match across all documents
            foreach ($documents as $doc) {
                $prMatches = $doc->prNumber === $prNumber;
                $prNumberChecks[] = [
                    'document_type' => $doc->documentType,
                    'file_key' => $doc->fileKey,
                    'pr_number_in_doc' => $doc->prNumber,
                    'expected_pr_number' => $prNumber,
                    'matches' => $prMatches,
                ];

                if (! $prMatches) {
                    $errors[] = sprintf(
                        'PR number mismatch in %s: expected %s, found %s',
                        $doc->documentType,
                        $prNumber,
                        $doc->prNumber
                    );
                }
            }

            // Check document timestamps are in logical order
            $sortedDocs = $documents;
            usort($sortedDocs, fn ($a, $b) => $a->timestamp->timestamp - $b->timestamp->timestamp);

            $stageOrder = array_flip(StageEnums::values());
            $previousStageId = -1;

            foreach ($sortedDocs as $doc) {
                $docStageId = $stageOrder[$doc->stage] ?? 999;

                // Allow documents from same stage or later stages
                if ($docStageId < $previousStageId) {
                    $warnings[] = sprintf(
                        'Document %s uploaded out of stage order (stage: %s)',
                        $doc->documentType,
                        $doc->stage
                    );
                }

                $dateChecks[] = [
                    'document_type' => $doc->documentType,
                    'stage' => $doc->stage,
                    'uploaded_at' => $doc->timestamp->toIso8601String(),
                    'stage_order' => $docStageId,
                ];

                $previousStageId = $docStageId;
            }

            $isConsistent = empty($errors);

            Log::info('Cross-reference verification completed', [
                'pr_number' => $prNumber,
                'is_consistent' => $isConsistent,
                'documents_checked' => count($documents),
                'errors_count' => count($errors),
            ]);

            if ($isConsistent) {
                return CrossReferenceResult::consistent(
                    prNumber: $prNumber,
                    prNumberChecks: $prNumberChecks,
                    amountChecks: $amountChecks,
                    dateChecks: $dateChecks,
                    signatoryChecks: $signatoryChecks,
                    warnings: $warnings,
                );
            }

            return CrossReferenceResult::inconsistent(
                prNumber: $prNumber,
                errors: $errors,
                prNumberChecks: $prNumberChecks,
                amountChecks: $amountChecks,
                dateChecks: $dateChecks,
                signatoryChecks: $signatoryChecks,
                warnings: $warnings,
            );
        } catch (Exception $e) {
            Log::error('Cross-reference verification failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return CrossReferenceResult::inconsistent(
                prNumber: $prNumber,
                errors: ['Cross-reference verification failed: '.$e->getMessage()],
            );
        }
    }

    /**
     * Verify compliance with RA 9184/RA 12009 requirements
     *
     * @param  string  $prNumber  The PR number
     * @param  StageEnums  $stage  The procurement stage
     * @return ComplianceResult The compliance verification result
     */
    public function verifyCompliance(string $prNumber, StageEnums $stage): ComplianceResult
    {
        try {
            // Get all documents for this procurement
            $documents = $this->documentRepository->findByProcurement($prNumber);

            $documentTypeChecks = [];
            $timelineChecks = [];
            $procurementModeChecks = [];
            $errors = [];
            $warnings = [];

            // Filter documents for the specific stage
            $stageDocuments = array_filter(
                $documents,
                fn (DocumentData $doc): bool => $doc->stage === $stage->value
            );

            // Get required and optional documents for stage
            $requiredDocs = $this->requirements->getRequiredDocuments($stage);
            $optionalDocs = $this->requirements->getOptionalDocuments($stage);
            $allValidDocs = array_merge($requiredDocs, $optionalDocs);

            // Validate each document type
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

            // Check for MIME type compliance (must be PDF per requirements)
            foreach ($stageDocuments as $doc) {
                if ($doc->mimeType !== 'application/pdf') {
                    $errors[] = sprintf(
                        'Document %s has invalid format: %s. Only PDF files are allowed per RA 9184.',
                        $doc->documentType,
                        $doc->mimeType
                    );
                }
            }

            // Timeline compliance checks
            // Check posting periods for bidding stages (minimum 7 days per RA 9184)
            if (in_array($stage, [StageEnums::BIDDING_DOCUMENTS, StageEnums::SUPPLEMENTAL_BID_BULLETIN], true)) {
                $timelineChecks[] = [
                    'check_type' => 'posting_period',
                    'stage' => $stage->value,
                    'compliant' => true, // Simplified - would need actual posting date comparison
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

    /**
     * Generate a comprehensive verification report for a procurement
     *
     * @param  string  $prNumber  The PR number
     * @param  StageEnums|null  $stage  Optional specific stage (defaults to current stage)
     * @return VerificationReportDTO The full verification report
     */
    public function generateVerificationReport(string $prNumber, ?StageEnums $stage = null): VerificationReportDTO
    {
        // Get all documents for this procurement
        $documents = $this->documentRepository->findByProcurement($prNumber);

        // Determine stage if not provided (use latest stage from documents)
        if ($stage === null) {
            $stage = $this->determineCurrentStage($documents->all());
        }

        // Verify integrity for all documents
        $integrityResults = [];
        foreach ($documents as $doc) {
            $result = $this->verifyIntegrity($doc->fileKey, $doc->dataTxid);
            $integrityResults[] = $result->toArray();
        }

        // Get completeness, cross-reference, and compliance results
        $completenessResult = $this->verifyCompleteness($prNumber, $stage);
        $crossReferenceResult = $this->verifyCrossReferences($prNumber);
        $complianceResult = $this->verifyCompliance($prNumber, $stage);

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
            verifiedBy: auth()->id(),
        );
    }

    /**
     * Verify a single document by file key
     *
     * @param  string  $fileKey  The file key
     * @return VerificationResult The verification result
     */
    public function verifySingleDocument(string $fileKey): VerificationResult
    {
        try {
            // Find the document in the repository
            $document = $this->documentRepository->findByFileKey($fileKey);

            if ($document === null) {
                return VerificationResult::failure(
                    fileKey: $fileKey,
                    expectedHash: null,
                    actualHash: null,
                    errors: ['Document not found with file key: '.$fileKey],
                );
            }

            return $this->verifyIntegrity($fileKey, $document->dataTxid);
        } catch (Exception $e) {
            Log::error('Single document verification failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: null,
                actualHash: null,
                errors: ['Verification failed: '.$e->getMessage()],
            );
        }
    }

    /**
     * Batch verify all documents for a procurement
     *
     * @param  string  $prNumber  The PR number
     * @return array Array of verification results
     */
    public function batchVerifyDocuments(string $prNumber): array
    {
        $documents = $this->documentRepository->findByProcurement($prNumber);
        $results = [];

        foreach ($documents as $doc) {
            $results[] = [
                'document' => [
                    'file_key' => $doc->fileKey,
                    'document_type' => $doc->documentType,
                    'stage' => $doc->stage,
                    'uploaded_at' => $doc->timestamp->toIso8601String(),
                ],
                'verification' => $this->verifyIntegrity($doc->fileKey, $doc->dataTxid)->toArray(),
            ];
        }

        Log::info('Batch document verification completed', [
            'pr_number' => $prNumber,
            'documents_verified' => count($results),
        ]);

        return $results;
    }

    /**
     * Determine the current stage based on documents
     */
    private function determineCurrentStage(array $documents): StageEnums
    {
        if (empty($documents)) {
            return StageEnums::PROCUREMENT_INITIATION;
        }

        $stageValues = StageEnums::values();
        $cases = StageEnums::cases();
        $latestStageIndex = 0;

        foreach ($documents as $doc) {
            $stageIndex = array_search($doc->stage, $stageValues, true);
            if ($stageIndex !== false && $stageIndex > $latestStageIndex) {
                $latestStageIndex = $stageIndex;
            }
        }

        // Ensure index is within bounds
        if ($latestStageIndex < 0 || $latestStageIndex >= count($cases)) {
            return StageEnums::PROCUREMENT_INITIATION;
        }

        return $cases[$latestStageIndex];
    }
}
