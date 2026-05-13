import UserDetailsSheet from '@/components/admin/user-details-sheet';
import UserLoginHistorySheet from '@/components/admin/user-login-history-sheet';
import { HeroCard } from '@/components/hero-card';
import { Pagination } from '@/components/pagination';
import { StatsGrid } from '@/components/stats-grid';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import accounts from '@/routes/admin/accounts';
import { User, type BreadcrumbItem } from '@/types';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    AlertTriangle,
    Clock,
    Copy,
    Download,
    ExternalLink,
    History,
    MoreHorizontal,
    QrCode,
    RefreshCw,
    RotateCcw,
    Search,
    Shield,
    ShieldOff,
    Unlock,
    User as UserIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface PageProps extends InertiaPageProps {
    lockedAccounts: User[];
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: dashboard.url(),
    },
    {
        title: 'Locked Accounts',
        href: accounts.locked.url(),
    },
];

const roleFilterLabels: Record<string, string> = {
    all: 'All Roles',
    admin: 'Administrator',
    bac_chairman: 'BAC Chairman',
    bac_secretariat: 'BAC Secretariat',
    hope: 'HOPE',
};

const statusFilterLabels: Record<string, string> = {
    all: 'All Status',
    active: 'Active Lock',
    expired: 'Expired Lock',
};

