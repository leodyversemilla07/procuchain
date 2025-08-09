<?php

namespace App\Services;

use App\Models\User;
use App\Models\DocumentView;
use App\Models\UserLoginLog;
use App\Enums\StreamEnums;
use App\Enums\StageEnums;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class AnalyticsService
{
    protected $multichainService;

    public function __construct(MultichainService $multichainService)
    {
        $this->multichainService = $multichainService;
    }

    /**
     * Get comprehensive procurement analytics
     */
    public function getProcurementAnalytics(array $options = []): array
    {
        $cacheKey = 'procurement_analytics_' . md5(serialize($options));
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($options) {
            try {
                $timeRange = $options['time_range'] ?? '30_days';
                $procurementId = $options['procurement_id'] ?? null;

                return [
                    'overview' => $this->getProcurementOverview($timeRange, $procurementId),
                    'stage_analytics' => $this->getStageAnalytics($timeRange, $procurementId),
                    'performance_metrics' => $this->getPerformanceMetrics($timeRange, $procurementId),
                    'timeline_analytics' => $this->getTimelineAnalytics($timeRange, $procurementId),
                    'generated_at' => now()->toISOString(),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate procurement analytics', [
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);
                return $this->getEmptyAnalytics();
            }
        });
    }

    /**
     * Get document analytics
     */
    public function getDocumentAnalytics(array $options = []): array
    {
        $cacheKey = 'document_analytics_' . md5(serialize($options));
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($options) {
            try {
                $timeRange = $options['time_range'] ?? '30_days';
                $procurementId = $options['procurement_id'] ?? null;

                return [
                    'view_statistics' => $this->getDocumentViewStatistics($timeRange, $procurementId),
                    'access_patterns' => $this->getDocumentAccessPatterns($timeRange, $procurementId),
                    'popular_documents' => $this->getPopularDocuments($timeRange, $procurementId),
                    'user_engagement' => $this->getDocumentEngagement($timeRange, $procurementId),
                    'generated_at' => now()->toISOString(),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate document analytics', [
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);
                return $this->getEmptyDocumentAnalytics();
            }
        });
    }

    /**
     * Get user activity analytics
     */
    public function getUserActivityAnalytics(array $options = []): array
    {
        $cacheKey = 'user_activity_analytics_' . md5(serialize($options));
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($options) {
            try {
                $timeRange = $options['time_range'] ?? '30_days';
                $userId = $options['user_id'] ?? null;

                return [
                    'login_patterns' => $this->getLoginPatterns($timeRange, $userId),
                    'role_activity' => $this->getRoleActivityBreakdown($timeRange),
                    'session_analytics' => $this->getSessionAnalytics($timeRange, $userId),
                    'security_metrics' => $this->getSecurityMetrics($timeRange),
                    'generated_at' => now()->toISOString(),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate user activity analytics', [
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);
                return $this->getEmptyUserAnalytics();
            }
        });
    }

    /**
     * Get blockchain analytics
     */
    public function getBlockchainAnalytics(array $options = []): array
    {
        $cacheKey = 'blockchain_analytics_' . md5(serialize($options));
        
        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($options) {
            try {
                $timeRange = $options['time_range'] ?? '30_days';

                return [
                    'transaction_volume' => $this->getBlockchainTransactionVolume($timeRange),
                    'integrity_metrics' => $this->getBlockchainIntegrityMetrics($timeRange),
                    'stream_analytics' => $this->getStreamAnalytics($timeRange),
                    'verification_statistics' => $this->getVerificationStatistics($timeRange),
                    'generated_at' => now()->toISOString(),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate blockchain analytics', [
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);
                return $this->getEmptyBlockchainAnalytics();
            }
        });
    }

    /**
     * Get procurement overview metrics
     */
    private function getProcurementOverview(string $timeRange, ?string $procurementId): array
    {
        $statusItems = $this->multichainService->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            10000, // Increased limit to ensure we get all data
            0,
            false
        );

        if (!$statusItems) {
            Log::warning('No status items found in blockchain');
            return $this->getEmptyProcurementOverview();
        }

        Log::info('Raw status items retrieved', [
            'count' => count($statusItems),
            'sample_first_item' => isset($statusItems[0]) ? [
                'procurement_id' => $statusItems[0]['data']['json']['procurement_id'] ?? 'missing',
                'stage' => $statusItems[0]['data']['json']['stage'] ?? 'missing',
                'timestamp' => $statusItems[0]['data']['json']['timestamp'] ?? 'missing'
            ] : null,
            'sample_last_item' => isset($statusItems[count($statusItems)-1]) ? [
                'procurement_id' => $statusItems[count($statusItems)-1]['data']['json']['procurement_id'] ?? 'missing',
                'stage' => $statusItems[count($statusItems)-1]['data']['json']['stage'] ?? 'missing',
                'timestamp' => $statusItems[count($statusItems)-1]['data']['json']['timestamp'] ?? 'missing'
            ] : null,
        ]);

        $procurements = collect($statusItems)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];
                return isset($data['procurement_id']) && 
                       ($procurementId === null || $data['procurement_id'] === $procurementId);
            })
            ->groupBy(function ($item) {
                return $item['data']['json']['procurement_id'];
            })
            ->map(function ($group) {
                // Sort by timestamp like ViewProcurementsController does, not blocktime
                return $group->sortByDesc(function ($item) {
                    return strtotime($item['data']['json']['timestamp'] ?? '0');
                })->first();
            });

        Log::info('Analytics data processing', [
            'raw_status_items' => count($statusItems),
            'filtered_status_items' => collect($statusItems)->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];
                return isset($data['procurement_id']) && 
                       ($procurementId === null || $data['procurement_id'] === $procurementId);
            })->count(),
            'grouped_procurements' => $procurements->count(),
            'procurement_ids' => $procurements->keys()->toArray(),
            'sample_procurement_stages' => $procurements->take(5)->map(function($procurement, $id) {
                // $procurement is now a single item (latest status), not a collection
                return [
                    'id' => $id,
                    'stage' => $procurement['data']['json']['stage'] ?? 'unknown',
                    'timestamp' => $procurement['data']['json']['timestamp'] ?? 'null',
                ];
            })->toArray(),
        ]);

        $totalProcurements = $procurements->count();
        $stageDistribution = $this->calculateStageDistribution($procurements);
        $statusDistribution = $this->calculateStatusDistribution($procurements);
        $averageProcessingTime = $this->calculateAverageProcessingTime($procurements);

        return [
            'total_procurements' => $totalProcurements,
            'active_procurements' => $this->countActiveProcurements($procurements),
            'completed_procurements' => $this->countCompletedProcurements($procurements),
            'stage_distribution' => $stageDistribution,
            'status_distribution' => $statusDistribution,
            'average_processing_time_days' => $averageProcessingTime,
            'completion_rate' => $totalProcurements > 0 ? 
                round($this->countCompletedProcurements($procurements) / $totalProcurements * 100, 2) : 0,
            'total_value_change' => 0, // Add this field that was referenced in frontend
            // Add debugging info to understand what user is seeing
            'debug_info' => [
                'unique_procurement_count' => $totalProcurements,
                'total_status_records' => count($statusItems),
                'explanation' => 'Stage distribution shows current stage of each unique procurement',
            ],
        ];
    }

    /**
     * Get stage analytics with performance metrics
     */
    private function getStageAnalytics(string $timeRange, ?string $procurementId): array
    {
        $eventItems = $this->multichainService->listStreamItems(
            StreamEnums::EVENTS->value,
            true,
            10000,
            0,
            false
        );

        if (!$eventItems) {
            return [];
        }

        $filteredEvents = collect($eventItems)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];
                return isset($data['procurement_id']) && 
                       ($procurementId === null || $data['procurement_id'] === $procurementId);
            });

        return [
            'stage_transitions' => $this->analyzeStageTransitions($filteredEvents),
            'stage_duration' => $this->calculateStageDurations($filteredEvents),
            'bottlenecks' => $this->identifyProcessBottlenecks($filteredEvents),
            'efficiency_scores' => $this->calculateStageEfficiency($filteredEvents),
        ];
    }

    /**
     * Get document view statistics
     */
    private function getDocumentViewStatistics(string $timeRange, ?string $procurementId): array
    {
        try {
            // Since this is a blockchain-based system, get document data from blockchain
            $documentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                5000,
                0,
                false
            );

            if (!$documentItems) {
                return $this->getEmptyDocumentViewStatistics();
            }

            $dateConstraint = $this->getDateConstraint($timeRange);
            
            // Filter documents by time range and procurement
            $filteredDocuments = collect($documentItems)->filter(function ($item) use ($dateConstraint, $procurementId) {
                $data = $item['data']['json'] ?? [];
                $blocktime = $item['blocktime'] ?? 0;
                $itemDate = Carbon::createFromTimestamp($blocktime);

                // Filter by time range
                if ($dateConstraint && $itemDate->lt($dateConstraint)) {
                    return false;
                }

                // Filter by procurement if specified
                if ($procurementId && ($data['procurement_id'] ?? null) !== $procurementId) {
                    return false;
                }

                return true;
            });

            // Calculate basic statistics
            $totalViews = $filteredDocuments->count();
            
            // Get unique uploaders/viewers (users who uploaded documents)
            $uniqueViewers = $filteredDocuments->map(function ($item) {
                return $item['data']['json']['uploaded_by'] ?? 'unknown';
            })->unique()->count();

            // Calculate views by document type
            $viewsByDocumentType = $filteredDocuments->groupBy(function ($item) {
                return $item['data']['json']['document_type'] ?? 'Unknown';
            })->map(function ($group) {
                return $group->count();
            })->toArray();

            // Calculate views by stage
            $viewsByStage = $filteredDocuments->groupBy(function ($item) {
                return $item['data']['json']['stage'] ?? 'Unknown';
            })->map(function ($group) {
                return $group->count();
            })->toArray();

            // Calculate engagement rate (documents per unique user)
            $engagementRate = $uniqueViewers > 0 ? round($totalViews / $uniqueViewers, 2) : 0;

            Log::info('Document analytics calculated', [
                'total_documents' => $totalViews,
                'unique_uploaders' => $uniqueViewers,
                'engagement_rate' => $engagementRate,
                'document_types' => array_keys($viewsByDocumentType),
            ]);

            return [
                'total_views' => $totalViews,
                'unique_viewers' => $uniqueViewers,
                'average_view_duration_seconds' => 0, // Not available in blockchain data
                'views_by_stage' => $viewsByStage,
                'views_by_document_type' => $viewsByDocumentType,
                'engagement_rate' => $engagementRate,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get document view statistics', [
                'error' => $e->getMessage(),
                'time_range' => $timeRange,
                'procurement_id' => $procurementId,
            ]);
            return $this->getEmptyDocumentViewStatistics();
        }
    }

    /**
     * Get login patterns analytics
     */
    private function getLoginPatterns(string $timeRange, ?int $userId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        $query = UserLoginLog::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->when($userId, function ($q) use ($userId) {
                return $q->where('user_id', $userId);
            });

        $totalLogins = $query->count();
        $successfulLogins = $query->where('successful', true)->count();
        $failedLogins = $totalLogins - $successfulLogins;
        
        $peakHours = $query->selectRaw('HOUR(login_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'count' => $item->count,
                    'formatted_hour' => sprintf('%02d:00 - %02d:59', $item->hour, $item->hour),
                ];
            });

        $dailyLogins = $query->selectRaw('DATE(login_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->count];
            });

        return [
            'total_logins' => $totalLogins,
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'success_rate' => $totalLogins > 0 ? round($successfulLogins / $totalLogins * 100, 2) : 0,
            'peak_hours' => $peakHours->toArray(),
            'daily_login_trend' => $dailyLogins->toArray(),
        ];
    }

    /**
     * Get blockchain transaction volume
     */
    private function getBlockchainTransactionVolume(string $timeRange): array
    {
        try {
            $streams = [
                StreamEnums::DOCUMENTS->value,
                StreamEnums::STATUS->value,
                StreamEnums::EVENTS->value,
            ];

            $volumeData = [];
            $totalTransactions = 0;

            foreach ($streams as $stream) {
                $items = $this->multichainService->listStreamItems($stream, true, 5000, 0, false);
                
                if ($items) {
                    $count = count($items);
                    $volumeData[$stream] = $count;
                    $totalTransactions += $count;
                }
            }

            return [
                'total_transactions' => $totalTransactions,
                'transactions_by_stream' => $volumeData,
                'average_daily_transactions' => $this->calculateAverageDailyTransactions($timeRange, $totalTransactions),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get blockchain transaction volume', ['error' => $e->getMessage()]);
            return [
                'total_transactions' => 0,
                'transactions_by_stream' => [],
                'average_daily_transactions' => 0,
            ];
        }
    }

    /**
     * Helper methods
     */
    private function getDateConstraint(string $timeRange): ?Carbon
    {
        return match ($timeRange) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            '1_year' => now()->subYear(),
            default => now()->subDays(30),
        };
    }

    private function calculateStageDistribution(Collection $procurements): array
    {
        // Add debugging to understand the data structure
        Log::info('calculateStageDistribution debug', [
            'total_procurement_groups' => $procurements->count(),
            'first_procurement_sample' => $procurements->first(),
        ]);

        $stageDistribution = $procurements->map(function ($procurement) {
            // $procurement is now a single item (latest status), not a collection
            if (!$procurement || !isset($procurement['data']['json']['stage'])) {
                return 'No Data';
            }
            
            $stageKey = $procurement['data']['json']['stage'] ?? 'Unknown';
            
            // Convert enum value to display name for better readability
            $displayName = $this->getStageDisplayName($stageKey);   
            
            Log::debug('Procurement stage mapping', [
                'procurement_id' => $procurement['data']['json']['procurement_id'] ?? 'unknown',
                'stage_key' => $stageKey,
                'display_name' => $displayName,
                'timestamp' => $procurement['data']['json']['timestamp'] ?? 'null',
            ]);
            
            return $displayName;
        })->countBy()->toArray();

        Log::info('Final stage distribution', [
            'distribution' => $stageDistribution,
            'total_procurements' => $procurements->count()
        ]);
        
        return $stageDistribution;
    }

    private function calculateStatusDistribution(Collection $procurements): array
    {
        return $procurements->map(function ($procurement) {
            // $procurement is now a single item, not a collection
            $status = $procurement['data']['json']['current_status'] ?? 'Unknown';
            
            // Map enum value to display name
            if ($status !== 'Unknown') {
                try {
                    $statusEnum = \App\Enums\StatusEnums::from($status);
                    return $statusEnum->getDisplayName();
                } catch (\ValueError $e) {
                    return $status; // Return original if enum not found
                }
            }
            
            return $status;
        })->countBy()->toArray();
    }

    private function countActiveProcurements(Collection $procurements): int
    {
        return $procurements->filter(function ($procurement) {
            // $procurement is now a single item, not a collection
            $stage = $procurement['data']['json']['stage'] ?? '';
            return !in_array($stage, ['completed', 'cancelled']);
        })->count();
    }

    private function countCompletedProcurements(Collection $procurements): int
    {
        return $procurements->filter(function ($procurement) {
            // $procurement is now a single item, not a collection
            $stage = $procurement['data']['json']['stage'] ?? '';
            return $stage === 'completed';
        })->count();
    }

    private function calculateAverageProcessingTime(Collection $procurements): float
    {
        // Since we now only have the latest status for each procurement,
        // we cannot calculate accurate processing time without full history.
        // For now, return a reasonable default or calculate based on current stage
        
        $processingTimes = $procurements->map(function ($procurement) {
            // $procurement is now a single item (latest status), not a collection
            if (!$procurement || !isset($procurement['data']['json'])) {
                return null;
            }
            
            $data = $procurement['data']['json'];
            $stage = $data['stage'] ?? 'unknown';
            
            // Estimate processing time based on current stage
            // These are rough estimates in days for different stages
            $stageEstimates = [
                'procurement_initiation' => 5,
                'pre_procurement_conference' => 10,
                'bidding_documents' => 15,
                'pre_bid_conference' => 20,
                'supplemental_bid_bulletin' => 25,
                'bid_opening' => 30,
                'bid_evaluation' => 40,
                'post_qualification' => 50,
                'bac_resolution' => 60,
                'notice_of_award' => 70,
                'performance_bond_contract_and_po' => 80,
                'notice_to_proceed' => 90,
                'monitoring' => 120,
                'completion' => 150,
                'completed' => 180,
            ];
            
            return $stageEstimates[$stage] ?? 30; // Default 30 days if stage not found
        })->filter(function ($time) {
            return $time !== null;
        });

        $averageTime = $processingTimes->count() > 0 ? $processingTimes->avg() : 0;
        
        Log::info('Processing time calculation (estimated)', [
            'procurement_count' => $procurements->count(),
            'valid_processing_times' => $processingTimes->count(),
            'average_processing_time' => $averageTime,
            'processing_times_sample' => $processingTimes->take(5)->toArray(),
            'note' => 'Using stage-based estimates since we only have latest status'
        ]);

        return round($averageTime, 2);
    }

    private function analyzeStageTransitions(Collection $events): array
    {
        $transitions = $events->filter(function ($event) {
            return ($event['data']['json']['event_type'] ?? '') === 'stage_transition';
        });

        return $transitions->groupBy(function ($event) {
            $data = $event['data']['json'];
            return ($data['stage_identifier'] ?? 'Unknown') . ' → ' . ($data['next_stage'] ?? 'Unknown');
        })->map(function ($group) {
            return $group->count();
        })->toArray();
    }

    private function calculateStageDurations(Collection $events): array
    {
        $stageDurations = [];
        $procurementStages = [];

        // Group events by procurement_id and sort by timestamp
        $eventsByProcurement = $events->groupBy(function ($event) {
            return $event['data']['json']['procurement_id'] ?? 'unknown';
        });

        foreach ($eventsByProcurement as $procurementId => $procurementEvents) {
            $sortedEvents = $procurementEvents->sortBy(function ($event) {
                return Carbon::parse($event['data']['json']['timestamp'] ?? now());
            });

            $currentStage = null;
            $stageStartTime = null;

            foreach ($sortedEvents as $event) {
                $eventData = $event['data']['json'];
                $eventType = $eventData['event_type'] ?? '';
                
                if ($eventType === 'stage_transition') {
                    $newStage = $eventData['next_stage'] ?? null;
                    $eventTime = Carbon::parse($eventData['timestamp'] ?? now());

                    // If we have a previous stage, calculate its duration
                    if ($currentStage && $stageStartTime) {
                        $duration = $stageStartTime->diffInHours($eventTime);
                        
                        if (!isset($stageDurations[$currentStage])) {
                            $stageDurations[$currentStage] = [];
                        }
                        $stageDurations[$currentStage][] = $duration;
                    }

                    $currentStage = $newStage;
                    $stageStartTime = $eventTime;
                }
            }
        }

        // Calculate averages for each stage
        $averageDurations = [];
        foreach ($stageDurations as $stage => $durations) {
            if (!empty($durations)) {
                $averageDurations[$stage] = [
                    'average_hours' => round(array_sum($durations) / count($durations), 2),
                    'min_hours' => min($durations),
                    'max_hours' => max($durations),
                    'count' => count($durations),
                ];
            }
        }

        return $averageDurations;
    }

    private function identifyProcessBottlenecks(Collection $events): array
    {
        $stageDurations = $this->calculateStageDurations($events);
        $bottlenecks = [];

        if (empty($stageDurations)) {
            return $bottlenecks;
        }

        // Calculate overall average duration
        $allDurations = [];
        foreach ($stageDurations as $stage => $data) {
            $allDurations[] = $data['average_hours'];
        }
        
        $overallAverage = array_sum($allDurations) / count($allDurations);
        $threshold = $overallAverage * 1.5; // Stages taking 50% longer than average

        foreach ($stageDurations as $stage => $data) {
            if ($data['average_hours'] > $threshold) {
                $bottlenecks[] = [
                    'stage' => $stage,
                    'average_duration_hours' => $data['average_hours'],
                    'delay_factor' => round($data['average_hours'] / $overallAverage, 2),
                    'severity' => $this->calculateBottleneckSeverity($data['average_hours'], $overallAverage),
                    'affected_procurements' => $data['count'],
                ];
            }
        }

        // Sort by severity (highest delay factor first)
        usort($bottlenecks, function ($a, $b) {
            return $b['delay_factor'] <=> $a['delay_factor'];
        });

        return $bottlenecks;
    }

    private function calculateBottleneckSeverity(float $stageDuration, float $averageDuration): string
    {
        $ratio = $stageDuration / $averageDuration;
        
        if ($ratio >= 3.0) return 'Critical';
        if ($ratio >= 2.0) return 'High';
        if ($ratio >= 1.5) return 'Medium';
        return 'Low';
    }

    private function calculateStageEfficiency(Collection $events): array
    {
        $stageDurations = $this->calculateStageDurations($events);
        $efficiencyScores = [];

        if (empty($stageDurations)) {
            return $efficiencyScores;
        }

        // Define expected/ideal durations for each stage (in hours)
        $idealDurations = [
            'Procurement Initiation' => 24, // 1 day
            'Pre-procurement Conference' => 48, // 2 days
            'Bidding Documents Preparation' => 72, // 3 days
            'Pre-bid Conference' => 8, // 8 hours
            'Bid Opening' => 4, // 4 hours
            'Bid Evaluation' => 120, // 5 days
            'Post-qualification' => 48, // 2 days
            'NOA' => 24, // 1 day
            'Performance Bond/Contract/PO' => 72, // 3 days
            'Completion' => 12, // 12 hours
        ];

        foreach ($stageDurations as $stage => $data) {
            $idealDuration = $idealDurations[$stage] ?? $data['average_hours'];
            $actualDuration = $data['average_hours'];
            
            // Calculate efficiency score (100% = ideal, less than 100% = taking longer than ideal)
            $efficiencyScore = $idealDuration > 0 ? min(100, ($idealDuration / $actualDuration) * 100) : 0;
            
            $efficiencyScores[$stage] = [
                'efficiency_score' => round($efficiencyScore, 1),
                'ideal_duration_hours' => $idealDuration,
                'actual_duration_hours' => $actualDuration,
                'performance_rating' => $this->getPerformanceRating($efficiencyScore),
                'improvement_potential_hours' => max(0, $actualDuration - $idealDuration),
            ];
        }

        return $efficiencyScores;
    }

    private function getPerformanceRating(float $efficiencyScore): string
    {
        if ($efficiencyScore >= 90) return 'Excellent';
        if ($efficiencyScore >= 75) return 'Good';
        if ($efficiencyScore >= 60) return 'Average';
        if ($efficiencyScore >= 40) return 'Below Average';
        return 'Poor';
    }

    private function getDocumentAccessPatterns(string $timeRange, ?string $procurementId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        $query = DocumentView::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('viewed_at', '>=', $dateConstraint);
            })
            ->when($procurementId, function ($q) use ($procurementId) {
                return $q->where('procurement_id', $procurementId);
            });

        // Access patterns by hour of day
        $hourlyPattern = $query->selectRaw('HOUR(viewed_at) as hour, COUNT(*) as views')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->hour => $item->views];
            })->toArray();

        // Access patterns by day of week
        $weeklyPattern = $query->selectRaw('DAYOFWEEK(viewed_at) as day_of_week, COUNT(*) as views')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get()
            ->map(function ($item) {
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return [
                    'day' => $dayNames[$item->day_of_week - 1] ?? 'Unknown',
                    'views' => $item->views,
                ];
            })->toArray();

        // Most active users
        $activeUsers = $query->select('user_id', User::raw('COUNT(*) as view_count'))
            ->join('users', 'document_views.user_id', '=', 'users.id')
            ->groupBy('user_id', 'users.name', 'users.role')
            ->orderBy('view_count', 'desc')
            ->take(10)
            ->get(['users.name', 'users.role', 'view_count'])
            ->toArray();

        // Document type preferences
        $typePreferences = $query->selectRaw('document_type, COUNT(*) as views')
            ->groupBy('document_type')
            ->orderBy('views', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->document_type => $item->views];
            })->toArray();

        return [
            'hourly_access_pattern' => $hourlyPattern,
            'weekly_access_pattern' => $weeklyPattern,
            'most_active_users' => $activeUsers,
            'document_type_preferences' => $typePreferences,
            'peak_access_hour' => !empty($hourlyPattern) ? array_keys($hourlyPattern, max($hourlyPattern))[0] : null,
            'peak_access_day' => !empty($weeklyPattern) && !empty(array_column($weeklyPattern, 'views')) ? $weeklyPattern[array_search(max(array_column($weeklyPattern, 'views')), array_column($weeklyPattern, 'views'))]['day'] : null,
        ];
    }

    private function getPopularDocuments(string $timeRange, ?string $procurementId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        return DocumentView::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('viewed_at', '>=', $dateConstraint);
            })
            ->when($procurementId, function ($q) use ($procurementId) {
                return $q->where('procurement_id', $procurementId);
            })
            ->selectRaw('file_key, document_type, procurement_title, COUNT(*) as view_count')
            ->groupBy(['file_key', 'document_type', 'procurement_title'])
            ->orderBy('view_count', 'desc')
            ->take(10)
            ->get()
            ->toArray();
    }

    private function getDocumentEngagement(string $timeRange, ?string $procurementId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        $avgDuration = DocumentView::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('viewed_at', '>=', $dateConstraint);
            })
            ->when($procurementId, function ($q) use ($procurementId) {
                return $q->where('procurement_id', $procurementId);
            })
            ->whereNotNull('view_duration')
            ->avg('view_duration');

        return [
            'average_engagement_time' => round($avgDuration ?? 0, 2),
            'high_engagement_threshold' => round(($avgDuration ?? 0) * 1.5, 2),
        ];
    }

    private function getRoleActivityBreakdown(string $timeRange): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        return UserLoginLog::query()
            ->join('users', 'user_login_logs.user_id', '=', 'users.id')
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->selectRaw('users.role, COUNT(*) as login_count')
            ->groupBy('users.role')
            ->orderBy('login_count', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->role => $item->login_count];
            })
            ->toArray();
    }

    private function getSessionAnalytics(string $timeRange, ?int $userId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        $query = UserLoginLog::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->when($userId, function ($q) use ($userId) {
                return $q->where('user_id', $userId);
            })
            ->where('successful', true);

        // Calculate session durations (assuming logout_at field exists, or estimate from next login)
        $sessions = $query->orderBy('user_id')->orderBy('login_at')->get();
        
        $sessionDurations = [];
        $userSessions = $sessions->groupBy('user_id');
        
        foreach ($userSessions as $userId => $userLogins) {
            $sortedLogins = $userLogins->sortBy('login_at');
            
            for ($i = 0; $i < $sortedLogins->count() - 1; $i++) {
                $currentLogin = $sortedLogins->values()[$i];
                $nextLogin = $sortedLogins->values()[$i + 1];
                
                $loginTime = Carbon::parse($currentLogin->login_at);
                $nextLoginTime = Carbon::parse($nextLogin->login_at);
                
                // Estimate session duration (max 8 hours to avoid overnight sessions)
                $duration = min($loginTime->diffInMinutes($nextLoginTime), 480);
                $sessionDurations[] = $duration;
            }
        }

        $averageSessionDuration = !empty($sessionDurations) ? array_sum($sessionDurations) / count($sessionDurations) : 0;
        
        // Session frequency analysis
        $dailySessions = $query->selectRaw('DATE(login_at) as date, COUNT(DISTINCT user_id) as unique_users, COUNT(*) as total_sessions')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'unique_users' => $item->unique_users,
                    'total_sessions' => $item->total_sessions,
                    'sessions_per_user' => $item->unique_users > 0 ? round($item->total_sessions / $item->unique_users, 2) : 0,
                ];
            })->toArray();

        // User engagement levels
        $userEngagement = $query->selectRaw('user_id, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                $level = 'Low';
                if ($item->session_count >= 20) $level = 'High';
                elseif ($item->session_count >= 10) $level = 'Medium';
                
                return [
                    'user_id' => $item->user_id,
                    'session_count' => $item->session_count,
                    'engagement_level' => $level,
                ];
            });

        $engagementDistribution = $userEngagement->groupBy('engagement_level')
            ->map(function ($group) {
                return count($group);
            })->toArray();

        return [
            'average_session_duration_minutes' => round($averageSessionDuration, 2),
            'total_sessions' => $sessions->count(),
            'unique_active_users' => $sessions->unique('user_id')->count(),
            'daily_session_trends' => $dailySessions,
            'user_engagement_distribution' => $engagementDistribution,
            'sessions_per_user_average' => $sessions->count() > 0 ? round($sessions->count() / $sessions->unique('user_id')->count(), 2) : 0,
        ];
    }

    private function getSecurityMetrics(string $timeRange): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);
        
        $totalAttempts = UserLoginLog::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->count();

        $failedAttempts = UserLoginLog::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->where('successful', false)
            ->count();

        $suspiciousIPs = UserLoginLog::query()
            ->when($dateConstraint, function ($q) use ($dateConstraint) {
                return $q->where('login_at', '>=', $dateConstraint);
            })
            ->where('successful', false)
            ->selectRaw('ip_address, COUNT(*) as failed_count')
            ->groupBy('ip_address')
            ->having('failed_count', '>=', 5)
            ->count();

        return [
            'security_score' => $totalAttempts > 0 ? round((1 - $failedAttempts / $totalAttempts) * 100, 2) : 100,
            'failed_login_rate' => $totalAttempts > 0 ? round($failedAttempts / $totalAttempts * 100, 2) : 0,
            'suspicious_ip_count' => $suspiciousIPs,
        ];
    }

    private function getBlockchainIntegrityMetrics(string $timeRange): array
    {
        // Implementation for blockchain integrity verification
        return [
            'integrity_score' => 100, // Placeholder
            'verified_documents' => 0,
            'hash_mismatches' => 0,
        ];
    }

    private function getStreamAnalytics(string $timeRange): array
    {
        try {
            $streams = [
                StreamEnums::DOCUMENTS->value => 'Documents',
                StreamEnums::STATUS->value => 'Status Updates',
                StreamEnums::EVENTS->value => 'Events',
            ];

            $streamAnalytics = [];
            $totalItems = 0;

            foreach ($streams as $streamKey => $streamName) {
                $items = $this->multichainService->listStreamItems($streamKey, true, 10000, 0, false);
                
                if ($items) {
                    $itemCount = count($items);
                    $totalItems += $itemCount;

                    // Analyze item creation patterns
                    $recentItems = collect($items)->filter(function ($item) use ($timeRange) {
                        $blocktime = $item['blocktime'] ?? 0;
                        $itemDate = Carbon::createFromTimestamp($blocktime);
                        $dateConstraint = $this->getDateConstraint($timeRange);
                        
                        return !$dateConstraint || $itemDate->gte($dateConstraint);
                    });

                    // Daily activity pattern
                    $dailyActivity = $recentItems->groupBy(function ($item) {
                        $blocktime = $item['blocktime'] ?? 0;
                        return Carbon::createFromTimestamp($blocktime)->format('Y-m-d');
                    })->map(function ($dayItems) {
                        return $dayItems->count();
                    })->toArray();

                    // Size analysis
                    $sizes = $recentItems->map(function ($item) {
                        return strlen(json_encode($item['data'] ?? []));
                    });

                    $streamAnalytics[$streamName] = [
                        'total_items' => $itemCount,
                        'recent_items' => $recentItems->count(),
                        'daily_activity' => $dailyActivity,
                        'average_item_size_bytes' => $sizes->isNotEmpty() ? round($sizes->avg(), 2) : 0,
                        'total_size_kb' => $sizes->isNotEmpty() ? round($sizes->sum() / 1024, 2) : 0,
                        'items_per_day_average' => $this->calculateAverageItemsPerDay($dailyActivity, $timeRange),
                    ];
                }
            }

            // Calculate distribution percentages
            foreach ($streamAnalytics as $streamName => &$data) {
                $data['percentage_of_total'] = $totalItems > 0 ? round(($data['total_items'] / $totalItems) * 100, 2) : 0;
            }

            return [
                'stream_breakdown' => $streamAnalytics,
                'total_blockchain_items' => $totalItems,
                'most_active_stream' => $this->getMostActiveStream($streamAnalytics),
                'blockchain_growth_rate' => $this->calculateBlockchainGrowthRate($streamAnalytics, $timeRange),
            ];
        } catch (Exception $e) {
            Log::error('Failed to generate stream analytics', ['error' => $e->getMessage()]);
            return [
                'stream_breakdown' => [],
                'total_blockchain_items' => 0,
                'most_active_stream' => null,
                'blockchain_growth_rate' => 0,
            ];
        }
    }

    private function calculateAverageItemsPerDay(array $dailyActivity, string $timeRange): float
    {
        if (empty($dailyActivity)) return 0;

        $days = match ($timeRange) {
            '7_days' => 7,
            '30_days' => 30,
            '90_days' => 90,
            '1_year' => 365,
            default => 30,
        };

        return round(array_sum($dailyActivity) / max(1, min($days, count($dailyActivity))), 2);
    }

    private function getMostActiveStream(array $streamAnalytics): ?string
    {
        if (empty($streamAnalytics)) return null;

        $maxItems = 0;
        $mostActive = null;

        foreach ($streamAnalytics as $streamName => $data) {
            if ($data['recent_items'] > $maxItems) {
                $maxItems = $data['recent_items'];
                $mostActive = $streamName;
            }
        }

        return $mostActive;
    }

    private function calculateBlockchainGrowthRate(array $streamAnalytics, string $timeRange): float
    {
        $totalRecentItems = 0;
        $totalAllItems = 0;

        foreach ($streamAnalytics as $data) {
            $totalRecentItems += $data['recent_items'];
            $totalAllItems += $data['total_items'];
        }

        if ($totalAllItems === 0) return 0;

        $days = match ($timeRange) {
            '7_days' => 7,
            '30_days' => 30,
            '90_days' => 90,
            '1_year' => 365,
            default => 30,
        };

        // Calculate items per day for recent period
        $recentRate = $totalRecentItems / $days;
        
        // Estimate historical rate (total - recent) / estimated historical days
        $historicalItems = $totalAllItems - $totalRecentItems;
        $estimatedHistoricalDays = 365; // Assume 1 year of historical data
        $historicalRate = $historicalItems / $estimatedHistoricalDays;

        // Calculate growth rate percentage
        return $historicalRate > 0 ? round((($recentRate - $historicalRate) / $historicalRate) * 100, 2) : 0;
    }

    private function getVerificationStatistics(string $timeRange): array
    {
        try {
            // Get document items from blockchain
            $documentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                5000,
                0,
                false
            );

            if (!$documentItems) {
                return [
                    'total_documents' => 0,
                    'verified_documents' => 0,
                    'verification_rate' => 0,
                    'integrity_checks' => [],
                    'hash_verification_stats' => [],
                ];
            }

            $dateConstraint = $this->getDateConstraint($timeRange);
            $verificationStats = [
                'total_documents' => 0,
                'verified_documents' => 0,
                'hash_matches' => 0,
                'hash_mismatches' => 0,
                'pending_verification' => 0,
                'verification_by_stage' => [],
                'verification_by_type' => [],
            ];

            foreach ($documentItems as $item) {
                $data = $item['data']['json'] ?? [];
                $blocktime = $item['blocktime'] ?? 0;
                $itemDate = Carbon::createFromTimestamp($blocktime);

                // Filter by time range if specified
                if ($dateConstraint && $itemDate->lt($dateConstraint)) {
                    continue;
                }

                $verificationStats['total_documents']++;

                // Check verification status
                $isVerified = $data['verified'] ?? false;
                $hashStatus = $data['hash_verification_status'] ?? 'pending';
                $stage = $data['stage'] ?? 'Unknown';
                $documentType = $data['document_type'] ?? 'Unknown';

                if ($isVerified) {
                    $verificationStats['verified_documents']++;
                }

                // Hash verification statistics
                if ($hashStatus === 'verified') {
                    $verificationStats['hash_matches']++;
                } elseif ($hashStatus === 'failed') {
                    $verificationStats['hash_mismatches']++;
                } else {
                    $verificationStats['pending_verification']++;
                }

                // Verification by stage
                if (!isset($verificationStats['verification_by_stage'][$stage])) {
                    $verificationStats['verification_by_stage'][$stage] = [
                        'total' => 0,
                        'verified' => 0,
                    ];
                }
                $verificationStats['verification_by_stage'][$stage]['total']++;
                if ($isVerified) {
                    $verificationStats['verification_by_stage'][$stage]['verified']++;
                }

                // Verification by document type
                if (!isset($verificationStats['verification_by_type'][$documentType])) {
                    $verificationStats['verification_by_type'][$documentType] = [
                        'total' => 0,
                        'verified' => 0,
                    ];
                }
                $verificationStats['verification_by_type'][$documentType]['total']++;
                if ($isVerified) {
                    $verificationStats['verification_by_type'][$documentType]['verified']++;
                }
            }

            // Calculate verification rates
            $verificationRate = $verificationStats['total_documents'] > 0 
                ? round(($verificationStats['verified_documents'] / $verificationStats['total_documents']) * 100, 2) 
                : 0;

            $hashVerificationRate = ($verificationStats['hash_matches'] + $verificationStats['hash_mismatches']) > 0
                ? round(($verificationStats['hash_matches'] / ($verificationStats['hash_matches'] + $verificationStats['hash_mismatches'])) * 100, 2)
                : 0;

            // Calculate verification rates by stage and type
            foreach ($verificationStats['verification_by_stage'] as $stage => &$stageData) {
                $stageData['verification_rate'] = $stageData['total'] > 0 
                    ? round(($stageData['verified'] / $stageData['total']) * 100, 2) 
                    : 0;
            }

            foreach ($verificationStats['verification_by_type'] as $type => &$typeData) {
                $typeData['verification_rate'] = $typeData['total'] > 0 
                    ? round(($typeData['verified'] / $typeData['total']) * 100, 2) 
                    : 0;
            }

            return [
                'total_documents' => $verificationStats['total_documents'],
                'verified_documents' => $verificationStats['verified_documents'],
                'verification_rate' => $verificationRate,
                'integrity_checks' => [
                    'hash_matches' => $verificationStats['hash_matches'],
                    'hash_mismatches' => $verificationStats['hash_mismatches'],
                    'pending_verification' => $verificationStats['pending_verification'],
                    'hash_verification_rate' => $hashVerificationRate,
                ],
                'verification_by_stage' => $verificationStats['verification_by_stage'],
                'verification_by_type' => $verificationStats['verification_by_type'],
                'security_score' => $this->calculateSecurityScore($verificationStats),
            ];
        } catch (Exception $e) {
            Log::error('Failed to generate verification statistics', ['error' => $e->getMessage()]);
            return [
                'total_documents' => 0,
                'verified_documents' => 0,
                'verification_rate' => 0,
                'integrity_checks' => [],
                'verification_by_stage' => [],
                'verification_by_type' => [],
                'security_score' => 0,
            ];
        }
    }

    private function calculateSecurityScore(array $verificationStats): float
    {
        $totalDocuments = $verificationStats['total_documents'];
        
        if ($totalDocuments === 0) return 100;

        $verifiedWeight = 0.6;
        $hashWeight = 0.4;

        $verificationScore = ($verificationStats['verified_documents'] / $totalDocuments) * 100;
        
        $totalHashChecks = $verificationStats['hash_matches'] + $verificationStats['hash_mismatches'];
        $hashScore = $totalHashChecks > 0 
            ? ($verificationStats['hash_matches'] / $totalHashChecks) * 100 
            : 100;

        return round(($verificationScore * $verifiedWeight) + ($hashScore * $hashWeight), 2);
    }

    private function calculateAverageDailyTransactions(string $timeRange, int $totalTransactions): float
    {
        $days = match ($timeRange) {
            '7_days' => 7,
            '30_days' => 30,
            '90_days' => 90,
            '1_year' => 365,
            default => 30,
        };

        return $days > 0 ? round($totalTransactions / $days, 2) : 0;
    }

    /**
     * Empty analytics fallbacks
     */
    private function getEmptyAnalytics(): array
    {
        return [
            'overview' => $this->getEmptyProcurementOverview(),
            'stage_analytics' => [],
            'performance_metrics' => [],
            'timeline_analytics' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    private function getEmptyProcurementOverview(): array
    {
        return [
            'total_procurements' => 0,
            'active_procurements' => 0,
            'completed_procurements' => 0,
            'stage_distribution' => [],
            'status_distribution' => [],
            'average_processing_time_days' => 0,
            'completion_rate' => 0,
        ];
    }

    private function getEmptyDocumentAnalytics(): array
    {
        return [
            'view_statistics' => $this->getEmptyDocumentViewStatistics(),
            'access_patterns' => [],
            'popular_documents' => [],
            'user_engagement' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    private function getEmptyDocumentViewStatistics(): array
    {
        return [
            'total_views' => 0,
            'unique_viewers' => 0,
            'average_view_duration_seconds' => 0,
            'views_by_stage' => [],
            'views_by_document_type' => [],
            'engagement_rate' => 0,
        ];
    }

    private function getEmptyUserAnalytics(): array
    {
        return [
            'login_patterns' => [],
            'role_activity' => [],
            'session_analytics' => [],
            'security_metrics' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    private function getEmptyBlockchainAnalytics(): array
    {
        return [
            'transaction_volume' => [],
            'integrity_metrics' => [],
            'stream_analytics' => [],
            'verification_statistics' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Performance metrics calculation
     */
    private function getPerformanceMetrics(string $timeRange, ?string $procurementId): array
    {
        try {
            // Get procurement overview for baseline metrics
            $overview = $this->getProcurementOverview($timeRange, $procurementId);
            
            // Get stage analytics for performance calculation
            $stageAnalytics = $this->getStageAnalytics($timeRange, $procurementId);
            
            // Calculate average cycle time (total processing time)
            $averageCycleTime = $overview['average_processing_time_days'] ?? 0;
            
            // Calculate efficiency rating based on completion rate and cycle time
            $completionRate = $overview['completion_rate'] ?? 0;
            $targetCycleTime = 30; // 30 days target
            $cycleTimeEfficiency = $averageCycleTime > 0 ? min(100, ($targetCycleTime / $averageCycleTime) * 100) : 100;
            $efficiencyRating = ($completionRate * 0.6) + ($cycleTimeEfficiency * 0.4);
            
            // Estimate cost per procurement (placeholder calculation)
            $baseCostPerProcurement = 50000; // Base cost in PHP
            $inefficiencyMultiplier = max(1, $averageCycleTime / $targetCycleTime);
            $costPerProcurement = $baseCostPerProcurement * $inefficiencyMultiplier;
            
            // Calculate time savings compared to manual process
            $manualProcessDays = 60; // Estimated manual process time
            $digitalProcessDays = $averageCycleTime;
            $timeSavingsPercentage = $manualProcessDays > 0 
                ? (($manualProcessDays - $digitalProcessDays) / $manualProcessDays) * 100 
                : 0;
            
            // Calculate process improvement metrics
            $bottlenecks = $stageAnalytics['bottlenecks'] ?? [];
            $processHealthScore = $this->calculateProcessHealthScore($overview, $stageAnalytics);
            
            // Resource utilization estimates
            $resourceUtilization = $this->calculateResourceUtilization($overview);
            
            return [
                'average_cycle_time' => round($averageCycleTime, 2),
                'efficiency_rating' => round($efficiencyRating, 2),
                'cost_per_procurement' => round($costPerProcurement, 2),
                'time_savings' => round($timeSavingsPercentage, 2),
                'process_health_score' => round($processHealthScore, 2),
                'bottleneck_count' => count($bottlenecks),
                'resource_utilization' => $resourceUtilization,
                'performance_trends' => $this->calculatePerformanceTrends($timeRange),
                'improvement_opportunities' => $this->identifyImprovementOpportunities($stageAnalytics),
            ];
        } catch (Exception $e) {
            Log::error('Failed to calculate performance metrics', ['error' => $e->getMessage()]);
            return [
                'average_cycle_time' => 0,
                'efficiency_rating' => 0,
                'cost_per_procurement' => 0,
                'time_savings' => 0,
                'process_health_score' => 0,
                'bottleneck_count' => 0,
                'resource_utilization' => [],
                'performance_trends' => [],
                'improvement_opportunities' => [],
            ];
        }
    }

    private function calculateProcessHealthScore(array $overview, array $stageAnalytics): float
    {
        $completionRate = $overview['completion_rate'] ?? 0;
        $bottleneckCount = count($stageAnalytics['bottlenecks'] ?? []);
        $activeRatio = $overview['total_procurements'] > 0 
            ? ($overview['active_procurements'] / $overview['total_procurements']) * 100 
            : 0;
        
        // Health score based on completion rate (40%), bottlenecks (30%), active ratio (30%)
        $completionScore = $completionRate;
        $bottleneckScore = max(0, 100 - ($bottleneckCount * 20)); // Deduct 20 points per bottleneck
        $balanceScore = min(100, 100 - abs($activeRatio - 70)); // Optimal active ratio around 70%
        
        return ($completionScore * 0.4) + ($bottleneckScore * 0.3) + ($balanceScore * 0.3);
    }

    private function calculateResourceUtilization(array $overview): array
    {
        $totalProcurements = $overview['total_procurements'] ?? 0;
        $activeProcurements = $overview['active_procurements'] ?? 0;
        
        return [
            'current_load' => $activeProcurements,
            'capacity_utilization' => $totalProcurements > 0 ? round(($activeProcurements / $totalProcurements) * 100, 2) : 0,
            'estimated_capacity' => max($totalProcurements, 100), // Assume minimum capacity of 100
            'availability' => max(0, 100 - (($activeProcurements / max($totalProcurements, 100)) * 100)),
        ];
    }

    private function calculatePerformanceTrends(string $timeRange): array
    {
        // Simplified trend calculation - in a real implementation, 
        // this would compare with previous periods
        return [
            'cycle_time_trend' => 'stable', // Could be 'improving', 'declining', 'stable'
            'completion_rate_trend' => 'improving',
            'efficiency_trend' => 'stable',
            'trend_confidence' => 75, // Percentage confidence in trend analysis
        ];
    }

    private function identifyImprovementOpportunities(array $stageAnalytics): array
    {
        $opportunities = [];
        $bottlenecks = $stageAnalytics['bottlenecks'] ?? [];
        
        foreach ($bottlenecks as $bottleneck) {
            $impact = $bottleneck['severity'] === 'Critical' ? 'High' : 
                     ($bottleneck['severity'] === 'High' ? 'Medium' : 'Low');
            
            $opportunities[] = [
                'area' => $bottleneck['stage'],
                'type' => 'Process Optimization',
                'impact' => $impact,
                'description' => "Optimize {$bottleneck['stage']} stage to reduce {$bottleneck['average_duration_hours']} hour average duration",
                'estimated_savings_hours' => round($bottleneck['average_duration_hours'] * 0.3, 2), // 30% improvement potential
            ];
        }
        
        return $opportunities;
    }

    /**
     * Timeline analytics for trend visualization
     */
    private function getTimelineAnalytics(string $timeRange, ?string $procurementId): array
    {
        try {
            // Get events for timeline analysis
            $eventItems = $this->multichainService->listStreamItems(
                StreamEnums::EVENTS->value,
                true,
                10000,
                0,
                false
            );

            if (!$eventItems) {
                return $this->getEmptyTimelineAnalytics();
            }

            $dateConstraint = $this->getDateConstraint($timeRange);
            
            $filteredEvents = collect($eventItems)
                ->filter(function ($item) use ($procurementId, $dateConstraint) {
                    $data = $item['data']['json'] ?? [];
                    $blocktime = $item['blocktime'] ?? 0;
                    $eventDate = Carbon::createFromTimestamp($blocktime);
                    
                    $matchesProcurement = $procurementId === null || 
                                        ($data['procurement_id'] ?? null) === $procurementId;
                    $matchesTimeRange = !$dateConstraint || $eventDate->gte($dateConstraint);
                    
                    return $matchesProcurement && $matchesTimeRange;
                });

            // Daily activity analysis
            $dailyActivity = $filteredEvents->groupBy(function ($event) {
                $blocktime = $event['blocktime'] ?? 0;
                return Carbon::createFromTimestamp($blocktime)->format('Y-m-d');
            })->map(function ($dayEvents) {
                return [
                    'total_events' => $dayEvents->count(),
                    'stage_transitions' => $dayEvents->where('data.json.event_type', 'stage_transition')->count(),
                    'document_uploads' => $dayEvents->where('data.json.event_type', 'document_upload')->count(),
                    'status_updates' => $dayEvents->where('data.json.event_type', 'status_update')->count(),
                ];
            })->sortKeys()->toArray();

            // Weekly trends analysis
            $weeklyTrends = $filteredEvents->groupBy(function ($event) {
                $blocktime = $event['blocktime'] ?? 0;
                return Carbon::createFromTimestamp($blocktime)->format('Y-W');
            })->map(function ($weekEvents) {
                $procurements = $weekEvents->unique('data.json.procurement_id');
                $avgEventsPerProcurement = $procurements->count() > 0 
                    ? $weekEvents->count() / $procurements->count() 
                    : 0;
                
                return [
                    'total_events' => $weekEvents->count(),
                    'active_procurements' => $procurements->count(),
                    'events_per_procurement' => round($avgEventsPerProcurement, 2),
                    'activity_intensity' => $this->calculateActivityIntensity($weekEvents),
                ];
            })->sortKeys()->toArray();

            // Monthly patterns analysis
            $monthlyPatterns = $filteredEvents->groupBy(function ($event) {
                $blocktime = $event['blocktime'] ?? 0;
                return Carbon::createFromTimestamp($blocktime)->format('Y-m');
            })->map(function ($monthEvents) {
                $stageDistribution = $monthEvents->groupBy('data.json.event_type')->map->count();
                $peakDay = $monthEvents->groupBy(function ($event) {
                    $blocktime = $event['blocktime'] ?? 0;
                    return Carbon::createFromTimestamp($blocktime)->format('d');
                })->map->count()->sortDesc()->keys()->first();
                
                return [
                    'total_events' => $monthEvents->count(),
                    'event_type_distribution' => $stageDistribution->toArray(),
                    'peak_activity_day' => $peakDay,
                    'average_daily_events' => round($monthEvents->count() / 30, 2),
                ];
            })->sortKeys()->toArray();

            // Seasonal analysis (quarters)
            $seasonalAnalysis = $filteredEvents->groupBy(function ($event) {
                $blocktime = $event['blocktime'] ?? 0;
                $month = Carbon::createFromTimestamp($blocktime)->month;
                
                if ($month <= 3) return 'Q1';
                if ($month <= 6) return 'Q2';
                if ($month <= 9) return 'Q3';
                return 'Q4';
            })->map(function ($quarterEvents, $quarter) {
                $months = $quarterEvents->groupBy(function ($event) {
                    $blocktime = $event['blocktime'] ?? 0;
                    return Carbon::createFromTimestamp($blocktime)->format('Y-m');
                })->count();
                
                return [
                    'quarter' => $quarter,
                    'total_events' => $quarterEvents->count(),
                    'active_months' => $months,
                    'average_monthly_events' => $months > 0 ? round($quarterEvents->count() / $months, 2) : 0,
                    'quarter_intensity' => $this->calculateSeasonalIntensity($quarterEvents),
                ];
            })->values()->toArray();

            // Activity patterns by hour and day of week
            $activityPatterns = $this->analyzeActivityPatterns($filteredEvents);

            return [
                'daily_activity' => $dailyActivity,
                'weekly_trends' => $weeklyTrends,
                'monthly_patterns' => $monthlyPatterns,
                'seasonal_analysis' => $seasonalAnalysis,
                'activity_patterns' => $activityPatterns,
                'timeline_summary' => $this->generateTimelineSummary($filteredEvents),
            ];
        } catch (Exception $e) {
            Log::error('Failed to generate timeline analytics', ['error' => $e->getMessage()]);
            return $this->getEmptyTimelineAnalytics();
        }
    }

    private function calculateActivityIntensity(Collection $events): string
    {
        $eventCount = $events->count();
        
        if ($eventCount >= 50) return 'Very High';
        if ($eventCount >= 30) return 'High';
        if ($eventCount >= 15) return 'Medium';
        if ($eventCount >= 5) return 'Low';
        return 'Very Low';
    }

    private function calculateSeasonalIntensity(Collection $events): string
    {
        $eventCount = $events->count();
        
        if ($eventCount >= 200) return 'Peak Season';
        if ($eventCount >= 100) return 'High Season';
        if ($eventCount >= 50) return 'Normal Season';
        return 'Low Season';
    }

    private function analyzeActivityPatterns(Collection $events): array
    {
        // Activity by hour of day
        $hourlyPattern = $events->groupBy(function ($event) {
            $blocktime = $event['blocktime'] ?? 0;
            return Carbon::createFromTimestamp($blocktime)->hour;
        })->map->count()->sortKeys()->toArray();

        // Activity by day of week
        $weeklyPattern = $events->groupBy(function ($event) {
            $blocktime = $event['blocktime'] ?? 0;
            return Carbon::createFromTimestamp($blocktime)->dayOfWeek;
        })->map(function ($dayEvents, $dayOfWeek) {
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            return [
                'day' => $dayNames[$dayOfWeek] ?? 'Unknown',
                'event_count' => $dayEvents->count(),
                'is_weekend' => in_array($dayOfWeek, [0, 6]),
            ];
        })->values()->toArray();

        return [
            'hourly_distribution' => $hourlyPattern,
            'weekly_distribution' => $weeklyPattern,
            'peak_hour' => !empty($hourlyPattern) ? array_keys($hourlyPattern, max($hourlyPattern))[0] : null,
            'most_active_day' => collect($weeklyPattern)->sortByDesc('event_count')->first()['day'] ?? null,
        ];
    }

    private function generateTimelineSummary(Collection $events): array
    {
        $totalEvents = $events->count();
        $timeSpan = $this->calculateTimeSpan($events);
        $eventTypes = $events->groupBy('data.json.event_type')->map->count()->sortDesc();
        
        return [
            'total_events' => $totalEvents,
            'time_span_days' => $timeSpan,
            'events_per_day' => $timeSpan > 0 ? round($totalEvents / $timeSpan, 2) : 0,
            'most_common_event_type' => $eventTypes->keys()->first(),
            'event_type_diversity' => $eventTypes->count(),
            'activity_consistency' => $this->calculateActivityConsistency($events),
        ];
    }

    private function calculateTimeSpan(Collection $events): float
    {
        if ($events->isEmpty()) return 0;
        
        $timestamps = $events->map(function ($event) {
            return Carbon::createFromTimestamp($event['blocktime'] ?? 0);
        })->sort();
        
        $earliest = $timestamps->first();
        $latest = $timestamps->last();
        
        return $earliest->diffInDays($latest) + 1; // +1 to include both start and end days
    }

    private function calculateActivityConsistency(Collection $events): string
    {
        if ($events->isEmpty()) return 'No Data';
        
        $dailyActivityVariance = $events->groupBy(function ($event) {
            $blocktime = $event['blocktime'] ?? 0;
            return Carbon::createFromTimestamp($blocktime)->format('Y-m-d');
        })->map->count();
        
        if ($dailyActivityVariance->isEmpty()) return 'No Data';
        
        $mean = $dailyActivityVariance->avg();
        $variance = $dailyActivityVariance->map(function ($count) use ($mean) {
            return pow($count - $mean, 2);
        })->avg();
        
        $coefficientOfVariation = $mean > 0 ? sqrt($variance) / $mean : 0;
        
        if ($coefficientOfVariation <= 0.3) return 'Very Consistent';
        if ($coefficientOfVariation <= 0.6) return 'Consistent';
        if ($coefficientOfVariation <= 1.0) return 'Moderate';
        return 'Inconsistent';
    }

    private function getEmptyTimelineAnalytics(): array
    {
        return [
            'daily_activity' => [],
            'weekly_trends' => [],
            'monthly_patterns' => [],
            'seasonal_analysis' => [],
            'activity_patterns' => [
                'hourly_distribution' => [],
                'weekly_distribution' => [],
                'peak_hour' => null,
                'most_active_day' => null,
            ],
            'timeline_summary' => [
                'total_events' => 0,
                'time_span_days' => 0,
                'events_per_day' => 0,
                'most_common_event_type' => null,
                'event_type_diversity' => 0,
                'activity_consistency' => 'No Data',
            ],
        ];
    }

    /**
     * Convert stage enum value to display name
     */
    private function getStageDisplayName(string $stageKey): string
    {
        try {
            $stageEnum = StageEnums::from($stageKey);
            return $stageEnum->getDisplayName();
        } catch (\ValueError $e) {
            // If the stage key doesn't match any enum value, return a formatted version
            return ucwords(str_replace('_', ' ', $stageKey));
        }
    }
}
