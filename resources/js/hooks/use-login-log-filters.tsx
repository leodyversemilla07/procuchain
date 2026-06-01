import loginLogs from '@/routes/admin/login-logs';
import { type SharedData } from '@/types';
import { router, usePage, usePoll } from '@inertiajs/react';
import { format } from 'date-fns';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { type DateRange } from 'react-day-picker';
import { toast } from 'sonner';

export interface LoginLog {
    id: number;
    user_id?: number;
    user?: {
        id: number;
        name: string;
        email: string;
        primary_role: string;
        two_factor_enabled?: boolean;
        two_factor_confirmed_at?: string;
    };
    ip_address: string;
    user_agent?: string;
    device_type?: string;
    browser?: string;
    platform?: string;
    location?: string;
    successful: boolean;
    login_at: string;
    logout_at?: string;
}

export interface LoginStatistics {
    total_logins: number;
    successful_logins: number;
    failed_logins: number;
    unique_users: number;
    today_logins: number;
    this_week_logins: number;
    this_month_logins: number;
}

export type CombinedLog = LoginLog & { category: 'recent' | 'suspicious' };

interface UseLoginLogFiltersOptions {
    recentLogins: LoginLog[];
    suspiciousActivities?: LoginLog[];
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

export function useLoginLogFilters({ recentLogins, suspiciousActivities, flash }: UseLoginLogFiltersOptions) {
    const page = usePage<SharedData>();
    const auth = (page.props as any).auth;

    // Search and filter states
    const [searchTerm, setSearchTerm] = useState('');
    const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
    const [selectedRole, setSelectedRole] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [selectedDeviceType, setSelectedDeviceType] = useState<string>('all');
    const [selectedBrowser, setSelectedBrowser] = useState<string>('all');
    const [dateRange, setDateRange] = useState<DateRange | undefined>();
    const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<'all' | 'recent' | 'suspicious'>('all');

    // UI states
    const [isLoading, setIsLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [selectedLogs, setSelectedLogs] = useState<Set<number>>(new Set());
    const [isExporting, setIsExporting] = useState(false);

    // Pagination
    const [combinedPage, setCombinedPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    // Detail/block dialogs
    const [selectedLog, setSelectedLog] = useState<LoginLog | null>(null);
    const [selectedLogCategory, setSelectedLogCategory] = useState<'recent' | 'suspicious' | undefined>(undefined);
    const [isDetailsDialogOpen, setIsDetailsDialogOpen] = useState(false);
    const [ipToBlock, setIpToBlock] = useState<string | null>(null);
    const [isBlockDialogOpen, setIsBlockDialogOpen] = useState(false);
    const [isBlocking, setIsBlocking] = useState(false);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    // Initialize selectedCategory from URL
    useEffect(() => {
        try {
            const params = new URLSearchParams(window.location.search);
            const cat = params.get('category');
            if (cat === 'recent' || cat === 'suspicious' || cat === 'all') {
                setSelectedCategory(cat);
            }
        } catch {
            /* ignore */
        }
    }, []);

    // Auto-refresh via Inertia polling
    const { stop, start } = usePoll(
        30000,
        {
            only: ['recentLogins', 'statistics', 'suspiciousActivities'],
            onStart: () => setIsRefreshing(true),
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Data refreshed', { description: 'Login logs updated successfully', duration: 2000 });
            },
        },
        { autoStart: false, keepAlive: false },
    );

    useEffect(() => {
        if (autoRefresh) {
            start();
        } else {
            stop();
        }
        return () => stop();
    }, [autoRefresh, start, stop]);

    // Persist category to URL
    useEffect(() => {
        try {
            const url = new URL(window.location.href);
            if (selectedCategory === 'all') {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', selectedCategory);
            }
            window.history.replaceState({}, '', url.toString());
        } catch {
            /* ignore */
        }
    }, [selectedCategory]);

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearchTerm(searchTerm);
            setIsLoading(false);
        }, 300);
        if (searchTerm !== debouncedSearchTerm) {
            setIsLoading(true);
        }
        return () => clearTimeout(timer);
    }, [searchTerm, debouncedSearchTerm]);

    // Filtered + sorted combined logs
    const combinedFilteredAndSortedLogs: CombinedLog[] = useMemo(() => {
        const recentTagged: CombinedLog[] = recentLogins.map((l) => ({ ...l, category: 'recent' as const }));
        const suspiciousTagged: CombinedLog[] = (suspiciousActivities || []).map((l) => ({ ...l, category: 'suspicious' as const }));

        let logs: CombinedLog[] =
            selectedCategory === 'all' ? [...recentTagged, ...suspiciousTagged] : selectedCategory === 'recent' ? recentTagged : suspiciousTagged;

        logs = logs.filter((log) => {
            const isNotCurrentUser = !log.user || !auth?.user || log.user.id !== auth.user.id;
            const matchesSearch =
                !debouncedSearchTerm ||
                log.user?.name?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.user?.email?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.ip_address.includes(debouncedSearchTerm) ||
                log.location?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.browser?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.platform?.toLowerCase().includes(debouncedSearchTerm.toLowerCase());
            const matchesRole = selectedRole === 'all' || log.user?.primary_role === selectedRole;
            const matchesStatus =
                selectedStatus === 'all' || (selectedStatus === 'success' && log.successful) || (selectedStatus === 'failed' && !log.successful);
            const matchesDeviceType = selectedDeviceType === 'all' || log.device_type === selectedDeviceType;
            const matchesBrowser = selectedBrowser === 'all' || log.browser === selectedBrowser;
            const matchesDateRange =
                !dateRange?.from ||
                !dateRange?.to ||
                (() => {
                    const d = new Date(log.login_at);
                    return d >= dateRange.from! && d <= dateRange.to!;
                })();

            return isNotCurrentUser && matchesSearch && matchesRole && matchesStatus && matchesDeviceType && matchesBrowser && matchesDateRange;
        });

        return logs.sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [
        recentLogins,
        suspiciousActivities,
        selectedCategory,
        debouncedSearchTerm,
        selectedRole,
        selectedStatus,
        selectedDeviceType,
        selectedBrowser,
        dateRange,
        auth?.user,
    ]);

    const paginatedCombinedLogs = useMemo(() => {
        const start = (combinedPage - 1) * pageSize;
        return combinedFilteredAndSortedLogs.slice(start, start + pageSize);
    }, [combinedFilteredAndSortedLogs, combinedPage, pageSize]);

    const totalCombinedPages = Math.ceil(combinedFilteredAndSortedLogs.length / pageSize);

    // Reset page on filter changes
    useEffect(() => {
        setCombinedPage(1);
    }, [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, dateRange, selectedCategory]);

    // Unique filter options
    const getUniqueRoles = useMemo(() => {
        const roles = new Set<string>();
        [...recentLogins, ...(suspiciousActivities || [])].forEach((l) => {
            if (l.user?.primary_role) roles.add(l.user.primary_role);
        });
        return Array.from(roles).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueBrowsers = useMemo(() => {
        const browsers = new Set<string>();
        [...recentLogins, ...(suspiciousActivities || [])].forEach((l) => {
            if (l.browser) browsers.add(l.browser);
        });
        return Array.from(browsers).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueDeviceTypes = useMemo(() => {
        const types = new Set<string>();
        [...recentLogins, ...(suspiciousActivities || [])].forEach((l) => {
            if (l.device_type) types.add(l.device_type);
        });
        return Array.from(types).sort();
    }, [recentLogins, suspiciousActivities]);

    const hasActiveFilters = useMemo(
        () =>
            debouncedSearchTerm !== '' ||
            selectedRole !== 'all' ||
            selectedStatus !== 'all' ||
            selectedDeviceType !== 'all' ||
            selectedBrowser !== 'all' ||
            selectedCategory !== 'all' ||
            !!dateRange?.from ||
            !!dateRange?.to,
        [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, selectedCategory, dateRange],
    );

    // Actions
    const exportToCSV = useCallback(() => {
        setIsExporting(true);
        try {
            const logs = selectedLogs.size > 0 ? combinedFilteredAndSortedLogs.filter((l) => selectedLogs.has(l.id)) : combinedFilteredAndSortedLogs;

            if (logs.length === 0) {
                toast.error('No data to export');
                return;
            }

            const headers = [
                'Date/Time',
                'User',
                'Email',
                'Role',
                '2FA',
                'Status',
                'IP Address',
                'Location',
                'Device',
                'Browser',
                'Platform',
                'Session Duration',
            ];
            const rows = logs.map((log) => [
                formatDateTime(log.login_at),
                log.user?.name || 'Unknown',
                log.user?.email || 'Unknown',
                log.user?.primary_role || '-',
                log.user?.two_factor_enabled ? 'Enabled' : 'Disabled',
                log.successful ? 'Success' : 'Failed',
                log.ip_address,
                log.location || '-',
                log.device_type || 'Unknown',
                log.browser || 'Unknown',
                log.platform || '-',
                getSessionDuration(log.login_at, log.logout_at),
            ]);

            const csv = [headers, ...rows].map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `login-logs-${format(new Date(), 'yyyy-MM-dd-HHmmss')}.csv`;
            link.click();
            toast.success('Export successful', { description: `Exported ${logs.length} login log${logs.length !== 1 ? 's' : ''}` });
            setSelectedLogs(new Set());
        } catch {
            toast.error('Export failed');
        } finally {
            setIsExporting(false);
        }
    }, [combinedFilteredAndSortedLogs, selectedLogs]);

    const handleRefresh = useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            only: ['recentLogins', 'statistics', 'suspiciousActivities'],
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Data refreshed');
            },
        });
    }, []);

    const toggleSelectAll = useCallback(() => {
        setSelectedLogs((prev) => (prev.size === paginatedCombinedLogs.length ? new Set() : new Set(paginatedCombinedLogs.map((l) => l.id))));
    }, [paginatedCombinedLogs]);

    const toggleLogSelection = useCallback((id: number) => {
        setSelectedLogs((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }, []);

    const clearAllFilters = useCallback(() => {
        setSearchTerm('');
        setSelectedRole('all');
        setSelectedStatus('all');
        setSelectedDeviceType('all');
        setSelectedBrowser('all');
        setDateRange(undefined);
        setSelectedCategory('all');
    }, []);

    const setDateRangePreset = useCallback((preset: string) => {
        const today = new Date();
        const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const endOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
        switch (preset) {
            case 'today':
                setDateRange({ from: startOfToday, to: endOfToday });
                break;
            case 'last7days': {
                const d = new Date(today);
                d.setDate(today.getDate() - 7);
                setDateRange({ from: d, to: today });
                break;
            }
            case 'last30days': {
                const d = new Date(today);
                d.setDate(today.getDate() - 30);
                setDateRange({ from: d, to: today });
                break;
            }
            case 'thisMonth':
                setDateRange({
                    from: new Date(today.getFullYear(), today.getMonth(), 1),
                    to: new Date(today.getFullYear(), today.getMonth() + 1, 0),
                });
                break;
            default:
                setDateRange(undefined);
        }
    }, []);

    const handleViewDetails = useCallback((log: LoginLog, category: 'recent' | 'suspicious') => {
        setSelectedLog(log);
        setSelectedLogCategory(category);
        setIsDetailsDialogOpen(true);
    }, []);

    const handleBlockIpClick = useCallback((ip: string) => {
        setIpToBlock(ip);
        setIsBlockDialogOpen(true);
    }, []);

    const handleBlockIpConfirm = useCallback(
        async (reason: string, duration: 'temporary' | 'permanent') => {
            if (!ipToBlock) return;
            setIsBlocking(true);
            router.post(
                loginLogs.blockIp.url(),
                { ip_address: ipToBlock, reason, duration },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsBlockDialogOpen(false);
                        setIpToBlock(null);
                    },
                    onFinish: () => {
                        setIsBlocking(false);
                    },
                },
            );
        },
        [ipToBlock],
    );

    return {
        // States
        searchTerm,
        setSearchTerm,
        debouncedSearchTerm,
        selectedRole,
        setSelectedRole,
        selectedStatus,
        setSelectedStatus,
        selectedDeviceType,
        setSelectedDeviceType,
        selectedBrowser,
        setSelectedBrowser,
        dateRange,
        setDateRange,
        showAdvancedFilters,
        setShowAdvancedFilters,
        selectedCategory,
        setSelectedCategory,
        isLoading,
        isRefreshing,
        autoRefresh,
        setAutoRefresh,
        selectedLogs,
        isExporting,
        combinedPage,
        setCombinedPage,
        pageSize,
        setPageSize,
        // Computed
        combinedFilteredAndSortedLogs,
        paginatedCombinedLogs,
        totalCombinedPages,
        hasActiveFilters,
        getUniqueRoles,
        getUniqueBrowsers,
        getUniqueDeviceTypes,
        // Dialog
        selectedLog,
        selectedLogCategory,
        isDetailsDialogOpen,
        setIsDetailsDialogOpen,
        ipToBlock,
        isBlockDialogOpen,
        setIsBlockDialogOpen,
        isBlocking,
        // Actions
        exportToCSV,
        handleRefresh,
        toggleSelectAll,
        toggleLogSelection,
        clearAllFilters,
        setDateRangePreset,
        handleViewDetails,
        handleBlockIpClick,
        handleBlockIpConfirm,
    };
}

// Shared helpers
export function highlightSearchTerm(text: string, term: string): React.ReactNode {
    if (!term || !text) return text;
    const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    const parts = text.split(regex);
    return parts.map((part, i) =>
        regex.test(part) ? (
            <span key={i} className="rounded bg-yellow-200 px-0.5 dark:bg-yellow-800">
                {part}
            </span>
        ) : (
            part
        ),
    );
}

export function formatDateTime(dateString: string) {
    try {
        const d = new Date(dateString);
        return isNaN(d.getTime()) ? 'Invalid Date' : d.toLocaleString();
    } catch {
        return 'Invalid Date';
    }
}

export function getSessionDuration(loginAt: string, logoutAt?: string) {
    if (!logoutAt) return 'Active';
    try {
        const ms = new Date(logoutAt).getTime() - new Date(loginAt).getTime();
        const h = Math.floor(ms / 3600000),
            m = Math.floor((ms % 3600000) / 60000);
        return h > 0 ? `${h}h ${m}m` : `${m}m`;
    } catch {
        return 'Unknown';
    }
}
