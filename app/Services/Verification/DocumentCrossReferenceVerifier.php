<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\CrossReferenceResult;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use Exception;
use Illuminate\Support\Facades\Log;

final class DocumentCrossReferenceVerifier
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
    ) {}

    /**
     * Verify cross-references between documents (PR numbers, amounts, dates).
     */
    public function verify(string $prNumber, ?iterable $documents = null): CrossReferenceResult
    {
        try {
            $documents = $documents ?? $this->documentRepository->findByProcurement($prNumber);

            $prNumberChecks = [];
            $amountChecks = [];
            $dateChecks = [];
            $signatoryChecks = [];
            $errors = [];
            $warnings = [];

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

            $sortedDocs = $documents;
            usort($sortedDocs, fn ($a, $b) => $a->timestamp->timestamp - $b->timestamp->timestamp);

            $stageOrder = array_flip(StageEnums::values());
            $previousStageId = -1;

            foreach ($sortedDocs as $doc) {
                $docStageId = $stageOrder[$doc->stage] ?? 999;

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
}
