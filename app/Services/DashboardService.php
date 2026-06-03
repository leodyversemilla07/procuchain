<?php

namespace App\Services;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\ProcurementMirrorRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Dashboard\ModeAnalyzer;
use App\Services\Dashboard\StatisticsCalculator;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Service
 *
 * Provides shared dashboard functionality for all role-based dashboards.
 * Handles procurement data retrieval, transformation, and statistics calculation.
 *
 * Merged EventTypeLabelMapper into this service to reduce redundancy.
 *
 * NGPA Compliance:
 * - Supports all 11 NGPA procurement modes (IRR Sections 27-37)
 * - Provides mode-based statistics for Municipality of Gloria (4th Class)
 * - Tracks competitive vs alternative mode distribution
 */
class DashboardService
{
    private array $eventLabelMap = [
        'document_upload' => 'Uploaded Documents',
        'phase_transition' => 'Phase Transition',
        'publication' => 'Published Documents',
        'procurement completed' => 'Completed Procurement',
    ];

    public function __construct(
    private Manager $multichain,
    private ProcurementMirrorRepository $mirrorRepository,
    private EventRepository $eventRepository,
    private DocumentRepository $documentRepository,
    private UserService $userService,
    private ProcurementRepository $procurementRepository,
    private StatisticsCalculator $statisticsCalculator,
    private ModeAnalyzer $modeAnalyzer
    ) {}

    /**
     * Get user name from blockchain address with caching
     *
     * @param  string  $address  Blockchain address
     * @return string User name or 'Unknown'
     */
    public function getUserName(string $address): string
    {
        return $this->userService->getUserNameByAddress($address);
    }

