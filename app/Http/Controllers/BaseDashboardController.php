<?php

namespace App\Http\Controllers;

use App\Contracts\CacheStrategyInterface;
use App\Enums\StreamEnums;
use App\Enums\UserRoleEnums;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\Manager;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

abstract class BaseDashboardController extends Controller
{
    public function __construct(
        protected Manager $multichain,
        protected DashboardService $dashboardService,
        protected CacheStrategyInterface $cacheStrategy
    ) {}

    /**
     * Display the dashboard for the role
     */
    public function index(): Response
    {
        try {
            $roleName = $this->getRoleName();
            $roleLabel = $this->getRoleLabel();
            $cacheUserId = $this->getDashboardCacheUserId($roleName);

            Log::info("Fetching {$roleLabel} Dashboard data");

            $procurementsByKey = $this->getCachedProcurements($roleName, $roleLabel);

            if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
                Log::warning("{$roleLabel} ProcurementsByKey is null or empty after cache check.");
                $procurementsByKey = collect();
            }

            // Use database cache for large data to keep Redis usage low
            $recentActivities = $this->cacheStrategy->rememberLarge(
                DashboardCacheKeys::recentActivities($roleName),
                now()->addMinutes(config('dashboard.cache_ttl.activities')),
                fn () => $this->dashboardService->getRecentActivities()
            );

            $stats = $this->cacheStrategy->rememberSmall(
                DashboardCacheKeys::stats($roleName, $cacheUserId),
                now()->addMinutes(config('dashboard.cache_ttl.stats')),
                function () use ($procurementsByKey, $roleLabel) {
                    Log::info("Cache miss: Recalculating {$roleLabel} Dashboard stats");

                    return $this->getDashboardStats($procurementsByKey, 0);
                }
            );

            $procurementDistribution = $this->cacheStrategy->rememberLarge(
                DashboardCacheKeys::procurementDistribution($roleName, $cacheUserId),
                now()->addMinutes(config('dashboard.cache_ttl.distribution')),
                fn () => $this->dashboardService->getProcurementDistributionData($procurementsByKey)
            );

            $dashboardData = array_merge([
                // Immediate data - loads first for fast initial render
                'stats' => $stats,
                // Deferred data - loads after initial render
                'recentProcurements' => Inertia::defer(fn () => $this->dashboardService->getRecentProcurements($procurementsByKey)),
                'procurementDistribution' => Inertia::defer(fn () => $procurementDistribution),
                'recentActivities' => Inertia::defer(fn () => $recentActivities),
                // Phase-based data
                'phaseStatistics' => Inertia::defer(fn () => $this->dashboardService->getPhaseStatistics($procurementsByKey)),
                'procurementsByPhase' => Inertia::defer(fn () => $this->dashboardService->groupProcurementsByPhase($procurementsByKey)),
                // Mode-based statistics (NGPA IRR Sections 27-37)
                'modeStatistics' => Inertia::defer(fn () => $this->dashboardService->getModeStatistics($procurementsByKey)),
            ], $this->getAdditionalDashboardData($procurementsByKey, $roleName));

            Log::info("Successfully retrieved {$roleLabel} Dashboard data");

            return Inertia::render($this->getViewName(), $dashboardData);

        } catch (\Exception $e) {
            Log::error("Failed to retrieve {$this->getRoleLabel()} Dashboard data", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DashboardCacheKeys::clearAll($this->getRoleName());

            return $this->renderErrorResponse($e);
        }
    }

    /**
     * Get cached procurements for the role
     * Uses database cache since this is typically large blockchain data
     *
     * NOTE: Procurement visibility by role:
     * - BAC Secretariat sees only procurements they created or interacted with
     * - Admin, BAC Chairman, and HOPE see all procurements
     */
    protected function getCachedProcurements(string $roleName, string $roleLabel)
    {
        $user = auth()->user();
        $isBacSecretariat = $roleName === UserRoleEnums::BAC_SECRETARIAT->value;
        $cacheKey = DashboardCacheKeys::procurements($roleName, $this->getDashboardCacheUserId($roleName));

        return $this->cacheStrategy->rememberLarge(
            $cacheKey,
            now()->addMinutes(config('dashboard.cache_ttl.procurements')),
            function () use ($isBacSecretariat, $roleLabel, $user) {
                Log::info("Cache miss: Recalculating procurementsByKey for {$roleLabel} Dashboard");
                $states = $this->multichain->liststreamitems(
                    StreamEnums::STATUS->value,
                    true,
                    config('dashboard.stream_limits.status_items'),
                    0,
                    false
                );

                if ($states === null) {
                    throw new Exception("Failed to retrieve status stream items for {$roleLabel} procurementsByKey cache");
                }

                $procurementsByKey = $this->dashboardService->getProcurementsByKey($states);

                if ($isBacSecretariat && $user !== null) {
                    $procurementsByKey = $this->filterProcurementsByUser(
                        $procurementsByKey,
                        (string) $user->id,
                        $user->blockchain_address
                    );
                }

                return $procurementsByKey;
            }
        );
    }

