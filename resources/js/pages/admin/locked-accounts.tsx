import { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
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
import {
    Shield,
    ShieldOff,
    MoreHorizontal,
    Clock,
    User as UserIcon,
    AlertTriangle,
    Unlock,
    RotateCcw,
    RefreshCw
} from 'lucide-react';
import { toast } from 'sonner';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { User, type BreadcrumbItem } from '@/types';

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
        href: route('admin.dashboard'),
    },
    {
        title: 'Locked Accounts',
        href: route('admin.accounts.locked'),
    },
];

export default function AdminLockedAccounts() {
    const { lockedAccounts, flash } = usePage<PageProps>().props;
    const [isUnlockDialogOpen, setIsUnlockDialogOpen] = useState(false);
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // Handle flash messages
    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
        if (flash.warning) {
            toast.warning(flash.warning);
        }
        if (flash.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    const refreshPage = () => {
        setIsLoading(true);
        router.reload({
            onFinish: () => setIsLoading(false),
        });
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

        router.post(`/admin/accounts/${selectedUser.id}/unlock`, {
            reason: 'Unlocked by administrator',
        }, {
            onSuccess: () => {
                setIsUnlockDialogOpen(false);
                setSelectedUser(null);
                // The success message will be shown via Inertia's flash message handling
            },
            onError: (errors) => {
                console.error('Unlock account errors:', errors);
                // Error handling will be done via Inertia's error handling
            },
        });
    };

    const confirmResetAttempts = () => {
        if (!selectedUser) return;

        router.post(`/admin/accounts/${selectedUser.id}/reset-attempts`, {}, {
            onSuccess: () => {
                setIsResetDialogOpen(false);
                setSelectedUser(null);
                // The success message will be shown via Inertia's flash message handling
            },
            onError: (errors) => {
                console.error('Reset attempts errors:', errors);
                // Error handling will be done via Inertia's error handling
            },
        });
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
        if (user.is_currently_locked) {
            return 'bg-red-100 text-red-800 border-red-200';
        }
        return 'bg-orange-100 text-orange-800 border-orange-200';

    }; return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locked Accounts - Admin" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="border-b pb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight flex items-center">
                                <Shield className="h-6 w-6 md:h-8 md:w-8 mr-3 text-primary" />
                                Locked Accounts
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm md:text-base">
                                Manage user accounts that have been locked due to security reasons
                            </p>
                        </div>
                        <Button
                            onClick={refreshPage}
                            disabled={isLoading}
                            variant="outline"
                            className="flex items-center space-x-2"
                        >
                            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                            <span>Refresh</span>
                        </Button>
                    </div>
                </div>
                {/* Error Display */}
                {flash.error && (
                    <Card className="border-red-200 bg-red-50">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2 text-red-800">
                                <AlertTriangle className="h-5 w-5" />
                                <span>{flash.error}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Locked Accounts</CardTitle>
                            <ShieldOff className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{lockedAccounts.length}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Currently Locked</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {lockedAccounts.filter(user => user.is_currently_locked).length}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Failed Attempts</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {lockedAccounts.reduce((sum, user) => sum + (user.failed_login_attempts || 0), 0)}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Locked Accounts Table */}
                <Card>
                    <CardHeader className="pb-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center space-x-2">
                                    <ShieldOff className="h-5 w-5" />
                                    <span>Locked User Accounts</span>
                                </CardTitle>
                                <CardDescription className="mt-2">
                                    Accounts that have been locked due to failed login attempts or administrative action
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="px-6 pb-6">
                        {lockedAccounts.length === 0 ? (
                            <div className="text-center py-8">
                                <Shield className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium mb-2">
                                    No Locked Accounts
                                </h3>
                                <p className="text-muted-foreground">
                                    There are currently no locked user accounts in the system.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>User</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Lock Status</TableHead>
                                            <TableHead>Failed Attempts</TableHead>
                                            <TableHead>Locked At</TableHead>
                                            <TableHead>Expires At</TableHead>
                                            <TableHead>Reason</TableHead>
                                            <TableHead>Time Remaining</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lockedAccounts.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <div className="flex items-center space-x-3">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                                            <UserIcon className="h-4 w-4 text-muted-foreground" />
                                                        </div>
                                                        <div>
                                                            <div className="font-medium">{user.name}</div>
                                                            <div className="text-sm text-muted-foreground">{user.email}</div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getRoleBadgeColor(user.role)}>
                                                        {getRoleDisplayName(user.role)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={getLockStatusColor(user)}
                                                    >
                                                        {user.is_currently_locked ? 'Active Lock' : 'Expired Lock'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        <span className="text-sm font-medium">
                                                            {user.failed_login_attempts || 0}
                                                        </span>
                                                        {(user.failed_login_attempts || 0) >= 3 && (
                                                            <AlertTriangle className="h-4 w-4 text-red-500" />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {formatDateTime(user.locked_at)}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {formatDateTime(user.lock_expires_at)}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm max-w-xs truncate" title={user.locked_reason || 'N/A'}>
                                                        {user.locked_reason || 'N/A'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {user.is_currently_locked ? (
                                                            <div className="flex items-center space-x-1 text-orange-600">
                                                                <Clock className="h-3 w-3" />
                                                                <span>{user.lock_time_remaining || 'N/A'}</span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">Expired</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" className="h-8 w-8 p-0">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuSeparator />
                                                            {user.is_currently_locked && (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleUnlockAccount(user)}
                                                                    className="text-green-600"
                                                                >
                                                                    <Unlock className="mr-2 h-4 w-4" />
                                                                    Unlock Account
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem
                                                                onClick={() => handleResetAttempts(user)}
                                                            >
                                                                <RotateCcw className="mr-2 h-4 w-4" />
                                                                Reset Attempts
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Unlock Account Dialog */}
            <AlertDialog open={isUnlockDialogOpen} onOpenChange={setIsUnlockDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Unlock Account</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to unlock the account for <strong>{selectedUser?.name}</strong>?
                            This will immediately allow them to log in again and reset their failed login attempts.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmUnlockAccount}
                            className="bg-green-600 hover:bg-green-700"
                        >
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
                            Are you sure you want to reset the failed login attempts for <strong>{selectedUser?.name}</strong>?
                            This will set their failed login attempts back to 0.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmResetAttempts}>
                            Reset Attempts
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
