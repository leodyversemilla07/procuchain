import BlockIpConfirmationDialog from '@/components/admin/block-ip-confirmation-dialog';
import LoginLogDetailsDialog from '@/components/admin/login-log-details-dialog';
import { HeroCard } from '@/components/hero-card';
import { Pagination } from '@/components/pagination';
import { StatsGrid } from '@/components/stats-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import loginLogs from '@/routes/admin/login-logs';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Activity,
    AlertTriangle,
    Calendar as CalendarIcon,
    ChevronDown,
    Clock,
    Download,
    Eye,
    Filter,
    Globe,
    Loader2,
    MapPin,
    Monitor,
    MoreVertical,
    QrCode,
    RefreshCw,
    Search,
    ShieldBan,
    Shield,
    Smartphone,
    Tablet,
    TrendingUp,
    User,
    X,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { DateRange } from 'react-day-picker';
import { toast } from 'sonner';

interface LoginLog {
    id: number;
    user_id?: number;
    user?: {
        id: number;
        name: string;
        email: string;
        role: string;
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

interface LoginStatistics {
    total_logins: number;
    successful_logins: number;
    failed_logins: number;
    unique_users: number;
    today_logins: number;
    this_week_logins: number;
    this_month_logins: number;
}

interface Props {
    recentLogins: LoginLog[];
    statistics: LoginStatistics;
    suspiciousActivities: LoginLog[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: dashboard.url(),
    },
    {
        title: 'Login Logs',
        href: loginLogs.index.url(),
    },
];

export default function LoginLogs({ recentLogins, statistics, suspiciousActivities }: Props) {
    // Access authentication data to get current user
    const page = usePage<Props & SharedData>();
    const { auth } = page.props;

    const [searchTerm, setSearchTerm] = useState('');
    // Pagination state for merged table
    const [combinedPage, setCombinedPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    // Enhanced search and filter states
    const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
    const [selectedRole, setSelectedRole] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [selectedDeviceType, setSelectedDeviceType] = useState<string>('all');
    const [selectedBrowser, setSelectedBrowser] = useState<string>('all');
    const [dateRange, setDateRange] = useState<DateRange | undefined>();
    const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<'all' | 'recent' | 'suspicious'>('all');

    // New state for enhancements
    const [isLoading, setIsLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [selectedLogs, setSelectedLogs] = useState<Set<number>>(new Set());
    const [isExporting, setIsExporting] = useState(false);

    // Dialog state for viewing log details
    const [selectedLog, setSelectedLog] = useState<LoginLog | null>(null);
    const [selectedLogCategory, setSelectedLogCategory] = useState<'recent' | 'suspicious' | undefined>(undefined);
    const [isDetailsDialogOpen, setIsDetailsDialogOpen] = useState(false);

    // Dialog state for blocking IP
    const [ipToBlock, setIpToBlock] = useState<string | null>(null);
    const [isBlockDialogOpen, setIsBlockDialogOpen] = useState(false);
    const [isBlocking, setIsBlocking] = useState(false);

    // Initialize selectedCategory from URL query on mount
    useEffect(() => {
        try {
            const params = new URLSearchParams(window.location.search);
            const cat = params.get('category');
            if (cat === 'recent' || cat === 'suspicious' || cat === 'all') {
                setSelectedCategory(cat);
            }
        } catch {
            // ignore parsing issues
        }
    }, []);

    // Auto-refresh functionality
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            setIsRefreshing(true);
            router.reload({
                only: ['recentLogins', 'statistics', 'suspiciousActivities'],
                onFinish: () => {
                    setIsRefreshing(false);
                    toast.success('Data refreshed', {
                        description: 'Login logs updated successfully',
                        duration: 2000,
                    });
                },
            });
        }, 30000); // 30 seconds

        return () => clearInterval(interval);
    }, [autoRefresh]);