    /**
     * Get procurements grouped by key with latest status
     *
     * @param  array  $allStates  Raw status stream items from blockchain
     * @return Collection Collection of procurements keyed by procurement ID
     */
    public function getProcurementsByKey(array $allStates): Collection
    {
        $startedAt = microtime(true);

        try {
            // First pass: collect all PR numbers
            $prNumbers = collect($allStates)
                ->map(fn ($item) => $item['data']['json']['pr_number'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Build mode map for all PR numbers
            $modeMap = $this->buildProcurementModeMap($prNumbers);

            return collect($allStates)
                ->map(function ($item) use ($modeMap) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['pr_number'], $data['procurement_title'])) {
                        Log::warning('Invalid procurement data structure', ['data' => $data]);

                        return null;
                    }

                    $prNumber = $data['pr_number'];
                    $modeInfo = $modeMap[$prNumber] ?? null;

                    return [
                        'id' => $prNumber,
                        'title' => $data['procurement_title'],
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['current_status'] ?? $data['stage'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => $data['timestamp'] ?? '',
                        'blockchain_time' => $item['time'] ?? 0,
                        'procurement_mode' => $modeInfo['value'] ?? null,
                        'procurement_mode_label' => $modeInfo['label'] ?? null,
                        'is_alternative_mode' => $modeInfo['is_alternative'] ?? null,
                    ];
                })
                ->filter()
                ->groupBy('id')
                ->map(function ($group) {
                    return $group->sortByDesc('blockchain_time')->first();
                });
        } catch (Exception $e) {
            Log::error('Error processing procurement data', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            Log::debug('Dashboard procurement aggregation completed', [
                'state_count' => count($allStates),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }
    }

    /**
     * Get recent procurements for dashboard display
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array of recent procurements
     */
    public function getRecentProcurements(Collection $procurementsByKey): array
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->take(config('dashboard.display_limits.recent_procurements'))
            ->values()
            ->map(function ($item) {
                $stageEnum = StageEnums::tryFrom($item['stage']);
                $statusEnum = StatusEnums::tryFrom($item['status']);

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $stageEnum ? $stageEnum->getDisplayName() : $item['stage'],
                    'status' => $statusEnum ? $statusEnum->getDisplayName() : $item['status'],
                    'procurement_mode' => $item['procurement_mode'] ?? null,
                    'procurement_mode_label' => $item['procurement_mode_label'] ?? null,
                    'is_alternative_mode' => $item['is_alternative_mode'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get procurement distribution data for charts/visualization
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array of procurement distribution data
     */
    public function getProcurementDistributionData(Collection $procurementsByKey): array
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->values()
            ->map(function ($item) {
                $stageEnum = StageEnums::tryFrom($item['stage']);
                $statusEnum = StatusEnums::tryFrom($item['status']);

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $stageEnum ? $stageEnum->getDisplayName() : $item['stage'],
                    'status' => $statusEnum ? $statusEnum->getDisplayName() : $item['status'],
                    'procurement_mode' => $item['procurement_mode'] ?? null,
                    'procurement_mode_label' => $item['procurement_mode_label'] ?? null,
                    'is_alternative_mode' => $item['is_alternative_mode'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activities from blockchain events stream
     * Optimized with pagination and limits
     *
     * @return array Array of recent activities
     */
    public function getRecentActivities(): array
    {
        try {
            $limit = config('dashboard.display_limits.recent_activities_display');
            // Fetch only the required amount plus some buffer for filtering
            $fetchLimit = min($limit * 2, config('dashboard.stream_limits.recent_activities'));
            $eventDtos = $this->mirrorRepository->findRecentEvents($fetchLimit);

            if (empty($eventDtos)) {
                Log::warning('No events found in repository');

                return [];
            }

            return collect($eventDtos)
                ->map(function (EventData $event) {
                    $actionLabel = $this->getEventLabel(
                        $event->eventType,
                        $event->details
                    );

                    return [
                        'id' => $event->prNumber,
                        'title' => $event->procurementTitle,
                        'action' => $actionLabel,
                        'details' => $event->details,
                        'raw_event_type' => $event->eventType,
                        'stage' => $event->stage,
                        'date' => $event->timestamp->toIso8601String(),
                        'user' => $this->getUserName($event->userAddress),
                        'timestamp' => $event->timestamp->timestamp,
                    ];
                })
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values()
                ->toArray();
        } catch (Exception $e) {
            Log::error('Failed to retrieve recent activities', [
                'error' => $e->getMessage(),
                'stack_trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return [];
        }
    }

    /**
     * Get total document count for procurements on dashboard
     * Optimized with limits and better performance
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Total document count
     */
    public function getTotalDocuments(Collection $procurementsByKey): int
    {
        try {
            // Only fetch recent documents to improve performance (from mirror)
            $documentLimit = config('dashboard.stream_limits.document_items', 500);
            $documentDtos = $this->mirrorRepository->getAllDocuments($documentLimit);

            if (empty($documentDtos)) {
                Log::warning('Failed to retrieve document stream items for dashboard stats.');

                return 0;
            }

            // Get unique procurement IDs from dashboard procurements
            $dashboardPrNumbers = $procurementsByKey->keys()->toArray();

            // Count unique documents per procurement, but only for dashboard procurements
            $documentCountMap = collect($documentDtos)
                ->filter(fn (DocumentData $doc) => in_array($doc->prNumber, $dashboardPrNumbers))
                ->groupBy(fn (DocumentData $doc) => $doc->prNumber)
                ->map(function ($docs) {
                    return collect($docs)->pluck('hash')->unique()->count();
                });

            $totalDocuments = $documentCountMap->sum();

            Log::info('Dashboard document count calculated', [
                'total_documents' => $totalDocuments,
                'procurements_counted' => count($dashboardPrNumbers),
                'documents_fetched' => count($documentDtos),
            ]);

            return $totalDocuments;
        } catch (Exception $e) {
            Log::error('Failed to calculate total documents for dashboard', [
                'error' => $e->getMessage(),
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return 0;
        }
    }

    /**
     * Count ongoing projects (not completed)
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Count of ongoing projects
     */
    public function countOngoingProjects(Collection $procurementsByKey): int
    {
        return $this->statisticsCalculator->countOngoingProjects($procurementsByKey);
    }

    /**
     * Count completed biddings (procurements in post-award stages)
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Count of completed biddings
     */
    public function countCompletedBiddings(Collection $procurementsByKey): int
    {
        return $this->statisticsCalculator->countCompletedBiddings($procurementsByKey);
    }

    /**
     * Get empty stats array for error fallback
     *
     * @return array Empty stats structure
     */
    public function getEmptyStats(): array
    {
        return $this->statisticsCalculator->getEmptyStats();
    }

    /**
     * Calculate dashboard statistics
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @param  int  $totalDocuments  Total document count
     * @return array Dashboard statistics
     */
    public function calculateStats(Collection $procurementsByKey, int $totalDocuments): array
    {
        return $this->statisticsCalculator->calculateStats($procurementsByKey, $totalDocuments);
    }

    /**
     * Group procurements by phase
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array grouped by phase
     */
    public function groupProcurementsByPhase(Collection $procurementsByKey): array
    {
        return $this->statisticsCalculator->groupProcurementsByPhase($procurementsByKey);
    }

    /**
     * Get phase statistics
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Phase-based statistics
     */
    public function getPhaseStatistics(Collection $procurementsByKey): array
    {
        return $this->statisticsCalculator->getPhaseStatistics($procurementsByKey);
    }

    /**
     * Get event label from event type
     *
     * Merged from EventTypeLabelMapper
     */
    public function getEventLabel(string $eventType, string $details = ''): string
    {
        $eventType = strtolower($eventType);

        if (isset($this->eventLabelMap[$eventType])) {
            return $this->eventLabelMap[$eventType];
        }

        if ($eventType === 'decision' && str_contains(strtolower($details), 'pre-procurement')) {
            return 'Pre-Procurement Decision';
        } elseif ($eventType === 'decision') {
            return 'Decision Made';
        }

        return ucwords(str_replace('_', ' ', $eventType));
    }

    /**
     * Build a map of procurement modes by PR number
     *
     * @param  array  $prNumbers  Array of PR numbers
     * @return array<string, array{value: string, label: string, is_alternative: bool}>
     */
    private function buildProcurementModeMap(array $prNumbers): array
    {
        if ($prNumbers === []) {
            return [];
        }

        $modeMap = [];

        try {
        $procurements = $this->mirrorRepository->findManyByProcurement($prNumbers);

            foreach ($prNumbers as $prNumber) {
                $procurement = $procurements[$prNumber] ?? null;

                if ($procurement?->procurementMode) {
                    $modeMap[$prNumber] = [
                        'value' => $procurement->procurementMode->value,
                        'label' => $procurement->procurementMode->getDisplayName(),
                        'is_alternative' => $procurement->procurementMode->isAlternativeMode(),
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to batch fetch procurement modes for dashboard', [
                'pr_numbers' => $prNumbers,
                'error' => $e->getMessage(),
            ]);
        }

        return $modeMap;
    }

    /**
     * Get mode distribution statistics
     *
     * NGPA Compliance: Tracks distribution across all 11 procurement modes
     * per IRR Sections 27-37, supporting both competitive and alternative methods.
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Mode distribution data for charts
     */
    public function getModeDistribution(Collection $procurementsByKey): array
    {
        return $this->modeAnalyzer->getModeDistribution($procurementsByKey);
    }

    /**
     * Get mode type statistics (competitive vs alternative)
     *
     * NGPA Compliance:
     * - Competitive modes: Competitive Bidding, Limited Source, etc. (IRR Sections 27-30)
     * - Alternative modes: Direct Contracting, SVP, etc. (IRR Sections 31-37)
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Mode type statistics
     */
    public function getModeTypeStatistics(Collection $procurementsByKey): array
    {
        return $this->modeAnalyzer->getModeTypeStatistics($procurementsByKey);
    }

    /**
     * Group procurements by mode
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Procurements grouped by mode
     */
    public function groupProcurementsByMode(Collection $procurementsByKey): array
    {
        return $this->modeAnalyzer->groupProcurementsByMode($procurementsByKey);
    }

    /**
     * Get comprehensive mode statistics for dashboard
     *
     * Provides all mode-related statistics in one call for dashboard efficiency.
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Comprehensive mode statistics
     */
    public function getModeStatistics(Collection $procurementsByKey): array
    {
        return $this->modeAnalyzer->getModeStatistics($procurementsByKey);
    }
}
