<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementDocument;
use App\Services\DocumentValidationService;
use App\Services\StageDocumentRequirementsService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DocumentCompletenessVerifier
{
    public function __construct(
        private readonly DocumentValidationService $validationService,
        private readonly StageDocumentRequirementsService $requirements,
    ) {}

    public function verify(string $prNumber, StageEnums $stage, ?iterable $documents = null): array
    {
        try {
            if ($documents === null) {
                $documents = ProcurementDocument::with('procurement')
                    ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
                    ->orderByDesc('uploaded_at')
                    ->get();
            }

            $stageDocuments = array_filter(
                $documents instanceof Collection ? $documents->all() : (array) $documents,
                fn ($doc): bool => $doc->stage === $stage->value
            );

            $uploadedTypes = [];
            foreach ($stageDocuments as $doc) {
                $docType = DocumentTypeEnums::tryFrom($doc->document_type);
                if ($docType !== null) {
                    $uploadedTypes[] = $docType;
                }
            }

            $validationResult = $this->validationService->validateStageCompletion($stage, $uploadedTypes);

            $errors = [];
            $warnings = [];

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

            return $this->buildResult($prNumber, $stage, $validationResult, $errors, $warnings);
        } catch (Exception $e) {
            Log::error('Document completeness verification failed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult($prNumber, $stage, [
                'can_complete' => false,
                'completion_percentage' => 0.0,
                'required_documents' => [],
                'uploaded_documents' => [],
                'missing_documents' => [],
            ], ['Completeness verification failed: '.$e->getMessage()], []);
        }
    }

    private function buildResult(
        string $prNumber,
        StageEnums $stage,
        array $validationResult,
        array $errors,
        array $warnings,
    ): array {
        $isComplete = $validationResult['can_complete'] ?? false;
        $requiredDocuments = $validationResult['required_documents'] ?? [];
        $uploadedDocuments = $validationResult['uploaded_documents'] ?? [];
        $missingDocuments = $validationResult['missing_documents'] ?? [];

        $requiredValues = array_flip($requiredDocuments);
        $uploadedRequiredCount = count(array_filter($uploadedDocuments, fn (string $doc): bool => isset($requiredValues[$doc])));
        $uploadedOptionalCount = count(array_filter($uploadedDocuments, fn (string $doc): bool => ! isset($requiredValues[$doc])));

        return [
            'is_complete' => $isComplete,
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'completion_percentage' => $validationResult['completion_percentage'] ?? 0.0,
            'required_documents' => $requiredDocuments,
            'uploaded_documents' => $uploadedDocuments,
            'missing_documents' => $missingDocuments,
            'document_counts' => [
                'required' => count($requiredDocuments),
                'uploaded' => $uploadedRequiredCount,
                'uploaded_optional' => $uploadedOptionalCount,
                'missing' => count($missingDocuments),
            ],
            'can_complete_stage' => $isComplete && empty($missingDocuments),
            'errors' => $errors,
            'warnings' => $warnings,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
