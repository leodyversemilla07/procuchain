import { Can } from '@/components/auth/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnDef,
    type ColumnFiltersState,
    type RowSelectionState,
    type SortingState,
    type VisibilityState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CheckCircle2,
    Clock,
    Download,
    Edit,
    History,
    KeyRound,
    MoreHorizontal,
    Plus,
    QrCode,
    RefreshCw,
    Search,
    Shield,
    Trash2,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// Dialog Components
import BulkDeleteDialog from '@/components/admin/bulk-delete-dialog';
import CreateUserDialog from '@/components/admin/create-user-dialog';
import DeleteUserDialog from '@/components/admin/delete-user-dialog';
import EditUserDialog from '@/components/admin/edit-user-dialog';
import ResetPasswordDialog from '@/components/admin/reset-password-dialog';
import UserDetailsSheet from '@/components/admin/user-details-sheet';
import UserLoginHistorySheet from '@/components/admin/user-login-history-sheet';
import { HeroCard } from '@/components/hero-card';
import { Pagination } from '@/components/pagination';
import { StatsGrid } from '@/components/stats-grid';
import { dashboard } from '@/routes/admin';
import users from '@/routes/admin/users';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string;
    email_verified_at?: string;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string;
    two_factor_recovery_codes?: string;
    backup_codes?: string[];
    backup_codes_generated_at?: string;
    created_at: string;
    updated_at?: string;
    roles?: Array<{ id: number; name: string }>;
}

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface PageProps {
    users: User[];
    roles: string[];
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: dashboard.url(),
    },
    {
        title: 'Users',
        href: users.index.url(),
    },
];

