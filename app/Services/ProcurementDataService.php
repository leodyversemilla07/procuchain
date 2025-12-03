<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Procurement\ProcurementFetcherService;
use App\Services\Procurement\ProcurementFormatterService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Procurement Data Service - Orchestration Layer
 *
 * This service acts as a facade/orchestrator for procurement data operations,
 * delegating to specialized services:
 *
 * - ProcurementFetcherService: Data fetching and aggregation from blockchain
 * - ProcurementFormatterService: Formatting dates, stages, statuses, currencies
 *
 * All public methods maintain backward compatibility with existing code.
 *
 * @see \App\Services\Procurement\ProcurementFetcherService
 * @see \App\Services\Procurement\ProcurementFormatterService
 */
class ProcurementDataService
{
    public function __construct(
        private readonly ProcurementFetcherService $fetcher,
        private readonly ProcurementFormatterService $formatter,
    ) {}

    // =========================================================================
    // FETCHING METHODS - Delegate to ProcurementFetcherService
    // =========================================================================

    /**
     * Fetch and process all procurement data
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAndProcessProcurements(): array
    {
        return $this->fetcher->fetchAllProcurements();
    }

    /**
     * Fetch status items for a specific procurement
     */
    public function fetchStatusItems(string $pr_number): Collection
    {
        return $this->fetcher->fetchStatusItems($pr_number);
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAndProcessAllDocuments(string $pr_number): array
    {
        return $this->fetcher->fetchDocuments($pr_number);
    }

    /**
     * Fetch and process events for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAndProcessEvents(string $pr_number): array
    {
        return $this->fetcher->fetchEvents($pr_number);
    }

    /**
     * Get document data from blockchain by file key
     */
    public function getDocumentDataByFileKey(string $fileKey): ?array
    {
        $document = $this->fetcher->getDocumentByFileKey($fileKey);

        if (! $document) {
            return null;
        }

        return [
            'pr_number' => $document->prNumber,
            'procurement_title' => $document->procurementTitle,
            'document_type' => $document->documentType,
            'stage' => $document->stage,
            'file_size' => $document->fileSize,
            'timestamp' => $document->timestamp,
            'hash' => $document->hash,
            'user_address' => $document->userAddress,
            'stage_metadata' => $document->stageMetadata,
            'data_txid' => $document->dataTxid,
        ];
    }

    /**
     * Get current procurement status from blockchain
     */
    public function getCurrentProcurementStatus(string $pr_number): ?array
    {
        $statusItems = $this->fetchStatusItems($pr_number);
        $latestStatus = $statusItems->first();

        if ($latestStatus) {
            $stage = $latestStatus['stage'] ?? '';

            return [
                'current_status' => $latestStatus['current_status'] ?? '',
                'stage' => $stage,
                'timestamp' => $latestStatus['timestamp'] ?? '',
                'pr_number' => $latestStatus['pr_number'] ?? '',
                'procurement_title' => $latestStatus['procurement_title'] ?? '',
                'user_address' => $latestStatus['user_address'] ?? '',
                'phase' => $this->getStagePhase($stage),
                'phase_display_name' => $this->getStagePhaseDisplayName($stage),
            ];
        }

        return null;
    }

    /**
     * Get hash by procurement number and file key pattern matching
     */
    public function getHashBypr_number(string $pr_number, string $fileKey): ?string
    {
        return $this->fetcher->getHashByPrNumber($pr_number, $fileKey);
    }

    /**
     * Validate that the file exists in document stream
     */
    public function validateDocumentExistsInBlockchain(string $fileKey): ?array
    {
        $document = $this->fetcher->validateDocumentExists($fileKey);

        if (! $document) {
            return null;
        }

        return [
            'file_key' => $document->fileKey,
            'pr_number' => $document->prNumber,
            'procurement_title' => $document->procurementTitle,
            'document_type' => $document->documentType,
            'stage' => $document->stage,
            'file_size' => $document->fileSize,
            'timestamp' => $document->timestamp,
            'hash' => $document->hash,
            'user_address' => $document->userAddress,
            'stage_metadata' => $document->stageMetadata,
            'data_txid' => $document->dataTxid,
        ];
    }

    /**
     * Preload user names for batch user lookup
     */
    public function preloadUserNames(Collection $items): void
    {
        $this->fetcher->preloadUserNames($items);
    }

    /**
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        return $this->fetcher->getUserName($address);
    }

    // =========================================================================
    // DATA BUILDING METHODS
    // =========================================================================

    /**
     * Build procurement data structure
     *
     * @param  array<string, mixed>  $currentStatus
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function buildProcurementData(
        string $pr_number,
        array $currentStatus,
        array $documents,
        array $events,
        Collection $statusItems
    ): array {
        $stage = $currentStatus['stage'] ?? '';
        $progress = $this->calculateProgress($stage);

        $phase = $this->getStagePhase($stage);
        $phaseDisplayName = $this->getStagePhaseDisplayName($stage);

        return [
            'id' => $pr_number,
            'title' => $currentStatus['procurement_title'] ?? 'N/A',
            'status' => [
                'stage' => $stage,
                'stage_formatted' => $currentStatus['stage_formatted'] ?? $this->formatStageName($stage),
                'stage_description' => $currentStatus['stage_description'] ?? $this->getStageDescription($stage),
                'stage_order' => $currentStatus['stage_order'] ?? $this->getStageOrderIndex($stage),
                'current_status' => $currentStatus['current_status'] ?? '',
                'status_formatted' => $currentStatus['status_formatted'] ?? $this->formatStatus($currentStatus['current_status'] ?? ''),
                'timestamp' => $currentStatus['timestamp'] ?? '',
                'formatted_date' => $currentStatus['formatted_date'] ?? '',
                'formatted_date_only' => $currentStatus['formatted_date_only'] ?? '',
                'pr_number' => $currentStatus['pr_number'] ?? '',
                'procurement_title' => $currentStatus['procurement_title'] ?? '',
                'user_address' => $currentStatus['user_address'] ?? '',
                'progress' => $progress,
                'total_stages' => $this->getTotalStages(),
                'phase' => $phase,
                'phase_display_name' => $phaseDisplayName,
            ],
            'documents' => $documents,
            'events' => $events,
            'timeline' => $statusItems->values()->toArray(),
        ];
    }

    // =========================================================================
    // FORMATTING METHODS - Delegate to ProcurementFormatterService
    // =========================================================================

    /**
     * Format stage name to human-readable format
     */
    public function formatStageName(string $stage): string
    {
        return $this->formatter->formatStageName($stage);
    }

    /**
     * Format status name for display
     */
    public function formatStatus(string $statusText): string
    {
        return $this->formatter->formatStatus($statusText);
    }

    /**
     * Get status information including variant and formatted label
     *
     * @return array{variant: string, label: string, description: string}
     */
    public function getStatusInfo(string $statusText): array
    {
        return $this->formatter->getStatusInfo($statusText);
    }

    /**
     * Format file size to human-readable format
     */
    public function formatFileSize(?int $bytes): string
    {
        return $this->formatter->formatFileSize($bytes);
    }

    /**
     * Shorten hash for display
     */
    public function shortenHash(?string $hash, int $startLength = 5, int $endLength = 5): string
    {
        return $this->formatter->shortenHash($hash, $startLength, $endLength);
    }

    /**
     * Format timestamp to full date and time
     */
    public function formatDateTime(Carbon|string|null $dateString): string
    {
        return $this->formatter->formatDateTime($dateString);
    }

    /**
     * Format timestamp to date only
     */
    public function formatDateOnly(Carbon|string|null $dateString): string
    {
        return $this->formatter->formatDateOnly($dateString);
    }

    /**
     * Format timestamp to time only
     */
    public function formatTimeOnly(Carbon|string|null $dateString): string
    {
        return $this->formatter->formatTimeOnly($dateString);
    }

    /**
     * Get stage order index
     */
    public function getStageOrderIndex(string $stage): int
    {
        return $this->formatter->getStageOrderIndex($stage);
    }

    /**
     * Get stage description
     */
    public function getStageDescription(string $stage): ?string
    {
        return $this->formatter->getStageDescription($stage);
    }

    /**
     * Get stage phase
     */
    public function getStagePhase(string $stage): string
    {
        return $this->formatter->getStagePhase($stage);
    }

    /**
     * Get stage phase display name
     */
    public function getStagePhaseDisplayName(string $stage): string
    {
        return $this->formatter->getStagePhaseDisplayName($stage);
    }

    /**
     * Get total number of stages
     */
    public function getTotalStages(): int
    {
        return $this->formatter->getTotalStages();
    }

    /**
     * Calculate progress percentage based on stage
     */
    public function calculateProgress(string $stage): float
    {
        return $this->formatter->calculateProgress($stage);
    }

    /**
     * Format document type to human-readable format
     */
    public function formatDocumentType(string $documentType): string
    {
        return $this->formatter->formatDocumentType($documentType);
    }

    /**
     * Format event type to human-readable format
     */
    public function formatEventType(string $eventType): string
    {
        return $this->formatter->formatEventType($eventType);
    }

    /**
     * Format event category to human-readable format
     */
    public function formatEventCategory(string $category): string
    {
        return $this->formatter->formatEventCategory($category);
    }

    /**
     * Format currency value with peso sign
     */
    public function formatCurrency(float|int|string|null $value): string
    {
        return $this->formatter->formatCurrency($value);
    }

    /**
     * Format stage metadata with all formatted fields
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function formatStageMetadata(array $metadata): array
    {
        return $this->formatter->formatStageMetadata($metadata);
    }
}
