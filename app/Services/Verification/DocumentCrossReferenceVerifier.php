<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\CrossReferenceResult;
use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DocumentCrossReferenceVerifier
{
    public function __construct(
    ) {}

    /**
     * Verify cross-references between documents (PR numbers, amounts, dates).
     */
    public function verify(string $prNumber, ?iterable $documents = null): CrossReferenceResult
    {
        try {
            if ($documents === null) {
                $documents = ProcurementDocument::with('procurement')
                    ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
                    ->orderByDesc('uploaded_at')
                    ->get();
            }

            $prNumberChecks = [];
            $amountChecks = [];
            $dateChecks = [];
            $signatoryChecks = [];
            $errors = [];
            $warnings = [];

            foreach ($documents as $doc) {
                $docPrNumber = $doc->procurement?->pr_number ?? '';
                $prMatches = $docPrNumber === $prNumber;
                $prNumberChecks[] = [
                    'document_type' => $doc->document_type,
                    'file_key' => $doc->file_key,
                    'pr_number_in_doc' => $docPrNumber,
                    'expected_pr_number' => $prNumber,
                    'matches' => $prMatches,
                ];

                if (! $prMatches) {
                    $errors[] = sprintf(
                        'PR number mismatch in %s: expected %s, found %s',
                        $doc->document_type,
                        $prNumber,
                        $docPrNumber
                    );
                }
            }

            $sortedDocs = $documents instanceof Collection ? $documents->all() : (array) $documents;
            usort($sortedDocs, fn ($a, $b) => ($a->uploaded_at?->timestamp ?? 0) - ($b->uploaded_at?->timestamp ?? 0));

            $stageOrder = array_flip(StageEnums::values());
            $previousStageId = -1;

            foreach ($sortedDocs as $doc) {
                $docStageId = $stageOrder[$doc->stage] ?? 999;

                if ($docStageId < $previousStageId) {
                    $warnings[] = sprintf(
                        'Document %s uploaded out of stage order (stage: %s)',
                        $doc->document_type,
                        $doc->stage
                    );
                }

                $dateChecks[] = [
                    'document_type' => $doc->document_type,
                    'stage' => $doc->stage,
                    'uploaded_at' => $doc->uploaded_at?->toIso8601String(),
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
