<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\DataTransferObjects\StatusData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\StatusRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching, processing, and formatting procurement data from blockchain
 *
 * Merged ProcurementFormatterService into this service to reduce redundancy
 */
class ProcurementDataService
{
    private Manager $multichain;

    private StatusRepository $statusRepository;

    private DocumentRepository $documentRepository;

    private EventRepository $eventRepository;

    private UserService $userService;

    /**
     * @var array<string, string>
     */
    private array $userCache = [];

    // Issue #20 fix: Load from config instead of hardcoded constants
    private int $statusPageSize;

    private int $documentPageSize;

    public function __construct(
        Manager $multichain,
        StatusRepository $statusRepository,
        DocumentRepository $documentRepository,
        EventRepository $eventRepository,
        UserService $userService
    ) {
        $this->multichain = $multichain;
        $this->statusRepository = $statusRepository;
        $this->documentRepository = $documentRepository;
        $this->eventRepository = $eventRepository;
        $this->userService = $userService;

        // Load configuration values (Issue #20 fix)
        $this->statusPageSize = config('blockchain.pagination.status_page_size', 1000);
        $this->documentPageSize = config('blockchain.pagination.document_page_size', 10000);
    }

    /**
     * Fetch and process all procurement data
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessProcurements(): array
    {
        Log::info('fetchAndProcessProcurements: Starting to fetch data from repositories');

        // Fetch all status items using repository
        $statusDtos = $this->statusRepository->all();
        $statusItems = collect($statusDtos);

        // Fetch all document items using repository
        $documentDtos = $this->documentRepository->all();

        Log::info('fetchAndProcessProcurements: Fetched data from repositories', [
            'status_count' => $statusItems->count(),
            'document_count' => count($documentDtos),
        ]);

        // Preload user names for performance
        $this->preloadUserNamesFromDtos($statusItems);

        // Build document count map by pr_number
        $documentCountMap = collect($documentDtos)
            ->groupBy(fn (DocumentData $doc) => $doc->prNumber)
            ->map(fn ($docs) => $docs->count())
            ->all();

        // Process status items to get latest status per procurement
        $result = $statusItems
            ->map(function (StatusData $statusDto) use ($documentCountMap) {
                $originalTimestamp = $statusDto->timestamp;
                $displayTimestamp = Carbon::parse($statusDto->timestamp)->toDateString();

                // Get stage enum and phase information
                $stageEnum = StageEnums::tryFrom($statusDto->stage);
                $phase = $stageEnum?->getPhase() ?? 'unknown';
                $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';
                $phaseProgress = $stageEnum?->getPhaseProgress() ?? [
                    'phase' => 'unknown',
                    'progress' => 0,
                    'current_stage_in_phase' => 0,
                    'total_stages_in_phase' => 0,
                ];

                return [
                    'id' => $statusDto->prNumber,
                    'title' => $statusDto->procurementTitle,
                    'stage' => $statusDto->stage,
                    'stage_formatted' => $this->formatStageName($statusDto->stage),
                    'phase' => $phase,
                    'phase_display' => $phaseDisplayName,
                    'phase_progress' => $phaseProgress,
                    'current_status' => $statusDto->currentStatus,
                    'status_formatted' => $this->formatStatus($statusDto->currentStatus),
                    'timestamp' => $originalTimestamp,
                    'display_date' => $displayTimestamp,
                    'last_updated' => $displayTimestamp,
                    'user_address' => $statusDto->userAddress,
                    'user' => $this->getUserName($statusDto->userAddress),
                    'document_count' => $documentCountMap[$statusDto->prNumber] ?? 0,
                    'metadata' => $statusDto->metadata,
                ];
            })
            ->groupBy('id')
            ->map(function ($group) {
                return $group->sortByDesc(function ($item) {
                    // Handle both Carbon instance and string timestamp
                    $timestamp = $item['timestamp'] ?? '0';
                    $unixTimestamp = $timestamp instanceof Carbon ? $timestamp->timestamp : strtotime($timestamp);

                    // If metadata indicates this is a transition, add a small offset to prioritize it
                    // when timestamps are identical (auto-transitions publish completion + transition at same time)
                    $isTransition = isset($item['metadata']['transition']) && $item['metadata']['transition'] === true;
                    $priorityOffset = $isTransition ? 0.001 : 0;

                    return $unixTimestamp + $priorityOffset;
                })->first();
            })
            ->values()
            ->all();

        Log::info('fetchAndProcessProcurements: Final procurements result count', ['count' => count($result)]);

        return $result;
    }

    /**
     * Fetch status items for a specific procurement
     *
     * @throws Exception
     */
    public function fetchStatusItems(string $pr_number): Collection
    {
        $statusDtos = $this->statusRepository->findByProcurement($pr_number);

        return collect($statusDtos)
            ->map(function (StatusData $statusDto) {
                $stage = $statusDto->stage;
                $currentStatus = $statusDto->currentStatus;

                // Get stage enum and phase information
                $stageEnum = StageEnums::tryFrom($stage);
                $phase = $stageEnum?->getPhase() ?? 'unknown';
                $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';

                return [
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_description' => $this->getStageDescription($stage),
                    'stage_order' => $this->getStageOrderIndex($stage),
                    'phase' => $phase,
                    'phase_display' => $phaseDisplayName,
                    'current_status' => $currentStatus,
                    'status' => $currentStatus,
                    'status_formatted' => $this->formatStatus($currentStatus),
                    'timestamp' => $statusDto->timestamp,
                    'formatted_date' => $statusDto->getFormattedDateTime(),
                    'formatted_date_only' => $statusDto->getFormattedDateOnly(),
                    'formatted_time_only' => $statusDto->getFormattedTimeOnly(),
                    'pr_number' => $statusDto->prNumber,
                    'procurement_title' => $statusDto->procurementTitle,
                    'user_address' => $statusDto->userAddress,
                    'metadata' => $statusDto->metadata,
                ];
            })
            ->sort(function ($a, $b) {
                // Sort by timestamp descending
                $timestampA = $a['timestamp'] instanceof Carbon ? $a['timestamp']->timestamp : strtotime($a['timestamp']);
                $timestampB = $b['timestamp'] instanceof Carbon ? $b['timestamp']->timestamp : strtotime($b['timestamp']);

                if ($timestampA !== $timestampB) {
                    return $timestampB <=> $timestampA;
                }

                // If timestamps are equal, prioritize transitions
                $isTransitionA = isset($a['metadata']['transition']) && $a['metadata']['transition'] === true;
                $isTransitionB = isset($b['metadata']['transition']) && $b['metadata']['transition'] === true;

                if ($isTransitionA !== $isTransitionB) {
                    return $isTransitionA ? -1 : 1; // Transition comes first (descending order)
                }

                return 0;
            });
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessAllDocuments(string $pr_number): array
    {
        $documentDtos = $this->documentRepository->findByProcurement($pr_number);

        $totalAfterFilter = count($documentDtos);

        Log::debug('Document Fetching Stats', [
            'pr_number' => $pr_number,
            'total_after_filtering_by_id' => $totalAfterFilter,
        ]);

        return collect($documentDtos)
            ->map(function (DocumentData $doc) {
                $fileKey = $doc->fileKey;
                $hash = $doc->hash;
                $stage = $doc->stage;
                $timestamp = $doc->timestamp;

                $fileUrl = ! empty($fileKey) ? route('files.download', ['fileKey' => $fileKey]) : '';

                // Format stage metadata if present
                $stageMetadata = $doc->stageMetadata;
                if ($stageMetadata && is_array($stageMetadata)) {
                    $stageMetadata = $this->formatStageMetadata($stageMetadata);
                }

                return [
                    'file_key' => $fileKey,
                    'document_type' => $doc->documentType,
                    'document_type_formatted' => $this->formatDocumentType($doc->documentType),
                    'spaces_url' => $fileUrl,
                    'hash' => $hash,
                    'hash_short' => $doc->getShortenedHash(),
                    'hash_medium' => $doc->getShortenedHash(6, 4),
                    'file_size' => $doc->fileSize,
                    'file_size_formatted' => $doc->getFormattedFileSize(),
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_metadata' => $stageMetadata,
                    'pr_number' => $doc->prNumber,
                    'procurement_title' => $doc->procurementTitle,
                    'user_address' => $doc->userAddress,
                    'timestamp' => $timestamp,
                    'formatted_date' => $doc->getFormattedDateTime(),
                    'formatted_date_only' => $doc->getFormattedDateOnly(),
                    'formatted_time_only' => $doc->getFormattedTimeOnly(),
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Fetch and process events for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAndProcessEvents(string $pr_number): array
    {
        $eventDtos = $this->eventRepository->findByProcurement($pr_number);

        return collect($eventDtos)
            ->map(function (EventData $event) {
                $timestamp = $event->timestamp;
                $stage = $event->stage;

                return [
                    'timestamp' => $timestamp,
                    'formatted_date' => $event->getFormattedDateTime(),
                    'formatted_date_only' => $event->getFormattedDateOnly(),
                    'formatted_time_only' => $event->getFormattedTimeOnly(),
                    'event_type' => $event->eventType,
                    'event_type_formatted' => $this->formatEventType($event->eventType),
                    'details' => $event->details,
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_order' => $this->getStageOrderIndex($stage),
                    'document_count' => $event->documentCount,
                    'pr_number' => $event->prNumber,
                    'procurement_title' => $event->procurementTitle,
                    'user_address' => $event->userAddress,
                    'category' => $event->category,
                    'category_formatted' => $this->formatEventCategory($event->category),
                    'severity' => $event->severity,
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();
    }

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
            ],
            'documents' => $documents,
            'events' => $events,
            'timeline' => $statusItems->values()->toArray(),
        ];
    }

    /**
     * Preload user names for batch user lookup from raw stream items
     */
    public function preloadUserNames(Collection $items): void
    {
        $addresses = $items->pluck('data.json.user_address')->unique()->filter()->all();
        if (empty($addresses)) {
            return;
        }

        $names = User::whereIn('blockchain_address', $addresses)
            ->pluck('name', 'blockchain_address')
            ->all();

        $this->userCache = $names;
    }

    /**
     * Preload user names from DTOs for performance
     */
    private function preloadUserNamesFromDtos(Collection $statusDtos): void
    {
        $addresses = $statusDtos->map(fn (StatusData $dto) => $dto->userAddress)
            ->unique()
            ->filter()
            ->toArray();

        $this->userService->preloadUserNames($addresses);

        // Build local cache from UserService
        $this->userCache = [];
        foreach ($addresses as $address) {
            $this->userCache[$address] = $this->userService->getUserNameByAddress($address);
        }
    }

    /**
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        // Check local cache first, then fallback to UserService
        return $this->userCache[$address] ?? $this->userService->getUserNameByAddress($address);
    }

    // =====================================================================
    // FORMATTING METHODS (Merged from ProcurementFormatterService)
    // =====================================================================

    /**
     * Get all stages in order
     *
     * @return array<string>
     */
    private function getStageOrder(): array
    {
        return array_map(fn (StageEnums $stage) => $stage->getDisplayName(), StageEnums::cases());
    }

    /**
     * Get status badge variant based on status
     */
    private function getStatusVariant(StatusEnums $status): string
    {
        return match ($status) {
            StatusEnums::PROCUREMENT_SUBMITTED => 'default',
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD => 'secondary',
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED => 'outline',
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED => 'secondary',
            StatusEnums::BIDDING_DOCUMENTS_PUBLISHED => 'secondary',
            StatusEnums::BIDDING_DOCUMENTS_SUBMITTED => 'secondary',
            StatusEnums::PRE_BID_CONFERENCE_HELD => 'secondary',
            StatusEnums::PRE_BID_CONFERENCE_SKIPPED => 'outline',
            StatusEnums::PRE_BID_CONFERENCE_COMPLETED => 'secondary',
            StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING => 'default',
            StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED => 'secondary',
            StatusEnums::BIDS_OPENED => 'outline',
            StatusEnums::BIDS_EVALUATED => 'default',
            StatusEnums::POST_QUALIFICATION_VERIFIED => 'secondary',
            StatusEnums::POST_QUALIFICATION_FAILED => 'destructive',
            StatusEnums::RESOLUTION_RECORDED => 'default',
            StatusEnums::AWARDED => 'secondary',
            StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED => 'outline',
            StatusEnums::NTP_RECORDED => 'default',
            StatusEnums::MONITORING_COMPLETED => 'secondary',
            StatusEnums::COMPLETION_DOCUMENTS_UPLOADED => 'outline',
            StatusEnums::COMPLETED => 'default',
        };
    }

    /**
     * Format stage name to human-readable format
     */
    public function formatStageName(string $stage): string
    {
        if (empty($stage)) {
            return StageEnums::PROCUREMENT_INITIATION->getDisplayName();
        }

        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getDisplayName();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getDisplayName();
            }
        }

        return ucwords(str_replace('_', ' ', $stage));
    }

    /**
     * Format status name for display
     */
    public function formatStatus(string $statusText): string
    {
        if (empty($statusText)) {
            return 'Unknown Status';
        }

        $statusEnum = StatusEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $statusText)));

        if ($statusEnum !== null) {
            return $statusEnum->getDisplayName();
        }

        foreach (StatusEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $statusText) === 0) {
                return $case->getDisplayName();
            }
        }

        return ucwords(str_replace('_', ' ', strtolower($statusText)));
    }

    /**
     * Get status information including variant and formatted label
     *
     * @return array{variant: string, label: string, description: string}
     */
    public function getStatusInfo(string $statusText): array
    {
        if (empty($statusText)) {
            return [
                'variant' => 'outline',
                'label' => 'Unknown Status',
                'description' => 'Status information not available',
            ];
        }

        $statusEnum = StatusEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $statusText)));

        if ($statusEnum !== null) {
            return [
                'variant' => $this->getStatusVariant($statusEnum),
                'label' => $statusEnum->getDisplayName(),
                'description' => $statusEnum->getDescription(),
            ];
        }

        return [
            'variant' => 'outline',
            'label' => ucwords(str_replace('_', ' ', strtolower($statusText))),
            'description' => 'Status information not available',
        ];
    }

    /**
     * Format file size to human-readable format
     */
    public function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return 'N/A';
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log(1024));
        $decimals = $i === 1 ? 0 : ($i > 1 ? 1 : 0);
        $size = round($bytes / pow(1024, $i), $decimals);

        return number_format($size, $decimals, '.', ',').' '.$units[$i];
    }

    /**
     * Shorten hash for display
     */
    public function shortenHash(?string $hash, int $startLength = 5, int $endLength = 5): string
    {
        if (empty($hash)) {
            return 'N/A';
        }

        if (strlen($hash) <= $startLength + $endLength) {
            return $hash;
        }

        return substr($hash, 0, $startLength).'...'.substr($hash, -$endLength);
    }

    /**
     * Format timestamp to full date and time
     */
    public function formatDateTime(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('M j, Y, g:i A');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to date only
     */
    public function formatDateOnly(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('M j, Y');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to time only
     */
    public function formatTimeOnly(Carbon|string|null $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Time';
        }

        try {
            $date = $dateString instanceof Carbon ? $dateString : Carbon::parse($dateString);

            return $date->format('g:i A');
        } catch (\Exception $e) {
            return 'Invalid Time';
        }
    }

    /**
     * Get stage order index
     */
    public function getStageOrderIndex(string $stage): int
    {
        $formattedStage = $this->formatStageName($stage);
        $stageOrder = $this->getStageOrder();
        $index = array_search($formattedStage, $stageOrder);

        return $index !== false ? $index : 999;
    }

    /**
     * Get stage description
     */
    public function getStageDescription(string $stage): ?string
    {
        $stageEnum = StageEnums::tryFrom(strtolower(str_replace([' ', '-'], '_', $stage)));

        if ($stageEnum !== null) {
            return $stageEnum->getDescription();
        }

        foreach (StageEnums::cases() as $case) {
            if (strcasecmp($case->getDisplayName(), $stage) === 0) {
                return $case->getDescription();
            }
        }

        return null;
    }

    /**
     * Get total number of stages
     */
    public function getTotalStages(): int
    {
        return count(StageEnums::cases());
    }

    /**
     * Calculate progress percentage based on stage
     */
    public function calculateProgress(string $stage): float
    {
        $stageIndex = $this->getStageOrderIndex($stage) + 1;
        $totalStages = $this->getTotalStages();

        if ($stageIndex > 0 && $stageIndex <= $totalStages) {
            return ($stageIndex / $totalStages) * 100;
        }

        return 0.0;
    }

    /**
     * Format document type to human-readable format
     */
    public function formatDocumentType(string $documentType): string
    {
        if (empty($documentType)) {
            return 'Unknown Document';
        }

        $docTypeEnum = DocumentTypeEnums::fromString($documentType);

        if ($docTypeEnum !== null) {
            return $docTypeEnum->getDisplayName();
        }

        return ucwords(str_replace('_', ' ', $documentType));
    }

    /**
     * Format event type to human-readable format
     */
    public function formatEventType(string $eventType): string
    {
        if (empty($eventType)) {
            return 'Unknown Event';
        }

        // Map common event types to proper labels
        $eventTypeMap = [
            'document_upload' => 'Document Uploaded',
            'document_uploaded' => 'Document Uploaded',
            'stage_transition' => 'Stage Transition',
            'phase_transition' => 'Phase Transition',
            'stage_completed' => 'Stage Completed',
            'procurement_created' => 'Procurement Created',
            'procurement_completed' => 'Procurement Completed',
            'status_update' => 'Status Update',
            'document_verified' => 'Document Verified',
            'document_rejected' => 'Document Rejected',
            'approval_granted' => 'Approval Granted',
            'approval_rejected' => 'Approval Rejected',
        ];

        // Check if we have a predefined mapping
        $lowerEventType = strtolower($eventType);
        if (isset($eventTypeMap[$lowerEventType])) {
            return $eventTypeMap[$lowerEventType];
        }

        // Fallback: Convert underscores to spaces and capitalize words
        return ucwords(str_replace('_', ' ', $eventType));
    }

    /**
     * Format event category to human-readable format
     */
    public function formatEventCategory(string $category): string
    {
        if (empty($category)) {
            return '';
        }

        // Map common categories to proper labels
        $categoryMap = [
            'stage_transition' => 'Workflow',
            'document' => 'Document',
            'procurement' => 'Procurement',
            'workflow' => 'Workflow',
            'milestone' => 'Milestone',
            'approval' => 'Approval',
            'notification' => 'Notification',
        ];

        // Check if we have a predefined mapping
        $lowerCategory = strtolower($category);
        if (isset($categoryMap[$lowerCategory])) {
            return $categoryMap[$lowerCategory];
        }

        // Fallback: Convert underscores to spaces and capitalize words
        return ucwords(str_replace('_', ' ', $category));
    }

    /**
     * Format currency value with peso sign
     */
    public function formatCurrency(float|int|string|null $value): string
    {
        if ($value === null || $value === '' || $value === 0) {
            return '₱ 0.00';
        }

        $numericValue = is_string($value) ? (float) $value : $value;

        return '₱ '.number_format($numericValue, 2);
    }

    /**
     * Format stage metadata with all formatted fields
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function formatStageMetadata(array $metadata): array
    {
        $dateFields = [
            'meeting_date',
            'submission_date',
            'issuance_date',
            'opening_date',
            'evaluation_date',
            'signing_date',
            'report_date',
            'issue_date',
            'completion_date',
        ];

        foreach ($dateFields as $field) {
            if (isset($metadata[$field]) && ! empty($metadata[$field])) {
                $metadata[$field.'_formatted'] = $this->formatDateOnly($metadata[$field]);
            }
        }

        if (isset($metadata['validity_period']) && is_array($metadata['validity_period'])) {
            if (isset($metadata['validity_period']['start_date'])) {
                $metadata['validity_period']['start_date_formatted'] = $this->formatDateOnly(
                    $metadata['validity_period']['start_date']
                );
            }
            if (isset($metadata['validity_period']['end_date'])) {
                $metadata['validity_period']['end_date_formatted'] = $this->formatDateOnly(
                    $metadata['validity_period']['end_date']
                );
            }
        }

        $currencyFields = ['appropriation', 'bid_value', 'bond_amount'];
        foreach ($currencyFields as $field) {
            if (isset($metadata[$field]) && ! empty($metadata[$field])) {
                $metadata[$field.'_formatted'] = $this->formatCurrency($metadata[$field]);
            }
        }

        return $metadata;
    }

    // =====================================================================
    // DOCUMENT METHODS (Merged from DocumentBlockchainService)
    // =====================================================================

    /**
     * Get document data from blockchain by file key
     *
     * Merged from DocumentBlockchainService
     */
    public function getDocumentDataByFileKey(string $fileKey): ?array
    {
        try {
            Log::info('Attempting to get blockchain data', ['file_key' => $fileKey]);

            $allDocuments = $this->documentRepository->all();

            Log::info('Retrieved document stream items', [
                'file_key' => $fileKey,
                'total_items' => count($allDocuments),
            ]);

            $document = collect($allDocuments)
                ->first(fn (DocumentData $doc) => $doc->fileKey === $fileKey);

            if (! $document) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $document->hash,
                'pr_number' => $document->prNumber,
            ]);

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
        } catch (\Exception $e) {
            Log::error('Failed to get document data from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get current procurement status from blockchain
     *
     * Merged from DocumentBlockchainService - optimized to use existing method
     */
    public function getCurrentProcurementStatus(string $pr_number): ?array
    {
        try {
            $statusItems = $this->fetchStatusItems($pr_number);

            $latestStatus = $statusItems->first();

            if ($latestStatus) {
                return [
                    'current_status' => $latestStatus['current_status'] ?? '',
                    'stage' => $latestStatus['stage'] ?? '',
                    'timestamp' => $latestStatus['timestamp'] ?? '',
                    'pr_number' => $latestStatus['pr_number'] ?? '',
                    'procurement_title' => $latestStatus['procurement_title'] ?? '',
                    'user_address' => $latestStatus['user_address'] ?? '',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get procurement status', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Alternative method to get document hash by procurement ID and file pattern matching
     *
     * Merged from DocumentBlockchainService
     */
    public function getHashBypr_number(string $pr_number, string $fileKey): ?string
    {
        try {
            Log::info('Attempting alternative hash lookup', [
                'pr_number' => $pr_number,
                'file_key' => $fileKey,
            ]);

            $allDocuments = $this->documentRepository->all();

            $document = collect($allDocuments)
                ->first(function (DocumentData $doc) use ($pr_number, $fileKey) {
                    // Match by procurement ID
                    if ($doc->prNumber === $pr_number) {
                        return true;
                    }

                    // Pattern matching on file keys
                    $fileKeyParts = explode('/', $fileKey);
                    $docFileKeyParts = explode('/', $doc->fileKey);

                    if (count($fileKeyParts) >= 1 && count($docFileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $docFileKeyParts[0];
                    }

                    return false;
                });

            if ($document) {
                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($document->hash),
                    'hash_value' => $document->hash,
                    'matched_file_key' => $document->fileKey,
                ]);

                return $document->hash;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Alternative hash lookup failed', [
                'pr_number' => $pr_number,
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the file exists in document stream
     *
     * Merged from DocumentBlockchainService
     */
    public function validateDocumentExistsInBlockchain(string $fileKey): ?array
    {
        try {
            $allDocuments = $this->documentRepository->all();

            $document = collect($allDocuments)
                ->first(fn (DocumentData $doc) => $doc->fileKey === $fileKey);

            if ($document) {
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

            return null;
        } catch (\Exception $e) {
            Log::error('Blockchain validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
