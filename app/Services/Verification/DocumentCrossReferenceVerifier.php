<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DocumentCrossReferenceVerifier
{
    public function verify(string $prNumber, ?iterable $documents = null): array
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

            return $this->buildResult($prNumber, $isConsistent, $prNumberChecks, $amountChecks, $dateChecks, $signatoryChecks, $errors, $warnings);
        } catch (Exception $e) {
            Log::error('Cross-reference verification failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult($prNumber, false, [], [], [], [], ['Cross-reference verification failed: '.$e->getMessage()], []);
        }
    }

    private function buildResult(
        string $prNumber,
        bool $isConsistent,
        array $prNumberChecks,
        array $amountChecks,
        array $dateChecks,
        array $signatoryChecks,
        array $errors,
        array $warnings,
    ): array {
        $prNumberChecksWithDisplayNames = array_map(function ($check) {
            $documentType = DocumentTypeEnums::tryFrom($check['document_type'] ?? '');
            $check['document_type_display'] = $documentType?->getDisplayName() ?? ucwords(str_replace('_', ' ', $check['document_type'] ?? 'Unknown'));

            return $check;
        }, $prNumberChecks);

        $hasPrMismatch = false;
        foreach ($prNumberChecks as $check) {
            if (! ($check['matches'] ?? true)) {
                $hasPrMismatch = true;
                break;
            }
        }

        $hasAmountInconsistency = false;
        foreach ($amountChecks as $check) {
            if (! ($check['consistent'] ?? true)) {
                $hasAmountInconsistency = true;
                break;
            }
        }

        return [
            'is_consistent' => $isConsistent,
            'pr_number' => $prNumber,
            'pr_number_checks' => $prNumberChecksWithDisplayNames,
            'amount_checks' => $amountChecks,
            'date_checks' => $dateChecks,
            'signatory_checks' => $signatoryChecks,
            'summary' => [
                'total_issues' => count($errors),
                'total_warnings' => count($warnings),
                'has_pr_mismatch' => $hasPrMismatch,
                'has_amount_inconsistency' => $hasAmountInconsistency,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
