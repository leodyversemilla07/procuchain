import { getUserColumns } from '@/components/user-management/user-columns';
import { usePermissions } from '@/hooks/use-permissions';
import admin from '@/routes/admin';
import type { User } from '@/types/user';
import { getRoleDisplayName } from '@/types/user';
import { router, usePage, usePoll } from '@inertiajs/react';
import {
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnFiltersState,
    type RowSelectionState,
    type SortingState,
    type VisibilityState,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface PageProps {
    users: User[];
    roles: string[];
    [key: string]: unknown;
}

const formatDate = (dateValue: string | undefined) => {
    if (!dateValue || dateValue === '' || dateValue === 'null' || dateValue === 'undefined') return null;
    const date = new Date(dateValue);
    return isNaN(date.getTime()) ? null : date;
};

export function useUserManagement() {
    const page = usePage<PageProps>();
    const { users, roles } = page.props;
    const { hasPermission } = usePermissions();

    // Modal state
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isBulkDeleteDialogOpen, setIsBulkDeleteDialogOpen] = useState(false);
    const [isDetailsDialogOpen, setIsDetailsDialogOpen] = useState(false);
    const [isLoginHistoryDialogOpen, setIsLoginHistoryDialogOpen] = useState(false);
    const [isResetPasswordDialogOpen, setIsResetPasswordDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [userToDelete, setUserToDelete] = useState<User | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        role: '',
        password: '',
        password_confirmation: '',
    });

    // UI state
    const [isLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [roleFilter, setRoleFilter] = useState<string>('all');
    const [verificationFilter, setVerificationFilter] = useState<string>('all');
    const [twoFactorFilter, setTwoFactorFilter] = useState<string>('all');
    const [activeQuickFilter, setActiveQuickFilter] = useState<string | null>(null);

    // Table state
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({
        blockchain_address: false,
        created_at: false,
        updated_at: false,
    });
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    const resetForm = () => {
        setFormData({ name: '', email: '', role: '', password: '', password_confirmation: '' });
    };

    // Auto-refresh
    const { stop, start } = usePoll(
        30000,
        {
            only: ['users'],
            onStart: () => setIsRefreshing(true),
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Users refreshed');
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

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['users'],
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Users refreshed');
            },
        });
    };

    // Filtering
    const filteredUsers = useMemo(() => {
        return users.filter((user) => {
            const matchesSearch =
                searchQuery === '' ||
                user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (user.blockchain_address && user.blockchain_address.toLowerCase().includes(searchQuery.toLowerCase()));
            const matchesRole = roleFilter === 'all' || user.role === roleFilter;
            const matchesVerification =
                verificationFilter === 'all' ||
                (verificationFilter === 'verified' && user.email_verified_at) ||
                (verificationFilter === 'unverified' && !user.email_verified_at);
            const matches2FA =
                twoFactorFilter === 'all' ||
                (twoFactorFilter === 'enabled' && user.two_factor_enabled) ||
                (twoFactorFilter === 'disabled' && !user.two_factor_enabled);
            return matchesSearch && matchesRole && matchesVerification && matches2FA;
        });
    }, [users, searchQuery, roleFilter, verificationFilter, twoFactorFilter]);

    const handleQuickFilter = (filterType: string) => {
        if (activeQuickFilter === filterType) {
            setActiveQuickFilter(null);
            setRoleFilter('all');
            setVerificationFilter('all');
            setTwoFactorFilter('all');
        } else {
            setActiveQuickFilter(filterType);
            switch (filterType) {
                case 'verified':
                    setVerificationFilter('verified');
                    setRoleFilter('all');
                    setTwoFactorFilter('all');
                    break;
                case '2fa':
                    setTwoFactorFilter('enabled');
                    setRoleFilter('all');
                    setVerificationFilter('all');
                    break;
                case 'admin':
                    setRoleFilter('admin');
                    setVerificationFilter('all');
                    setTwoFactorFilter('all');
                    break;
                case 'unverified':
                    setVerificationFilter('unverified');
                    setRoleFilter('all');
                    setTwoFactorFilter('all');
                    break;
            }
        }
    };

    // Stats
    const stats = useMemo(
        () => ({
            total: filteredUsers.length,
            verified: filteredUsers.filter((u) => u.email_verified_at).length,
            twoFactor: filteredUsers.filter((u) => u.two_factor_enabled).length,
            admins: filteredUsers.filter((u) => u.role === 'admin').length,
            verifiedPercentage:
                filteredUsers.length > 0 ? Math.round((filteredUsers.filter((u) => u.email_verified_at).length / filteredUsers.length) * 100) : 0,
            twoFactorPercentage:
                filteredUsers.length > 0 ? Math.round((filteredUsers.filter((u) => u.two_factor_enabled).length / filteredUsers.length) * 100) : 0,
        }),
        [filteredUsers],
    );

    // Handlers
    const handleCreateUser = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(admin.users.store.url(), formData, {
            only: ['users'],
            onSuccess: () => {
                setIsCreateModalOpen(false);
                resetForm();
                toast.success('User created successfully');
            },
            onError: () => {
                toast.error('Failed to create user');
            },
        });
    };

    const handleEditUser = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        router.put(admin.users.update.url({ user: selectedUser.id }), formData, {
            only: ['users'],
            onSuccess: () => {
                setIsEditModalOpen(false);
                setSelectedUser(null);
                resetForm();
                toast.success('User updated successfully');
            },
            onError: () => {
                toast.error('Failed to update user');
            },
        });
    };

    const handleDeleteUser = (user: User) => {
        setUserToDelete(user);
        setIsDeleteDialogOpen(true);
    };

    const confirmDeleteUser = () => {
        if (!userToDelete) return;
        router.delete(admin.users.destroy.url({ user: userToDelete.id }), {
            only: ['users'],
            onSuccess: () => {
                toast.success('User deleted successfully');
                setIsDeleteDialogOpen(false);
                setUserToDelete(null);
            },
            onError: () => {
                toast.error('Failed to delete user');
            },
        });
    };

    const handleBulkDelete = () => {
        const selectedRows = table.getFilteredSelectedRowModel().rows;
        if (selectedRows.length === 0) {
            toast.error('Please select users to delete');
            return;
        }
        setIsBulkDeleteDialogOpen(true);
    };

    const confirmBulkDelete = () => {
        const selectedRows = table.getFilteredSelectedRowModel().rows;
        if (selectedRows.length === 0) return;
        const userIds = selectedRows.map((row) => row.original.id);
        router.delete(admin.users.bulkDelete.url(), {
            data: { user_ids: userIds },
            only: ['users'],
            onSuccess: () => {
                const count = selectedRows.length;
                toast.success(count === 1 ? 'User deleted successfully' : `${count} users deleted successfully`);
                setIsBulkDeleteDialogOpen(false);
                table.toggleAllPageRowsSelected(false);
            },
            onError: (errors) => {
                const errorMessage = errors?.error || 'Failed to delete users';
                toast.error(errorMessage, { duration: 5000 });
            },
        });
    };

    const openEditModal = (user: User) => {
        setSelectedUser(user);
        setFormData({ name: user.name, email: user.email, role: user.role, password: '', password_confirmation: '' });
        setIsEditModalOpen(true);
    };

    const exportSelectedToCSV = () => {
        const selectedRows = table.getFilteredSelectedRowModel().rows;
        if (selectedRows.length === 0) {
            toast.error('Please select users to export');
            return;
        }

        const csvData = selectedRows.map((row) => {
            const user = row.original;
            return {
                Name: user.name,
                Email: user.email,
                Role: getRoleDisplayName(user.role),
                'Blockchain Address': user.blockchain_address || 'Not set',
                'Email Verified': user.email_verified_at ? 'Yes' : 'No',
                '2FA Status': user.two_factor_enabled ? 'Enabled' : 'Disabled',
                'Created Date':
                    formatDate(user.created_at)?.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) ?? 'No date',
                'Updated Date':
                    formatDate(user.updated_at)?.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) ?? 'No date',
            };
        });

        const headers = Object.keys(csvData[0]);
        const csvContent = [headers.join(','), ...csvData.map((row) => headers.map((h) => `"${row[h as keyof typeof row]}"`).join(','))].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.setAttribute('href', URL.createObjectURL(blob));
        link.setAttribute('download', `users-export-${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success(`Exported ${selectedRows.length} users to CSV`);
    };

    // Column definitions - extracted to separate file
    const columns = useMemo(
        () =>
            getUserColumns({
                hasPermission,
                setSelectedUser,
                setIsDetailsDialogOpen,
                setIsLoginHistoryDialogOpen,
                setIsResetPasswordDialogOpen,
                openEditModal,
                handleDeleteUser,
            }),
        [hasPermission],
    );

    // Table instance
    const table = useReactTable({
        data: filteredUsers,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onRowSelectionChange: setRowSelection,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        state: { sorting, columnFilters, columnVisibility, rowSelection },
    });

    const toggleAutoRefresh = () => setAutoRefresh(!autoRefresh);

    const viewDetails = (user: User) => {
        setSelectedUser(user);
        setIsDetailsDialogOpen(true);
    };
    const viewLoginHistory = (user: User) => {
        setSelectedUser(user);
        setIsLoginHistoryDialogOpen(true);
    };
    const resetPassword = (user: User) => {
        setSelectedUser(user);
        setIsResetPasswordDialogOpen(true);
    };

    return {
        // Data
        users,
        roles,
        filteredUsers,
        stats,
        // Modal state
        isCreateModalOpen,
        setIsCreateModalOpen,
        isEditModalOpen,
        setIsEditModalOpen,
        isDeleteDialogOpen,
        setIsDeleteDialogOpen,
        isBulkDeleteDialogOpen,
        setIsBulkDeleteDialogOpen,
        isDetailsDialogOpen,
        setIsDetailsDialogOpen,
        isLoginHistoryDialogOpen,
        setIsLoginHistoryDialogOpen,
        isResetPasswordDialogOpen,
        setIsResetPasswordDialogOpen,
        selectedUser,
        setSelectedUser,
        userToDelete,
        formData,
        setFormData,
        // UI state
        isLoading,
        isRefreshing,
        autoRefresh,
        toggleAutoRefresh,
        searchQuery,
        setSearchQuery,
        roleFilter,
        setRoleFilter,
        verificationFilter,
        setVerificationFilter,
        twoFactorFilter,
        setTwoFactorFilter,
        activeQuickFilter,
        // Handlers
        handleRefresh,
        handleQuickFilter,
        handleCreateUser,
        handleEditUser,
        handleDeleteUser,
        confirmDeleteUser,
        handleBulkDelete,
        confirmBulkDelete,
        openEditModal,
        exportSelectedToCSV,
        viewDetails,
        viewLoginHistory,
        resetPassword,
        // Table
        table,
        columns,
        // Permissions
        hasPermission,
    };
}
