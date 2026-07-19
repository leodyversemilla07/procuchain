<?php

declare(strict_types=1);

namespace App\Services\Verification;

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

    public function verify(string $prNumber, StageEnums $stage, ?iterable $documents = null): array
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

            return $this->buildResult($prNumber, $stage, $isCompliant, $documentTypeChecks, $timelineChecks, $procurementModeChecks, $errors, $warnings);
        } catch (Exception $e) {
            Log::error('Compliance verification failed', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult($prNumber, $stage, false, [], [], [], ['Compliance verification failed: '.$e->getMessage()], []);
        }
    }

    private function buildResult(
        string $prNumber,
        StageEnums $stage,
        bool $isCompliant,
        array $documentTypeChecks,
        array $timelineChecks,
        array $procurementModeChecks,
        array $errors,
        array $warnings,
    ): array {
        $documentTypeChecksWithDisplayNames = array_map(function ($check) {
            $documentType = DocumentTypeEnums::tryFrom($check['document_type'] ?? '');
            $check['document_type_display'] = $documentType?->getDisplayName() ?? ucwords(str_replace('_', ' ', $check['document_type'] ?? 'Unknown'));

            return $check;
        }, $documentTypeChecks);

        $hasDocumentViolations = false;
        foreach ($documentTypeChecks as $check) {
            if (! ($check['valid'] ?? true)) {
                $hasDocumentViolations = true;
                break;
            }
        }

        $hasTimelineViolations = false;
        foreach ($timelineChecks as $check) {
            if (! ($check['compliant'] ?? true)) {
                $hasTimelineViolations = true;
                break;
            }
        }

        return [
            'is_compliant' => $isCompliant,
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'document_type_checks' => $documentTypeChecksWithDisplayNames,
            'timeline_checks' => $timelineChecks,
            'procurement_mode_checks' => $procurementModeChecks,
            'summary' => [
                'violations_count' => count($errors),
                'warnings_count' => count($warnings),
                'has_document_violations' => $hasDocumentViolations,
                'has_timeline_violations' => $hasTimelineViolations,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