export default function AdminLockedAccounts() {
    const { lockedAccounts, flash } = usePage<PageProps>().props;
    const { hasPermission } = usePermissions();
    const [isUnlockDialogOpen, setIsUnlockDialogOpen] = useState(false);
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);
    const [isProfileDialogOpen, setIsProfileDialogOpen] = useState(false);
    const [isLoginHistoryDialogOpen, setIsLoginHistoryDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [isLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [selectedAccounts, setSelectedAccounts] = useState<Set<number>>(new Set());
    const [isExporting, setIsExporting] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [roleFilter, setRoleFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    // Pagination state
    const [pageIndex, setPageIndex] = useState(0);
    const [pageSize, setPageSize] = useState(10);

    // Filter and search accounts
    const filteredAccounts = useMemo(() => {
        return lockedAccounts.filter((account) => {
            const matchesSearch =
                searchQuery === '' ||
                account.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                account.email.toLowerCase().includes(searchQuery.toLowerCase());

            const matchesRole = roleFilter === 'all' || account.role === roleFilter;

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && account.is_currently_locked) ||
                (statusFilter === 'expired' && !account.is_currently_locked);

            return matchesSearch && matchesRole && matchesStatus;
        });
    }, [lockedAccounts, searchQuery, roleFilter, statusFilter]);

    const pageCount = Math.ceil(filteredAccounts.length / pageSize);
    const paginatedAccounts = filteredAccounts.slice(pageIndex * pageSize, (pageIndex + 1) * pageSize);

    // Handle flash messages
    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info) toast.info(flash.info);
    }, [flash]);

    // Auto-refresh functionality using Inertia's usePoll
    const { stop, start } = usePoll(
        30000,
        {
            only: ['lockedAccounts'],
            onStart: () => setIsRefreshing(true),
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Locked accounts refreshed');
            },
        },
        {
            autoStart: false,
            keepAlive: false, // Throttle by 90% when tab is in background
        },
    );

    // Start/stop polling based on autoRefresh toggle
    useEffect(() => {
        if (autoRefresh) {
            start();
        } else {
            stop();
        }
        return () => stop();
    }, [autoRefresh, start, stop]);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['lockedAccounts'],
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Locked accounts refreshed');
            },
        });
    };

    // Export to CSV
    const exportToCSV = () => {
        setIsExporting(true);
        try {
            const accountsToExport = selectedAccounts.size > 0 ? filteredAccounts.filter((a) => selectedAccounts.has(a.id)) : filteredAccounts;

            const headers = [
                'Name',
                'Email',
                'Role',
                '2FA Status',
                'Lock Status',
                'Failed Attempts',
                'Locked At',
                'Expires At',
                'Reason',
                'Time Remaining',
            ];
            const rows = accountsToExport.map((account) => [
                account.name,
                account.email,
                getRoleDisplayName(account.role),
                account.two_factor_enabled ? 'Enabled' : 'Disabled',
                account.is_currently_locked ? 'Active Lock' : 'Expired Lock',
                account.failed_login_attempts || 0,
                formatDateTime(account.locked_at),
                formatDateTime(account.lock_expires_at),
                account.locked_reason || 'N/A',
                account.is_currently_locked ? account.lock_time_remaining || '—' : 'Expired',
            ]);

            const csvContent = [headers, ...rows].map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `locked-accounts-${format(new Date(), 'yyyy-MM-dd-HHmmss')}.csv`;
            link.click();

            toast.success(`Exported ${accountsToExport.length} locked account(s)`);
        } catch (error) {
            toast.error('Failed to export data');
            console.error('Export error:', error);
        } finally {
            setIsExporting(false);
        }
    };

    // Bulk selection
    const toggleSelectAll = () => {
        if (selectedAccounts.size === paginatedAccounts.length) {
            setSelectedAccounts(new Set());
        } else {
            setSelectedAccounts(new Set(paginatedAccounts.map((a) => a.id)));
        }
    };

    const toggleAccountSelection = (accountId: number) => {
        const newSelection = new Set(selectedAccounts);
        if (newSelection.has(accountId)) {
            newSelection.delete(accountId);
        } else {
            newSelection.add(accountId);
        }
        setSelectedAccounts(newSelection);
    };

    const handleBulkUnlock = () => {
        if (selectedAccounts.size === 0) {
            toast.warning('No accounts selected');
            return;
        }

        const accountIds = Array.from(selectedAccounts);
        router.post(
            accounts.bulkUnlock.url(),
            { account_ids: accountIds },
            {
                // Reload locked accounts data to sync across tabs/windows
                only: ['lockedAccounts'],
                onSuccess: () => {
                    setSelectedAccounts(new Set());
                },
                onError: () => {
                    // Error toast handled by flash message from server
                },
                preserveScroll: true,
            },
        );
    };

    const handleBulkResetAttempts = () => {
        if (selectedAccounts.size === 0) {
            toast.warning('No accounts selected');
            return;
        }

        const accountIds = Array.from(selectedAccounts);
        router.post(
            accounts.bulkResetAttempts.url(),
            { account_ids: accountIds },
            {
                // Reload locked accounts data to sync across tabs/windows
                only: ['lockedAccounts'],
                onSuccess: () => {
                    setSelectedAccounts(new Set());
                },
                onError: () => {
                    // Error toast handled by flash message from server
                },
                preserveScroll: true,
            },
        );
    };

    const handleUnlockAccount = (user: User) => {
        setSelectedUser(user);
        setIsUnlockDialogOpen(true);
    };

    const handleResetAttempts = (user: User) => {
        setSelectedUser(user);
        setIsResetDialogOpen(true);
    };

    const confirmUnlockAccount = () => {
        if (!selectedUser) return;
        router.post(
            accounts.unlock.url(selectedUser.id),
            { reason: 'Unlocked by administrator' },
            {
                // Reload locked accounts data to sync across tabs/windows
                only: ['lockedAccounts'],
                onSuccess: () => {
                    setIsUnlockDialogOpen(false);
                    setSelectedUser(null);
                },
                onError: () => {
                    // Error toast handled by flash message from server
                },
                preserveScroll: true,
            },
        );
    };

    const confirmResetAttempts = () => {
        if (!selectedUser) return;
        router.post(
            accounts.resetAttempts.url(selectedUser.id),
            {},
            {
                // Reload locked accounts data to sync across tabs/windows
                only: ['lockedAccounts'],
                onSuccess: () => {
                    setIsResetDialogOpen(false);
                    setSelectedUser(null);
                },
                onError: () => {
                    // Error toast handled by flash message from server
                },
                preserveScroll: true,
            },
        );
    };

    const getRoleBadgeColor = (role: string) => {
        switch (role) {
            case 'admin':
                return 'bg-destructive/10 text-destructive hover:bg-destructive/20';
            case 'bac_chairman':
                return 'bg-accent/10 text-accent-foreground hover:bg-accent/20';
            case 'hope':
                return 'bg-success/10 text-success hover:bg-success/20';
            case 'bac_secretariat':
                return 'bg-warning/10 text-warning hover:bg-warning/20';
            default:
                return 'bg-muted text-muted-foreground hover:bg-accent/5';
        }
    };

    const getRoleDisplayName = (role: string) => {
        switch (role) {
            case 'bac_secretariat':
                return 'BAC Secretariat';
            case 'bac_chairman':
                return 'BAC Chairman';
            case 'hope':
                return 'HOPE';
            case 'admin':
                return 'Administrator';
            default:
                return role;
        }
    };

    const formatDateTime = (dateString: string | null | undefined) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString();
    };

    const getLockStatusColor = (user: User) => {
        if (user.is_currently_locked) return 'bg-destructive/10 text-destructive border-destructive/50';
        return 'bg-warning/10 text-warning border-warning/50';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locked Accounts - Admin" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Header Section */}
                <HeroCard
                    icon={Shield}
                    title="Locked Accounts"
                    description="Manage user accounts that have been locked due to security reasons"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={handleRefresh} disabled={isRefreshing} variant="outline" className="flex items-center gap-2">
                                <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                <span className="hidden sm:inline">Refresh</span>
                            </Button>
                            <Button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                variant={autoRefresh ? 'default' : 'outline'}
                                className="flex items-center gap-2"
                            >
                                <Clock className="h-4 w-4" />
                                <span className="hidden sm:inline">Auto</span>
                            </Button>
                            <Button onClick={exportToCSV} disabled={isExporting} variant="outline" className="flex items-center gap-2">
                                <Download className={`h-4 w-4 ${isExporting ? 'animate-pulse' : ''}`} />
                                <span className="hidden sm:inline">Export</span>
                            </Button>
                            {selectedAccounts.size > 0 && hasPermission('manage users') && (
                                <>
                                    <Button onClick={handleBulkUnlock} variant="default" className="flex items-center gap-2">
                                        <Unlock className="h-4 w-4" />
                                        <span className="hidden sm:inline">Unlock ({selectedAccounts.size})</span>
                                        <span className="sm:hidden">{selectedAccounts.size}</span>
                                    </Button>
                                    <Button onClick={handleBulkResetAttempts} variant="outline" className="flex items-center gap-2">
                                        <RotateCcw className="h-4 w-4" />
                                        <span className="hidden sm:inline">Reset ({selectedAccounts.size})</span>
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {/* Search and Filter Section */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center">
                            <div className="relative min-w-0 flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search by name or email..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Select value={roleFilter} onValueChange={(value) => value && setRoleFilter(value)}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="Filter by role">
                                            {() => roleFilterLabels[roleFilter] ?? 'Filter by role'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">All Roles</SelectItem>
                                            <SelectItem value="admin">Administrator</SelectItem>
                                            <SelectItem value="bac_chairman">BAC Chairman</SelectItem>
                                            <SelectItem value="bac_secretariat">BAC Secretariat</SelectItem>
                                            <SelectItem value="hope">HOPE</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Select value={statusFilter} onValueChange={(value) => value && setStatusFilter(value)}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="Filter by status">
                                            {() => statusFilterLabels[statusFilter] ?? 'Filter by status'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="active">Active Lock</SelectItem>
                                            <SelectItem value="expired">Expired Lock</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        {(searchQuery || roleFilter !== 'all' || statusFilter !== 'all') && (
                            <div className="text-muted-foreground mt-3 text-sm">
                                Showing {filteredAccounts.length} of {lockedAccounts.length} account(s)
                                {selectedAccounts.size > 0 && ` • ${selectedAccounts.size} selected`}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Error Display */}
                {flash.error && (
                    <Card className="border-destructive/50 bg-destructive/10">
                        <CardContent className="p-4">
                            <div className="text-destructive flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5" />
                                <span>{flash.error}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Statistics Cards */}
                <StatsGrid
                    items={[
                        {
                            label: 'Total Locked Accounts',
                            value: filteredAccounts.length,
                            icon: ShieldOff,
                            iconClassName: 'bg-destructive/10 text-destructive',
                        },
                        {
                            label: 'Currently Locked',
                            value: filteredAccounts.filter((user) => user.is_currently_locked).length,
                            icon: AlertTriangle,
                            iconClassName: 'bg-warning/10 text-warning',
                        },
                        {
                            label: 'Total Failed Attempts',
                            value: filteredAccounts.reduce((sum, user) => sum + (user.failed_login_attempts || 0), 0),
                            icon: Clock,
                            iconClassName: 'bg-muted/50 text-muted-foreground',
                        },
                    ]}
                />

                {/* Lock Activity Insights */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base font-medium">
                            <span>Lock Activity Insights</span>
                            <Shield className="text-muted-foreground h-5 w-5" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-2">
                            <div className="text-muted-foreground text-sm">High-Risk Accounts</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold">
                                    {filteredAccounts.filter((u) => (u.failed_login_attempts || 0) >= 3).length}
                                </span>
                                <Badge variant="destructive" className="text-xs">
                                    3+ attempts
                                </Badge>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <div className="text-muted-foreground text-sm">Accounts with 2FA</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold">{filteredAccounts.filter((u) => u.two_factor_enabled).length}</span>
                                <Badge variant="outline" className="bg-success/10 text-success border-success/50 text-xs">
                                    Protected
                                </Badge>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <div className="text-muted-foreground text-sm">Expiring Soon</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold">
                                    {
                                        filteredAccounts.filter((u) => {
                                            if (!u.is_currently_locked || !u.lock_time_remaining) return false;
                                            return u.lock_time_remaining.includes('minute') || u.lock_time_remaining.includes('second');
                                        }).length
                                    }
                                </span>
                                <Badge variant="outline" className="text-xs">
                                    {'<'}1 hour
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Locked Accounts Table - Desktop */}
                {filteredAccounts.length === 0 ? (
                    <Card>
                        <CardContent className="flex justify-center px-6 py-12">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Shield className="h-8 w-8" />
                                    </EmptyMedia>
                                </EmptyHeader>
                                <EmptyTitle>No Locked Accounts Found</EmptyTitle>
                                <EmptyDescription>
                                    {searchQuery || roleFilter !== 'all' || statusFilter !== 'all'
                                        ? 'Try adjusting your search or filter criteria.'
                                        : 'There are currently no locked user accounts in the system.'}
                                </EmptyDescription>
                            </Empty>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="hidden md:block">
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            <Checkbox
                                                checked={selectedAccounts.size === paginatedAccounts.length && paginatedAccounts.length > 0}
                                                onCheckedChange={toggleSelectAll}
                                            />
                                        </TableHead>
                                        <TableHead>User</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead className="hidden lg:table-cell">2FA Status</TableHead>
                                        <TableHead>Lock Status</TableHead>
                                        <TableHead>Failed Attempts</TableHead>
                                        <TableHead className="hidden xl:table-cell">Locked At</TableHead>
                                        <TableHead className="hidden xl:table-cell">Expires At</TableHead>
                                        <TableHead className="hidden lg:table-cell">Time Remaining</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {isLoading || isRefreshing
                                        ? Array.from({ length: 5 }).map((_, index) => (
                                              <TableRow key={index}>
                                                  <TableCell>
                                                      <Skeleton className="h-4 w-4" />
                                                  </TableCell>
                                                  <TableCell>
                                                      <div className="flex items-center space-x-3">
                                                          <Skeleton className="h-8 w-8 rounded-full" />
                                                          <div className="space-y-2">
                                                              <Skeleton className="h-4 w-32" />
                                                              <Skeleton className="h-3 w-48" />
                                                          </div>
                                                      </div>
                                                  </TableCell>
                                                  <TableCell>
                                                      <Skeleton className="h-5 w-20" />
                                                  </TableCell>
                                                  <TableCell className="hidden lg:table-cell">
                                                      <Skeleton className="h-5 w-16" />
                                                  </TableCell>
                                                  <TableCell>
                                                      <Skeleton className="h-5 w-24" />
                                                  </TableCell>
                                                  <TableCell>
                                                      <Skeleton className="h-4 w-8" />
                                                  </TableCell>
                                                  <TableCell className="hidden xl:table-cell">
                                                      <Skeleton className="h-4 w-32" />
                                                  </TableCell>
                                                  <TableCell className="hidden xl:table-cell">
                                                      <Skeleton className="h-4 w-32" />
                                                  </TableCell>
                                                  <TableCell className="hidden lg:table-cell">
                                                      <Skeleton className="h-4 w-24" />
                                                  </TableCell>
                                                  <TableCell>
                                                      <Skeleton className="h-8 w-8 rounded" />
                                                  </TableCell>
                                              </TableRow>
                                          ))
                                        : paginatedAccounts.map((user) => (
                                              <TableRow key={user.id}>
                                                  <TableCell>
                                                      <Checkbox
                                                          checked={selectedAccounts.has(user.id)}
                                                          onCheckedChange={() => toggleAccountSelection(user.id)}
                                                      />
                                                  </TableCell>
                                                  <TableCell>
                                                      <div className="flex items-center space-x-3">
                                                          <div className="bg-muted flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                                              <UserIcon className="text-muted-foreground h-4 w-4" />
                                                          </div>
                                                          <div className="min-w-0">
                                                              <div className="truncate font-medium">{user.name}</div>
                                                              <div className="text-muted-foreground truncate text-sm">{user.email}</div>
                                                          </div>
                                                      </div>
                                                  </TableCell>
                                                  <TableCell>
                                                      <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                                                  </TableCell>
                                                  <TableCell className="hidden lg:table-cell">
                                                      <div className="flex items-center space-x-1">
                                                          {user.two_factor_enabled ? (
                                                              <Badge className="bg-success/10 dark:bg-success/20 text-success dark:text-success-foreground border-success/50 dark:border-success/30 border px-2 py-1 text-xs">
                                                                  <QrCode className="mr-1 h-3 w-3" />
                                                                  Enabled
                                                              </Badge>
                                                          ) : (
                                                              <Badge className="bg-muted dark:bg-muted/50 text-muted-foreground dark:text-muted-foreground/80 border-muted/50 dark:border-muted/30 border px-2 py-1 text-xs">
                                                                  Disabled
                                                              </Badge>
                                                          )}
                                                      </div>
                                                  </TableCell>
                                                  <TableCell>
                                                      <Badge variant="outline" className={getLockStatusColor(user)}>
                                                          {user.is_currently_locked ? 'Active Lock' : 'Expired Lock'}
                                                      </Badge>
                                                  </TableCell>
                                                  <TableCell>
                                                      <div className="flex items-center space-x-2">
                                                          <span className="text-sm font-medium">{user.failed_login_attempts || 0}</span>
                                                          {(user.failed_login_attempts || 0) >= 3 && (
                                                              <AlertTriangle className="text-destructive h-4 w-4" />
                                                          )}
                                                      </div>
                                                  </TableCell>
                                                  <TableCell className="hidden xl:table-cell">
                                                      <div className="text-sm">{formatDateTime(user.locked_at)}</div>
                                                  </TableCell>
                                                  <TableCell className="hidden xl:table-cell">
                                                      <div className="text-sm">{formatDateTime(user.lock_expires_at)}</div>
                                                  </TableCell>
                                                  <TableCell className="hidden lg:table-cell">
                                                      <div className="text-sm">
                                                          {user.is_currently_locked ? (
                                                              <div className="text-warning flex items-center space-x-1">
                                                                  <Clock className="h-3 w-3" />
                                                                  <span>{user.lock_time_remaining ?? '—'}</span>
                                                              </div>
                                                          ) : (
                                                              <span className="text-muted-foreground">Expired</span>
                                                          )}
                                                      </div>
                                                  </TableCell>
                                                  <TableCell className="text-right">
                                                      <DropdownMenu>
                                                          <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
                                                              <MoreHorizontal className="h-4 w-4" />
                                                          </DropdownMenuTrigger>
                                                          <DropdownMenuContent align="end">
                                                              <div className="text-muted-foreground px-1.5 py-1 text-xs font-medium">Actions</div>
                                                              <DropdownMenuSeparator />
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      navigator.clipboard.writeText(user.email);
                                                                      toast.success('Email copied to clipboard');
                                                                  }}
                                                              >
                                                                  <Copy className="mr-2 h-4 w-4" />
                                                                  Copy Email
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      setSelectedUser(user);
                                                                      setIsProfileDialogOpen(true);
                                                                  }}
                                                              >
                                                                  <ExternalLink className="mr-2 h-4 w-4" />
                                                                  View Profile
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      setSelectedUser(user);
                                                                      setIsLoginHistoryDialogOpen(true);
                                                                  }}
                                                              >
                                                                  <History className="mr-2 h-4 w-4" />
                                                                  Login History
                                                              </DropdownMenuItem>
                                                              <DropdownMenuSeparator />
                                                              {user.is_currently_locked && hasPermission('manage users') && (
                                                                  <DropdownMenuItem
                                                                      onClick={() => handleUnlockAccount(user)}
                                                                      className="text-success"
                                                                  >
                                                                      <Unlock className="mr-2 h-4 w-4" />
                                                                      Unlock Account
                                                                  </DropdownMenuItem>
                                                              )}
                                                              {hasPermission('manage users') && (
                                                                  <DropdownMenuItem onClick={() => handleResetAttempts(user)}>
                                                                      <RotateCcw className="mr-2 h-4 w-4" />
                                                                      Reset Attempts
                                                                  </DropdownMenuItem>
                                                              )}
                                                          </DropdownMenuContent>
                                                      </DropdownMenu>
                                                  </TableCell>
                                              </TableRow>
                                          ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                        <CardFooter className="justify-end border-t px-6 py-5">
                            <Pagination
                                pageIndex={pageIndex}
                                pageSize={pageSize}
                                pageCount={pageCount}
                                totalItems={filteredAccounts.length}
                                onPageChange={setPageIndex}
                                onPageSizeChange={(size) => {
                                    setPageSize(size);
                                    setPageIndex(0);
                                }}
                            />
                        </CardFooter>
                    </Card>
                )}

                {/* Mobile Card View */}
                {filteredAccounts.length > 0 && (
                    <div className="md:hidden">
                        <div className="space-y-4">
                            {isLoading || isRefreshing
                                ? Array.from({ length: 3 }).map((_, index) => (
                                      <Card key={index}>
                                          <CardContent className="p-4">
                                              <div className="space-y-4">
                                                  <div className="flex items-start justify-between">
                                                      <div className="flex items-center space-x-3">
                                                          <Skeleton className="h-10 w-10 rounded-full" />
                                                          <div className="space-y-2">
                                                              <Skeleton className="h-4 w-32" />
                                                              <Skeleton className="h-3 w-48" />
                                                          </div>
                                                      </div>
                                                      <Skeleton className="h-8 w-8" />
                                                  </div>
                                                  <div className="space-y-2">
                                                      <Skeleton className="h-4 w-full" />
                                                      <Skeleton className="h-4 w-full" />
                                                      <Skeleton className="h-4 w-2/3" />
                                                  </div>
                                              </div>
                                          </CardContent>
                                      </Card>
                                  ))
                                : paginatedAccounts.map((user) => (
                                      <Card key={user.id}>
                                          <CardContent className="p-4">
                                              <div className="space-y-4">
                                                  {/* Header */}
                                                  <div className="flex items-start justify-between">
                                                      <div className="flex items-center space-x-3">
                                                          <Checkbox
                                                              checked={selectedAccounts.has(user.id)}
                                                              onCheckedChange={() => toggleAccountSelection(user.id)}
                                                          />
                                                          <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                                                              <UserIcon className="text-muted-foreground h-5 w-5" />
                                                          </div>
                                                          <div>
                                                              <div className="font-medium">{user.name}</div>
                                                              <div className="text-muted-foreground text-sm">{user.email}</div>
                                                          </div>
                                                      </div>
                                                      <DropdownMenu>
                                                          <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
                                                              <MoreHorizontal className="h-4 w-4" />
                                                          </DropdownMenuTrigger>
                                                          <DropdownMenuContent align="end">
                                                              <div className="text-muted-foreground px-1.5 py-1 text-xs font-medium">Actions</div>
                                                              <DropdownMenuSeparator />
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      navigator.clipboard.writeText(user.email);
                                                                      toast.success('Email copied to clipboard');
                                                                  }}
                                                              >
                                                                  <Copy className="mr-2 h-4 w-4" />
                                                                  Copy Email
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      setSelectedUser(user);
                                                                      setIsProfileDialogOpen(true);
                                                                  }}
                                                              >
                                                                  <ExternalLink className="mr-2 h-4 w-4" />
                                                                  View Profile
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem
                                                                  onClick={() => {
                                                                      setSelectedUser(user);
                                                                      setIsLoginHistoryDialogOpen(true);
                                                                  }}
                                                              >
                                                                  <History className="mr-2 h-4 w-4" />
                                                                  Login History
                                                              </DropdownMenuItem>
                                                              <DropdownMenuSeparator />
                                                              {user.is_currently_locked && hasPermission('manage users') && (
                                                                  <DropdownMenuItem
                                                                      onClick={() => handleUnlockAccount(user)}
                                                                      className="text-success"
                                                                  >
                                                                      <Unlock className="mr-2 h-4 w-4" />
                                                                      Unlock Account
                                                                  </DropdownMenuItem>
                                                              )}
                                                              {hasPermission('manage users') && (
                                                                  <DropdownMenuItem onClick={() => handleResetAttempts(user)}>
                                                                      <RotateCcw className="mr-2 h-4 w-4" />
                                                                      Reset Attempts
                                                                  </DropdownMenuItem>
                                                              )}
                                                          </DropdownMenuContent>
                                                      </DropdownMenu>
                                                  </div>

                                                  {/* Details */}
                                                  <div className="space-y-2 text-sm">
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Role</span>
                                                          <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">2FA Status</span>
                                                          {user.two_factor_enabled ? (
                                                              <Badge className="bg-success/10 text-success border-success/50 border px-2 py-1 text-xs">
                                                                  <QrCode className="mr-1 h-3 w-3" />
                                                                  Enabled
                                                              </Badge>
                                                          ) : (
                                                              <Badge className="bg-muted text-muted-foreground border-muted/50 border px-2 py-1 text-xs">
                                                                  Disabled
                                                              </Badge>
                                                          )}
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Lock Status</span>
                                                          <Badge variant="outline" className={getLockStatusColor(user)}>
                                                              {user.is_currently_locked ? 'Active Lock' : 'Expired Lock'}
                                                          </Badge>
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Failed Attempts</span>
                                                          <div className="flex items-center space-x-2">
                                                              <span className="font-medium">{user.failed_login_attempts || 0}</span>
                                                              {(user.failed_login_attempts || 0) >= 3 && (
                                                                  <AlertTriangle className="text-destructive h-4 w-4" />
                                                              )}
                                                          </div>
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Locked At</span>
                                                          <span>{formatDateTime(user.locked_at)}</span>
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Expires At</span>
                                                          <span>{formatDateTime(user.lock_expires_at)}</span>
                                                      </div>
                                                      <div className="flex items-center justify-between">
                                                          <span className="text-muted-foreground">Time Remaining</span>
                                                          {user.is_currently_locked ? (
                                                              <div className="text-warning flex items-center space-x-1">
                                                                  <Clock className="h-3 w-3" />
                                                                  <span>{user.lock_time_remaining ?? '—'}</span>
                                                              </div>
                                                          ) : (
                                                              <span className="text-muted-foreground">Expired</span>
                                                          )}
                                                      </div>
                                                      {user.locked_reason && (
                                                          <div className="bg-muted/50 mt-2 rounded-md p-2">
                                                              <div className="text-muted-foreground mb-1 text-xs font-medium">Lock Reason</div>
                                                              <div className="text-sm">{user.locked_reason}</div>
                                                          </div>
                                                      )}
                                                  </div>
                                              </div>
                                          </CardContent>
                                      </Card>
                                  ))}
                        </div>
                        <div className="mt-4 flex justify-center">
                            <Pagination
                                pageIndex={pageIndex}
                                pageSize={pageSize}
                                pageCount={pageCount}
                                totalItems={filteredAccounts.length}
                                onPageChange={setPageIndex}
                                onPageSizeChange={(size) => {
                                    setPageSize(size);
                                    setPageIndex(0);
                                }}
                            />
                        </div>
                    </div>
                )}
            </div>
            {/* Unlock Account Dialog */}
            <AlertDialog open={isUnlockDialogOpen} onOpenChange={setIsUnlockDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Unlock Account</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to unlock the account for <strong>{selectedUser?.name}</strong>? This will immediately allow them to
                            log in again and reset their failed login attempts.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmUnlockAccount} className="bg-success text-success-foreground hover:bg-success/90">
                            Unlock Account
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            {/* Reset Attempts Dialog */}
            <AlertDialog open={isResetDialogOpen} onOpenChange={setIsResetDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Reset Failed Login Attempts</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to reset the failed login attempts for <strong>{selectedUser?.name}</strong>? This will set their
                            failed login attempts back to 0.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmResetAttempts}>Reset Attempts</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* User Details Sheet */}
            <UserDetailsSheet open={isProfileDialogOpen} onOpenChange={setIsProfileDialogOpen} user={selectedUser} />

            {/* Login History Sheet */}
            <UserLoginHistorySheet
                open={isLoginHistoryDialogOpen}
                onOpenChange={setIsLoginHistoryDialogOpen}
                userId={selectedUser?.id ?? null}
                userName={selectedUser?.name}
            />
        </AppLayout>
    );
}
