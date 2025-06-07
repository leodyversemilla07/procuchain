import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Plus, Edit, Trash2, Users, MoreHorizontal, ArrowUpDown, Download, Shield, Mail, Key, Calendar, Clock } from 'lucide-react';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
    PaginationEllipsis,
} from '@/components/ui/pagination';
import { ScrollArea } from '@/components/ui/scroll-area';
import { toast } from 'sonner';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    RowSelectionState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string;
    email_verified_at?: string;
    remember_token?: string;
    created_at: string;
    updated_at?: string;
}

interface PageProps extends InertiaPageProps {
    users: User[]; roles: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: route('admin.dashboard'),
    },
    {
        title: 'Users',
        href: route('admin.users'),
    },
];

export default function AdminUsers() {
    const page = usePage<PageProps & SharedData>();
    const { users, roles } = page.props; const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
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
    });    // Table state
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

        const userIds = selectedRows.map(row => row.original.id);

        router.delete('/admin/users', {
            data: { user_ids: userIds },
            onSuccess: () => {
                const count = selectedRows.length;
                const message = count === 1 ? 'User deleted successfully' : `${count} users deleted successfully`;
                toast.success(message);
                setIsBulkDeleteDialogOpen(false);
                // Clear selection after successful deletion
                table.toggleAllPageRowsSelected(false);
            },
            onError: (errors) => {
                console.error('Bulk delete errors:', errors);
                const errorMessage = errors?.error || 'Failed to delete users';
                toast.error(errorMessage, { duration: 5000 });
            },
        });
    }; const openEditModal = (user: User) => {
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
                return 'bg-red-100 text-red-800 hover:bg-red-200';
            case 'bac_chairman':
                return 'bg-blue-100 text-blue-800 hover:bg-blue-200';
            case 'hope':
                return 'bg-green-100 text-green-800 hover:bg-green-200';
            case 'bac_secretariat':
                return 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200';
            default:
                return 'bg-gray-100 text-gray-800 hover:bg-gray-200';
        }
    }; const getRoleDisplayName = (role: string) => {
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
        } const csvData = selectedRows.map(row => {
            const user = row.original;

            // Helper function for date formatting in CSV
            const formatDateForCSV = (dateValue: string | undefined) => {
                if (dateValue === null || dateValue === undefined || dateValue === '' ||
                    dateValue === 'null' || dateValue === 'undefined') {
                    return 'No date';
                }

                const date = new Date(dateValue);
                const isValidDate = !isNaN(date.getTime());

                return isValidDate ?
                    date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    }) : 'Invalid date';
            };

            return {
                Name: user.name,
                Email: user.email,
                Role: getRoleDisplayName(user.role),
                'Blockchain Address': user.blockchain_address || 'Not set',
                'Email Verified': user.email_verified_at ? 'Yes' : 'No',
                'Email Verified Date': user.email_verified_at ? formatDateForCSV(user.email_verified_at) : 'Not verified',
                'Remember Token': user.remember_token ? 'Set' : 'None',
                'Created Date': formatDateForCSV(user.created_at),
                'Updated Date': formatDateForCSV(user.updated_at)
            };
        });

        // Convert to CSV format
        const headers = Object.keys(csvData[0]);
        const csvContent = [
            headers.join(','),
            ...csvData.map(row =>
                headers.map(header => `"${row[header as keyof typeof row]}"`).join(',')
            )
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
            id: "select",
            header: ({ table }) => (
                <Checkbox
                    checked={
                        table.getIsAllPageRowsSelected() ||
                        (table.getIsSomePageRowsSelected() && "indeterminate")
                    }
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: "name",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        Name
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                return (
                    <div className="font-medium">{row.getValue("name")}</div>
                );
            },
        },
        {
            accessorKey: "email",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        Email
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            }, cell: ({ row }) => {
                return (
                    <div className="text-muted-foreground">{row.getValue("email")}</div>
                );
            },
        },
        {
            accessorKey: "role",
            header: "Role",
            cell: ({ row }) => {
                const role = row.getValue("role") as string;
                return (
                    <Badge className={`${getRoleBadgeColor(role)} px-3 py-1 text-xs font-medium`}>
                        {getRoleDisplayName(role)}
                    </Badge>
                );
            },
        },
        {
            accessorKey: "blockchain_address",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        <Shield className="mr-2 h-4 w-4" />
                        Blockchain Address
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const address = row.getValue("blockchain_address") as string;
                return (
                    <div className="text-muted-foreground text-sm font-mono">
                        {address ? (
                            <span className="truncate max-w-[200px] block" title={address}>
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
            accessorKey: "email_verified_at",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        <Mail className="mr-2 h-4 w-4" />
                        Email Verified
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const verifiedAt = row.getValue("email_verified_at") as string;
                return (
                    <div className="text-muted-foreground text-sm">
                        {verifiedAt ? (
                            <div className="flex items-center">
                                <Badge className="bg-green-100 text-green-800 px-2 py-1 text-xs">
                                    Verified
                                </Badge>
                            </div>
                        ) : (
                            <Badge className="bg-yellow-100 text-yellow-800 px-2 py-1 text-xs">
                                Pending
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: "remember_token",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        <Key className="mr-2 h-4 w-4" />
                        Remember Token
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const token = row.getValue("remember_token") as string;
                return (
                    <div className="text-muted-foreground text-sm font-mono">
                        {token ? (
                            <span className="truncate max-w-[150px] block" title={token}>
                                {token.substring(0, 20)}...
                            </span>
                        ) : (
                            <span className="text-muted-foreground/50">None</span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: "created_at",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        <Calendar className="mr-2 h-4 w-4" />
                        Created
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            }, cell: ({ row }) => {
                const dateValue = row.getValue("created_at") as string;

                // Handle null, undefined, or empty string
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
                                day: 'numeric'
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
            accessorKey: "updated_at",
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="h-8 p-0 hover:bg-transparent"
                    >
                        <Clock className="mr-2 h-4 w-4" />
                        Updated
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            }, cell: ({ row }) => {
                const dateValue = row.getValue("updated_at") as string;

                // Handle null, undefined, or empty string more carefully
                if (dateValue === null || dateValue === undefined || dateValue === '' ||
                    dateValue === 'null' || dateValue === 'undefined') {
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
                                day: 'numeric'
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
            id: "actions",
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
                                            duration: 5000
                                        });
                                    }
                                }}
                            >
                                Copy email
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => openEditModal(user)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit user
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => handleDeleteUser(user)}
                                className="text-destructive hover:text-destructive"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete user
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },];

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
    }); return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="border-b pb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight flex items-center">
                                <Users className="h-6 w-6 md:h-8 md:w-8 mr-3 text-primary" />
                                User Management
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm md:text-base">
                                Manage system users and their roles
                            </p>
                        </div>

                        <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                            <DialogTrigger asChild>
                                <Button className="flex items-center space-x-2 shadow-md">
                                    <Plus className="h-4 w-4" />
                                    <span>Add User</span>
                                </Button>
                            </DialogTrigger>                            <DialogContent className="sm:max-w-[600px] p-0 gap-0">
                                <DialogHeader className="px-6 py-6 pb-4 bg-gradient-to-r from-primary/5 to-background border-b">
                                    <div className="flex items-center space-x-3">
                                        <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <Plus className="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <DialogTitle className="text-xl font-semibold text-foreground">Create New User</DialogTitle>
                                            <DialogDescription className="text-sm text-muted-foreground mt-1">
                                                Add a new user to the system with their basic information and access credentials
                                            </DialogDescription>
                                        </div>
                                    </div>
                                </DialogHeader>

                                <ScrollArea className="h-[calc(90vh-200px)] px-6">
                                    <form onSubmit={handleCreateUser} className="space-y-6 pb-6 pt-6">
                                        {/* Personal Information Section */}
                                        <Card className="border-border">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-center space-x-2">
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                    <CardTitle className="text-base">Personal Information</CardTitle>
                                                </div>
                                                <CardDescription className="text-xs text-muted-foreground">
                                                    Enter the user's basic details and contact information
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-4">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="name" className="text-sm font-medium flex items-center">
                                                            <Users className="h-3 w-3 mr-1" />
                                                            Full Name
                                                            <span className="text-destructive ml-1">*</span>
                                                        </Label>
                                                        <Input
                                                            id="name"
                                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                                            placeholder="Enter full name"
                                                            value={formData.name}
                                                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="email" className="text-sm font-medium flex items-center">
                                                            <Mail className="h-3 w-3 mr-1" />
                                                            Email Address
                                                            <span className="text-destructive ml-1">*</span>
                                                        </Label>
                                                        <Input
                                                            id="email"
                                                            type="email"
                                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                                            placeholder="Enter email address"
                                                            value={formData.email}
                                                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="role" className="text-sm font-medium flex items-center">
                                                        <Shield className="h-3 w-3 mr-1" />
                                                        Role & Permissions
                                                        <span className="text-destructive ml-1">*</span>
                                                    </Label>
                                                    <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                                                        <SelectTrigger className="h-11 focus:ring-2 focus:ring-primary/20">
                                                            <SelectValue placeholder="Select a role" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {roles.map((role) => (
                                                                <SelectItem key={role} value={role}>
                                                                    {getRoleDisplayName(role)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </CardContent>
                                        </Card>
                                        {/* Security Information Section */}
                                        <Card className="border-border">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-center space-x-2">
                                                    <Key className="h-4 w-4 text-muted-foreground" />
                                                    <CardTitle className="text-base">Security & Access</CardTitle>
                                                </div>
                                                <CardDescription className="text-xs text-muted-foreground">
                                                    Set up login credentials for this user
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-4">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="password" className="text-sm font-medium flex items-center">
                                                            <Key className="h-3 w-3 mr-1" />
                                                            Password
                                                            <span className="text-destructive ml-1">*</span>
                                                        </Label>
                                                        <Input
                                                            id="password"
                                                            type="password"
                                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                                            placeholder="Enter secure password"
                                                            value={formData.password}
                                                            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="password_confirmation" className="text-sm font-medium flex items-center">
                                                            <Key className="h-3 w-3 mr-1" />
                                                            Confirm Password
                                                            <span className="text-destructive ml-1">*</span>
                                                        </Label>
                                                        <Input
                                                            id="password_confirmation"
                                                            type="password"
                                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                                            placeholder="Confirm password"
                                                            value={formData.password_confirmation}
                                                            onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                        {/* Blockchain Information Section */}
                                        <Card className="border-border border-dashed">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-center space-x-2">
                                                    <div className="h-4 w-4 rounded border-2 border-muted-foreground flex items-center justify-center">
                                                        <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    </div>
                                                    <CardTitle className="text-base text-muted-foreground">Blockchain Integration</CardTitle>
                                                    <Badge variant="secondary" className="text-xs">Optional</Badge>
                                                </div>
                                                <CardDescription className="text-xs text-muted-foreground">
                                                    Associate a blockchain address for enhanced security features
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-2">
                                                    <Label htmlFor="blockchain_address" className="text-sm font-medium text-muted-foreground">
                                                        Blockchain Address
                                                    </Label>
                                                    <Input
                                                        id="blockchain_address"
                                                        className="h-11 focus:ring-2 focus:ring-primary/20 font-mono text-sm"
                                                        placeholder="0x... (optional blockchain address)"
                                                        value={formData.blockchain_address}
                                                        onChange={(e) => setFormData({ ...formData, blockchain_address: e.target.value })}
                                                    />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </form>
                                </ScrollArea>

                                {/* Action Buttons */}
                                <div className="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-6 border-t bg-muted/30 px-6 py-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11 px-6 order-2 sm:order-1"
                                        onClick={() => setIsCreateModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="h-11 px-6 shadow-md order-1 sm:order-2"
                                        onClick={handleCreateUser}
                                    >
                                        <Plus className="h-4 w-4 mr-2" />
                                        Create User
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* Data Table Section */}
                <div className="flex-1">
                    <Card>
                        <CardHeader className="pb-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-lg md:text-xl font-semibold">
                                        Users ({users.length})
                                    </CardTitle>
                                    <CardDescription className="mt-2">
                                        All registered users in the procurement system
                                    </CardDescription>
                                </div>
                            </div>

                            {/* Search and Filter */}
                            <div className="flex items-center space-x-2 mt-6">
                                <Input
                                    placeholder="Search users..."
                                    value={(table.getColumn("name")?.getFilterValue() as string) ?? ""}
                                    onChange={(event) =>
                                        table.getColumn("name")?.setFilterValue(event.target.value)
                                    }
                                    className="max-w-sm h-9"
                                />
                            </div>

                            {/* Bulk Actions Bar */}
                            {table.getFilteredSelectedRowModel().rows.length > 0 && (
                                <div className="flex items-center justify-between p-4 mt-4 bg-accent/50 border border-accent rounded-lg">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-sm font-medium text-accent-foreground">
                                            {table.getFilteredSelectedRowModel().rows.length} user(s) selected
                                        </span>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={exportSelectedToCSV}
                                            className="h-8 border-primary/20 text-primary hover:bg-primary/10"
                                        >
                                            <Download className="h-4 w-4 mr-2" />
                                            Export to CSV
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={handleBulkDelete}
                                            className="h-8"
                                        >
                                            <Trash2 className="h-4 w-4 mr-2" />
                                            Delete Selected
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => table.toggleAllPageRowsSelected(false)}
                                            className="h-8 text-muted-foreground hover:bg-muted hover:text-muted-foreground"
                                        >
                                            Clear Selection
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardHeader>

                        <CardContent className="px-6 pb-6">
                            {users.length === 0 ? (<div className="text-center py-16">
                                <Users className="h-16 w-16 text-muted-foreground/30 mx-auto mb-4" />
                                <p className="text-muted-foreground text-lg font-medium">No users found</p>
                                <p className="text-muted-foreground/70 text-sm mt-2">
                                    Click "Add User" to create your first user
                                </p>
                            </div>
                            ) : (
                                <div className="space-y-6">
                                    {/* Data Table */}
                                    <div className="rounded-md border">
                                        <Table>
                                            <TableHeader>
                                                {table.getHeaderGroups().map((headerGroup) => (
                                                    <TableRow key={headerGroup.id}>
                                                        {headerGroup.headers.map((header) => {
                                                            return (
                                                                <TableHead key={header.id} className="font-semibold">
                                                                    {header.isPlaceholder
                                                                        ? null
                                                                        : flexRender(
                                                                            header.column.columnDef.header,
                                                                            header.getContext()
                                                                        )}
                                                                </TableHead>
                                                            );
                                                        })}
                                                    </TableRow>
                                                ))}
                                            </TableHeader>
                                            <TableBody>
                                                {table.getRowModel().rows?.length ? (
                                                    table.getRowModel().rows.map((row) => (
                                                        <TableRow
                                                            key={row.id} data-state={row.getIsSelected() && "selected"}
                                                            className="hover:bg-muted/50"
                                                        >
                                                            {row.getVisibleCells().map((cell) => (
                                                                <TableCell key={cell.id}>
                                                                    {flexRender(
                                                                        cell.column.columnDef.cell,
                                                                        cell.getContext()
                                                                    )}
                                                                </TableCell>
                                                            ))}
                                                        </TableRow>
                                                    ))
                                                ) : (
                                                    <TableRow>
                                                        <TableCell
                                                            colSpan={columns.length}
                                                            className="h-24 text-center"
                                                        >
                                                            No results.
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                    {/* Pagination */}
                                    <div className="flex flex-col lg:flex-row items-center justify-between space-y-6 lg:space-y-0 py-6 px-2 border-t bg-gradient-to-r from-background via-muted/30 to-background backdrop-blur-sm">
                                        {/* Left side - Selected rows info */}
                                        <div className="flex items-center justify-center lg:justify-start">
                                            <div className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-primary/10 text-primary border border-primary/20">
                                                <span className="w-2 h-2 bg-primary rounded-full mr-2 animate-pulse"></span>
                                                {table.getFilteredSelectedRowModel().rows.length} of{" "}
                                                {table.getFilteredRowModel().rows.length} row(s) selected
                                            </div>
                                        </div>

                                        {/* Right side - Pagination controls and info */}
                                        <div className="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-6">
                                            {/* Page Size Selector */}
                                            <div className="flex items-center space-x-3">
                                                <span className="text-sm font-medium text-muted-foreground">Rows per page:</span>
                                                <Select
                                                    value={table.getState().pagination.pageSize.toString()}
                                                    onValueChange={(value) => table.setPageSize(Number(value))}
                                                >
                                                    <SelectTrigger className="h-9 w-20 border-primary/20 focus:border-primary focus:ring-primary/20 rounded-lg shadow-sm">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent side="top" align="center" className="border-primary/20">
                                                        {[10, 25, 50, 100].map((pageSize) => (
                                                            <SelectItem
                                                                key={pageSize}
                                                                value={pageSize.toString()}
                                                                className="cursor-pointer hover:bg-primary/10 focus:bg-primary/10"
                                                            >
                                                                {pageSize}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            {/* Page Info */}
                                            {table.getPageCount() > 1 && (
                                                <div className="flex items-center space-x-2 text-sm text-muted-foreground font-medium">
                                                    <span>Page</span>
                                                    <span className="px-2 py-1 bg-primary/10 text-primary rounded-md border border-primary/20">
                                                        {table.getState().pagination.pageIndex + 1}
                                                    </span>
                                                    <span>of</span>
                                                    <span className="px-2 py-1 bg-muted rounded-md border">
                                                        {table.getPageCount()}
                                                    </span>
                                                </div>
                                            )}

                                            {/* Pagination Controls */}
                                            {table.getPageCount() > 1 && (
                                                <div className="flex items-center">
                                                    <Pagination className="w-auto">
                                                        <PaginationContent className="gap-1">
                                                            <PaginationItem>
                                                                <PaginationPrevious
                                                                    href="#"
                                                                    onClick={(e) => {
                                                                        e.preventDefault();
                                                                        table.previousPage();
                                                                    }}
                                                                    className={`rounded-lg transition-all duration-200 hover:bg-primary/10 hover:border-primary/30 ${!table.getCanPreviousPage() ? 'pointer-events-none opacity-50' : 'shadow-sm hover:shadow-md'}`}
                                                                />
                                                            </PaginationItem>

                                                            {/* Page Numbers */}
                                                            {(() => {
                                                                const currentPage = table.getState().pagination.pageIndex + 1;
                                                                const totalPages = table.getPageCount();
                                                                const pages = [];

                                                                // Show first page
                                                                pages.push(1);

                                                                // Show ellipsis if needed
                                                                if (currentPage > 3) {
                                                                    pages.push('ellipsis1');
                                                                }

                                                                // Show pages around current
                                                                const start = Math.max(2, currentPage - 1);
                                                                const end = Math.min(totalPages - 1, currentPage + 1);

                                                                for (let i = start; i <= end; i++) {
                                                                    if (i !== 1 && i !== totalPages) {
                                                                        pages.push(i);
                                                                    }
                                                                }

                                                                // Show ellipsis if needed
                                                                if (currentPage < totalPages - 2) {
                                                                    pages.push('ellipsis2');
                                                                }

                                                                // Show last page
                                                                if (totalPages > 1) {
                                                                    pages.push(totalPages);
                                                                }

                                                                return pages.map((page) => {
                                                                    if (typeof page === 'string') {
                                                                        return (
                                                                            <PaginationItem key={page}>
                                                                                <PaginationEllipsis className="text-muted-foreground" />
                                                                            </PaginationItem>
                                                                        );
                                                                    }

                                                                    return (
                                                                        <PaginationItem key={page}>
                                                                            <PaginationLink
                                                                                href="#"
                                                                                onClick={(e) => {
                                                                                    e.preventDefault();
                                                                                    table.setPageIndex(page - 1);
                                                                                }}
                                                                                isActive={currentPage === page}
                                                                                className={`rounded-lg transition-all duration-200 ${currentPage === page
                                                                                    ? 'bg-primary !text-white shadow-md border-primary'
                                                                                    : 'hover:bg-primary/10 hover:border-primary/30 shadow-sm hover:shadow-md'
                                                                                    }`}
                                                                            >
                                                                                {page}
                                                                            </PaginationLink>
                                                                        </PaginationItem>
                                                                    );
                                                                });
                                                            })()}

                                                            <PaginationItem>
                                                                <PaginationNext
                                                                    href="#"
                                                                    onClick={(e) => {
                                                                        e.preventDefault();
                                                                        table.nextPage();
                                                                    }}
                                                                    className={`rounded-lg transition-all duration-200 hover:bg-primary/10 hover:border-primary/30 ${!table.getCanNextPage() ? 'pointer-events-none opacity-50' : 'shadow-sm hover:shadow-md'}`}
                                                                />
                                                            </PaginationItem>
                                                        </PaginationContent>
                                                    </Pagination>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>                {/* Edit User Modal */}
                <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                    <DialogContent className="sm:max-w-[600px] p-0 gap-0">
                        <DialogHeader className="px-6 py-6 pb-4 bg-gradient-to-r from-primary/5 to-background border-b">
                            <div className="flex items-center space-x-3">
                                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <Edit className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <DialogTitle className="text-xl font-semibold text-foreground">Edit User</DialogTitle>
                                    <DialogDescription className="text-sm text-muted-foreground mt-1">
                                        Update user information and access permissions
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>
                        <ScrollArea className="h-[calc(90vh-180px)] px-6">
                            <form onSubmit={handleEditUser} className="space-y-6 pb-6 pt-6">
                                {/* Personal Information Section */}
                                <Card className="border-border">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center space-x-2">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <CardTitle className="text-base">Personal Information</CardTitle>
                                        </div>
                                        <CardDescription className="text-xs text-muted-foreground">
                                            Update user's basic details and contact information
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_name" className="text-sm font-medium flex items-center">
                                                    <Users className="h-3 w-3 mr-1" />
                                                    Full Name
                                                    <span className="text-destructive ml-1">*</span>
                                                </Label>
                                                <Input
                                                    id="edit_name"
                                                    className="h-11 focus:ring-2 focus:ring-primary/20"
                                                    placeholder="Enter full name"
                                                    value={formData.name}
                                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_email" className="text-sm font-medium flex items-center">
                                                    <Mail className="h-3 w-3 mr-1" />
                                                    Email Address
                                                    <span className="text-destructive ml-1">*</span>
                                                </Label>
                                                <Input
                                                    id="edit_email"
                                                    type="email"
                                                    className="h-11 focus:ring-2 focus:ring-primary/20"
                                                    placeholder="Enter email address"
                                                    value={formData.email}
                                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_role" className="text-sm font-medium flex items-center">
                                                <Shield className="h-3 w-3 mr-1" />
                                                Role & Permissions
                                                <span className="text-destructive ml-1">*</span>
                                            </Label>
                                            <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                                                <SelectTrigger className="h-11 focus:ring-2 focus:ring-primary/20">
                                                    <SelectValue placeholder="Select a role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem key={role} value={role}>
                                                            {getRoleDisplayName(role)}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Security Information Section */}
                                <Card className="border-border border-dashed">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center space-x-2">
                                            <Key className="h-4 w-4 text-muted-foreground" />
                                            <CardTitle className="text-base text-muted-foreground">Security & Access</CardTitle>
                                            <Badge variant="secondary" className="text-xs">Optional</Badge>
                                        </div>
                                        <CardDescription className="text-xs text-muted-foreground">
                                            Update login credentials (leave blank to keep current password)
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_password" className="text-sm font-medium flex items-center text-muted-foreground">
                                                    <Key className="h-3 w-3 mr-1" />
                                                    New Password
                                                </Label>
                                                <Input
                                                    id="edit_password"
                                                    type="password"
                                                    className="h-11 focus:ring-2 focus:ring-primary/20"
                                                    placeholder="Leave blank to keep current"
                                                    value={formData.password}
                                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_password_confirmation" className="text-sm font-medium flex items-center text-muted-foreground">
                                                    <Key className="h-3 w-3 mr-1" />
                                                    Confirm New Password
                                                </Label>
                                                <Input
                                                    id="edit_password_confirmation"
                                                    type="password"
                                                    className="h-11 focus:ring-2 focus:ring-primary/20"
                                                    placeholder="Confirm new password"
                                                    value={formData.password_confirmation}
                                                    onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Blockchain Information Section */}
                                <Card className="border-border border-dashed">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center space-x-2">
                                            <div className="h-4 w-4 rounded border-2 border-muted-foreground flex items-center justify-center">
                                                <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                            </div>
                                            <CardTitle className="text-base text-muted-foreground">Blockchain Integration</CardTitle>
                                            <Badge variant="secondary" className="text-xs">Optional</Badge>
                                        </div>
                                        <CardDescription className="text-xs text-muted-foreground">
                                            Update blockchain address for enhanced security features
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_blockchain_address" className="text-sm font-medium text-muted-foreground">
                                                Blockchain Address
                                            </Label>
                                            <Input
                                                id="edit_blockchain_address"
                                                className="h-11 focus:ring-2 focus:ring-primary/20 font-mono text-sm"
                                                placeholder="0x... (optional blockchain address)"
                                                value={formData.blockchain_address}
                                                onChange={(e) => setFormData({ ...formData, blockchain_address: e.target.value })}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </form>
                        </ScrollArea>

                        {/* Action Buttons */}
                        <div className="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-6 border-t bg-muted/30 px-6 py-4">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 px-6 order-2 sm:order-1"
                                onClick={() => setIsEditModalOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                className="h-11 px-6 shadow-md order-1 sm:order-2"
                                onClick={handleEditUser}
                            >
                                <Edit className="h-4 w-4 mr-2" />
                                Update User
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>                {/* Delete User Alert Dialog */}
                <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                    <AlertDialogContent className="sm:max-w-[500px] p-0 gap-0">
                        <AlertDialogHeader className="px-6 py-6 pb-4 bg-gradient-to-r from-destructive/5 to-background border-b">
                            <div className="flex items-center space-x-3">
                                <div className="h-12 w-12 rounded-lg bg-destructive/10 flex items-center justify-center">
                                    <Trash2 className="h-6 w-6 text-destructive" />
                                </div>
                                <div>
                                    <AlertDialogTitle className="text-xl font-semibold text-foreground">
                                        Delete User Account
                                    </AlertDialogTitle>
                                    <AlertDialogDescription className="text-sm text-muted-foreground mt-1">
                                        This action cannot be undone and will permanently remove the user
                                    </AlertDialogDescription>
                                </div>
                            </div>
                        </AlertDialogHeader>

                        <div className="px-6 py-6">
                            <div className="space-y-4">
                                <div className="p-4 bg-destructive/5 border border-destructive/20 rounded-lg">
                                    <div className="flex items-start space-x-3">
                                        <div className="h-5 w-5 rounded-full bg-destructive/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <div className="h-2 w-2 bg-destructive rounded-full"></div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-destructive">
                                                Warning: Permanent Data Loss
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                You are about to permanently delete the user account for{' '}
                                                <span className="font-semibold text-foreground">{userToDelete?.name}</span>
                                                {userToDelete?.email && (
                                                    <>
                                                        {' '}(<span className="font-mono text-xs">{userToDelete.email}</span>)
                                                    </>
                                                )}. This will:
                                            </p>
                                            <ul className="text-sm text-muted-foreground space-y-1 ml-4">
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Remove all user data from the system</span>
                                                </li>
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Revoke all access permissions and roles</span>
                                                </li>
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Invalidate any active sessions</span>
                                                </li>
                                                {userToDelete?.blockchain_address && (
                                                    <li className="flex items-center space-x-2">
                                                        <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                        <span>Disconnect blockchain address association</span>
                                                    </li>
                                                )}
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div className="p-3 bg-muted/30 border border-border rounded-lg">
                                    <p className="text-xs text-muted-foreground text-center">
                                        <strong>Note:</strong> This action is irreversible. Please ensure you have any necessary backups before proceeding.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <AlertDialogFooter className="flex flex-col sm:flex-row gap-2 sm:gap-3 px-6 py-4 border-t bg-muted/30">
                            <AlertDialogCancel className="h-11 px-6 order-2 sm:order-1">
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmDeleteUser}
                                className="h-11 px-6 bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-md order-1 sm:order-2"
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete User Permanently
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                {/* Bulk Delete Alert Dialog */}
                <AlertDialog open={isBulkDeleteDialogOpen} onOpenChange={setIsBulkDeleteDialogOpen}>
                    <AlertDialogContent className="sm:max-w-[500px] p-0 gap-0">
                        <AlertDialogHeader className="px-6 py-6 pb-4 bg-gradient-to-r from-destructive/5 to-background border-b">
                            <div className="flex items-center space-x-3">
                                <div className="h-12 w-12 rounded-lg bg-destructive/10 flex items-center justify-center">
                                    <Trash2 className="h-6 w-6 text-destructive" />
                                </div>
                                <div>
                                    <AlertDialogTitle className="text-xl font-semibold text-foreground">
                                        Delete Multiple Users
                                    </AlertDialogTitle>
                                    <AlertDialogDescription className="text-sm text-muted-foreground mt-1">
                                        This action cannot be undone and will permanently remove all selected users
                                    </AlertDialogDescription>
                                </div>
                            </div>
                        </AlertDialogHeader>

                        <div className="px-6 py-6">
                            <div className="space-y-4">
                                <div className="p-4 bg-destructive/5 border border-destructive/20 rounded-lg">
                                    <div className="flex items-start space-x-3">
                                        <div className="h-5 w-5 rounded-full bg-destructive/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <div className="h-2 w-2 bg-destructive rounded-full"></div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-destructive">
                                                Warning: Bulk Data Deletion
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                You are about to permanently delete{' '}
                                                <span className="font-semibold text-foreground">
                                                    {table.getFilteredSelectedRowModel().rows.length} user account(s)
                                                </span>
                                                . This will:
                                            </p>
                                            <ul className="text-sm text-muted-foreground space-y-1 ml-4">
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Remove all selected user data from the system</span>
                                                </li>
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Revoke all access permissions and roles</span>
                                                </li>
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Invalidate any active sessions</span>
                                                </li>
                                                <li className="flex items-center space-x-2">
                                                    <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                                    <span>Disconnect any blockchain address associations</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                {/* List of users to be deleted */}
                                <div className="p-4 bg-muted/30 border border-border rounded-lg max-h-32 overflow-y-auto">
                                    <p className="text-xs font-medium text-muted-foreground mb-2">Users to be deleted:</p>
                                    <div className="space-y-1">
                                        {table.getFilteredSelectedRowModel().rows.map((row) => (
                                            <div key={row.original.id} className="text-sm text-foreground font-mono">
                                                {row.original.name} ({row.original.email})
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="p-3 bg-muted/30 border border-border rounded-lg">
                                    <p className="text-xs text-muted-foreground text-center">
                                        <strong>Note:</strong> This action is irreversible. Please ensure you have any necessary backups before proceeding.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <AlertDialogFooter className="flex flex-col sm:flex-row gap-2 sm:gap-3 px-6 py-4 border-t bg-muted/30">
                            <AlertDialogCancel className="h-11 px-6 order-2 sm:order-1">
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmBulkDelete}
                                className="h-11 px-6 bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-md order-1 sm:order-2"
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete {table.getFilteredSelectedRowModel().rows.length} User(s) Permanently
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
};