    /**
     * Filter procurements collection by user ID and/or blockchain address
     * Used for BAC Secretariat isolation
     */
    protected function filterProcurementsByUser($procurementsByKey, ?string $filterByUserId, ?string $filterByUserAddress)
    {
        if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
            return $procurementsByKey;
        }

        // Get procurement IDs from the collection
        $prNumbers = $procurementsByKey->keys()->all();

        // Fetch procurement metadata to check ownership
        $procurements = app(\App\Repositories\ProcurementRepository::class)->findManyByProcurement($prNumbers);

        // Filter to only include procurements owned by the user
        $allowedPrNumbers = [];
        foreach ($procurements as $prNumber => $procurement) {
            if ($procurement) {
                // Check userId if filter is provided
                $userIdMatch = $filterByUserId === null || $procurement->userId === $filterByUserId;

                if ($userIdMatch) {
                    $allowedPrNumbers[] = $prNumber;
                }
            }
        }

        // Filter the collection to only allowed procurement numbers
        $filtered = $procurementsByKey->filter(function ($items, $prNumber) use ($allowedPrNumbers, $filterByUserAddress) {
            // Check if procurement is in allowed list (user created it)
            $prNumberAllowed = in_array($prNumber, $allowedPrNumbers, true);

            // Check if user has interacted with this procurement via blockchain
            $addressAllowed = $filterByUserAddress === null || collect($items)->contains(function ($item) use ($filterByUserAddress) {
                return isset($item['user_address']) && $item['user_address'] === $filterByUserAddress;
            });

            // Use OR logic: Show if user created it OR interacted with it
            // This allows BAC Secretariat to see procurements they're working on
            return $prNumberAllowed || $addressAllowed;
        });

        Log::info('Filtered dashboard procurements by userId and/or blockchain address', [
            'filter_user_id' => $filterByUserId,
            'filter_user_address' => $filterByUserAddress ? substr($filterByUserAddress, 0, 10).'...' : null,
            'total_procurements' => count($prNumbers),
            'filtered_count' => $filtered->count(),
        ]);

        return $filtered;
    }

    /**
     * Calculate dashboard statistics
     */
    protected function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        try {
            $roleName = $this->getRoleName();
            $roleLabel = $this->getRoleLabel();
            $cacheUserId = $this->getDashboardCacheUserId($roleName);

            $totalDocuments = $this->cacheStrategy->rememberSmall(
                DashboardCacheKeys::totalDocuments($roleName, $cacheUserId),
                now()->addMinutes(config('dashboard.cache_ttl.stats')),
                function () use ($procurementsByKey, $roleLabel) {
                    Log::info("Cache miss: Recalculating total documents for {$roleLabel} Dashboard stats");

                    return $this->dashboardService->getTotalDocuments($procurementsByKey);
                }
            );

            return $this->dashboardService->calculateStats($procurementsByKey, $totalDocuments);
        } catch (\Exception $e) {
            Log::error("Failed to calculate {$this->getRoleLabel()} Dashboard stats", ['error' => $e->getMessage()]);
            Cache::forget(DashboardCacheKeys::totalDocuments($this->getRoleName(), $this->getDashboardCacheUserId($this->getRoleName())));

            return $this->dashboardService->getEmptyStats();
        }
    }

    protected function getDashboardCacheUserId(string $roleName): ?string
    {
        if ($roleName !== UserRoleEnums::BAC_SECRETARIAT->value) {
            return null;
        }

        $userId = auth()->id();

        return $userId === null ? null : (string) $userId;
    }

    /**
     * Render error response with empty dashboard data
     */
    protected function renderErrorResponse(\Exception $e): Response
    {
        return Inertia::render($this->getViewName(), array_merge([
            'recentProcurements' => [],
            'procurementDistribution' => [],
            'recentActivities' => [],
            'stats' => $this->dashboardService->getEmptyStats(),
            'modeStatistics' => [
                'distribution' => [],
                'type_breakdown' => [
                    'competitive' => ['count' => 0, 'percentage' => 0],
                    'alternative' => ['count' => 0, 'percentage' => 0],
                    'unknown' => ['count' => 0, 'percentage' => 0],
                    'total' => 0,
                ],
                'by_mode' => [],
            ],
            'error' => 'Failed to retrieve dashboard data. Please try again later.',
        ], $this->getEmptyAdditionalData()));
    }

    /**
     * Get the role name for middleware and cache keys (e.g., 'hope', 'admin', 'bac_chairman')
     */
    abstract protected function getRoleName(): string;

    /**
     * Get the human-readable role label for logging (e.g., 'Head of Procuring Entity', 'Admin')
     */
    abstract protected function getRoleLabel(): string;

    /**
     * Get the Inertia view name (e.g., 'hope/dashboard', 'admin/dashboard')
     */
    abstract protected function getViewName(): string;

    /**
     * Get additional dashboard data specific to the role (e.g., analytics for admin, priority actions for secretariat)
     */
    protected function getAdditionalDashboardData($procurementsByKey, string $roleName): array
    {
        return [];
    }

    /**
     * Get empty additional data for error responses
     */
    protected function getEmptyAdditionalData(): array
    {
        return [];
    }
}
