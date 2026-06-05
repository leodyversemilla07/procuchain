<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Contracts\CorrectionRepositoryInterface;
use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;
use Illuminate\Support\Facades\Log;

final class ProcurementCorrectionService
{
    public function __construct(
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementCorrectionRepositoryInterface $procurementCorrectionRepository,
        private readonly CorrectionRepositoryInterface $correctionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ProcurementDataService $procurementDataService,
    ) {}

    /**
     * Find a procurement for correction, with fallback to STATUS stream.
     *
     * @throws \RuntimeException If procurement is not found in any stream
     */
    public function findProcurementForCorrection(string $prNumber, ?User $authUser = null): ProcurementData
    {
        $originalProcurement = $this->procurementRepository->findByProcurement($prNumber);

        if ($originalProcurement) {
            return $originalProcurement;
        }

        Log::warning('Procurement not found in METADATA stream for correction, attempting fallback to STATUS stream', [
            'pr_number' => $prNumber,
            'user' => $authUser?->email ?? 'unknown',
        ]);

        $statusData = $this->procurementDataService->fetchStatusItems($prNumber)->first();
        if (! $statusData) {
            throw new \RuntimeException('Procurement not found in blockchain.');
        }

        return ProcurementData::fromBlockchainArray([
            'pr_number' => $prNumber,
            'title' => $statusData['procurement_title'] ?? 'N/A',
            'description' => $statusData['description'] ?? '',
            'abc_amount' => $statusData['abc_amount'] ?? 0,
            'funding_source' => $statusData['funding_source'] ?? '',
            'category' => $statusData['category'] ?? 'goods',
            'procurement_mode' => $statusData['procurement_mode'] ?? 'competitive_bidding',
            'office' => $statusData['office'] ?? '',
            'end_user' => $statusData['end_user'] ?? null,
            'status' => $statusData['current_status'] ?? 'procurement_submitted',
            'user_id' => $authUser?->id ?? '',
            'user_address' => $statusData['user_address'] ?? $authUser?->blockchain_address ?? null,
            'created_at' => $statusData['timestamp'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Extract corrected data from validated request fields.
     */
    public function extractCorrectedData(array $validated): array
    {
        $correctedData = [];

        // Basic information
        if (isset($validated['title'])) {
            $correctedData['title'] = $validated['title'];
        }
        if (isset($validated['description'])) {
            $correctedData['description'] = $validated['description'];
        }
        if (isset($validated['abc_amount'])) {
            $correctedData['abc_amount'] = (float) $validated['abc_amount'];
        }
        if (isset($validated['funding_source'])) {
            $correctedData['funding_source'] = $validated['funding_source'];
        }
        if (isset($validated['category'])) {
            $correctedData['category'] = $validated['category'];
        }
        if (isset($validated['procurement_mode'])) {
            $correctedData['procurement_mode'] = $validated['procurement_mode'];
        }

        // Office and organizational details
        if (isset($validated['office'])) {
            $correctedData['office'] = $validated['office'];
        }
        if (isset($validated['end_user'])) {
            $correctedData['end_user'] = $validated['end_user'];
        }

        // BAC Resolution
        if (isset($validated['bac_resolution_number'])) {
            $correctedData['bac_resolution_number'] = $validated['bac_resolution_number'];
        }
        if (isset($validated['bac_resolution_date'])) {
            $correctedData['bac_resolution_date'] = $validated['bac_resolution_date'];
        }

        // PhilGEPS
        if (isset($validated['philgeps_reference'])) {
            $correctedData['philgeps_reference'] = $validated['philgeps_reference'];
        }
        if (isset($validated['philgeps_posting_date'])) {
            $correctedData['philgeps_posting_date'] = $validated['philgeps_posting_date'];
        }

        // Approval
        if (isset($validated['approved_by'])) {
            $correctedData['approved_by'] = $validated['approved_by'];
        }
        if (isset($validated['approval_date'])) {
            $correctedData['approval_date'] = $validated['approval_date'];
        }

        return $correctedData;
    }

    /**
     * Get correction history for the API endpoint (full detail).
     */
    public function getCorrectionHistory(string $prNumber, ?User $authUser = null): array
    {
        Log::info('Fetching correction history', ['pr_number' => $prNumber, 'user' => $authUser?->id]);

        $procurementCorrections = $this->procurementCorrectionRepository->findByProcurement($prNumber);
        $documentCorrections = $this->correctionRepository->findByProcurement($prNumber);

        Log::info('Found corrections', [
            'pr_number' => $prNumber,
            'procurement_corrections' => count($procurementCorrections),
            'document_corrections' => count($documentCorrections),
        ]);

        return collect([...$procurementCorrections, ...$documentCorrections])
            ->map(fn ($correction) => $this->formatCorrectionForApi($correction))
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    /**
     * Get assembled page data for the procurement corrections Inertia page.
     */
    public function getCorrectionPageData(string $prNumber): array
    {
        $procurement = $this->procurementRepository->findByProcurement($prNumber);

        if (! $procurement) {
            abort(404, 'Procurement not found in blockchain');
        }

        $hasCorrections = $this->procurementCorrectionRepository->hasCorrections($prNumber);
        $latestCorrection = $hasCorrections ? $this->procurementCorrectionRepository->getLatest($prNumber) : null;

        $procurementCorrections = $this->procurementCorrectionRepository->findByProcurement($prNumber);
        $documentCorrections = $this->correctionRepository->findByProcurement($prNumber);

        $allCorrections = collect([...$procurementCorrections, ...$documentCorrections])
            ->map(fn ($correction) => $this->formatCorrectionForPage($correction))
            ->sortByDesc('timestamp')
            ->values()
            ->all();

        return [
            'procurement' => [
                'pr_number' => $procurement->prNumber,
                'title' => $procurement->title,
                'description' => $procurement->description,
                'abc_amount' => $procurement->abcAmount,
                'formatted_abc_amount' => $procurement->getFormattedAbcAmount(),
                'funding_source' => $procurement->fundingSource,
                'category' => $procurement->category->value,
                'category_display' => $procurement->category->getDisplayName(),
                'procurement_mode' => $procurement->procurementMode->value,
                'procurement_mode_display' => $procurement->procurementMode->getDisplayName(),
                'office' => $procurement->office,
                'end_user' => $procurement->endUser,
                'bac_resolution_number' => $procurement->bacResolutionNumber,
                'bac_resolution_date' => $procurement->getFormattedBacResolutionDate(),
                'philgeps_reference' => $procurement->philgepsReference,
                'philgeps_posting_date' => $procurement->getFormattedPhilgepsPostingDate(),
                'approved_by' => $procurement->approvedBy,
                'approval_date' => $procurement->getFormattedApprovalDate(),
                'status' => $procurement->status,
                'has_corrections' => $hasCorrections,
                'latest_correction' => $latestCorrection ? [
                    'timestamp' => $latestCorrection->timestamp->toIso8601String(),
                    'corrected_by' => $latestCorrection->correctedBy,
                    'reason' => $latestCorrection->reason,
                    'changed_fields' => $latestCorrection->getChangedFields(),
                ] : null,
            ],
            'corrections' => $allCorrections,
            'documents' => $this->getFormattedDocuments($prNumber),
        ];
    }

    /**
     * Get formatted documents for a procurement.
     */
    public function getFormattedDocuments(string $prNumber): array
    {
        $documents = $this->documentRepository->findByProcurement($prNumber);
        $formattedDocuments = [];

        foreach ($documents as $index => $doc) {
            $formattedDocuments[] = [
                'id' => $index,
                'pr_number' => $doc->prNumber,
                'file_key' => $doc->fileKey,
                'document_type' => $doc->documentType,
                'document_type_display' => $this->formatDocumentType($doc->documentType),
                'stage' => $doc->stage,
                'stage_display' => $this->formatStage($doc->stage),
                'file_size' => $doc->fileSize,
                'hash' => $doc->hash,
                'timestamp' => $doc->timestamp->toIso8601String(),
                'blockchain_txid' => $doc->dataTxid,
                'uploaded_by' => $doc->uploadedBy,
                'metadata' => [
                    'file_name' => $doc->fileName,
                    'mime_type' => $doc->mimeType,
                    'description' => $doc->description,
                    'metadata_txid' => $doc->metadataTxid,
                    'stage_metadata' => $doc->stageMetadata,
                ],
            ];
        }

        return $formattedDocuments;
    }

    /**
     * Check if a procurement has corrections.
     *
     * @return array{has_corrections: bool, latest_correction: mixed}
     */
    public function checkCorrections(string $prNumber): array
    {
        $hasCorrections = $this->procurementCorrectionRepository->hasCorrections($prNumber);
        $latestCorrection = $hasCorrections ? $this->procurementCorrectionRepository->getLatest($prNumber) : null;

        return [
            'has_corrections' => $hasCorrections,
            'latest_correction' => $latestCorrection,
        ];
    }

    /**
     * Format stage enum to display name.
     */
    public function formatStage(?string $stage): string
    {
        if (! $stage || $stage === 'Unknown') {
            return 'Unknown';
        }

        try {
            $stageEnum = StageEnums::tryFrom($stage);
            if ($stageEnum) {
                return $stageEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to format stage', ['stage' => $stage, 'error' => $e->getMessage()]);
        }

        return $stage;
    }

    /**
     * Format document type to display name.
     */
    public function formatDocumentType(?string $documentType): string
    {
        if (! $documentType || $documentType === 'Unknown') {
            return 'Unknown Document';
        }

        try {
            $documentTypeEnum = DocumentTypeEnums::fromString($documentType);
            if ($documentTypeEnum) {
                return $documentTypeEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to format document type', ['document_type' => $documentType, 'error' => $e->getMessage()]);
        }

        if (! preg_match('/[A-Z\s]/', $documentType)) {
            return ucwords(str_replace(['_', '-'], ' ', $documentType));
        }

        return $documentType;
    }

    /**
     * Format a correction for API response (full detail).
     */
    private function formatCorrectionForApi(mixed $correction): array
    {
        if (method_exists($correction, 'getChangedFields')) {
            return [
                'pr_number' => $correction->prNumber,
                'timestamp' => $correction->timestamp->toIso8601String(),
                'reason' => $correction->reason,
                'corrected_by' => $correction->correctedBy,
                'correction_type' => $correction->correctionType ?? 'procurement_metadata',
                'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType ?? 'procurement_metadata')),
                'action' => $correction->action ?? 'replace',
                'txid' => $correction->txid ?? '',
                'original_txid' => $correction->originalTxid ?? '',
                'original_document_hash' => $correction->originalDocumentHash ?? '',
                'document_hash' => $correction->documentHash ?? '',
                'file_name' => $correction->fileName ?? '',
                'file_key' => $correction->fileKey ?? '',
                'document_type' => $correction->documentType ?? '',
                'document_type_display' => $correction->documentTypeDisplay ?? '',
                'changed_fields' => $correction->getChangedFields(),
                'corrected_metadata' => $correction->correctedMetadata ?? null,
                'metadata' => $correction->toBlockchainArray(),
            ];
        }

        // CorrectionData (document corrections)
        return [
            'pr_number' => $correction->prNumber,
            'timestamp' => $correction->timestamp->toIso8601String(),
            'reason' => $correction->reason,
            'corrected_by' => $correction->correctedBy,
            'correction_type' => $correction->correctionType,
            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
            'action' => $correction->action ?? 'replace',
            'txid' => $correction->txid ?? '',
            'original_txid' => $correction->originalTxid ?? '',
            'original_document_hash' => $correction->originalDocumentHash ?? '',
            'document_hash' => $correction->documentHash ?? '',
            'file_name' => $correction->fileName ?? '',
            'file_key' => $correction->fileKey ?? '',
            'document_type' => $correction->documentType ?? '',
            'document_type_display' => $correction->documentTypeDisplay ?? '',
            'changed_fields' => [],
            'corrected_metadata' => $correction->correctedMetadata ?? null,
            'metadata' => $correction->toBlockchainArray(),
        ];
    }

    /**
     * Format a correction for Inertia page (simplified).
     */
    private function formatCorrectionForPage(mixed $correction): array
    {
        if (method_exists($correction, 'getChangedFields')) {
            return [
                'pr_number' => $correction->prNumber,
                'timestamp' => $correction->timestamp->toIso8601String(),
                'reason' => $correction->reason,
                'corrected_by' => $correction->correctedBy,
                'correction_type' => $correction->correctionType ?? 'procurement_metadata',
                'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType ?? 'procurement_metadata')),
                'changed_fields' => $correction->getChangedFields(),
                'metadata' => $correction->toBlockchainArray(),
            ];
        }

        return [
            'pr_number' => $correction->prNumber,
            'timestamp' => $correction->timestamp->toIso8601String(),
            'reason' => $correction->reason,
            'corrected_by' => $correction->correctedBy,
            'correction_type' => $correction->correctionType,
            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
            'changed_fields' => [],
            'metadata' => $correction->toBlockchainArray(),
        ];
    }
}
