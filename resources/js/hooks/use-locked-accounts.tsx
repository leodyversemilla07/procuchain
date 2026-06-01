import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes/admin';
import accounts from '@/routes/admin/accounts';
import { User, type BreadcrumbItem } from '@/types';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { router, usePage, usePoll } from '@inertiajs/react';
import { format } from 'date-fns';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// =============================================================================
// Types
// =============================================================================

export interface LockedAccountsPageProps extends InertiaPageProps {
    lockedAccounts: User[];
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

// =============================================================================
// Constants
// =============================================================================

export const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: dashboard.url(),
    },
    {
        title: 'Locked Accounts',
        href: accounts.locked.url(),
    },
];

export const roleFilterLabels: Record<string, string> = {
    all: 'All Roles',
    admin: 'Administrator',
    bac_chairman: 'BAC Chairman',
    bac_secretariat: 'BAC Secretariat',
    hope: 'HOPE',
};

export const statusFilterLabels: Record<string, string> = {
    all: 'All Status',
    active: 'Active Lock',
    expired: 'Expired Lock',
};

// =============================================================================
// Helpers
// =============================================================================

export function getRoleBadgeColor(role: string) {
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
}

export function getRoleDisplayName(role: string) {
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
}

export function formatDateTime(dateString: string | null | undefined) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
}

export function getLockStatusColor(user: User) {
    if (user.is_currently_locked) return 'bg-destructive/10 text-destructive border-destructive/50';
    return 'bg-warning/10 text-warning border-warning/50';
}

// =============================================================================
// Hook
// =============================================================================

export function useLockedAccounts() {
    const { lockedAccounts, flash } = usePage<LockedAccountsPageProps>().props;
    const { hasPermission } = usePermissions();

    // Dialog states
    const [isUnlockDialogOpen, setIsUnlockDialogOpen] = useState(false);
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);
    const [isProfileDialogOpen, setIsProfileDialogOpen] = useState(false);
    const [isLoginHistoryDialogOpen, setIsLoginHistoryDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // UI states
    const [isLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [selectedAccounts, setSelectedAccounts] = useState<Set<number>>(new Set());
    const [isExporting, setIsExporting] = useState(false);

    // Filter states
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
            keepAlive: false,
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

    const handleRefresh = useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            only: ['lockedAccounts'],
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Locked accounts refreshed');
            },
        });
    }, []);

    // Export to CSV
    const exportToCSV = useCallback(() => {
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
    }, [filteredAccounts, selectedAccounts]);

    // Bulk selection
    const toggleSelectAll = useCallback(() => {
        if (selectedAccounts.size === paginatedAccounts.length) {
            setSelectedAccounts(new Set());
        } else {
            setSelectedAccounts(new Set(paginatedAccounts.map((a) => a.id)));
        }
    }, [selectedAccounts, paginatedAccounts]);

    const toggleAccountSelection = useCallback((accountId: number) => {
        setSelectedAccounts((prev) => {
            const newSelection = new Set(prev);
            if (newSelection.has(accountId)) {
                newSelection.delete(accountId);
            } else {
                newSelection.add(accountId);
            }
            return newSelection;
        });
    }, []);

    const handleBulkUnlock = useCallback(() => {
        if (selectedAccounts.size === 0) {
            toast.warning('No accounts selected');
            return;
        }

        const accountIds = Array.from(selectedAccounts);
        router.post(
            accounts.bulkUnlock.url(),
            { account_ids: accountIds },
            {
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
    }, [selectedAccounts]);

    const handleBulkResetAttempts = useCallback(() => {
        if (selectedAccounts.size === 0) {
            toast.warning('No accounts selected');
            return;
        }

        const accountIds = Array.from(selectedAccounts);
        router.post(
            accounts.bulkResetAttempts.url(),
            { account_ids: accountIds },
            {
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
    }, [selectedAccounts]);

    const handleUnlockAccount = useCallback((user: User) => {
        setSelectedUser(user);
        setIsUnlockDialogOpen(true);
    }, []);

    const handleResetAttempts = useCallback((user: User) => {
        setSelectedUser(user);
        setIsResetDialogOpen(true);
    }, []);

    const confirmUnlockAccount = useCallback(() => {
        if (!selectedUser) return;
        router.post(
            accounts.unlock.url(selectedUser.id),
            { reason: 'Unlocked by administrator' },
            {
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
    }, [selectedUser]);

    const confirmResetAttempts = useCallback(() => {
        if (!selectedUser) return;
        router.post(
            accounts.resetAttempts.url(selectedUser.id),
            {},
            {
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
    }, [selectedUser]);

    return {
        // Data
        lockedAccounts,
        flash,
        filteredAccounts,
        paginatedAccounts,
        // Filter states
        searchQuery,
        setSearchQuery,
        roleFilter,
        setRoleFilter,
        statusFilter,
        setStatusFilter,
        // Pagination
        pageIndex,
        setPageIndex,
        pageSize,
        setPageSize,
        pageCount,
        // UI states
        isLoading,
        isRefreshing,
        autoRefresh,
        setAutoRefresh,
        selectedAccounts,
        isExporting,
        // Dialog states
        isUnlockDialogOpen,
        setIsUnlockDialogOpen,
        isResetDialogOpen,
        setIsResetDialogOpen,
        isProfileDialogOpen,
        setIsProfileDialogOpen,
        isLoginHistoryDialogOpen,
        setIsLoginHistoryDialogOpen,
        selectedUser,
        setSelectedUser,
        // Permissions
        hasPermission,
        // Actions
        handleRefresh,
        exportToCSV,
        toggleSelectAll,
        toggleAccountSelection,
        handleBulkUnlock,
        handleBulkResetAttempts,
        handleUnlockAccount,
        handleResetAttempts,
        confirmUnlockAccount,
        confirmResetAttempts,
    };
}
