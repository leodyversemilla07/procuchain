<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Enums\StreamEnums;
use App\Models\User;
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
    private MultichainService $multichainService;

    /**
     * @var array<string, string>
     */
    private array $userCache = [];

    private const STATUS_PAGE_SIZE = 1000;

    private const DOCUMENT_PAGE_SIZE = 10000;

    public function __construct(
        MultichainService $multichainService
    ) {
        $this->multichainService = $multichainService;
    }

    /**
     * Fetch and process all procurements for listing page
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessProcurements(): array
    {
        Log::info('fetchAndProcessProcurements: Fetching status items from MultiChain');
        $statusItems = $this->multichainService->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            self::STATUS_PAGE_SIZE,
            0,
            false
        );

        if ($statusItems === null) {
            Log::error('fetchAndProcessProcurements: Failed to retrieve status stream items');
            throw new Exception('Failed to retrieve status stream items');
        }

        Log::info('fetchAndProcessProcurements: Retrieved status items', ['count' => count($statusItems)]);

        Log::info('fetchAndProcessProcurements: Fetching document items from MultiChain');
        $documentItems = $this->multichainService->listStreamItems(
            StreamEnums::DOCUMENTS->value,
            true,
            self::DOCUMENT_PAGE_SIZE,
            0,
            false
        );

        if ($documentItems === null) {
            Log::error('fetchAndProcessProcurements: Failed to retrieve document stream items');
            throw new Exception('Failed to retrieve document stream items');
        }

        Log::info('fetchAndProcessProcurements: Retrieved document items', ['count' => count($documentItems)]);

        // Preload user names
        $this->preloadUserNames(collect($statusItems));

        // Build a map: procurement_id => document count (unique by hash)
        $documentCountMap = collect($documentItems)
            ->filter(fn ($item) => isset($item['data']['json']['procurement_id']) && isset($item['data']['json']['hash']))
            ->groupBy(fn ($item) => $item['data']['json']['procurement_id'])
            ->map(function ($items) {
                return collect($items)
                    ->map(fn ($item) => $item['data']['json']['hash'])
                    ->unique()
                    ->count();
            });

        Log::info('fetchAndProcessProcurements: Built document count map', ['count' => $documentCountMap->count()]);

        // Map procurements, using the precomputed document count
        $result = collect($statusItems)
            ->map(function ($item) use ($documentCountMap) {
                $data = $item['data']['json'] ?? [];
                $originalTimestamp = $data['timestamp'] ?? null;
                $displayTimestamp = isset($data['timestamp'])
                    ? Carbon::parse($data['timestamp'])->toDateString()
                    : Carbon::now()->toDateString();

                $currentStatus = $data['current_status'] ?? '';
                $stage = $data['stage'] ?? '';

                return [
                    'id' => $data['procurement_id'] ?? null,
                    'title' => $data['procurement_title'] ?? null,
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'current_status' => $currentStatus,
                    'status_formatted' => $this->formatStatus($currentStatus),
                    'timestamp' => $originalTimestamp,
                    'display_date' => $displayTimestamp,
                    'last_updated' => $displayTimestamp,
                    'user_address' => $data['user_address'] ?? '',
                    'user' => $this->getUserName($data['user_address'] ?? ''),
                    'document_count' => $documentCountMap[$data['procurement_id'] ?? ''] ?? 0,
                ];
            })
            ->groupBy('id')
            ->map(function ($group) {
                return $group->sortByDesc(function ($item) {
                    return strtotime($item['timestamp'] ?? '0');
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
    public function fetchStatusItems(string $procurementId): Collection
    {
        $statusStreamItems = $this->multichainService->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            self::STATUS_PAGE_SIZE,
            0,
            false
        );

        if (! $statusStreamItems) {
            throw new Exception('Status stream items not found');
        }

        return collect($statusStreamItems)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];

                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];
                $stage = $data['stage'] ?? '';
                $currentStatus = $data['current_status'] ?? '';

                return [
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_description' => $this->getStageDescription($stage),
                    'stage_order' => $this->getStageOrderIndex($stage),
                    'current_status' => $currentStatus,
                    'status' => $currentStatus,
                    'status_formatted' => $this->formatStatus($currentStatus),
                    'timestamp' => $data['timestamp'] ?? '',
                    'formatted_date' => $this->formatDateTime($data['timestamp'] ?? ''),
                    'formatted_date_only' => $this->formatDateOnly($data['timestamp'] ?? ''),
                    'formatted_time_only' => $this->formatTimeOnly($data['timestamp'] ?? ''),
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            })
            ->sortByDesc('timestamp');
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAndProcessAllDocuments(string $procurementId): array
    {
        $allDocumentItems = $this->multichainService->listStreamItems(
            StreamEnums::DOCUMENTS->value,
            true,
            self::DOCUMENT_PAGE_SIZE,
            0,
            false
        );

        if ($allDocumentItems === null) {
            Log::warning('Failed to retrieve any document stream items.', ['procurement_id' => $procurementId]);

            return [];
        }

        $totalFetched = count($allDocumentItems);

        $filteredItems = collect($allDocumentItems)
            ->filter(function ($item) use ($procurementId) {
                return isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $procurementId;
            });

        $totalAfterFilter = $filteredItems->count();

        Log::debug('Document Fetching Stats', [
            'procurement_id' => $procurementId,
            'total_fetched_from_stream' => $totalFetched,
            'total_after_filtering_by_id' => $totalAfterFilter,
        ]);

        return $filteredItems
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];
                $fileKey = $data['file_key'] ?? '';
                $hash = $data['hash'] ?? '';
                $stage = $data['stage'] ?? '';
                $timestamp = $data['timestamp'] ?? '';

                // Cast file_size to int or null to ensure type safety
                $fileSize = isset($data['file_size']) && $data['file_size'] !== ''
                    ? (int) $data['file_size']
                    : null;

                $fileUrl = ! empty($fileKey) ? route('files.download', ['fileKey' => $fileKey]) : '';

                // Format stage metadata if present
                $stageMetadata = $data['stage_metadata'] ?? null;
                if ($stageMetadata && is_array($stageMetadata)) {
                    $stageMetadata = $this->formatStageMetadata($stageMetadata);
                }

                return [
                    'file_key' => $fileKey,
                    'document_type' => $data['document_type'] ?? '',
                    'document_type_formatted' => $this->formatDocumentType($data['document_type'] ?? ''),
                    'spaces_url' => $fileUrl,
                    'hash' => $hash,
                    'hash_short' => $this->shortenHash($hash),
                    'hash_medium' => $this->shortenHash($hash, 6, 4),
                    'file_size' => $fileSize,
                    'file_size_formatted' => $this->formatFileSize($fileSize),
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_metadata' => $stageMetadata,
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $timestamp,
                    'formatted_date' => $this->formatDateTime($timestamp),
                    'formatted_date_only' => $this->formatDateOnly($timestamp),
                    'formatted_time_only' => $this->formatTimeOnly($timestamp),
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
    public function fetchAndProcessEvents(string $procurementId): array
    {
        $events = $this->multichainService->listStreamItems(
            StreamEnums::EVENTS->value
        );

        return collect($events)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];

                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];
                $timestamp = $data['timestamp'] ?? '';
                // Use 'stage' field (stream filter uses 'stage', not 'stage_identifier')
                $stage = $data['stage'] ?? $data['stage_identifier'] ?? '';

                return [
                    'timestamp' => $timestamp,
                    'formatted_date' => $this->formatDateTime($timestamp),
                    'formatted_date_only' => $this->formatDateOnly($timestamp),
                    'formatted_time_only' => $this->formatTimeOnly($timestamp),
                    'event_type' => $data['event_type'] ?? '',
                    'details' => $data['details'] ?? '',
                    'stage' => $stage,
                    'stage_formatted' => $this->formatStageName($stage),
                    'stage_order' => $this->getStageOrderIndex($stage),
                    'document_count' => $data['document_count'] ?? null,
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'category' => $data['category'] ?? '',
                    'severity' => $data['severity'] ?? '',
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
        string $procurementId,
        array $currentStatus,
        array $documents,
        array $events,
        Collection $statusItems
    ): array {
        $stage = $currentStatus['stage'] ?? '';
        $progress = $this->calculateProgress($stage);

        return [
            'id' => $procurementId,
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
                'procurement_id' => $currentStatus['procurement_id'] ?? '',
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
     * Preload user names for batch user lookup
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
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        return $this->userCache[$address] ?? 'Unknown';
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
    public function formatDateTime(?string $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            return Carbon::parse($dateString)->format('M j, Y, g:i A');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to date only
     */
    public function formatDateOnly(?string $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Date';
        }

        try {
            return Carbon::parse($dateString)->format('F j, Y');
        } catch (\Exception $e) {
            return 'Invalid Date';
        }
    }

    /**
     * Format timestamp to time only
     */
    public function formatTimeOnly(?string $dateString): string
    {
        if (empty($dateString)) {
            return 'Invalid Time';
        }

        try {
            return Carbon::parse($dateString)->format('g:i A');
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

            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                self::DOCUMENT_PAGE_SIZE,
                0,
                false
            );

            if ($allDocumentItems === null) {
                Log::warning('Failed to retrieve document stream items.', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Retrieved document stream items', [
                'file_key' => $fileKey,
                'total_items' => count($allDocumentItems),
            ]);

            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($fileKey) {
                    return isset($item['data']['json']['file_key']) &&
                        $item['data']['json']['file_key'] === $fileKey;
                })
                ->first();

            if (! $documentItem) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            $data = $documentItem['data']['json'] ?? [];

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $data['hash'] ?? 'NOT SET',
                'procurement_id' => $data['procurement_id'] ?? 'NOT SET',
            ]);

            return [
                'procurement_id' => $data['procurement_id'] ?? 'Unknown',
                'procurement_title' => $data['procurement_title'] ?? 'Unknown Document',
                'document_type' => $data['document_type'] ?? pathinfo($fileKey, PATHINFO_FILENAME),
                'stage' => $data['stage'] ?? 'Unknown',
                'file_size' => $data['file_size'] ?? null,
                'timestamp' => $data['timestamp'] ?? now()->toISOString(),
                'hash' => $data['hash'] ?? '',
                'user_address' => $data['user_address'] ?? 'unknown@example.com',
                'stage_metadata' => $data['stage_metadata'] ?? null,
                'data_txid' => $data['data_txid'] ?? null,
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
    public function getCurrentProcurementStatus(string $procurementId): ?array
    {
        try {
            $statusItems = $this->fetchStatusItems($procurementId);

            $latestStatus = $statusItems->first();

            if ($latestStatus) {
                return [
                    'current_status' => $latestStatus['current_status'] ?? '',
                    'stage' => $latestStatus['stage'] ?? '',
                    'timestamp' => $latestStatus['timestamp'] ?? '',
                    'procurement_id' => $latestStatus['procurement_id'] ?? '',
                    'procurement_title' => $latestStatus['procurement_title'] ?? '',
                    'user_address' => $latestStatus['user_address'] ?? '',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get procurement status', [
                'procurement_id' => $procurementId,
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
    public function getHashByProcurementId(string $procurementId, string $fileKey): ?string
    {
        try {
            Log::info('Attempting alternative hash lookup', [
                'procurement_id' => $procurementId,
                'file_key' => $fileKey,
            ]);

            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                self::DOCUMENT_PAGE_SIZE,
                0,
                false
            );

            if ($allDocumentItems === null) {
                return null;
            }

            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($procurementId, $fileKey) {
                    $data = $item['data']['json'] ?? [];
                    $itemProcurementId = $data['procurement_id'] ?? '';
                    $itemFileKey = $data['file_key'] ?? '';

                    if ($itemProcurementId === $procurementId) {
                        return true;
                    }

                    $fileKeyParts = explode('/', $fileKey);
                    $itemFileKeyParts = explode('/', $itemFileKey);

                    if (count($fileKeyParts) >= 1 && count($itemFileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $itemFileKeyParts[0];
                    }

                    return false;
                })
                ->first();

            if ($documentItem) {
                $data = $documentItem['data']['json'] ?? [];
                $hash = $data['hash'] ?? null;

                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($hash),
                    'hash_value' => $hash,
                    'matched_file_key' => $data['file_key'] ?? 'NOT SET',
                ]);

                return $hash;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Alternative hash lookup failed', [
                'procurement_id' => $procurementId,
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
            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                self::DOCUMENT_PAGE_SIZE,
                0,
                false
            );

            if ($allDocumentItems === null) {
                return null;
            }

            foreach ($allDocumentItems as $item) {
                $data = $item['data']['json'] ?? [];
                if (($data['file_key'] ?? '') === $fileKey) {
                    return $data;
                }
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
