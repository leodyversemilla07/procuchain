import { useState, useEffect, useCallback, useRef } from 'react';
import { toast } from 'sonner';
import type {
    AnalyticsApiResponse,
    ProcurementAnalytics,
    DocumentAnalytics,
    UserActivityAnalytics,
    BlockchainAnalytics,
    ComprehensiveAnalytics,
    RealtimeData,
    AnalyticsFilters,
    TimeRangeKey,
    UseAnalyticsOptions,
    UseAnalyticsReturn,
    AnalyticsExportOptions,
    AnalyticsExportResult,
} from '@/types/analytics';

/**
 * Custom hook for managing analytics data
 */
export function useAnalytics<T = unknown>(
    type: 'procurement' | 'document' | 'user-activity' | 'blockchain' | 'realtime',
    options: UseAnalyticsOptions = {}
): UseAnalyticsReturn<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

    const {
        autoRefresh = false,
        refreshInterval = 30000, // 30 seconds default
        filters = {},
        enabled = true,
    } = options;

    const intervalRef = useRef<NodeJS.Timeout | null>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    const fetchData = useCallback(async () => {
        if (!enabled) return;

        // Cancel any existing request
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        abortControllerRef.current = new AbortController();

        try {
            setLoading(true);
            setError(null);

            const queryParams = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    queryParams.append(key, String(value));
                }
            });

            const endpointMap: Record<typeof type, string> = {
                procurement: 'procurement-data',
                document: 'documents-data',
                'user-activity': 'user-activity-data',
                blockchain: 'blockchain-data',
                realtime: 'realtime-data',
            };
            const endpoint = `/analytics/${endpointMap[type]}?${queryParams.toString()}`;

            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: abortControllerRef.current.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result: AnalyticsApiResponse<T> = await response.json();

            if (result.success && result.data) {
                setData(result.data);
                setLastUpdated(new Date());
            } else {
                throw new Error(result.error || 'Failed to fetch analytics data');
            }
        } catch (err: unknown) {
            if (err instanceof Error && err.name !== 'AbortError') {
                const errorMessage = err.message || 'An error occurred while fetching analytics data';
                setError(errorMessage);
                toast.error('Analytics Error', {
                    description: errorMessage,
                });
            }
        } finally {
            setLoading(false);
        }
    }, [type, filters, enabled]);

    const refetch = useCallback(async () => {
        await fetchData();
    }, [fetchData]);

    // Initial fetch
    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Auto-refresh setup
    useEffect(() => {
        if (autoRefresh && enabled && refreshInterval > 0) {
            intervalRef.current = setInterval(fetchData, refreshInterval);

            return () => {
                if (intervalRef.current) {
                    clearInterval(intervalRef.current);
                }
            };
        }
    }, [autoRefresh, enabled, refreshInterval, fetchData]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
            }
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, []);

    return {
        data,
        loading,
        error,
        refetch,
        lastUpdated,
    };
}

/**
 * Hook for procurement analytics
 */
export function useProcurementAnalytics(
    filters: Partial<AnalyticsFilters> = {},
    options: UseAnalyticsOptions = {}
) {
    return useAnalytics<ProcurementAnalytics>('procurement', {
        ...options,
        filters: { time_range: '30_days', ...filters },
    });
}

/**
 * Hook for document analytics
 */
export function useDocumentAnalytics(
    filters: Partial<AnalyticsFilters> = {},
    options: UseAnalyticsOptions = {}
) {
    return useAnalytics<DocumentAnalytics>('document', {
        ...options,
        filters: { time_range: '30_days', ...filters },
    });
}

/**
 * Hook for user activity analytics
 */
export function useUserActivityAnalytics(
    filters: Partial<AnalyticsFilters> = {},
    options: UseAnalyticsOptions = {}
) {
    return useAnalytics<UserActivityAnalytics>('user-activity', {
        ...options,
        filters: { time_range: '30_days', ...filters },
    });
}

/**
 * Hook for blockchain analytics
 */
export function useBlockchainAnalytics(
    filters: Partial<AnalyticsFilters> = {},
    options: UseAnalyticsOptions = {}
) {
    return useAnalytics<BlockchainAnalytics>('blockchain', {
        ...options,
        filters: { time_range: '30_days', ...filters },
    });
}

/**
 * Hook for real-time analytics data
 */
export function useRealtimeAnalytics(
    options: UseAnalyticsOptions = {}
) {
    return useAnalytics<RealtimeData>('realtime', {
        autoRefresh: true,
        refreshInterval: 15000, // 15 seconds for real-time
        ...options,
    });
}

/**
 * Hook for comprehensive analytics report
 */
