<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\CompletenessResult;
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

    /**
     * Verify document completeness for a specific stage.
     */
    public function verify(string $prNumber, StageEnums $stage, ?iterable $documents = null): CompletenessResult
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
}
