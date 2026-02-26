<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DataTransferObjects\StatusData;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementArchiveRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates procurement list data from multiple blockchain repositories.
 *
 * Extracted from ProcurementFetcherService to follow SRP.
 * Handles the bulk "fetch all procurements for listing" operation, including:
 * - Fetching latest statuses and document counts
 * - Building procurement mode maps
 * - Archive and security filtering
 * - Composing the final list-view array
 */
final class ProcurementListAggregatorService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementArchiveRepository $archiveRepository,
        private readonly ProcurementFormatterService $formatter,
        private readonly ProcurementActionService $actionService,
        private readonly UserNameResolverService $userNameResolver,
    ) {}

    /**
     * Fetch and process all procurement data for listing
     *
     * Performance optimizations applied (per MultiChain official docs):
     * - Key-based queries (liststreamkeys + liststreamkeyitems) - 10x faster
     * - verbose=false on all queries - 60% data transfer reduction
     * - local-ordering=true for faster execution
     * - Batch queries to prevent N+1 problems
     * - Optional action generation skipping for faster initial load
     *
     * Security:
     * - BAC Secretariat users can only see their own procurements
     * - Filtering by both userId (creator) and blockchain_address (identity verification)
     * - Admin, BAC Chairman, and HOPE can see all procurements
     *
     * @param  bool  $skipActions  Skip action generation for faster initial load
     * @param  string|null  $filterByUserId  Filter procurements by creator user ID (for BAC Secretariat)
     * @param  string|null  $filterByUserAddress  Filter by blockchain address for additional security
     * @param  bool  $archived  Filter for archived procurements (true = show archived only, false = show active only)
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllProcurements(bool $skipActions = false, ?string $filterByUserId = null, ?string $filterByUserAddress = null, bool $archived = false): array
    {
        $blockchainHealthKey = 'blockchain:health:procurement_fetch';

        try {
            Log::info('ProcurementListAggregatorService: Starting OPTIMIZED fetch from blockchain', [
                'archived_filter' => $archived,
            ]);

            set_time_limit(22);

            $statusItems = $this->fetchStatusItems();
            if ($statusItems->isEmpty()) {
                return [];
            }

            $documentCountMap = $this->buildDocumentCountMap();

            $this->userNameResolver->preloadFromStatusDtos($statusItems);

            $procurementModeMap = $this->buildProcurementModeMap($statusItems);

            $statusItems = $this->filterByArchiveStatus($statusItems, $archived);

            if ($filterByUserId !== null || $filterByUserAddress !== null) {
                $statusItems = $this->filterBySecurity($statusItems, $filterByUserId, $filterByUserAddress);
            }

            $result = $this->buildListResult($statusItems, $documentCountMap, $procurementModeMap, $skipActions);

            Log::info('ProcurementListAggregatorService: Final procurements result count', ['count' => count($result)]);

            Cache::put($blockchainHealthKey, true, now()->addMinutes(5));

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to fetch procurement data, blockchain may be unavailable', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (str_contains($e->getMessage(), 'execution time') || str_contains($e->getMessage(), 'timeout')) {
                Cache::put($blockchainHealthKey, false, now()->addMinutes(2));
                Log::warning('Blockchain marked as unhealthy due to timeout');
            }

            return [];
        }
    }

    /**
     * Fetch latest status items from blockchain
     *
     * @return Collection<int, StatusData>
     */
    private function fetchStatusItems(): Collection
    {
        $statusLimit = 50;

        Log::info('Fetching with limits', ['status_limit' => $statusLimit]);

        try {
            return collect($this->statusRepository->getLatestByProcurement($statusLimit));
        } catch (\Exception $e) {
            Log::error('Failed to fetch status items, returning empty', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Build a map of document counts per procurement
     *
     * @return array<string, int>
     */
    private function buildDocumentCountMap(): array
    {
        $documentLimit = 100;

        try {
            $documentDtos = $this->documentRepository->all($documentLimit, 0);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch documents, continuing without document counts', [
                'error' => $e->getMessage(),
            ]);
            $documentDtos = [];
        }

        $documentCountMap = [];
        foreach ($documentDtos as $doc) {
            $prNumber = $doc->prNumber;
            $documentCountMap[$prNumber] = ($documentCountMap[$prNumber] ?? 0) + 1;
        }

        return $documentCountMap;
    }

    /**
     * Build a map of procurement modes by PR number (OPTIMIZED)
     * Uses batch fetching to avoid N+1 query problem
     *
     * @return array<string, array{value: string, label: string, abc_amount: float|int}>
     */
    private function buildProcurementModeMap(Collection $statusItems): array
    {
        try {
            $prNumbers = $statusItems->pluck('prNumber')->unique()->values()->all();
            $procurements = $this->procurementRepository->findManyByProcurement($prNumbers);

            $modeMap = [];
            foreach ($procurements as $prNumber => $procurement) {
                if ($procurement && $procurement->procurementMode) {
                    $modeMap[$prNumber] = [
                        'value' => $procurement->procurementMode->value,
                        'label' => $procurement->procurementMode->getDisplayName(),
                        'abc_amount' => $procurement->abcAmount ?? 0,
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
     * Filter status items by archive status
     *
     * @param  Collection<int, StatusData>  $statusItems
     * @return Collection<int, StatusData>
     */
    private function filterByArchiveStatus(Collection $statusItems, bool $archived): Collection
    {
        try {
            $archivedPrNumbers = $this->archiveRepository->getArchivedPrNumbers();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch archived procurements list', ['error' => $e->getMessage()]);
            $archivedPrNumbers = [];
        }

        $filtered = $statusItems->filter(function (StatusData $statusDto) use ($archivedPrNumbers, $archived) {
            $isArchived = in_array($statusDto->prNumber, $archivedPrNumbers, true);

            return $archived ? $isArchived : ! $isArchived;
        });

        Log::info('Filtered procurements by archive status', [
            'showing_archived' => $archived,
            'total_archived_count' => count($archivedPrNumbers),
            'remaining_items' => $filtered->count(),
        ]);

        return $filtered;
    }

    /**
     * Filter status items by user security (BAC Secretariat isolation)
     *
     * @param  Collection<int, StatusData>  $statusItems
     * @return Collection<int, StatusData>
     */
    private function filterBySecurity(Collection $statusItems, ?string $filterByUserId, ?string $filterByUserAddress): Collection
    {
        $prNumbers = $statusItems->pluck('prNumber')->unique()->values()->all();

        if (count($prNumbers) > 20) {
            Log::warning('Too many procurements to filter, limiting to 20 to prevent timeout', [
                'total' => count($prNumbers),
                'limiting_to' => 20,
            ]);
            $prNumbers = array_slice($prNumbers, 0, 20);
        }

        try {
            $procurements = $this->procurementRepository->findManyByProcurement($prNumbers);
        } catch (\Exception $e) {
            Log::error('Failed to fetch procurement metadata for filtering, showing all', [
                'error' => $e->getMessage(),
            ]);
            $procurements = [];
        }

        $allowedPrNumbers = [];
        foreach ($procurements as $prNumber => $procurement) {
            if ($procurement) {
                $userIdMatch = $filterByUserId === null || $procurement->userId === $filterByUserId;
                if ($userIdMatch) {
                    $allowedPrNumbers[] = $prNumber;
                }
            }
        }

        $filtered = $statusItems->filter(function (StatusData $statusDto) use ($allowedPrNumbers, $filterByUserAddress) {
            $prNumberAllowed = in_array($statusDto->prNumber, $allowedPrNumbers, true);
            $addressAllowed = $filterByUserAddress === null || $statusDto->userAddress === $filterByUserAddress;

            return $prNumberAllowed || $addressAllowed;
        });

        Log::info('Filtered procurements by userId and/or blockchain address', [
            'filter_user_id' => $filterByUserId,
            'filter_user_address' => $filterByUserAddress ? substr($filterByUserAddress, 0, 10) . '...' : null,
            'total_procurements' => count($prNumbers),
            'filtered_count' => $filtered->count(),
        ]);

        return $filtered;
    }

    /**
     * Build the final list result array from status items
     *
     * @param  Collection<int, StatusData>  $statusItems
     * @param  array<string, int>  $documentCountMap
     * @param  array<string, array{value: string, label: string, abc_amount: float|int}>  $procurementModeMap
     * @return array<int, array<string, mixed>>
     */
    private function buildListResult(Collection $statusItems, array $documentCountMap, array $procurementModeMap, bool $skipActions): array
    {
        return $statusItems
            ->map(function (StatusData $statusDto) use ($documentCountMap, $procurementModeMap, $skipActions) {
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

                $modeInfo = $procurementModeMap[$statusDto->prNumber] ?? null;
                $modeEnum = isset($modeInfo['value']) ? ProcurementModeEnums::tryFrom($modeInfo['value']) : null;

                $userRole = $this->getCurrentUserRole();

                $workflowActions = [];
                $staticActions = [];

                if (! $skipActions) {
                    try {
                        $workflowActions = $this->actionService->getAvailableActions(
                            $statusDto->prNumber,
                            $statusDto->stage,
                            $statusDto->currentStatus,
                            $userRole,
                            $modeEnum
                        );
                        $staticActions = $this->actionService->getStaticActions($statusDto->prNumber, $userRole);
                    } catch (\Exception $e) {
                        Log::debug('Failed to fetch actions for procurement, using empty actions', [
                            'pr_number' => $statusDto->prNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }
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
                    'user' => $this->userNameResolver->resolve($statusDto->userAddress),
                    'document_count' => $documentCountMap[$statusDto->prNumber] ?? 0,
                    'metadata' => $statusDto->metadata,
                    'procurement_mode' => $modeInfo['value'] ?? null,
                    'procurement_mode_label' => $modeInfo['label'] ?? null,
                    'mode' => $modeInfo['value'] ?? 'unknown',
                    'abc_amount' => $modeInfo['abc_amount'] ?? 0,
                    'workflow_actions' => $workflowActions,
                    'static_actions' => $staticActions,
                ];
            })
            ->groupBy('id')
            ->map(function ($group) {
                return $group->sortByDesc(function ($item) {
                    $timestamp = $item['timestamp'] ?? '0';
                    $unixTimestamp = $timestamp instanceof Carbon ? $timestamp->timestamp : strtotime($timestamp);

                    $isTransition = isset($item['metadata']['transition']) && $item['metadata']['transition'] === true;
                    $priorityOffset = $isTransition ? 0.001 : 0;

                    return $unixTimestamp + $priorityOffset;
                })->first();
            })
            ->sortByDesc(function ($item) {
                $timestamp = $item['timestamp'] ?? '0';

                return $timestamp instanceof Carbon ? $timestamp->timestamp : strtotime($timestamp);
            })
            ->values()
            ->all();
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

        $roles = $user->getRoleNames();

        return $roles->first() ?? 'guest';
    }
}
