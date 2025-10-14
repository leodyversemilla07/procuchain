import { Can } from '@/components/auth/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
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
import { Download, Edit, MoreHorizontal, Plus, QrCode, Trash2, Users } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';

// Dialog Components
import BulkDeleteDialog from '@/components/admin/bulk-delete-dialog';
import CreateUserDialog from '@/components/admin/create-user-dialog';
import DeleteUserDialog from '@/components/admin/delete-user-dialog';
import EditUserDialog from '@/components/admin/edit-user-dialog';
import { HeroCard } from '@/components/hero-card';
import { Pagination } from '@/components/pagination';
import { dashboard, users as usersRoute } from '@/routes/admin';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string;
    email_verified_at?: string;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string;
    backup_codes?: string[];
    backup_codes_generated_at?: string;
    created_at: string;
    updated_at?: string;
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
        href: usersRoute.url(),
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
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [userToDelete, setUserToDelete] = useState<User | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        role: '',
        password: '',
        password_confirmation: '',
        blockchain_address: '',
    });

    // Table state
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    const resetForm = () => {
        setFormData({
            name: '',
            email: '',
            role: '',
            password: '',
            password_confirmation: '',
            blockchain_address: '',
        });
    };

    const handleCreateUser = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/users', formData, {
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

        router.put(`/admin/users/${selectedUser.id}`, formData, {
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

        router.delete(`/admin/users/${userToDelete.id}`, {
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

        router.delete('/admin/users', {
            data: { user_ids: userIds },
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
            blockchain_address: user.blockchain_address || '',
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
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
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
            header: 'Name',
            cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
        },
        {
            accessorKey: 'email',
            header: 'Email',
            cell: ({ row }) => <div className="text-muted-foreground">{row.getValue('email')}</div>,
        },
        {
            accessorKey: 'role',
            header: 'Role',
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
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const user = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Open menu</span>
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
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
        data: users,
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
            <div className="flex h-full flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header Section */}
                <HeroCard
                    icon={Users}
                    title="User Management"
                    description="Manage system users and their roles"
                    actions={
                        <Can permission="create users">
                            <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Add User
                            </Button>
                        </Can>
                    }
                />

                {/* Data Table Section */}
                <div className="flex-1 space-y-4">
                    {/* Search and Filter */}
                    <div className="flex items-center gap-2">
                        <Input
                            placeholder="Search users..."
                            value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
                            onChange={(event) => table.getColumn('name')?.setFilterValue(event.target.value)}
                            className="h-10 max-w-sm"
                        />
                    </div>

                    {/* Bulk Actions Bar */}
                    {table.getFilteredSelectedRowModel().rows.length > 0 && (
                        <div className="bg-accent/50 dark:bg-accent/20 border-accent dark:border-accent/40 flex items-center justify-between rounded-lg border px-4 py-3">
                            <div className="flex items-center gap-2">
                                <span className="text-accent-foreground dark:text-accent-foreground text-sm font-medium">
                                    {table.getFilteredSelectedRowModel().rows.length} user(s) selected
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={exportSelectedToCSV}
                                    className="border-primary/20 dark:border-primary/30 text-primary dark:text-primary hover:bg-primary/10 dark:hover:bg-primary/20 h-8"
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Export to CSV
                                </Button>
                                {hasPermission('delete users') && (
                                    <Button variant="destructive" size="sm" onClick={handleBulkDelete} className="h-8">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Selected
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => table.toggleAllPageRowsSelected(false)}
                                    className="text-muted-foreground hover:bg-muted hover:text-muted-foreground h-8"
                                >
                                    Clear Selection
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Table and Empty State */}
                    {users.length === 0 ? (
                        <Card>
                            <CardContent className="flex justify-center px-6 py-12">
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <Users className="h-8 w-8" />
                                        </EmptyMedia>
                                    </EmptyHeader>
                                    <EmptyTitle>No users found</EmptyTitle>
                                    <EmptyDescription>Click "Add User" to create your first user</EmptyDescription>
                                </Empty>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
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
                                        {table.getRowModel().rows?.length ? (
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
                            <CardFooter className="justify-end">
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
            </div>
        </AppLayout>
    );
}
