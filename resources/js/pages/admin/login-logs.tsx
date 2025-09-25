import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { DateRange } from 'react-day-picker';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    AlertTriangle,
    Calendar as CalendarIcon,
    Clock,
    Globe,
    Monitor,
    Search,
    Shield,
    Smartphone,
    Tablet,
    User,
    MapPin, Activity,
    X,
    Filter,
    ChevronDown,
    QrCode
} from 'lucide-react';
import { Pagination } from '@/components/pagination';

interface LoginLog {
    id: number;
    user_id?: number;
    user?: {
        id: number;
        name: string;
        email: string;
        role: string;
        mfa_enabled?: boolean;
        mfa_enabled_at?: string;
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
        href: route('admin.dashboard'),
    },
    {
        title: 'Login Logs',
        href: route('admin.login-logs'),
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
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm]);

    // Enhanced filtering function
    const filterLogs = useCallback((logs: LoginLog[]) => {
        return logs.filter(log => {
            // Filter out the current logged-in admin user's entries
            const isNotCurrentUser = !log.user || log.user.id !== auth.user.id;

            // Text search
            const matchesSearch = !debouncedSearchTerm ||
                log.user?.name?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.user?.email?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.ip_address.includes(debouncedSearchTerm) ||
                log.location?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.browser?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
                log.platform?.toLowerCase().includes(debouncedSearchTerm.toLowerCase());

            // Role filter
            const matchesRole = selectedRole === 'all' || log.user?.role === selectedRole;

            // Status filter
            const matchesStatus = selectedStatus === 'all' ||
                (selectedStatus === 'success' && log.successful) ||
                (selectedStatus === 'failed' && !log.successful);

            // Device type filter
            const matchesDeviceType = selectedDeviceType === 'all' || log.device_type === selectedDeviceType;

            // Browser filter
            const matchesBrowser = selectedBrowser === 'all' || log.browser === selectedBrowser;

            // Date range filter
            const matchesDateRange = !dateRange?.from || !dateRange?.to || (() => {
                const loginDate = new Date(log.login_at);
                return loginDate >= dateRange.from! && loginDate <= dateRange.to!;
            })();

            return isNotCurrentUser && matchesSearch && matchesRole && matchesStatus && matchesDeviceType && matchesBrowser && matchesDateRange;
        });
    }, [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, dateRange, auth.user.id]);

    // Sort and filter recent logins (latest first)
    const filteredAndSortedRecentLogins = useMemo(() => {
        return filterLogs(recentLogins)
            .sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [recentLogins, filterLogs]);

    // Sort and filter suspicious activities (latest first)
    const filteredAndSortedSuspiciousActivities = useMemo(() => {
        return filterLogs(suspiciousActivities)
            .sort((a, b) => new Date(b.login_at).getTime() - new Date(a.login_at).getTime());
    }, [suspiciousActivities, filterLogs]);

    // Merge, sort, and paginate combined logs
    type CombinedLog = LoginLog & { category: 'recent' | 'suspicious' };
    const combinedFilteredAndSortedLogs: CombinedLog[] = useMemo(() => {
        const recentTagged = filteredAndSortedRecentLogins.map(l => ({ ...l, category: 'recent' as const }));
        const suspiciousTagged = filteredAndSortedSuspiciousActivities.map(l => ({ ...l, category: 'suspicious' as const }));
        let merged: CombinedLog[] = [...recentTagged, ...suspiciousTagged];
        if (selectedCategory !== 'all') {
            merged = merged.filter(l => l.category === selectedCategory);
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
        [...recentLogins, ...suspiciousActivities].forEach(log => {
            if (log.user?.role) roles.add(log.user.role);
        });
        return Array.from(roles).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueBrowsers = useMemo(() => {
        const browsers = new Set<string>();
        [...recentLogins, ...suspiciousActivities].forEach(log => {
            if (log.browser) browsers.add(log.browser);
        });
        return Array.from(browsers).sort();
    }, [recentLogins, suspiciousActivities]);

    const getUniqueDeviceTypes = useMemo(() => {
        const deviceTypes = new Set<string>();
        [...recentLogins, ...suspiciousActivities].forEach(log => {
            if (log.device_type) deviceTypes.add(log.device_type);
        });
        return Array.from(deviceTypes).sort();
    }, [recentLogins, suspiciousActivities]);

    // Check if any filters are active
    const hasActiveFilters = useMemo(() => {
        return debouncedSearchTerm !== '' ||
            selectedRole !== 'all' ||
            selectedStatus !== 'all' ||
            selectedDeviceType !== 'all' ||
            selectedBrowser !== 'all' ||
            selectedCategory !== 'all' ||
            dateRange?.from ||
            dateRange?.to;
    }, [debouncedSearchTerm, selectedRole, selectedStatus, selectedDeviceType, selectedBrowser, selectedCategory, dateRange]);

    // Helper function to highlight search terms
    const highlightSearchTerm = (text: string, searchTerm: string) => {
        if (!searchTerm || !text) return text;

        const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        const parts = text.split(regex);

        return parts.map((part, index) =>
            regex.test(part) ? (
                <span key={index} className="bg-yellow-200 dark:bg-yellow-800 px-0.5 rounded">
                    {part}
                </span>
            ) : part
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
        if (!role) return <span className="text-xs text-muted-foreground">-</span>;

        // Role-specific styling for better visual hierarchy
        const roleVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            'admin': 'destructive',
            'super_admin': 'destructive',
            'bac_secretariat': 'default',
            'bac_chairperson': 'default',
            'bac_member': 'secondary',
            'bac_technical_working_group': 'secondary',
            'procurement_officer': 'outline',
            'end_user': 'outline',
            'supplier': 'outline'
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
        return (
            <Badge variant={successful ? 'default' : 'destructive'}>
                {successful ? 'Success' : 'Failed'}
            </Badge>
        );
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

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Login Logs</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        Monitor user login activities and security events
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Logins</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.total_logins?.toLocaleString() || 0}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                            <Shield className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.total_logins > 0
                                    ? Math.round((statistics.successful_logins / statistics.total_logins) * 100)
                                    : 0
                                }%
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Today's Logins</CardTitle>
                            <CalendarIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.today_logins || 0}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Unique Users</CardTitle>
                            <User className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.unique_users || 0}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Enhanced Search and Filter Section */}
                <div className="space-y-4">
                    {/* Main Search Bar */}
                    <div className="flex flex-col sm:flex-row gap-4 w-full sm:items-center">
                        <div className="relative w-full sm:max-w-xl">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by name, email, IP address, location, browser..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full pl-10 pr-10"
                            />
                            {searchTerm && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSearchTerm('')}
                                    className="absolute right-2 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </div>

                        <div className="flex gap-2 sm:ml-auto">
                            <Button
                                variant="outline"
                                onClick={() => setShowAdvancedFilters(!showAdvancedFilters)}
                                className="whitespace-nowrap"
                            >
                                <Filter className="h-4 w-4 mr-2" />
                                Filters
                                <ChevronDown className={`h-4 w-4 ml-2 transition-transform ${showAdvancedFilters ? 'rotate-180' : ''}`} />
                            </Button>

                            {hasActiveFilters && (
                                <Button
                                    variant="outline"
                                    onClick={clearAllFilters}
                                    className="whitespace-nowrap"
                                >
                                    <X className="h-4 w-4 mr-2" />
                                    Clear All
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Quick Filter Presets */}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant={selectedCategory === 'suspicious' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setSelectedCategory(selectedCategory === 'suspicious' ? 'all' : 'suspicious')}
                        >
                            <AlertTriangle className="h-3 w-3 mr-1" />
                            Suspicious Only
                        </Button>
                        <Button
                            variant={selectedCategory === 'recent' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setSelectedCategory(selectedCategory === 'recent' ? 'all' : 'recent')}
                        >
                            Recent Only
                        </Button>
                        <Button
                            variant={selectedStatus === 'failed' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setSelectedStatus(selectedStatus === 'failed' ? 'all' : 'failed')}
                        >
                            <AlertTriangle className="h-3 w-3 mr-1" />
                            Failed Logins
                        </Button>
                        <Button
                            variant={selectedRole === 'admin' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setSelectedRole(selectedRole === 'admin' ? 'all' : 'admin')}
                        >
                            <Shield className="h-3 w-3 mr-1" />
                            Admin Users
                        </Button>
                        <Button
                            variant={selectedDeviceType === 'mobile' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setSelectedDeviceType(selectedDeviceType === 'mobile' ? 'all' : 'mobile')}
                        >
                            <Smartphone className="h-3 w-3 mr-1" />
                            Mobile Devices
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const today = new Date();
                                const startOfDay = new Date(today.setHours(0, 0, 0, 0));
                                const endOfDay = new Date(today.setHours(23, 59, 59, 999));
                                setDateRange({ from: startOfDay, to: endOfDay });
                            }}
                        >
                            <CalendarIcon className="h-3 w-3 mr-1" />
                            Today
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const today = new Date();
                                const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                                setDateRange({ from: weekAgo, to: today });
                            }}
                        >
                            <Clock className="h-3 w-3 mr-1" />
                            Last 7 Days
                        </Button>
                    </div>

                    {/* Advanced Filters */}
                    {showAdvancedFilters && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 p-4 bg-muted/30 rounded-lg border">
                            {/* Category Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Category</label>
                                <Select value={selectedCategory} onValueChange={(v) => setSelectedCategory(v as 'all' | 'recent' | 'suspicious')}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="recent">Recent</SelectItem>
                                        <SelectItem value="suspicious">Suspicious</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {/* Role Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Role</label>
                                <Select value={selectedRole} onValueChange={setSelectedRole}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="All roles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Roles</SelectItem>
                                        {getUniqueRoles.map(role => (
                                            <SelectItem key={role} value={role}>
                                                {role.replace('_', ' ').toUpperCase()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Status Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Status</label>
                                <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="success">Success</SelectItem>
                                        <SelectItem value="failed">Failed</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Device Type Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Device</label>
                                <Select value={selectedDeviceType} onValueChange={setSelectedDeviceType}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="All devices" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Devices</SelectItem>
                                        {getUniqueDeviceTypes.map(deviceType => (
                                            <SelectItem key={deviceType} value={deviceType}>
                                                {deviceType.charAt(0).toUpperCase() + deviceType.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Browser Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Browser</label>
                                <Select value={selectedBrowser} onValueChange={setSelectedBrowser}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="All browsers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Browsers</SelectItem>
                                        {getUniqueBrowsers.map(browser => (
                                            <SelectItem key={browser} value={browser}>
                                                {browser}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Date Range Filter */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-muted-foreground">Date Range</label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className={cn(
                                                'h-9 w-full justify-start text-left font-normal',
                                                !dateRange && 'text-muted-foreground'
                                            )}
                                        >
                                            <CalendarIcon className="mr-2 h-4 w-4" />
                                            {dateRange?.from ? (
                                                dateRange.to ? (
                                                    <>
                                                        {format(dateRange.from, 'LLL dd, y')} - {format(dateRange.to, 'LLL dd, y')}
                                                    </>
                                                ) : (
                                                    format(dateRange.from, 'LLL dd, y')
                                                )
                                            ) : (
                                                <span>Pick a date range</span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0" align="start">
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

                                {/* Quick Date Presets */}
                                <div className="flex flex-wrap gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setDateRangePreset('today')}
                                    >
                                        Today
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setDateRangePreset('last7days')}
                                    >
                                        Last 7 Days
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setDateRangePreset('last30days')}
                                    >
                                        Last 30 Days
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setDateRangePreset('thisMonth')}
                                    >
                                        This Month
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Active Filters Display */}
                    {hasActiveFilters && (
                        <div className="flex flex-wrap gap-2">
                            {selectedCategory !== 'all' && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Category: {selectedCategory.charAt(0).toUpperCase() + selectedCategory.slice(1)}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSelectedCategory('all')}
                                    />
                                </Badge>
                            )}
                            {debouncedSearchTerm && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Search: "{debouncedSearchTerm}"
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSearchTerm('')}
                                    />
                                </Badge>
                            )}
                            {selectedRole !== 'all' && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Role: {selectedRole.replace('_', ' ').toUpperCase()}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSelectedRole('all')}
                                    />
                                </Badge>
                            )}
                            {selectedStatus !== 'all' && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Status: {selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1)}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSelectedStatus('all')}
                                    />
                                </Badge>
                            )}
                            {selectedDeviceType !== 'all' && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Device: {selectedDeviceType.charAt(0).toUpperCase() + selectedDeviceType.slice(1)}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSelectedDeviceType('all')}
                                    />
                                </Badge>
                            )}
                            {selectedBrowser !== 'all' && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Browser: {selectedBrowser}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setSelectedBrowser('all')}
                                    />
                                </Badge>
                            )}                                    {(dateRange?.from || dateRange?.to) && (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    Date: {dateRange.from?.toLocaleDateString()} - {dateRange.to?.toLocaleDateString()}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => setDateRange(undefined)}
                                    />
                                </Badge>
                            )}
                        </div>
                    )}

                    {/* Search Results Summary */}
                    {hasActiveFilters && (
                        <div className="text-sm text-muted-foreground">
                            {selectedCategory === 'all'
                                ? (
                                    <>Found {filteredAndSortedRecentLogins.length} recent login{filteredAndSortedRecentLogins.length !== 1 ? 's' : ''} and {filteredAndSortedSuspiciousActivities.length} suspicious activit{filteredAndSortedSuspiciousActivities.length !== 1 ? 'ies' : 'y'}</>
                                ) : (
                                    <>Showing {combinedFilteredAndSortedLogs.length} {selectedCategory} record{combinedFilteredAndSortedLogs.length !== 1 ? 's' : ''}</>
                                )}
                        </div>
                    )}
                </div>

                {/* Login Logs - Unified Table */}
                <div className="flex-1 space-y-6">
                    <div className="pb-2">
                        <h2 className="text-lg md:text-xl font-semibold">All Login Activities</h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Combined recent logins and suspicious attempts ({combinedFilteredAndSortedLogs.length} total)
                        </p>
                    </div>
                    <div className="px-0 pb-0">
                        <div className="rounded-md border overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">Category</TableHead>
                                        <TableHead>User/Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>MFA</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>IP Address</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>Browser</TableHead>
                                        <TableHead className="pr-6">Time</TableHead>
                                        <TableHead className="pr-6">Session</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paginatedCombinedLogs.length > 0 ? (
                                        paginatedCombinedLogs.map((log) => (
                                            <TableRow key={`${log.category}-${log.id}`} className={log.category === 'suspicious' ? 'bg-destructive/5' : undefined}>
                                                <TableCell className="pl-6">
                                                    {log.category === 'suspicious' ? (
                                                        <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                                            <AlertTriangle className="h-3 w-3" /> Suspicious
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary" className="text-xs">Recent</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="font-medium">
                                                            {highlightSearchTerm(log.user?.name || 'Unknown User', debouncedSearchTerm)}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {highlightSearchTerm(log.user?.email || 'Unknown Email', debouncedSearchTerm)}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getRoleBadge(log.user?.role)}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-1">
                                                        {log.user?.mfa_enabled ? (
                                                            <Badge className="bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-2 py-1 text-xs border border-green-200 dark:border-green-800/30">
                                                                <QrCode className="mr-1 h-3 w-3" />
                                                                Enabled
                                                            </Badge>
                                                        ) : (
                                                            <Badge className="bg-gray-100 dark:bg-gray-800/50 text-gray-800 dark:text-gray-300 px-2 py-1 text-xs border border-gray-200 dark:border-gray-700/50">
                                                                Disabled
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(log.successful)}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        <Globe className="h-4 w-4 text-muted-foreground" />
                                                        <span className="font-mono text-sm">{highlightSearchTerm(log.ip_address, debouncedSearchTerm)}</span>
                                                    </div>
                                                    {log.location && (
                                                        <div className="flex items-center space-x-1 mt-1">
                                                            <MapPin className="h-3 w-3 text-muted-foreground" />
                                                            <span className="text-xs text-muted-foreground">{highlightSearchTerm(log.location, debouncedSearchTerm)}</span>
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        {getDeviceIcon(log.device_type)}
                                                        <div className="space-y-1">
                                                            <div className="text-sm">{log.device_type || 'Unknown'}</div>
                                                            {log.platform && (
                                                                <div className="text-xs text-muted-foreground">{highlightSearchTerm(log.platform, debouncedSearchTerm)}</div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">{highlightSearchTerm(log.browser || 'Unknown', debouncedSearchTerm)}</div>
                                                </TableCell>
                                                <TableCell className="pr-6">
                                                    <div className="flex items-center space-x-2">
                                                        <Clock className="h-4 w-4 text-muted-foreground" />
                                                        <span className="text-sm">{formatDateTime(log.login_at)}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-6">
                                                    {log.category === 'recent' ? (
                                                        <Badge variant={log.logout_at ? 'secondary' : 'default'}>
                                                            {getSessionDuration(log.login_at, log.logout_at)}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-center text-muted-foreground py-8">
                                                {searchTerm ? 'No activities match your search.' : 'No activities found.'}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="mt-4 px-6">
                            <Pagination
                                pageIndex={combinedPage - 1}
                                pageSize={pageSize}
                                pageCount={totalCombinedPages}
                                totalItems={combinedFilteredAndSortedLogs.length}
                                onPageChange={i => setCombinedPage(i + 1)}
                                onPageSizeChange={size => {
                                    setPageSize(size);
                                    setCombinedPage(1);
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
