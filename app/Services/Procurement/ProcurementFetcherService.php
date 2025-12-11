<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DataTransferObjects\CorrectionData;
use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\DataTransferObjects\StatusData;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching and aggregating procurement data from blockchain repositories
 *
 * Handles:
 * - Fetching status, documents, events, corrections from repositories
 * - Aggregating data across multiple repositories
 * - User name resolution and caching
 * - Sorting and filtering raw blockchain data
 */
final class ProcurementFetcherService
{
    /**
     * @var array<string, string>
     */
    private array $userCache = [];

    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly EventRepository $eventRepository,
        private readonly CorrectionRepository $correctionRepository,
        private readonly ProcurementRepository $procurementRepository,
        private readonly UserService $userService,
        private readonly ProcurementFormatterService $formatter,
        private readonly ProcurementActionService $actionService,
    ) {}

    /**
     * Fetch and process all procurement data for listing
     *
     * Performance optimizations applied (per MultiChain official docs):
     * - Key-based queries (liststreamkeys + liststreamkeyitems) - 10x faster
     * - verbose=false on all queries - 60% data transfer reduction
     * - local-ordering=true for faster execution
     * - Batch queries to prevent N+1 problems
     * - Short cache TTL (5min) for balance between speed and freshness
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllProcurements(bool $skipActions = true): array
    {
        // Cache for configurable TTL (default 2 minutes for balance between performance and freshness)
        $cacheTtl = (int) config('blockchain.cache.procurement_list_ttl', 120); // seconds

        return Cache::remember('procurements:list:all:v2', now()->addSeconds($cacheTtl), function () {
            try {
                Log::info('ProcurementFetcherService: Starting OPTIMIZED fetch from repositories');

                // OPTIMIZATION 1: Use optimized repository method that fetches only latest per PR
                $statusDtos = $this->statusRepository->getLatestByProcurement(100);
                $statusItems = collect($statusDtos);

                // OPTIMIZATION 2: Fetch documents with limit for faster response
                $documentDtos = $this->documentRepository->all(500, 0);

                Log::info('ProcurementFetcherService: Fetched data from repositories (OPTIMIZED)', [
                    'status_count' => $statusItems->count(),
                    'document_count' => count($documentDtos),
                    'optimized' => true,
                ]);

                // OPTIMIZATION 3: Batch user preloading
                $this->preloadUserNamesFromDtos($statusItems);

                // OPTIMIZATION 4: Use efficient array operations for document counting
                $documentCountMap = [];
                foreach ($documentDtos as $doc) {
                    $prNumber = $doc->prNumber;
                    $documentCountMap[$prNumber] = ($documentCountMap[$prNumber] ?? 0) + 1;
                }

                // Build procurement mode map (RESTORED - needed for frontend display)
                $procurementModeMap = $this->buildProcurementModeMap($statusItems);

                // Process status items to get latest status per procurement
                $result = $statusItems
                    ->map(function (StatusData $statusDto) use ($documentCountMap, $procurementModeMap) {
                        $originalTimestamp = $statusDto->timestamp;
                        $displayTimestamp = Carbon::parse($statusDto->timestamp)->toDateString();

                        $stageEnum = StageEnums::tryFrom($statusDto->stage);
                        $phase = $stageEnum?->getPhase() ?? 'unknown';
                        $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';
                        $phaseProgress = $stageEnum?->getPhaseProgress() ?? [
                            'phase' => 'unknown',
                            'progress' => 0,
                            'current_stage_in_phase' => 0,
                            'total_stages_in_phase' => 0,
                        ];

                        // Get procurement mode for this item
                        $modeInfo = $procurementModeMap[$statusDto->prNumber] ?? null;

                        // Get user role for action determination
                        $userRole = $this->getCurrentUserRole();

                        // Get available actions from the action service
                        try {
                            $workflowActions = $this->actionService->getAvailableActions(
                                $statusDto->prNumber,
                                $statusDto->stage,
                                $statusDto->currentStatus,
                                $userRole
                            );
                            $staticActions = $this->actionService->getStaticActions($statusDto->prNumber, $userRole);
                        } catch (\Exception $e) {
                            Log::debug('Failed to fetch actions for procurement, using empty actions', [
                                'pr_number' => $statusDto->prNumber,
                                'error' => $e->getMessage(),
                            ]);
                            $workflowActions = [];
                            $staticActions = [];
                        }

                        return [
                            'id' => $statusDto->prNumber,
                            'title' => $statusDto->procurementTitle,
                            'stage' => $statusDto->stage,
                            'stage_formatted' => $this->formatter->formatStageName($statusDto->stage),
                            'phase' => $phase,
                            'phase_display' => $phaseDisplayName,
                            'phase_progress' => $phaseProgress,
                            'current_status' => $statusDto->currentStatus,
                            'status_formatted' => $this->formatter->formatStatus($statusDto->currentStatus),
                            'timestamp' => $originalTimestamp,
                            'display_date' => $displayTimestamp,
                            'last_updated' => $displayTimestamp,
                            'user_address' => $statusDto->userAddress,
                            'user' => $this->getUserName($statusDto->userAddress),
                            'document_count' => $documentCountMap[$statusDto->prNumber] ?? 0,
                            'metadata' => $statusDto->metadata,
                            'procurement_mode' => $modeInfo['value'] ?? null,
                            'procurement_mode_label' => $modeInfo['label'] ?? null,
                            'workflow_actions' => $workflowActions,
                            'static_actions' => $staticActions,
                        ];
                    })
                    ->groupBy('id')
                    ->map(function ($group) {
                        return $group->sortByDesc(function ($item) {
                            $timestamp = $item['timestamp'] ?? '0';
                            $unixTimestamp = $timestamp instanceof Carbon ? $timestamp->timestamp : strtotime($timestamp);

                            // Prioritize transitions when timestamps are identical
                            $isTransition = isset($item['metadata']['transition']) && $item['metadata']['transition'] === true;
                            $priorityOffset = $isTransition ? 0.001 : 0;

                            return $unixTimestamp + $priorityOffset;
                        })->first();
                    })
                    ->sortByDesc(function ($item) {
                        // Sort all procurements by timestamp in descending order (newest first)
                        $timestamp = $item['timestamp'] ?? '0';

                        return $timestamp instanceof Carbon ? $timestamp->timestamp : strtotime($timestamp);
                    })
                    ->values()
                    ->all();

                Log::info('ProcurementFetcherService: Final procurements result count', ['count' => count($result)]);

                return $result;
            } catch (\Exception $e) {
                Log::error('Failed to fetch procurement data, blockchain may be unavailable', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Return empty array to allow page to load with error message
                return [];
            }
        });
    }

    /**
     * Build a map of procurement modes by PR number (OPTIMIZED)
     * Uses batch fetching to avoid N+1 query problem
     *
     * @return array<string, array{value: string, label: string}>
     */
    private function buildProcurementModeMap(Collection $statusItems): array
    {
        try {
            $prNumbers = $statusItems->pluck('prNumber')->unique()->values()->all();

            // OPTIMIZATION: Batch fetch all procurements in one query instead of N queries
            $procurements = $this->procurementRepository->findManyByProcurement($prNumbers);

            $modeMap = [];
            foreach ($procurements as $prNumber => $procurement) {
                if ($procurement && $procurement->procurementMode) {
                    $modeMap[$prNumber] = [
                        'value' => $procurement->procurementMode->value,
                        'label' => $procurement->procurementMode->getDisplayName(),
                    ];
                }
            }

            Log::debug('Built procurement mode map', [
                'total_procurements' => count($prNumbers),
                'with_modes' => count($modeMap),
            ]);

            return $modeMap;
        } catch (\Exception $e) {
            Log::warning('Failed to build procurement mode map, blockchain may be unavailable', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the current authenticated user's role
     */
    private function getCurrentUserRole(): string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 'guest';
        }

        // Get the first role from the user's roles
        $roles = $user->getRoleNames();

        return $roles->first() ?? 'guest';
    }

    /**
     * Fetch status items for a specific procurement
     */
    public function fetchStatusItems(string $prNumber): Collection
    {
        $statusDtos = $this->statusRepository->findByProcurement($prNumber);

        return collect($statusDtos)
            ->map(function (StatusData $statusDto) {
                $stage = $statusDto->stage;
                $currentStatus = $statusDto->currentStatus;

                $stageEnum = StageEnums::tryFrom($stage);
                $phase = $stageEnum?->getPhase() ?? 'unknown';
                $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';

                return [
                    'stage' => $stage,
                    'stage_formatted' => $this->formatter->formatStageName($stage),
                    'stage_description' => $this->formatter->getStageDescription($stage),
                    'stage_order' => $this->formatter->getStageOrderIndex($stage),
                    'phase' => $phase,
                    'phase_display' => $phaseDisplayName,
                    'current_status' => $currentStatus,
                    'status' => $currentStatus,
                    'status_formatted' => $this->formatter->formatStatus($currentStatus),
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
                $timestampA = $a['timestamp'] instanceof Carbon ? $a['timestamp']->timestamp : strtotime($a['timestamp']);
                $timestampB = $b['timestamp'] instanceof Carbon ? $b['timestamp']->timestamp : strtotime($b['timestamp']);

                if ($timestampA !== $timestampB) {
                    return $timestampB <=> $timestampA;
                }

                // If timestamps are equal, prioritize transitions
                $isTransitionA = isset($a['metadata']['transition']) && $a['metadata']['transition'] === true;
                $isTransitionB = isset($b['metadata']['transition']) && $b['metadata']['transition'] === true;

                if ($isTransitionA !== $isTransitionB) {
                    return $isTransitionA ? -1 : 1;
                }

                return 0;
            });
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchDocuments(string $prNumber): array
    {
        $documentDtos = $this->documentRepository->findByProcurement($prNumber);

        Log::debug('Document Fetching Stats', [
            'pr_number' => $prNumber,
            'total_after_filtering_by_id' => count($documentDtos),
        ]);

        // Fetch corrections for this procurement
        $correctionDtos = $this->correctionRepository->findByProcurement($prNumber);
        $correctionsByTxid = collect($correctionDtos)->groupBy(fn (CorrectionData $correction) => $correction->originalTxid);

        return collect($documentDtos)
            ->map(function (DocumentData $doc) use ($correctionsByTxid) {
                $fileKey = $doc->fileKey;
                $fileUrl = ! empty($fileKey) ? route('files.download', ['fileKey' => $fileKey]) : '';

                $stageMetadata = $doc->stageMetadata;
                if ($stageMetadata && is_array($stageMetadata)) {
                    $stageMetadata = $this->formatter->formatStageMetadata($stageMetadata);
                }

                // Check for corrections
                $documentCorrections = $correctionsByTxid->get($doc->dataTxid, collect());
                $hasCorrections = $documentCorrections->isNotEmpty();
                $latestCorrection = null;

                if ($hasCorrections) {
                    $latestCorrectionData = $documentCorrections->sortByDesc(fn (CorrectionData $c) => $c->timestamp)->first();

                    if ($latestCorrectionData) {
                        $latestCorrection = [
                            'txid' => $latestCorrectionData->txid,
                            'timestamp' => $latestCorrectionData->timestamp->toIso8601String(),
                            'correction_type' => $latestCorrectionData->correctionType,
                            'correction_type_display' => $this->formatter->formatCorrectionType($latestCorrectionData->correctionType),
                            'action' => $latestCorrectionData->action,
                            'reason' => $latestCorrectionData->reason,
                            'corrected_by' => $latestCorrectionData->correctedBy,
                            'corrected_metadata' => $latestCorrectionData->correctedMetadata,
                        ];
                    }
                }

                return [
                    'file_key' => $fileKey,
                    'document_type' => $doc->documentType,
                    'document_type_formatted' => $this->formatter->formatDocumentType($doc->documentType),
                    'spaces_url' => $fileUrl,
                    'hash' => $doc->hash,
                    'hash_short' => $doc->getShortenedHash(),
                    'hash_medium' => $doc->getShortenedHash(6, 4),
                    'file_size' => $doc->fileSize,
                    'file_size_formatted' => $doc->getFormattedFileSize(),
                    'stage' => $doc->stage,
                    'stage_formatted' => $this->formatter->formatStageName($doc->stage),
                    'stage_metadata' => $stageMetadata,
                    'pr_number' => $doc->prNumber,
                    'procurement_title' => $doc->procurementTitle,
                    'user_address' => $doc->userAddress,
                    'timestamp' => $doc->timestamp,
                    'formatted_date' => $doc->getFormattedDateTime(),
                    'formatted_date_only' => $doc->getFormattedDateOnly(),
                    'formatted_time_only' => $doc->getFormattedTimeOnly(),
                    'metadata_txid' => $doc->metadataTxid,
                    'data_txid' => $doc->dataTxid,
                    'has_corrections' => $hasCorrections,
                    'latest_correction' => $latestCorrection,
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
    public function fetchEvents(string $prNumber): array
    {
        $eventDtos = $this->eventRepository->findByProcurement($prNumber);

        return collect($eventDtos)
            ->map(function (EventData $event) {
                return [
                    'timestamp' => $event->timestamp,
                    'formatted_date' => $event->getFormattedDateTime(),
                    'formatted_date_only' => $event->getFormattedDateOnly(),
                    'formatted_time_only' => $event->getFormattedTimeOnly(),
                    'event_type' => $event->eventType,
                    'event_type_formatted' => $this->formatter->formatEventType($event->eventType),
                    'details' => $event->details,
                    'stage' => $event->stage,
                    'stage_formatted' => $this->formatter->formatStageName($event->stage),
                    'stage_order' => $this->formatter->getStageOrderIndex($event->stage),
                    'document_count' => $event->documentCount,
                    'pr_number' => $event->prNumber,
                    'procurement_title' => $event->procurementTitle,
                    'user_address' => $event->userAddress,
                    'category' => $event->category,
                    'category_formatted' => $this->formatter->formatEventCategory($event->category),
                    'severity' => $event->severity,
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Get document data from blockchain by file key
     */
    public function getDocumentByFileKey(string $fileKey): ?DocumentData
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

            return $document;
        } catch (\Exception $e) {
            Log::error('Failed to get document data from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get hash by procurement number and file key pattern matching
     */
    public function getHashByPrNumber(string $prNumber, string $fileKey): ?string
    {
        try {
            Log::info('Attempting alternative hash lookup', [
                'pr_number' => $prNumber,
                'file_key' => $fileKey,
            ]);

            $allDocuments = $this->documentRepository->all();

            $document = collect($allDocuments)
                ->first(function (DocumentData $doc) use ($prNumber, $fileKey) {
                    if ($doc->prNumber === $prNumber) {
                        return true;
                    }

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
                'pr_number' => $prNumber,
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the file exists in document stream
     */
    public function validateDocumentExists(string $fileKey): ?DocumentData
    {
        try {
            $allDocuments = $this->documentRepository->all();

            return collect($allDocuments)
                ->first(fn (DocumentData $doc) => $doc->fileKey === $fileKey);
        } catch (\Exception $e) {
            Log::error('Blockchain validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        return $this->userCache[$address] ?? $this->userService->getUserNameByAddress($address);
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

        $this->userCache = [];
        foreach ($addresses as $address) {
            $this->userCache[$address] = $this->userService->getUserNameByAddress($address);
        }
    }
}