    // Persist selectedCategory to URL query (without reload)
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
            // ignore URL update issues
        }
    }, [selectedCategory]);

    // Debounce search term
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

    // Enhanced filtering function
    const filterLogs = useCallback(
        (logs: LoginLog[]) => {
            return logs.filter((log) => {
                // Filter out the current logged-in admin user's entries
                const isNotCurrentUser = !log.user || !auth.user || log.user.id !== auth.user.id;

                // Text search
                const matchesSearch =
                    !debouncedSearchTerm ||
                    log.user?.name?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                    log.user?.email?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                    log.ip_address.includes(debouncedSearchTerm) ||
                    log.location?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                    log.browser?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                    log.platform?.toLowerCase().includes(debouncedSearchTerm.toLowerCase());

                // Role filter
                const matchesRole = selectedRole === 'all' || log.user?.role === selectedRole;

                // Status filter
                const matchesStatus =
                    selectedStatus === 'all' || (selectedStatus === 'success' && log.successful) || (selectedStatus === 'failed' && !log.successful);

                // Device type filter
                const matchesDeviceType = selectedDeviceType === 'all' || log.device_type === selectedDeviceType;

                // Browser filter
                const matchesBrowser = selectedBrowser === 'all' || log.browser === selectedBrowser;

                // Date range filter
                const matchesDateRange =
                    !dateRange?.from ||
                    !dateRange?.to ||
                    (() => {
                        const loginDate = new Date(log.login_at);
                        return loginDate >= dateRange.from! && loginDate <= dateRange.to!;
                    })();

                return isNotCurrentUser && matchesSearch && matchesRole && matchesStatus && matchesDeviceType && matchesBrowser && matchesDateRange;
            });
        },
        [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, dateRange, auth.user],
    );

    // Sort and filter recent logins (latest first)
    const filteredAndSortedRecentLogins = useMemo(() => {
        return filterLogs(recentLogins).sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [recentLogins, filterLogs]);

    // Sort and filter suspicious activities (latest first)
    const filteredAndSortedSuspiciousActivities = useMemo(() => {
        return filterLogs(suspiciousActivities).sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [suspiciousActivities, filterLogs]);

    // Merge, sort, and paginate combined logs
    type CombinedLog = LoginLog & { category: 'recent' | 'suspicious' };
    const combinedFilteredAndSortedLogs: CombinedLog[] = useMemo(() => {
        const recentTagged = filteredAndSortedRecentLogins.map((l) => ({ ...l, category: 'recent' as const }));
        const suspiciousTagged = filteredAndSortedSuspiciousActivities.map((l) => ({ ...l, category: 'suspicious' as const }));
        let merged: CombinedLog[] = [...recentTagged, ...suspiciousTagged];
        if (selectedCategory !== 'all') {
            merged = merged.filter((l) => l.category === selectedCategory);
        }
        return merged.sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [filteredAndSortedRecentLogins, filteredAndSortedSuspiciousActivities, selectedCategory]);

    const paginatedCombinedLogs = useMemo(() => {
        const startIndex = (combinedPage - 1) * pageSize;
        const endIndex = startIndex + pageSize;
        return combinedFilteredAndSortedLogs.slice(startIndex, endIndex);
    }, [combinedFilteredAndSortedLogs, combinedPage, pageSize]);

    const totalCombinedPages = Math.ceil(combinedFilteredAndSortedLogs.length / pageSize);

    // Reset page when filters change
    React.useEffect(() => {
        setCombinedPage(1);
    }, [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, dateRange, selectedCategory]);

    // Export to CSV functionality
    const exportToCSV = useCallback(
        (logsToExport?: LoginLog[]) => {
            setIsExporting(true);
            try {
                const logs = logsToExport || (selectedLogs.size > 0 ? combinedFilteredAndSortedLogs.filter((l) => selectedLogs.has(l.id)) : combinedFilteredAndSortedLogs);

                if (logs.length === 0) {
                    toast.error('No data to export');
                    return;
                }

                const headers = ['Date/Time', 'User', 'Email', 'Role', '2FA', 'Status', 'IP Address', 'Location', 'Device', 'Browser', 'Platform', 'Session Duration'];

                const rows = logs.map((log) => [
                    formatDateTime(log.login_at),
                    log.user?.name || 'Unknown',
                    log.user?.email || 'Unknown',
                    log.user?.role || '-',
                    log.user?.two_factor_enabled ? 'Enabled' : 'Disabled',
                    log.successful ? 'Success' : 'Failed',
                    log.ip_address,
                    log.location || '-',
                    log.device_type || 'Unknown',
                    log.browser || 'Unknown',
                    log.platform || '-',
                    getSessionDuration(log.login_at, log.logout_at),
                ]);

                const csvContent = [headers, ...rows].map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');

                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `login-logs-${format(new Date(), 'yyyy-MM-dd-HHmmss')}.csv`;
                link.click();

                toast.success('Export successful', {
                    description: `Exported ${logs.length} login log${logs.length !== 1 ? 's' : ''}`,
                });

                // Clear selection after export
                setSelectedLogs(new Set());
            } catch (error) {
                toast.error('Export failed', {
                    description: 'An error occurred while exporting data',
                });
                console.error('Export error:', error);
            } finally {
                setIsExporting(false);
            }
        },
        [combinedFilteredAndSortedLogs, selectedLogs],
    );

    // Refresh data manually
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

    // Toggle select all
    const toggleSelectAll = useCallback(() => {
        if (selectedLogs.size === paginatedCombinedLogs.length) {
            setSelectedLogs(new Set());
        } else {
            setSelectedLogs(new Set(paginatedCombinedLogs.map((l) => l.id)));
        }
    }, [paginatedCombinedLogs, selectedLogs]);

    // Toggle individual log selection
    const toggleLogSelection = useCallback((logId: number) => {
        setSelectedLogs((prev) => {
            const next = new Set(prev);
            if (next.has(logId)) {
                next.delete(logId);
            } else {
                next.add(logId);
            }
            return next;
        });
    }, []);

    // Clear all filters
    const clearAllFilters = useCallback(() => {
        setSearchTerm('');
        setSelectedRole('all');
        setSelectedStatus('all');
        setSelectedDeviceType('all');
        setSelectedBrowser('all');
        setDateRange(undefined);
        setSelectedCategory('all');
    }, []);

    // Quick date range presets
    const setDateRangePreset = useCallback((preset: string) => {
        const today = new Date();
        const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const endOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);

        switch (preset) {
            case 'today': {
                setDateRange({ from: startOfToday, to: endOfToday });
                break;
            }
            case 'last7days': {
                const last7Days = new Date(today);
                last7Days.setDate(today.getDate() - 7);
                setDateRange({ from: last7Days, to: today });
                break;
            }
            case 'last30days': {
                const last30Days = new Date(today);
                last30Days.setDate(today.getDate() - 30);
                setDateRange({ from: last30Days, to: today });
                break;
            }
            case 'thisMonth': {
                const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                setDateRange({ from: startOfMonth, to: endOfMonth });
                break;
            }
            default:
                setDateRange(undefined);
        }
    }, []);

    // Get unique values for filter options
    const getUniqueRoles = useMemo(() => {
        const roles = new Set<string>();
        [...recentLogins, ...suspiciousActivities].forEach((log) => {
            if (log.user?.role) roles.add(log.user.role);
        });
        return Array.from(roles).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueBrowsers = useMemo(() => {
        const browsers = new Set<string>();
        [...recentLogins, ...suspiciousActivities].forEach((log) => {
            if (log.browser) browsers.add(log.browser);
        });
        return Array.from(browsers).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueDeviceTypes = useMemo(() => {
        const deviceTypes = new Set<string>();
        [...recentLogins, ...suspiciousActivities].forEach((log) => {
            if (log.device_type) deviceTypes.add(log.device_type);
        });
        return Array.from(deviceTypes).sort();
    }, [recentLogins, suspiciousActivities]);

    // Check if any filters are active
    const hasActiveFilters = useMemo(() => {
        return (
            debouncedSearchTerm !== '' ||
            selectedRole !== 'all' ||
            selectedStatus !== 'all' ||
            selectedDeviceType !== 'all' ||
            selectedBrowser !== 'all' ||
            selectedCategory !== 'all' ||
            dateRange?.from ||
            dateRange?.to
        );
    }, [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, selectedCategory, dateRange]);

    // Handler to open the details dialog
    const handleViewDetails = useCallback((log: LoginLog, category: 'recent' | 'suspicious') => {
        setSelectedLog(log);
        setSelectedLogCategory(category);
        setIsDetailsDialogOpen(true);
    }, []);

    // Handler to open block IP dialog
    const handleBlockIpClick = useCallback((ipAddress: string) => {
        setIpToBlock(ipAddress);
        setIsBlockDialogOpen(true);
    }, []);

    // Handler to confirm blocking IP
    const handleBlockIpConfirm = useCallback(
        async (reason: string, duration: 'temporary' | 'permanent') => {
            if (!ipToBlock) return;

            setIsBlocking(true);
            try {
                const response = await fetch(route('admin.login-logs.block-ip'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        ip_address: ipToBlock,
                        reason,
                        duration,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    toast.success('IP Address Blocked', {
                        description: `${ipToBlock} has been blocked successfully.`,
                    });
                    setIsBlockDialogOpen(false);
                    setIpToBlock(null);
                } else {
                    toast.error('Failed to block IP', {
                        description: data.error || 'An error occurred while blocking the IP address.',
                    });
                }
            } catch (error) {
                console.error('Error blocking IP:', error);
                toast.error('Failed to block IP', {
                    description: 'An error occurred while blocking the IP address.',
                });
            } finally {
                setIsBlocking(false);
            }
        },
        [ipToBlock],
    );

    // Helper function to highlight search terms
    const highlightSearchTerm = (text: string, searchTerm: string) => {
        if (!searchTerm || !text) return text;

        const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        const parts = text.split(regex);

        return parts.map((part, index) =>
            regex.test(part) ? (
                <span key={index} className="rounded bg-yellow-200 px-0.5 dark:bg-yellow-800">
                    {part}
                </span>
            ) : (
                part
            ),
        );
    };

    const getDeviceIcon = (deviceType?: string) => {
        switch (deviceType?.toLowerCase()) {
            case 'mobile':
                return <Smartphone className="h-4 w-4" />;
            case 'tablet':
                return <Tablet className="h-4 w-4" />;
            case 'desktop':
            default:
                return <Monitor className="h-4 w-4" />;
        }
    };

    const getRoleBadge = (role?: string) => {
        if (!role) return <span className="text-muted-foreground text-xs">-</span>;

        // Role-specific styling for better visual hierarchy
        const roleVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            admin: 'destructive',
            super_admin: 'destructive',
            bac_secretariat: 'default',
            bac_chairperson: 'default',
            bac_member: 'secondary',
            bac_technical_working_group: 'secondary',
            procurement_officer: 'outline',
            end_user: 'outline',
            supplier: 'outline',
        };

        const variant = roleVariants[role.toLowerCase()] || 'secondary';
        const displayName = role.replace('_', ' ').toUpperCase();

        return (
            <Badge variant={variant} className="text-xs font-medium">
                {displayName}
            </Badge>
        );
    };

    const getStatusBadge = (successful: boolean) => {
        return <Badge variant={successful ? 'default' : 'destructive'}>{successful ? 'Success' : 'Failed'}</Badge>;
    };

    const formatDateTime = (dateString: string) => {
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return 'Invalid Date';
            }
            return date.toLocaleString();
        } catch {
            return 'Invalid Date';
        }
    };

    const getSessionDuration = (loginAt: string, logoutAt?: string) => {
        if (!logoutAt) return 'Active';

        try {
            const login = new Date(loginAt);
            const logout = new Date(logoutAt);
            const duration = logout.getTime() - login.getTime();
            const hours = Math.floor(duration / (1000 * 60 * 60));
            const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));

            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            }
            return `${minutes}m`;
        } catch {
            return 'Unknown';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Login Logs - Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header Section */}
                <HeroCard
                    icon={Shield}
                    title="Login Logs"
                    description="Monitor user login activities and security events"
                    actions={
                        <div className="flex gap-2">
                            <Button onClick={handleRefresh} variant="outline" disabled={isRefreshing} size="default">
                                <RefreshCw className={cn('mr-2 h-4 w-4', isRefreshing && 'animate-spin')} />
                                {isRefreshing ? 'Refreshing...' : 'Refresh'}
                            </Button>
                            <Button onClick={() => setAutoRefresh(!autoRefresh)} variant={autoRefresh ? 'default' : 'outline'} size="default">
                                {autoRefresh ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Auto-refresh On
                                    </>
                                ) : (
                                    <>
                                        <Clock className="mr-2 h-4 w-4" />
                                        Enable Auto-refresh
                                    </>
                                )}
                            </Button>
                            <Button onClick={() => exportToCSV()} variant="outline" disabled={isExporting || combinedFilteredAndSortedLogs.length === 0}>
                                {isExporting ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Exporting...
                                    </>
                                ) : (
                                    <>
                                        <Download className="mr-2 h-4 w-4" />
                                        Export CSV
                                    </>
                                )}
                            </Button>
                        </div>
                    }
                />

                {/* Statistics Cards */}
                <StatsGrid
                    items={[
                        {
                            label: 'Total Logins',
                            value: statistics.total_logins?.toLocaleString() || '0',
                            icon: Activity,
                            iconClassName: 'text-blue-600 dark:text-blue-400',
                        },
                        {
                            label: 'Success Rate',
                            value:
                                statistics.total_logins > 0 ? `${Math.round((statistics.successful_logins / statistics.total_logins) * 100)}%` : '0%',
                            icon: Shield,
                            iconClassName: 'text-green-600 dark:text-green-400',
                        },
                        {
                            label: "Today's Logins",
                            value: statistics.today_logins?.toString() || '0',
                            icon: CalendarIcon,
                            iconClassName: 'text-purple-600 dark:text-purple-400',
                        },
                        {
                            label: 'Unique Users',
                            value: statistics.unique_users?.toString() || '0',
                            icon: User,
                            iconClassName: 'text-orange-600 dark:text-orange-400',
                        },
                    ]}
                />

                {/* Search and Filter Section */}
                <Card>
                    <CardContent className="space-y-4 p-6">
                        {/* Search Bar and Main Actions */}
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search by name, email, IP, location..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                                {searchTerm && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSearchTerm('')}
                                        className="absolute top-1/2 right-2 h-6 w-6 -translate-y-1/2 p-0"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button variant="outline" onClick={() => setShowAdvancedFilters(!showAdvancedFilters)} className="whitespace-nowrap">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filters
                                    <ChevronDown className={`ml-2 h-4 w-4 transition-transform ${showAdvancedFilters ? 'rotate-180' : ''}`} />
                                </Button>
                                {hasActiveFilters && (
                                    <Button variant="outline" onClick={clearAllFilters}>
                                        <X className="mr-2 h-4 w-4" />
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Filter Options */}
                        {showAdvancedFilters && (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {/* Category Filter */}
                                <Select value={selectedCategory} onValueChange={(v) => setSelectedCategory(v as 'all' | 'recent' | 'suspicious')}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        <SelectItem value="recent">Recent</SelectItem>
                                        <SelectItem value="suspicious">Suspicious</SelectItem>
                                    </SelectContent>
                                </Select>

                                {/* Status Filter */}
                                <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="success">Success</SelectItem>
                                        <SelectItem value="failed">Failed</SelectItem>
                                    </SelectContent>
                                </Select>

                                {/* Role Filter */}
                                <Select value={selectedRole} onValueChange={setSelectedRole}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Roles</SelectItem>
                                        {getUniqueRoles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role.replace('_', ' ').toUpperCase()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/* Device Type Filter */}
                                <Select value={selectedDeviceType} onValueChange={setSelectedDeviceType}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Device" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Devices</SelectItem>
                                        {getUniqueDeviceTypes.map((deviceType) => (
                                            <SelectItem key={deviceType} value={deviceType}>
                                                {deviceType.charAt(0).toUpperCase() + deviceType.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/* Browser Filter */}
                                <Select value={selectedBrowser} onValueChange={setSelectedBrowser}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Browser" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Browsers</SelectItem>
                                        {getUniqueBrowsers.map((browser) => (
                                            <SelectItem key={browser} value={browser}>
                                                {browser}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/* Date Range Filter */}
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className={cn('justify-start text-left font-normal', !dateRange && 'text-muted-foreground')}
                                        >
                                            <CalendarIcon className="mr-2 h-4 w-4" />
                                            {dateRange?.from ? (
                                                dateRange.to ? (
                                                    <>
                                                        {format(dateRange.from, 'MMM dd')} - {format(dateRange.to, 'MMM dd')}
                                                    </>
                                                ) : (
                                                    format(dateRange.from, 'MMM dd, y')
                                                )
                                            ) : (
                                                <span>Date Range</span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0" align="start">
                                        <div className="border-b p-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button variant="ghost" size="sm" onClick={() => setDateRangePreset('today')}>
                                                    Today
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setDateRangePreset('last7days')}>
                                                    Last 7 Days
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setDateRangePreset('last30days')}>
                                                    Last 30 Days
                                                </Button>
                                            </div>
                                        </div>
                                        <Calendar
                                            initialFocus
                                            mode="range"
                                            defaultMonth={dateRange?.from}
                                            selected={dateRange}
                                            onSelect={setDateRange}
                                            numberOfMonths={2}
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>
                        )}

                        {/* Active Filters Display */}
                        {hasActiveFilters && (
                            <div className="flex flex-wrap gap-2">
                                {selectedCategory !== 'all' && (
                                    <Badge variant="secondary" className="gap-1">
                                        {selectedCategory.charAt(0).toUpperCase() + selectedCategory.slice(1)}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSelectedCategory('all')} />
                                    </Badge>
                                )}
                                {debouncedSearchTerm && (
                                    <Badge variant="secondary" className="gap-1">
                                        "{debouncedSearchTerm}"
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSearchTerm('')} />
                                    </Badge>
                                )}
                                {selectedRole !== 'all' && (
                                    <Badge variant="secondary" className="gap-1">
                                        {selectedRole.replace('_', ' ').toUpperCase()}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSelectedRole('all')} />
                                    </Badge>
                                )}
                                {selectedStatus !== 'all' && (
                                    <Badge variant="secondary" className="gap-1">
                                        {selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1)}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSelectedStatus('all')} />
                                    </Badge>
                                )}
                                {selectedDeviceType !== 'all' && (
                                    <Badge variant="secondary" className="gap-1">
                                        {selectedDeviceType.charAt(0).toUpperCase() + selectedDeviceType.slice(1)}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSelectedDeviceType('all')} />
                                    </Badge>
                                )}
                                {selectedBrowser !== 'all' && (
                                    <Badge variant="secondary" className="gap-1">
                                        {selectedBrowser}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setSelectedBrowser('all')} />
                                    </Badge>
                                )}
                                {(dateRange?.from || dateRange?.to) && (
                                    <Badge variant="secondary" className="gap-1">
                                        {dateRange.from?.toLocaleDateString()} - {dateRange.to?.toLocaleDateString()}
                                        <X className="h-3 w-3 cursor-pointer" onClick={() => setDateRange(undefined)} />
                                    </Badge>
                                )}
                            </div>
                        )}

                        {/* Bulk Actions Bar */}
                        {selectedLogs.size > 0 && (
                            <div className="flex items-center justify-between rounded-lg border bg-muted/50 p-3">
                                <div className="flex items-center gap-2">
                                    <Badge variant="default">{selectedLogs.size} selected</Badge>
                                    <Button variant="ghost" size="sm" onClick={() => setSelectedLogs(new Set())}>
                                        Clear selection
                                    </Button>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => exportToCSV()}
                                        disabled={isExporting}
                                    >
                                        <Download className="mr-2 h-4 w-4" />
                                        Export Selected
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Login Activity Trend */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5" />
                            Login Activity Trend
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-sm">This Week</p>
                                <div className="flex items-baseline gap-2">
                                    <p className="text-2xl font-bold">{statistics.this_week_logins?.toLocaleString() || '0'}</p>
                                    <Badge variant="secondary" className="text-xs">
                                        Logins
                                    </Badge>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-sm">This Month</p>
                                <div className="flex items-baseline gap-2">
                                    <p className="text-2xl font-bold">{statistics.this_month_logins?.toLocaleString() || '0'}</p>
                                    <Badge variant="secondary" className="text-xs">
                                        Logins
                                    </Badge>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-sm">Success Rate</p>
                                <div className="flex items-baseline gap-2">
                                    <p className="text-2xl font-bold">
                                        {statistics.total_logins > 0
                                            ? `${Math.round((statistics.successful_logins / statistics.total_logins) * 100)}%`
                                            : '0%'}
                                    </p>
                                    <Badge variant={statistics.total_logins > 0 && (statistics.successful_logins / statistics.total_logins) >= 0.9 ? 'default' : 'destructive'} className="text-xs">
                                        {statistics.total_logins > 0 && (statistics.successful_logins / statistics.total_logins) >= 0.9 ? 'Healthy' : 'Review'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Login Logs - Unified Table */}
                <div className="flex-1">
                    <Card>
                        {/* Mobile Card View */}
                        <div className="md:hidden">
                            <CardContent className="space-y-4 p-4">
                                {isLoading || isRefreshing ? (
                                    Array.from({ length: 3 }).map((_, index) => (
                                        <Card key={`mobile-skeleton-${index}`}>
                                            <CardContent className="space-y-3 p-4">
                                                <div className="flex items-center justify-between">
                                                    <Skeleton className="h-6 w-24" />
                                                    <Skeleton className="h-6 w-16" />
                                                </div>
                                                <Skeleton className="h-4 w-full" />
                                                <Skeleton className="h-4 w-3/4" />
                                                <div className="flex gap-2">
                                                    <Skeleton className="h-6 w-20" />
                                                    <Skeleton className="h-6 w-20" />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))
                                ) : paginatedCombinedLogs.length > 0 ? (
                                    paginatedCombinedLogs.map((log) => (
                                        <Card key={`mobile-${log.category}-${log.id}`} className={log.category === 'suspicious' ? 'border-destructive/50' : undefined}>
                                            <CardContent className="space-y-3 p-4">
                                                <div className="flex items-center justify-between">
                                                    {log.category === 'suspicious' ? (
                                                        <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                                            <AlertTriangle className="h-3 w-3" /> Suspicious
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Recent
                                                        </Badge>
                                                    )}
                                                    {getStatusBadge(log.successful)}
                                                </div>
                                                <div>
                                                    <p className="font-medium">{log.user?.name || 'Unknown User'}</p>
                                                    <p className="text-muted-foreground text-sm">{log.user?.email || 'Unknown Email'}</p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2 text-sm">
                                                    <div>
                                                        <p className="text-muted-foreground text-xs">IP Address</p>
                                                        <p className="font-mono">{log.ip_address}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground text-xs">Device</p>
                                                        <div className="flex items-center gap-1">
                                                            {getDeviceIcon(log.device_type)}
                                                            <span>{log.device_type || 'Unknown'}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground text-xs">{formatDateTime(log.login_at)}</span>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onClick={() => handleViewDetails(log, log.category)}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    navigator.clipboard.writeText(log.ip_address);
                                                                    toast.success('IP copied');
                                                                }}
                                                            >
                                                                <Globe className="mr-2 h-4 w-4" />
                                                                Copy IP
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))
                                ) : (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Shield className="h-6 w-6" />
                                            </EmptyMedia>
                                            <EmptyTitle>No login activities found</EmptyTitle>
                                            <EmptyDescription>
                                                {hasActiveFilters
                                                    ? 'No activities match your current filters.'
                                                    : 'No login activities have been recorded yet.'}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                )}
                            </CardContent>
                        </div>

                        {/* Desktop Table View */}
                        <CardContent className="hidden p-0 md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 pl-6">
                                            <Checkbox
                                                checked={selectedLogs.size === paginatedCombinedLogs.length && paginatedCombinedLogs.length > 0}
                                                onCheckedChange={toggleSelectAll}
                                                aria-label="Select all"
                                            />
                                        </TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>User/Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>2FA</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>IP Address</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>Browser</TableHead>
                                        <TableHead>Time</TableHead>
                                        <TableHead>Session</TableHead>
                                        <TableHead className="w-12 pr-6">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {isLoading || isRefreshing ? (
                                        // Loading skeleton
                                        Array.from({ length: 5 }).map((_, index) => (
                                            <TableRow key={`skeleton-${index}`}>
                                                <TableCell className="pl-6">
                                                    <Skeleton className="h-4 w-4" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-6 w-20" />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-2">
                                                        <Skeleton className="h-4 w-32" />
                                                        <Skeleton className="h-3 w-40" />
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-6 w-24" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-6 w-20" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-6 w-16" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-4 w-28" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-4 w-20" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-4 w-24" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-4 w-32" />
                                                </TableCell>
                                                <TableCell>
                                                    <Skeleton className="h-6 w-16" />
                                                </TableCell>
                                                <TableCell className="pr-6">
                                                    <Skeleton className="h-8 w-8" />
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : paginatedCombinedLogs.length > 0 ? (
                                        paginatedCombinedLogs.map((log) => (
                                            <TableRow
                                                key={`${log.category}-${log.id}`}
                                                className={log.category === 'suspicious' ? 'bg-destructive/5' : undefined}
                                            >
                                                <TableCell className="pl-6">
                                                    <Checkbox
                                                        checked={selectedLogs.has(log.id)}
                                                        onCheckedChange={() => toggleLogSelection(log.id)}
                                                        aria-label={`Select log ${log.id}`}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {log.category === 'suspicious' ? (
                                                        <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                                            <AlertTriangle className="h-3 w-3" /> Suspicious
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Recent
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="font-medium">
                                                            {highlightSearchTerm(log.user?.name || 'Unknown User', debouncedSearchTerm)}
                                                        </div>
                                                        <div className="text-muted-foreground text-sm">
                                                            {highlightSearchTerm(log.user?.email || 'Unknown Email', debouncedSearchTerm)}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getRoleBadge(log.user?.role)}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-1">
                                                        {log.user?.two_factor_enabled ? (
                                                            <Badge className="border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                                                <QrCode className="mr-1 h-3 w-3" />
                                                                Enabled
                                                            </Badge>
                                                        ) : (
                                                            <Badge className="border border-gray-200 bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-300">
                                                                Disabled
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getStatusBadge(log.successful)}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        <Globe className="text-muted-foreground h-4 w-4" />
                                                        <span className="font-mono text-sm">
                                                            {highlightSearchTerm(log.ip_address, debouncedSearchTerm)}
                                                        </span>
                                                    </div>
                                                    {log.location && (
                                                        <div className="mt-1 flex items-center space-x-1">
                                                            <MapPin className="text-muted-foreground h-3 w-3" />
                                                            <span className="text-muted-foreground text-xs">
                                                                {highlightSearchTerm(log.location, debouncedSearchTerm)}
                                                            </span>
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        {getDeviceIcon(log.device_type)}
                                                        <div className="space-y-1">
                                                            <div className="text-sm">{log.device_type || 'Unknown'}</div>
                                                            {log.platform && (
                                                                <div className="text-muted-foreground text-xs">
                                                                    {highlightSearchTerm(log.platform, debouncedSearchTerm)}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {highlightSearchTerm(log.browser || 'Unknown', debouncedSearchTerm)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-6">
                                                    <div className="flex items-center space-x-2">
                                                        <Clock className="text-muted-foreground h-4 w-4" />
                                                        <span className="text-sm">{formatDateTime(log.login_at)}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {log.category === 'recent' ? (
                                                        <Badge variant={log.logout_at ? 'secondary' : 'default'}>
                                                            {getSessionDuration(log.login_at, log.logout_at)}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="pr-6">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                <MoreVertical className="h-4 w-4" />
                                                                <span className="sr-only">Open menu</span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuItem
                                                                onClick={() => handleViewDetails(log, log.category)}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    navigator.clipboard.writeText(log.ip_address);
                                                                    toast.success('IP Address copied', {
                                                                        description: log.ip_address,
                                                                    });
                                                                }}
                                                            >
                                                                <Globe className="mr-2 h-4 w-4" />
                                                                Copy IP Address
                                                            </DropdownMenuItem>
                                                            {log.category === 'suspicious' && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        className="text-destructive focus:text-destructive"
                                                                        onClick={() => handleBlockIpClick(log.ip_address)}
                                                                    >
                                                                        <ShieldBan className="mr-2 h-4 w-4" />
                                                                        Block IP Address
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={12} className="h-96">
                                                <Empty>
                                                    <EmptyHeader>
                                                        <EmptyMedia variant="icon">
                                                            <Shield className="h-6 w-6" />
                                                        </EmptyMedia>
                                                        <EmptyTitle>No login activities found</EmptyTitle>
                                                        <EmptyDescription>
                                                            {hasActiveFilters
                                                                ? 'No activities match your current filters. Try adjusting your search criteria.'
                                                                : 'No login activities have been recorded yet.'}
                                                        </EmptyDescription>
                                                    </EmptyHeader>
                                                </Empty>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                        {paginatedCombinedLogs.length > 0 && (
                            <CardFooter className="justify-end">
                                <Pagination
                                    pageIndex={combinedPage - 1}
                                    pageSize={pageSize}
                                    pageCount={totalCombinedPages}
                                    totalItems={combinedFilteredAndSortedLogs.length}
                                    onPageChange={(i) => setCombinedPage(i + 1)}
                                    onPageSizeChange={(size) => {
                                        setPageSize(size);
                                        setCombinedPage(1);
                                    }}
                                />
                            </CardFooter>
                        )}
                    </Card>
                </div>

                {/* Login Log Details Dialog */}
                <LoginLogDetailsDialog
                    open={isDetailsDialogOpen}
                    onOpenChange={setIsDetailsDialogOpen}
                    log={selectedLog}
                    category={selectedLogCategory}
                />

                {/* Block IP Confirmation Dialog */}
                <BlockIpConfirmationDialog
                    open={isBlockDialogOpen}
                    onOpenChange={setIsBlockDialogOpen}
                    ipAddress={ipToBlock || ''}
                    onConfirm={handleBlockIpConfirm}
                    isBlocking={isBlocking}
                />
            </div>
        </AppLayout>
    );
}