export default function AdminUserManagement() {
    const page = usePage<PageProps>();
    const { users, roles } = page.props;
    const { hasPermission } = usePermissions();

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

    // New UI/UX state
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
        setFormData({
            name: '',
            email: '',
            role: '',
            password: '',
            password_confirmation: '',
        });
    };

    // Auto-refresh functionality using Inertia's usePoll
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
            only: ['users'],
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Users refreshed');
            },
        });
    };

    // Advanced filtering logic
    const filteredUsers = useMemo(() => {
        return users.filter((user) => {
            // Search filter
            const matchesSearch =
                searchQuery === '' ||
                user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (user.blockchain_address && user.blockchain_address.toLowerCase().includes(searchQuery.toLowerCase()));

            // Role filter
            const matchesRole = roleFilter === 'all' || user.role === roleFilter;

            // Verification filter
            const matchesVerification =
                verificationFilter === 'all' ||
                (verificationFilter === 'verified' && user.email_verified_at) ||
                (verificationFilter === 'unverified' && !user.email_verified_at);

            // 2FA filter
            const matches2FA =
                twoFactorFilter === 'all' ||
                (twoFactorFilter === 'enabled' && user.two_factor_enabled) ||
                (twoFactorFilter === 'disabled' && !user.two_factor_enabled);

            return matchesSearch && matchesRole && matchesVerification && matches2FA;
        });
    }, [users, searchQuery, roleFilter, verificationFilter, twoFactorFilter]);

    // Quick filter actions
    const handleQuickFilter = (filterType: string) => {
        if (activeQuickFilter === filterType) {
            // Clear filter
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

    // Stats calculations
    const stats = useMemo(() => {
        return {
            total: filteredUsers.length,
            verified: filteredUsers.filter((u) => u.email_verified_at).length,
            twoFactor: filteredUsers.filter((u) => u.two_factor_enabled).length,
            admins: filteredUsers.filter((u) => u.role === 'admin').length,
            verifiedPercentage:
                filteredUsers.length > 0 ? Math.round((filteredUsers.filter((u) => u.email_verified_at).length / filteredUsers.length) * 100) : 0,
            twoFactorPercentage:
                filteredUsers.length > 0 ? Math.round((filteredUsers.filter((u) => u.two_factor_enabled).length / filteredUsers.length) * 100) : 0,
        };
    }, [filteredUsers]);

    const handleCreateUser = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(admin.users.store.url(), formData, {
            // Reload users data to sync across tabs/windows
            only: ['users'],
            onSuccess: () => {
                setIsCreateModalOpen(false);
                resetForm();
                toast.success('User created successfully');
            },
            onError: (errors) => {
                console.error('Create user errors:', errors);
                toast.error('Failed to create user');
            },
        });
    };

    const handleEditUser = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;

        router.put(admin.users.update.url({ user: selectedUser.id }), formData, {
            // Reload users data to sync across tabs/windows
            only: ['users'],
            onSuccess: () => {
                setIsEditModalOpen(false);
                setSelectedUser(null);
                resetForm();
                toast.success('User updated successfully');
            },
            onError: (errors) => {
                console.error('Update user errors:', errors);
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
            // Reload users data to sync across tabs/windows
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
            // Reload users data to sync across tabs/windows
            only: ['users'],
            onSuccess: () => {
                const count = selectedRows.length;
                const message = count === 1 ? 'User deleted successfully' : `${count} users deleted successfully`;
                toast.success(message);
                setIsBulkDeleteDialogOpen(false);
                table.toggleAllPageRowsSelected(false);
            },
            onError: (errors) => {
                console.error('Bulk delete errors:', errors);
                const errorMessage = errors?.error || 'Failed to delete users';
                toast.error(errorMessage, { duration: 5000 });
            },
        });
    };

    const openEditModal = (user: User) => {
        setSelectedUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            role: user.role,
            password: '',
            password_confirmation: '',
        });
        setIsEditModalOpen(true);
    };

    const getRoleBadgeColor = (role: string) => {
        switch (role) {
            case 'admin':
                return 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 hover:bg-red-200 dark:hover:bg-red-900/30 border border-red-200 dark:border-red-800/30';
            case 'bac_chairman':
                return 'bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-900/30 border border-blue-200 dark:border-blue-800/30';
            case 'hope':
                return 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-900/30 border border-green-200 dark:border-green-800/30';
            case 'bac_secretariat':
                return 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800/30';
            default:
                return 'bg-gray-100 dark:bg-gray-800/50 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700/50 border border-gray-200 dark:border-gray-700/50';
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

    // Bulk export to CSV function
    const exportSelectedToCSV = () => {
        const selectedRows = table.getFilteredSelectedRowModel().rows;
        if (selectedRows.length === 0) {
            toast.error('Please select users to export');
            return;
        }

        const csvData = selectedRows.map((row) => {
            const user = row.original;

            const formatDateForCSV = (dateValue: string | undefined) => {
                if (dateValue === null || dateValue === undefined || dateValue === '' || dateValue === 'null' || dateValue === 'undefined') {
                    return 'No date';
                }

                const date = new Date(dateValue);
                const isValidDate = !isNaN(date.getTime());

                return isValidDate
                    ? date.toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                      })
                    : 'Invalid date';
            };

            return {
                Name: user.name,
                Email: user.email,
                Role: getRoleDisplayName(user.role),
                'Blockchain Address': user.blockchain_address || 'Not set',
                'Email Verified': user.email_verified_at ? 'Yes' : 'No',
                'Email Verified Date': user.email_verified_at ? formatDateForCSV(user.email_verified_at) : 'Not verified',
                '2FA Status': user.two_factor_enabled ? 'Enabled' : 'Disabled',
                '2FA Enabled Date': user.two_factor_confirmed_at ? formatDateForCSV(user.two_factor_confirmed_at) : 'Not enabled',
                'Backup Codes Count': user.backup_codes ? user.backup_codes.length.toString() : '0',
                'Backup Codes Generated': user.backup_codes_generated_at ? formatDateForCSV(user.backup_codes_generated_at) : 'Not generated',
                'Created Date': formatDateForCSV(user.created_at),
                'Updated Date': formatDateForCSV(user.updated_at),
            };
        });

        // Convert to CSV format
        const headers = Object.keys(csvData[0]);
        const csvContent = [
            headers.join(','),
            ...csvData.map((row) => headers.map((header) => `"${row[header as keyof typeof row]}"`).join(',')),
        ].join('\n');

        // Create and download file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `users-export-${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        toast.success(`Exported ${selectedRows.length} users to CSV`);
    };

    // Define columns for the data table
    const columns: ColumnDef<User>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected()}
                    indeterminate={!table.getIsAllPageRowsSelected() && table.getIsSomePageRowsSelected()}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <Checkbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} aria-label="Select row" />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'name',
            header: ({ column }) => {
                return (
                    <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
                        Name
                        {column.getIsSorted() === 'asc' ? (
                            <ArrowUp className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === 'desc' ? (
                            <ArrowDown className="ml-2 h-4 w-4" />
                        ) : (
                            <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />
                        )}
                    </Button>
                );
            },
            cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
        },
        {
            accessorKey: 'email',
            header: ({ column }) => {
                return (
                    <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
                        Email
                        {column.getIsSorted() === 'asc' ? (
                            <ArrowUp className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === 'desc' ? (
                            <ArrowDown className="ml-2 h-4 w-4" />
                        ) : (
                            <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />
                        )}
                    </Button>
                );
            },
            cell: ({ row }) => <div className="text-muted-foreground">{row.getValue('email')}</div>,
        },
        {
            accessorKey: 'role',
            header: ({ column }) => {
                return (
                    <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
                        Role
                        {column.getIsSorted() === 'asc' ? (
                            <ArrowUp className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === 'desc' ? (
                            <ArrowDown className="ml-2 h-4 w-4" />
                        ) : (
                            <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />
                        )}
                    </Button>
                );
            },
            cell: ({ row }) => {
                const role = row.getValue('role') as string;
                return <Badge className={`${getRoleBadgeColor(role)} px-3 py-1 text-xs font-medium`}>{getRoleDisplayName(role)}</Badge>;
            },
        },
        {
            accessorKey: 'blockchain_address',
            header: 'Blockchain Address',
            cell: ({ row }) => {
                const address = row.getValue('blockchain_address') as string;
                return (
                    <div className="text-muted-foreground font-mono text-sm">
                        {address ? (
                            <span className="block max-w-[200px] truncate" title={address}>
                                {address}
                            </span>
                        ) : (
                            <span className="text-muted-foreground/50">Not set</span>
                        )}
                    </div>
                );
            },
            meta: {
                hideBelow: 'xl',
            },
        },
        {
            accessorKey: 'email_verified_at',
            header: 'Email Verified',
            cell: ({ row }) => {
                const verifiedAt = row.getValue('email_verified_at') as string;
                return (
                    <div className="text-muted-foreground text-sm">
                        {verifiedAt ? (
                            <div className="flex items-center">
                                <Badge className="border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                    Verified
                                </Badge>
                            </div>
                        ) : (
                            <Badge className="border border-yellow-200 bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:border-yellow-800/30 dark:bg-yellow-900/20 dark:text-yellow-200">
                                Pending
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'two_factor_enabled',
            header: '2FA Status',
            cell: ({ row }) => {
                const user = row.original;
                const twoFactorEnabled = user.two_factor_enabled;
                const backupCodesCount = user.backup_codes ? user.backup_codes.length : 0;

                return (
                    <div className="flex items-center space-x-2">
                        {twoFactorEnabled ? (
                            <div className="flex items-center space-x-2">
                                <Badge className="border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                    <QrCode className="mr-1 h-3 w-3" />
                                    Enabled
                                </Badge>
                                {backupCodesCount > 0 && (
                                    <span className="text-muted-foreground text-xs" title={`${backupCodesCount} backup codes remaining`}>
                                        ({backupCodesCount} codes)
                                    </span>
                                )}
                            </div>
                        ) : (
                            <Badge className="border border-gray-200 bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-300">
                                Disabled
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            cell: ({ row }) => {
                const dateValue = row.getValue('created_at') as string;

                if (!dateValue || dateValue === '' || dateValue === 'null' || dateValue === 'undefined') {
                    return (
                        <div className="text-muted-foreground text-sm">
                            <span className="text-muted-foreground/50">No creation date</span>
                        </div>
                    );
                }

                const date = new Date(dateValue);
                const isValidDate = !isNaN(date.getTime());

                return (
                    <div className="text-muted-foreground text-sm">
                        {isValidDate ? (
                            date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                            })
                        ) : (
                            <span className="text-muted-foreground/50" title={`Invalid date: ${dateValue}`}>
                                Invalid date
                            </span>
                        )}
                    </div>
                );
            },
            meta: {
                hideBelow: 'xl',
            },
        },
        {
            accessorKey: 'updated_at',
            header: 'Updated',
            cell: ({ row }) => {
                const dateValue = row.getValue('updated_at') as string;

                if (dateValue === null || dateValue === undefined || dateValue === '' || dateValue === 'null' || dateValue === 'undefined') {
                    return (
                        <div className="text-muted-foreground text-sm">
                            <span className="text-muted-foreground/50">No update date</span>
                        </div>
                    );
                }

                const date = new Date(dateValue);
                const isValidDate = !isNaN(date.getTime());

                return (
                    <div className="text-muted-foreground text-sm">
                        {isValidDate ? (
                            date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                            })
                        ) : (
                            <span className="text-muted-foreground/50" title={`Invalid date: ${dateValue}`}>
                                Invalid date
                            </span>
                        )}
                    </div>
                );
            },
            meta: {
                hideBelow: 'xl',
            },
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const user = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="ghost" className="h-10 w-10 p-0 md:h-8 md:w-8" />}>
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <div className="px-1.5 py-1 text-xs font-medium text-muted-foreground">Actions</div>
                            <DropdownMenuItem
                                onClick={async () => {
                                    try {
                                        await navigator.clipboard.writeText(user.email);
                                        toast.success('Email copied to clipboard', { duration: 3000 });
                                    } catch (error) {
                                        toast.error('Failed to copy email', {
                                            description: String(error),
                                            duration: 5000,
                                        });
                                    }
                                }}
                            >
                                Copy email
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => {
                                    setSelectedUser(user);
                                    setIsDetailsDialogOpen(true);
                                }}
                            >
                                View Details
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => {
                                    setSelectedUser(user);
                                    setIsLoginHistoryDialogOpen(true);
                                }}
                            >
                                <History className="mr-2 h-4 w-4" />
                                Login History
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => {
                                    setSelectedUser(user);
                                    setIsResetPasswordDialogOpen(true);
                                }}
                            >
                                <KeyRound className="mr-2 h-4 w-4" />
                                Reset Password
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            {hasPermission('edit users') && (
                                <DropdownMenuItem onClick={() => openEditModal(user)}>
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit user
                                </DropdownMenuItem>
                            )}
                            {hasPermission('delete users') && (
                                <DropdownMenuItem onClick={() => handleDeleteUser(user)} className="text-destructive hover:text-destructive">
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete user
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    // Table setup
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
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
        },
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Header Section */}
                <HeroCard
                    icon={Users}
                    title="User Management"
                    description="Manage system users and their roles"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={handleRefresh} disabled={isRefreshing} variant="outline" size="sm" className="h-9 gap-1 sm:h-8 sm:gap-2">
                                <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                <span className="hidden sm:inline">Refresh</span>
                            </Button>
                            <Button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                variant={autoRefresh ? 'default' : 'outline'}
                                size="sm"
                                className="h-9 gap-1 sm:h-8 sm:gap-2"
                            >
                                <Clock className="h-4 w-4" />
                                <span className="hidden sm:inline">Auto</span>
                            </Button>
                            <Can permission="create users">
                                <Button onClick={() => setIsCreateModalOpen(true)} size="sm" className="h-9 gap-1 sm:h-8 sm:gap-2">
                                    <Plus className="h-4 w-4" />
                                    <span className="hidden sm:inline">Add User</span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                {/* Stats Grid */}
                <StatsGrid
                    items={[
                        {
                            label: 'Total Users',
                            value: stats.total,
                            icon: Users,
                            iconClassName: 'bg-primary/10 text-primary',
                        },
                        {
                            label: `Email Verified (${stats.verifiedPercentage}%)`,
                            value: stats.verified,
                            icon: CheckCircle2,
                            iconClassName: 'bg-success/10 text-success',
                        },
                        {
                            label: `2FA Enabled (${stats.twoFactorPercentage}%)`,
                            value: stats.twoFactor,
                            icon: Shield,
                            iconClassName: 'bg-blue-500/10 text-blue-600',
                        },
                        {
                            label: 'Administrators',
                            value: stats.admins,
                            icon: UserCheck,
                            iconClassName: 'bg-destructive/10 text-destructive',
                        },
                    ]}
                />

                {/* Data Table Section */}
                <div className="flex-1 space-y-3 sm:space-y-4">
                    {/* Search and Advanced Filters */}
                    <Card>
                        <CardContent className="p-3 sm:p-4">
                            <div className="space-y-3 sm:space-y-4">
                                {/* Search and Filter Row */}
                                <div className="flex flex-col gap-3 lg:flex-row lg:gap-4">
                                    <div className="relative flex-1">
                                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            placeholder="Search users..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="h-10 pl-9 text-sm"
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-3 lg:flex lg:gap-2">
                                        <Select value={roleFilter} onValueChange={(value) => value && setRoleFilter(value)}>
                                            <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                                <SelectValue placeholder="Filter by role" />
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
                                        <Select value={verificationFilter} onValueChange={(value) => value && setVerificationFilter(value)}>
                                            <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                                <SelectValue placeholder="Email status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="all">All Users</SelectItem>
                                                    <SelectItem value="verified">Verified</SelectItem>
                                                    <SelectItem value="unverified">Unverified</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <Select value={twoFactorFilter} onValueChange={(value) => value && setTwoFactorFilter(value)}>
                                            <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                                <SelectValue placeholder="2FA status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="all">All Users</SelectItem>
                                                    <SelectItem value="enabled">2FA Enabled</SelectItem>
                                                    <SelectItem value="disabled">2FA Disabled</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {/* Quick Filter Chips */}
                                <div className="scrollbar-hide -mx-3 flex items-center gap-2 overflow-x-auto px-3 pb-2 sm:mx-0 sm:gap-3 sm:px-0 sm:pb-0">
                                    <span className="text-muted-foreground shrink-0 text-xs font-medium sm:text-sm">Quick filters:</span>
                                    <div className="flex gap-2">
                                        <Button
                                            variant={activeQuickFilter === 'verified' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handleQuickFilter('verified')}
                                            className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                        >
                                            <CheckCircle2 className="h-3 w-3" />
                                            <span className="xs:inline hidden">Verified</span>
                                        </Button>
                                        <Button
                                            variant={activeQuickFilter === '2fa' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handleQuickFilter('2fa')}
                                            className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                        >
                                            <Shield className="h-3 w-3" />
                                            <span className="xs:inline hidden">2FA</span>
                                        </Button>
                                        <Button
                                            variant={activeQuickFilter === 'admin' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handleQuickFilter('admin')}
                                            className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                        >
                                            <UserCheck className="h-3 w-3" />
                                            <span className="xs:inline hidden">Admin</span>
                                        </Button>
                                        <Button
                                            variant={activeQuickFilter === 'unverified' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handleQuickFilter('unverified')}
                                            className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                        >
                                            <X className="h-3 w-3" />
                                            <span className="xs:inline hidden">Unverified</span>
                                        </Button>
                                    </div>
                                </div>

                                {/* Filter Info */}
                                {(searchQuery || roleFilter !== 'all' || verificationFilter !== 'all' || twoFactorFilter !== 'all') && (
                                    <div className="text-muted-foreground text-xs sm:text-sm">
                                        Showing {filteredUsers.length} of {users.length} user(s)
                                        {table.getFilteredSelectedRowModel().rows.length > 0 &&
                                            ` • ${table.getFilteredSelectedRowModel().rows.length} selected`}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bulk Actions Bar */}
                    {table.getFilteredSelectedRowModel().rows.length > 0 && (
                        <div className="bg-accent/50 dark:bg-accent/20 border-accent dark:border-accent/40 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-4">
                            <div className="flex items-center gap-2">
                                <span className="text-accent-foreground dark:text-accent-foreground text-xs font-medium sm:text-sm">
                                    {table.getFilteredSelectedRowModel().rows.length} selected
                                </span>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={exportSelectedToCSV}
                                    className="border-primary/20 dark:border-primary/30 text-primary dark:text-primary hover:bg-primary/10 dark:hover:bg-primary/20 h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                                >
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    <span>Export CSV</span>
                                </Button>
                                {hasPermission('delete users') && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={handleBulkDelete}
                                        className="h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                                    >
                                        <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                        <span>Delete</span>
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => table.toggleAllPageRowsSelected(false)}
                                    className="text-muted-foreground hover:bg-muted hover:text-muted-foreground h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                                >
                                    <span>Clear</span>
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Table - Desktop View */}
                    {filteredUsers.length === 0 && !isLoading && !isRefreshing ? (
                        <Card>
                            <CardContent className="flex justify-center px-4 py-8 sm:px-6 sm:py-10 md:py-12">
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <Users className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                        </EmptyMedia>
                                    </EmptyHeader>
                                    <EmptyTitle>No users found</EmptyTitle>
                                    <EmptyDescription>
                                        {searchQuery || roleFilter !== 'all' || verificationFilter !== 'all' || twoFactorFilter !== 'all'
                                            ? 'Try adjusting your search or filter criteria.'
                                            : 'Click "Add User" to create your first user'}
                                    </EmptyDescription>
                                </Empty>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="hidden md:block">
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <TableRow key={headerGroup.id}>
                                                {headerGroup.headers.map((header) => {
                                                    return (
                                                        <TableHead key={header.id}>
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(header.column.columnDef.header, header.getContext())}
                                                        </TableHead>
                                                    );
                                                })}
                                            </TableRow>
                                        ))}
                                    </TableHeader>
                                    <TableBody>
                                        {isLoading || isRefreshing ? (
                                            Array.from({ length: 5 }).map((_, index) => (
                                                <TableRow key={index}>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-4" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-32" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-48" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-5 w-24" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-40" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-5 w-20" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-5 w-20" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-24" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-4 w-24" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Skeleton className="h-8 w-8 rounded" />
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : table.getRowModel().rows?.length ? (
                                            table.getRowModel().rows.map((row) => (
                                                <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                                                    {row.getVisibleCells().map((cell) => (
                                                        <TableCell key={cell.id}>
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </TableCell>
                                                    ))}
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                                    No results.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                            {/* Pagination */}
                            <CardFooter className="justify-end border-t px-6 py-5">
                                <Pagination
                                    pageIndex={table.getState().pagination.pageIndex}
                                    pageSize={table.getState().pagination.pageSize}
                                    pageCount={table.getPageCount()}
                                    totalItems={table.getFilteredRowModel().rows.length}
                                    onPageChange={table.setPageIndex}
                                    onPageSizeChange={table.setPageSize}
                                />
                            </CardFooter>
                        </Card>
                    )}

                    {/* Mobile Card View */}
                    {filteredUsers.length > 0 && (
                        <div className="space-y-3 md:hidden">
                            <div className="space-y-4">
                                {isLoading || isRefreshing
                                    ? Array.from({ length: 3 }).map((_, index) => (
                                          <Card key={index}>
                                              <CardContent className="p-4">
                                                  <div className="space-y-4">
                                                      <div className="flex items-start justify-between">
                                                          <div className="flex-1 space-y-2">
                                                              <Skeleton className="h-5 w-32" />
                                                              <Skeleton className="h-4 w-48" />
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
                                    : table.getRowModel().rows.map((row) => {
                                          const user = row.original;
                                          return (
                                              <Card key={user.id}>
                                                  <CardHeader className="p-3 pb-2 sm:px-4 sm:py-3">
                                                      <div className="flex items-start justify-between gap-2">
                                                          <div className="flex min-w-0 flex-1 items-start gap-2">
                                                              <div className="flex h-9 w-9 shrink-0 items-center justify-center">
                                                                  <Checkbox
                                                                      checked={row.getIsSelected()}
                                                                      onCheckedChange={(value) => row.toggleSelected(!!value)}
                                                                  />
                                                              </div>
                                                              <div className="min-w-0 flex-1 space-y-0.5">
                                                                  <CardTitle className="truncate text-sm leading-tight">{user.name}</CardTitle>
                                                                  <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                                              </div>
                                                          </div>
                                                          <DropdownMenu>
                                                              <DropdownMenuTrigger
                                                                  render={<Button variant="ghost" size="icon" className="h-10 w-10 md:h-8 md:w-8" />}
                                                              >
                                                                  <MoreHorizontal className="h-4 w-4" />
                                                              </DropdownMenuTrigger>
                                                              <DropdownMenuContent align="end">
                                                                  <div className="px-1.5 py-1 text-xs font-medium text-muted-foreground">Actions</div>
                                                                  <DropdownMenuItem
                                                                      onClick={async () => {
                                                                          try {
                                                                              await navigator.clipboard.writeText(user.email);
                                                                              toast.success('Email copied');
                                                                          } catch {
                                                                              toast.error('Failed to copy');
                                                                          }
                                                                      }}
                                                                  >
                                                                      Copy email
                                                                  </DropdownMenuItem>
                                                                  <DropdownMenuItem
                                                                      onClick={() => {
                                                                          setSelectedUser(user);
                                                                          setIsDetailsDialogOpen(true);
                                                                      }}
                                                                  >
                                                                      View Details
                                                                  </DropdownMenuItem>
                                                                  <DropdownMenuSeparator />
                                                                  <DropdownMenuItem
                                                                      onClick={() => {
                                                                          setSelectedUser(user);
                                                                          setIsLoginHistoryDialogOpen(true);
                                                                      }}
                                                                  >
                                                                      <History className="mr-2 h-4 w-4" />
                                                                      Login History
                                                                  </DropdownMenuItem>
                                                                  <DropdownMenuItem
                                                                      onClick={() => {
                                                                          setSelectedUser(user);
                                                                          setIsResetPasswordDialogOpen(true);
                                                                      }}
                                                                  >
                                                                      <KeyRound className="mr-2 h-4 w-4" />
                                                                      Reset Password
                                                                  </DropdownMenuItem>
                                                                  <DropdownMenuSeparator />
                                                                  {hasPermission('edit users') && (
                                                                      <DropdownMenuItem onClick={() => openEditModal(user)}>
                                                                          <Edit className="mr-2 h-4 w-4" />
                                                                          Edit user
                                                                      </DropdownMenuItem>
                                                                  )}
                                                                  {hasPermission('delete users') && (
                                                                      <DropdownMenuItem
                                                                          onClick={() => handleDeleteUser(user)}
                                                                          className="text-destructive"
                                                                      >
                                                                          <Trash2 className="mr-2 h-4 w-4" />
                                                                          Delete user
                                                                      </DropdownMenuItem>
                                                                  )}
                                                              </DropdownMenuContent>
                                                          </DropdownMenu>
                                                      </div>
                                                  </CardHeader>
                                                  <CardContent className="space-y-2.5 p-3 pt-0 sm:px-4">
                                                      <div className="flex items-center justify-between text-xs sm:text-sm">
                                                          <span className="text-muted-foreground">Role</span>
                                                          <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                                                      </div>
                                                      <div className="flex items-center justify-between text-xs sm:text-sm">
                                                          <span className="text-muted-foreground">Email Status</span>
                                                          {user.email_verified_at ? (
                                                              <Badge className="border border-green-200 bg-green-100 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                                                  Verified
                                                              </Badge>
                                                          ) : (
                                                              <Badge className="border border-yellow-200 bg-yellow-100 text-xs text-yellow-800 dark:border-yellow-800/30 dark:bg-yellow-900/20 dark:text-yellow-200">
                                                                  Pending
                                                              </Badge>
                                                          )}
                                                      </div>
                                                      <div className="flex items-center justify-between text-xs sm:text-sm">
                                                          <span className="text-muted-foreground">2FA Status</span>
                                                          {user.two_factor_enabled ? (
                                                              <div className="flex items-center gap-1.5">
                                                                  <Badge className="border border-green-200 bg-green-100 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                                                      <QrCode className="mr-1 h-3 w-3" />
                                                                      On
                                                                  </Badge>
                                                                  {user.backup_codes && user.backup_codes.length > 0 && (
                                                                      <span className="text-muted-foreground text-[10px]">
                                                                          ({user.backup_codes.length})
                                                                      </span>
                                                                  )}
                                                              </div>
                                                          ) : (
                                                              <Badge className="border border-gray-200 bg-gray-100 text-xs text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-300">
                                                                  Off
                                                              </Badge>
                                                          )}
                                                      </div>
                                                      {user.blockchain_address && (
                                                          <div className="bg-muted/50 rounded p-2">
                                                              <div className="text-muted-foreground mb-1 text-[10px] font-medium tracking-wide uppercase">
                                                                  Blockchain
                                                              </div>
                                                              <div className="font-mono text-[10px] leading-relaxed break-all">
                                                                  {user.blockchain_address}
                                                              </div>
                                                          </div>
                                                      )}
                                                  </CardContent>
                                              </Card>
                                          );
                                      })}
                            </div>
                            <div className="mt-4 flex justify-center">
                                <Pagination
                                    pageIndex={table.getState().pagination.pageIndex}
                                    pageSize={table.getState().pagination.pageSize}
                                    pageCount={table.getPageCount()}
                                    totalItems={table.getFilteredRowModel().rows.length}
                                    onPageChange={table.setPageIndex}
                                    onPageSizeChange={table.setPageSize}
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/* Dialog Components */}
                <CreateUserDialog
                    open={isCreateModalOpen}
                    onOpenChange={setIsCreateModalOpen}
                    formData={formData}
                    setFormData={setFormData}
                    roles={roles}
                    onSubmit={handleCreateUser}
                    getRoleDisplayName={getRoleDisplayName}
                />

                <EditUserDialog
                    open={isEditModalOpen}
                    onOpenChange={setIsEditModalOpen}
                    formData={formData}
                    setFormData={setFormData}
                    roles={roles}
                    onSubmit={handleEditUser}
                    getRoleDisplayName={getRoleDisplayName}
                />

                <DeleteUserDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen} user={userToDelete} onConfirm={confirmDeleteUser} />

                <BulkDeleteDialog
                    open={isBulkDeleteDialogOpen}
                    onOpenChange={setIsBulkDeleteDialogOpen}
                    selectedUsers={table.getFilteredSelectedRowModel().rows.map((row) => row.original)}
                    onConfirm={confirmBulkDelete}
                />

                <UserDetailsSheet open={isDetailsDialogOpen} onOpenChange={setIsDetailsDialogOpen} user={selectedUser ? { ...selectedUser } : null} />

                <UserLoginHistorySheet
                    open={isLoginHistoryDialogOpen}
                    onOpenChange={setIsLoginHistoryDialogOpen}
                    userId={selectedUser?.id ?? null}
                    userName={selectedUser?.name}
                />

                <ResetPasswordDialog open={isResetPasswordDialogOpen} onOpenChange={setIsResetPasswordDialogOpen} user={selectedUser} />
            </div>
        </AppLayout>
    );
}
