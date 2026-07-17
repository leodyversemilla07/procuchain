<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMetadataCorrection;
use App\Models\User;
use App\Services\ProcurementDataService;
use Illuminate\Support\Facades\Log;

final class ProcurementCorrectionService
{
    public function __construct(
        private readonly ProcurementDataService $procurementDataService,
    ) {}

    /**
     * Find a procurement for correction, with fallback to STATUS stream.
     *
     * @throws \RuntimeException If procurement is not found in any stream
     */
    public function findProcurementForCorrection(string $prNumber, ?User $authUser = null): Procurement
    {
        $originalProcurement = Procurement::where('pr_number', $prNumber)->first();

        if ($originalProcurement) {
            return $originalProcurement;
        }

        Log::warning('Procurement not found in database for correction, attempting fallback to STATUS stream', [
            'pr_number' => $prNumber,
            'user' => $authUser?->email ?? 'unknown',
        ]);

        $statusData = $this->procurementDataService->fetchStatusItems($prNumber)->first();
        if (! $statusData) {
            throw new \RuntimeException('Procurement not found in blockchain.');
        }

        $procurement = new Procurement;
        $procurement->pr_number = $prNumber;
        $procurement->title = $statusData['procurement_title'] ?? 'N/A';
        $procurement->description = $statusData['description'] ?? '';
        $procurement->abc_amount = (float) ($statusData['abc_amount'] ?? 0);
        $procurement->fund_source = $statusData['funding_source'] ?? '';
        $procurement->category = $statusData['category'] ?? 'goods';
        $procurement->procurement_mode = $statusData['procurement_mode'] ?? 'competitive_bidding';
        $procurement->office = $statusData['office'] ?? '';
        $procurement->end_user = $statusData['end_user'] ?? null;
        $procurement->current_status = $statusData['current_status'] ?? 'procurement_submitted';
        $procurement->user_id = (string) ($authUser?->id ?? '');
        $procurement->user_address = $statusData['user_address'] ?? $authUser?->blockchain_address ?? null;

        return $procurement;
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

        $procurementCorrections = ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))->get();
        $documentCorrections = ProcurementCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))->get();

        Log::info('Found corrections', [
            'pr_number' => $prNumber,
            'procurement_corrections' => $procurementCorrections->count(),
            'document_corrections' => $documentCorrections->count(),
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
        $procurement = Procurement::where('pr_number', $prNumber)->first();

        if (! $procurement) {
            abort(404, 'Procurement not found in blockchain');
        }

        $procurementCorrectionsQuery = ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber));
        $hasCorrections = $procurementCorrectionsQuery->exists();
        $latestCorrection = $hasCorrections ? $procurementCorrectionsQuery->latest('corrected_at')->first() : null;

        $procurementCorrections = $procurementCorrectionsQuery->get();
        $documentCorrections = ProcurementCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))->get();

        $allCorrections = collect([...$procurementCorrections, ...$documentCorrections])
            ->map(fn ($correction) => $this->formatCorrectionForPage($correction))
            ->sortByDesc('timestamp')
            ->values()
            ->all();

        return [
            'procurement' => [
                'pr_number' => $procurement->pr_number,
                'title' => $procurement->title,
                'description' => $procurement->description,
                'abc_amount' => $procurement->abc_amount,
                'formatted_abc_amount' => $procurement->getFormattedAbcAmount(),
                'funding_source' => $procurement->fund_source,
                'category' => $procurement->category,
                'category_display' => ProcurementCategory::tryFrom($procurement->category)?->getDisplayName() ?? $procurement->category,
                'procurement_mode' => $procurement->procurement_mode,
                'procurement_mode_display' => ProcurementMode::tryFrom($procurement->procurement_mode)?->getDisplayName() ?? $procurement->procurement_mode,
                'office' => $procurement->office,
                'end_user' => $procurement->end_user,
                'bac_resolution_number' => $procurement->bac_resolution_number,
                'bac_resolution_date' => $procurement->getFormattedBacResolutionDate(),
                'philgeps_reference' => $procurement->philgeps_reference,
                'philgeps_posting_date' => $procurement->getFormattedPhilgepsPostingDate(),
                'approved_by' => $procurement->approved_by,
                'approval_date' => $procurement->getFormattedApprovalDate(),
                'status' => $procurement->current_status,
                'has_corrections' => $hasCorrections,
                'latest_correction' => $latestCorrection ? [
                    'timestamp' => $latestCorrection->corrected_at?->toIso8601String(),
                    'corrected_by' => $latestCorrection->corrected_by,
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
        $documents = ProcurementDocument::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get();

        $formattedDocuments = [];

        foreach ($documents as $index => $doc) {
            $formattedDocuments[] = [
                'id' => $index,
                'pr_number' => $doc->procurement?->pr_number ?? '',
                'file_key' => $doc->file_key,
                'document_type' => $doc->document_type,
                'document_type_display' => $this->formatDocumentType($doc->document_type),
                'stage' => $doc->stage,
                'stage_display' => $this->formatStage($doc->stage),
                'file_size' => $doc->file_size,
                'hash' => $doc->hash,
                'timestamp' => $doc->uploaded_at?->toIso8601String(),
                'blockchain_txid' => $doc->txid,
                'uploaded_by' => $doc->uploaded_by,
                'metadata' => [
                    'file_name' => $doc->filename,
                    'mime_type' => $doc->mime_type,
                    'description' => $doc->description,
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
        $correctionsQuery = ProcurementMetadataCorrection::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber));
        $hasCorrections = $correctionsQuery->exists();
        $latestCorrection = $hasCorrections ? $correctionsQuery->latest('corrected_at')->first() : null;

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
                'pr_number' => $correction->procurement?->pr_number,
                'timestamp' => $correction->corrected_at?->toIso8601String(),
                'reason' => $correction->reason,
                'corrected_by' => $correction->corrected_by,
                'correction_type' => $correction->correction_type ?? 'procurement_metadata',
                'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correction_type ?? 'procurement_metadata')),
                'action' => 'replace',
                'txid' => $correction->txid ?? '',
                'original_txid' => '',
                'original_document_hash' => '',
                'document_hash' => '',
                'file_name' => '',
                'file_key' => '',
                'document_type' => '',
                'document_type_display' => '',
                'changed_fields' => $correction->getChangedFields(),
                'corrected_metadata' => null,
                'metadata' => $correction->toBlockchainArray(),
            ];
        }

        return [
            'pr_number' => $correction->procurement?->pr_number,
            'timestamp' => $correction->corrected_at?->toIso8601String(),
            'reason' => $correction->reason,
            'corrected_by' => $correction->corrected_by,
            'correction_type' => $correction->correction_type,
            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correction_type)),
            'action' => $correction->action ?? 'replace',
            'txid' => $correction->txid ?? '',
            'original_txid' => $correction->original_txid ?? '',
            'original_document_hash' => $correction->original_document_hash ?? '',
            'document_hash' => '',
            'file_name' => '',
            'file_key' => '',
            'document_type' => '',
            'document_type_display' => '',
            'changed_fields' => [],
            'corrected_metadata' => $correction->corrected_metadata ?? null,
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
                'pr_number' => $correction->procurement?->pr_number,
                'timestamp' => $correction->corrected_at?->toIso8601String(),
                'reason' => $correction->reason,
                'corrected_by' => $correction->corrected_by,
                'correction_type' => $correction->correction_type ?? 'procurement_metadata',
                'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correction_type ?? 'procurement_metadata')),
                'changed_fields' => $correction->getChangedFields(),
                'metadata' => $correction->toBlockchainArray(),
            ];
        }

        return [
            'pr_number' => $correction->procurement?->pr_number,
            'timestamp' => $correction->corrected_at?->toIso8601String(),
            'reason' => $correction->reason,
            'corrected_by' => $correction->corrected_by,
            'correction_type' => $correction->correction_type,
            'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correction_type)),
            'changed_fields' => [],
            'metadata' => $correction->toBlockchainArray(),
        ];
    }
}
