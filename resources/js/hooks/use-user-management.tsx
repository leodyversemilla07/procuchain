import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/hooks/use-permissions';
import admin from '@/routes/admin';
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Edit,
  History,
  KeyRound,
  MoreHorizontal,
  QrCode,
  Trash2,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { router, usePage, usePoll } from '@inertiajs/react';
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
import { toast } from 'sonner';

export interface User {
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

interface PageProps {
  users: User[];
  roles: string[];
  [key: string]: unknown;
}

export const getRoleBadgeColor = (role: string) => {
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

export const getRoleDisplayName = (role: string) => {
  switch (role) {
    case 'bac_secretariat': return 'BAC Secretariat';
    case 'bac_chairman': return 'BAC Chairman';
    case 'hope': return 'HOPE';
    case 'admin': return 'Administrator';
    default: return role;
  }
};

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
      onFinish: () => { setIsRefreshing(false); toast.success('Users refreshed'); },
    },
    { autoStart: false, keepAlive: false },
  );

  useEffect(() => {
    if (autoRefresh) { start(); } else { stop(); }
    return () => stop();
  }, [autoRefresh, start, stop]);

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.reload({
      only: ['users'],
      onFinish: () => { setIsRefreshing(false); toast.success('Users refreshed'); },
    });
  };

  // Filtering
  const filteredUsers = useMemo(() => {
    return users.filter((user) => {
      const matchesSearch = searchQuery === '' ||
        user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (user.blockchain_address && user.blockchain_address.toLowerCase().includes(searchQuery.toLowerCase()));
      const matchesRole = roleFilter === 'all' || user.role === roleFilter;
      const matchesVerification = verificationFilter === 'all' ||
        (verificationFilter === 'verified' && user.email_verified_at) ||
        (verificationFilter === 'unverified' && !user.email_verified_at);
      const matches2FA = twoFactorFilter === 'all' ||
        (twoFactorFilter === 'enabled' && user.two_factor_enabled) ||
        (twoFactorFilter === 'disabled' && !user.two_factor_enabled);
      return matchesSearch && matchesRole && matchesVerification && matches2FA;
    });
  }, [users, searchQuery, roleFilter, verificationFilter, twoFactorFilter]);

  const handleQuickFilter = (filterType: string) => {
    if (activeQuickFilter === filterType) {
      setActiveQuickFilter(null);
      setRoleFilter('all'); setVerificationFilter('all'); setTwoFactorFilter('all');
    } else {
      setActiveQuickFilter(filterType);
      switch (filterType) {
        case 'verified': setVerificationFilter('verified'); setRoleFilter('all'); setTwoFactorFilter('all'); break;
        case '2fa': setTwoFactorFilter('enabled'); setRoleFilter('all'); setVerificationFilter('all'); break;
        case 'admin': setRoleFilter('admin'); setVerificationFilter('all'); setTwoFactorFilter('all'); break;
        case 'unverified': setVerificationFilter('unverified'); setRoleFilter('all'); setTwoFactorFilter('all'); break;
      }
    }
  };

  // Stats
  const stats = useMemo(() => ({
    total: filteredUsers.length,
    verified: filteredUsers.filter((u) => u.email_verified_at).length,
    twoFactor: filteredUsers.filter((u) => u.two_factor_enabled).length,
    admins: filteredUsers.filter((u) => u.role === 'admin').length,
    verifiedPercentage: filteredUsers.length > 0
      ? Math.round((filteredUsers.filter((u) => u.email_verified_at).length / filteredUsers.length) * 100) : 0,
    twoFactorPercentage: filteredUsers.length > 0
      ? Math.round((filteredUsers.filter((u) => u.two_factor_enabled).length / filteredUsers.length) * 100) : 0,
  }), [filteredUsers]);

  // Handlers
  const handleCreateUser = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(admin.users.store.url(), formData, {
      only: ['users'],
      onSuccess: () => { setIsCreateModalOpen(false); resetForm(); toast.success('User created successfully'); },
      onError: () => { toast.error('Failed to create user'); },
    });
  };

  const handleEditUser = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUser) return;
    router.put(admin.users.update.url({ user: selectedUser.id }), formData, {
      only: ['users'],
      onSuccess: () => { setIsEditModalOpen(false); setSelectedUser(null); resetForm(); toast.success('User updated successfully'); },
      onError: () => { toast.error('Failed to update user'); },
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
      onSuccess: () => { toast.success('User deleted successfully'); setIsDeleteDialogOpen(false); setUserToDelete(null); },
      onError: () => { toast.error('Failed to delete user'); },
    });
  };

  const handleBulkDelete = () => {
    const selectedRows = table.getFilteredSelectedRowModel().rows;
    if (selectedRows.length === 0) { toast.error('Please select users to delete'); return; }
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
    if (selectedRows.length === 0) { toast.error('Please select users to export'); return; }

    const csvData = selectedRows.map((row) => {
      const user = row.original;
      return {
        Name: user.name,
        Email: user.email,
        Role: getRoleDisplayName(user.role),
        'Blockchain Address': user.blockchain_address || 'Not set',
        'Email Verified': user.email_verified_at ? 'Yes' : 'No',
        '2FA Status': user.two_factor_enabled ? 'Enabled' : 'Disabled',
        'Created Date': formatDate(user.created_at)?.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) ?? 'No date',
        'Updated Date': formatDate(user.updated_at)?.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) ?? 'No date',
      };
    });

    const headers = Object.keys(csvData[0]);
    const csvContent = [
      headers.join(','),
      ...csvData.map((row) => headers.map((h) => `"${row[h as keyof typeof row]}"`).join(',')),
    ].join('\n');

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

  // Column definitions
  const columns: ColumnDef<User>[] = useMemo(() => [
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
      cell: ({ row }) => <Checkbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} aria-label="Select row" />,
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'name',
      header: ({ column }) => (
        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
          Name
          {column.getIsSorted() === 'asc' ? <ArrowUp className="ml-2 h-4 w-4" />
            : column.getIsSorted() === 'desc' ? <ArrowDown className="ml-2 h-4 w-4" />
            : <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />}
        </Button>
      ),
      cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
    },
    {
      accessorKey: 'email',
      header: ({ column }) => (
        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
          Email
          {column.getIsSorted() === 'asc' ? <ArrowUp className="ml-2 h-4 w-4" />
            : column.getIsSorted() === 'desc' ? <ArrowDown className="ml-2 h-4 w-4" />
            : <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />}
        </Button>
      ),
      cell: ({ row }) => <div className="text-muted-foreground">{row.getValue('email')}</div>,
    },
    {
      accessorKey: 'role',
      header: ({ column }) => (
        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
          Role
          {column.getIsSorted() === 'asc' ? <ArrowUp className="ml-2 h-4 w-4" />
            : column.getIsSorted() === 'desc' ? <ArrowDown className="ml-2 h-4 w-4" />
            : <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />}
        </Button>
      ),
      cell: ({ row }) => {
        const role = row.getValue('role') as string;
        return <span className={`${getRoleBadgeColor(role)} inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium`}>{getRoleDisplayName(role)}</span>;
      },
    },
    {
      accessorKey: 'blockchain_address',
      header: 'Blockchain Address',
      cell: ({ row }) => {
        const address = row.getValue('blockchain_address') as string;
        return (
          <div className="text-muted-foreground font-mono text-sm">
            {address ? <span className="block max-w-[200px] truncate" title={address}>{address}</span> : <span className="text-muted-foreground/50">Not set</span>}
          </div>
        );
      },
      meta: { hideBelow: 'xl' },
    },
    {
      accessorKey: 'email_verified_at',
      header: 'Email Verified',
      cell: ({ row }) => {
        const verifiedAt = row.getValue('email_verified_at') as string;
        return verifiedAt ? (
          <span className="border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200 inline-flex items-center rounded-md">Verified</span>
        ) : (
          <span className="border border-yellow-200 bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:border-yellow-800/30 dark:bg-yellow-900/20 dark:text-yellow-200 inline-flex items-center rounded-md">Pending</span>
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
                <span className="border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200 inline-flex items-center rounded-md">
                  <QrCode className="mr-1 h-3 w-3" />Enabled
                </span>
                {backupCodesCount > 0 && <span className="text-muted-foreground text-xs" title={`${backupCodesCount} backup codes remaining`}>({backupCodesCount} codes)</span>}
              </div>
            ) : (
              <span className="border border-gray-200 bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-300 inline-flex items-center rounded-md">Disabled</span>
            )}
          </div>
        );
      },
    },
    {
      accessorKey: 'created_at',
      header: 'Created',
      cell: ({ row }) => {
        const d = formatDate(row.getValue('created_at') as string);
        return <div className="text-muted-foreground text-sm">{d ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : <span className="text-muted-foreground/50">No date</span>}</div>;
      },
      meta: { hideBelow: 'xl' },
    },
    {
      accessorKey: 'updated_at',
      header: 'Updated',
      cell: ({ row }) => {
        const d = formatDate(row.getValue('updated_at') as string);
        return <div className="text-muted-foreground text-sm">{d ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : <span className="text-muted-foreground/50">No date</span>}</div>;
      },
      meta: { hideBelow: 'xl' },
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
              <div className="text-muted-foreground px-1.5 py-1 text-xs font-medium">Actions</div>
              <DropdownMenuItem
                onClick={async () => {
                  try { await navigator.clipboard.writeText(user.email); toast.success('Email copied to clipboard', { duration: 3000 }); }
                  catch (error) { toast.error('Failed to copy email', { description: String(error), duration: 5000 }); }
                }}
              >Copy email</DropdownMenuItem>
              <DropdownMenuItem onClick={() => { setSelectedUser(user); setIsDetailsDialogOpen(true); }}>View Details</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => { setSelectedUser(user); setIsLoginHistoryDialogOpen(true); }}>
                <History className="mr-2 h-4 w-4" />Login History
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => { setSelectedUser(user); setIsResetPasswordDialogOpen(true); }}>
                <KeyRound className="mr-2 h-4 w-4" />Reset Password
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              {hasPermission('edit users') && (
                <DropdownMenuItem onClick={() => openEditModal(user)}><Edit className="mr-2 h-4 w-4" />Edit user</DropdownMenuItem>
              )}
              {hasPermission('delete users') && (
                <DropdownMenuItem onClick={() => handleDeleteUser(user)} className="text-destructive hover:text-destructive">
                  <Trash2 className="mr-2 h-4 w-4" />Delete user
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    },
  ], [hasPermission]);

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

  const viewDetails = (user: User) => { setSelectedUser(user); setIsDetailsDialogOpen(true); };
  const viewLoginHistory = (user: User) => { setSelectedUser(user); setIsLoginHistoryDialogOpen(true); };
  const resetPassword = (user: User) => { setSelectedUser(user); setIsResetPasswordDialogOpen(true); };

  return {
    // Data
    users, roles, filteredUsers, stats,
    // Modal state
    isCreateModalOpen, setIsCreateModalOpen,
    isEditModalOpen, setIsEditModalOpen,
    isDeleteDialogOpen, setIsDeleteDialogOpen,
    isBulkDeleteDialogOpen, setIsBulkDeleteDialogOpen,
    isDetailsDialogOpen, setIsDetailsDialogOpen,
    isLoginHistoryDialogOpen, setIsLoginHistoryDialogOpen,
    isResetPasswordDialogOpen, setIsResetPasswordDialogOpen,
    selectedUser, setSelectedUser,
    userToDelete,
    formData, setFormData,
    // UI state
    isLoading, isRefreshing, autoRefresh, toggleAutoRefresh, searchQuery, setSearchQuery,
    roleFilter, setRoleFilter,
    verificationFilter, setVerificationFilter,
    twoFactorFilter, setTwoFactorFilter,
    activeQuickFilter,
    // Handlers
    handleRefresh, handleQuickFilter, handleCreateUser, handleEditUser,
    handleDeleteUser, confirmDeleteUser, handleBulkDelete, confirmBulkDelete,
    openEditModal, exportSelectedToCSV,
    viewDetails, viewLoginHistory, resetPassword,
    // Table
    table, columns,
    // Permissions
    hasPermission,
  };
}