export function useComprehensiveAnalytics(
    filters: Partial<AnalyticsFilters> = {},
    options: UseAnalyticsOptions = {}
) {
    const [data, setData] = useState<ComprehensiveAnalytics | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

    const fetchReport = useCallback(async () => {
        if (options.enabled === false) return;

        try {
            setLoading(true);
            setError(null);

            const queryParams = new URLSearchParams();
            const fullFilters = { time_range: '30_days' as TimeRangeKey, ...filters };
            Object.entries(fullFilters).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    queryParams.append(key, String(value));
                }
            });

            const response = await fetch(`/analytics/report?${queryParams.toString()}`);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result: AnalyticsApiResponse<ComprehensiveAnalytics> = await response.json();

            if (result.success && result.data) {
                setData(result.data);
                setLastUpdated(new Date());
            } else {
                throw new Error(result.error || 'Failed to fetch comprehensive analytics');
            }
        } catch (err: unknown) {
            const errorMessage = err instanceof Error ? err.message : 'An error occurred while fetching analytics report';
            setError(errorMessage);
            toast.error('Analytics Report Error', {
                description: errorMessage,
            });
        } finally {
            setLoading(false);
        }
    }, [filters, options.enabled]);

    const refetch = useCallback(async () => {
        await fetchReport();
    }, [fetchReport]);

    useEffect(() => {
        if (options.enabled !== false) {
            fetchReport();
        }
    }, [fetchReport, options.enabled]);

    return {
        data,
        loading,
        error,
        refetch,
        lastUpdated,
    };
}

/**
 * Hook for exporting analytics data
 */
export function useAnalyticsExport() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const exportData = useCallback(async (options: AnalyticsExportOptions): Promise<AnalyticsExportResult> => {
        try {
            setLoading(true);
            setError(null);

            const response = await fetch('/analytics/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(options),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result: AnalyticsExportResult = await response.json();

            if (result.success) {
                toast.success('Export Generated', {
                    description: `Your ${options.type} analytics export is ready for download.`,
                });
            } else {
                throw new Error(result.error || 'Failed to export analytics data');
            }

            return result;
        } catch (err: unknown) {
            const errorMessage = err instanceof Error ? err.message : 'An error occurred while exporting analytics data';
            setError(errorMessage);
            toast.error('Export Error', {
                description: errorMessage,
            });
            return {
                success: false,
                error: errorMessage,
            };
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        exportData,
        loading,
        error,
    };
}

/**
 * Hook for managing analytics filters with URL state
 */
export function useAnalyticsFilters(initialFilters: Partial<AnalyticsFilters> = {}) {
    const [filters, setFilters] = useState<AnalyticsFilters>(() => {
        // Try to get filters from URL params
        const urlParams = new URLSearchParams(window.location.search);
        const urlFilters: Partial<AnalyticsFilters> = {};

        const timeRange = urlParams.get('time_range') as TimeRangeKey;
        if (timeRange) {
            urlFilters.time_range = timeRange;
        }

        if (urlParams.get('procurement_id')) {
            urlFilters.procurement_id = urlParams.get('procurement_id')!;
        }
        if (urlParams.get('stage')) {
            urlFilters.stage = urlParams.get('stage')!;
        }
        if (urlParams.get('status')) {
            urlFilters.status = urlParams.get('status')!;
        }

        return {
            time_range: '30_days',
            ...initialFilters,
            ...urlFilters
        };
    });

    const updateFilters = useCallback((newFilters: Partial<AnalyticsFilters>) => {
        setFilters(prev => {
            const updated = { ...prev, ...newFilters };

            // Update URL params
            const urlParams = new URLSearchParams();
            Object.entries(updated).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    urlParams.set(key, String(value));
                }
            });

            // Update URL without triggering navigation
            const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
            window.history.replaceState({}, '', newUrl);

            return updated;
        });
    }, []);

    const resetFilters = useCallback(() => {
        const reset = { time_range: '30_days' as TimeRangeKey };
        setFilters(reset);

        // Clear URL params
        window.history.replaceState({}, '', window.location.pathname);
    }, []);

    return {
        filters,
        updateFilters,
        resetFilters,
        setTimeRange: (timeRange: TimeRangeKey) => updateFilters({ time_range: timeRange }),
        setProcurementId: (procurementId: string | undefined) => updateFilters({ procurement_id: procurementId }),
        setStage: (stage: string | undefined) => updateFilters({ stage }),
        setStatus: (status: string | undefined) => updateFilters({ status }),
    };
}

/**
 * Hook for tracking analytics page views
 */
export function useAnalyticsTracking() {
    const trackPageView = useCallback((page: string, filters?: AnalyticsFilters) => {
        // Track analytics page views for usage analytics
        try {
            fetch('/analytics/track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    page,
                    filters,
                    timestamp: new Date().toISOString(),
                }),
            });
        } catch (error) {
            // Silently fail tracking
            console.warn('Failed to track analytics page view:', error);
        }
    }, []);

    return { trackPageView };
}
