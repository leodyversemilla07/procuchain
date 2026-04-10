import SendInvitationDialog from '@/components/admin/send-invitation-dialog';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
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
    type SortingState,
} from '@tanstack/react-table';
import { Clock, Mail, MailCheck, MailX, MoreHorizontal, Plus, RefreshCw, Search, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Invitation {
    id: number;
    email: string;
    name: string;
    role: string;
    role_display: string;
    invited_by: {
        id: number;
        name: string;
        email: string;
    };
    expires_at: string;
    expires_at_human: string;
    accepted_at?: string;
    revoked: boolean;
    revoked_at?: string;
    revoked_by?: {
        id: number;
        name: string;
        email: string;
    };
    user?: {
        id: number;
        name: string;
        email: string;
    };
    status: 'pending' | 'accepted' | 'revoked' | 'expired';
    is_valid: boolean;
    is_pending: boolean;
    created_at: string;
}

interface PageProps {
    invitations: Invitation[];
    roles: string[];
    [key: string]: unknown;
}

export default function UserInvitations() {
    const { invitations, roles } = usePage<PageProps>().props;
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState('');

    // State for dialogs
    const [showSendInvitation, setShowSendInvitation] = useState(false);

    // Stats
    const stats = useMemo(() => {
        const pending = invitations.filter((inv) => inv.status === 'pending').length;
        const accepted = invitations.filter((inv) => inv.status === 'accepted').length;
        const expired = invitations.filter((inv) => inv.status === 'expired').length;
        const revoked = invitations.filter((inv) => inv.status === 'revoked').length;

        return [
            {
                title: 'Pending Invitations',
                value: pending.toString(),
                description: 'Awaiting acceptance',
                icon: Clock,
                iconColor: 'text-amber-500',
            },
            {
                title: 'Accepted',
                value: accepted.toString(),
                description: 'Invitations accepted',
                icon: MailCheck,
                iconColor: 'text-emerald-500',
            },
            {
                title: 'Expired',
                value: expired.toString(),
                description: 'Invitations expired',
                icon: MailX,
                iconColor: 'text-gray-500',
            },
            {
                title: 'Revoked',
                value: revoked.toString(),
                description: 'Invitations revoked',
                icon: X,
                iconColor: 'text-rose-500',
            },
        ];
    }, [invitations]);

    const handleResend = (invitation: Invitation) => {
        router.post(
            `/admin/invitations/${invitation.id}/resend`,
            {},
            {
                onSuccess: () => {
                    toast.success('Invitation resent successfully');
                },
                onError: () => {
                    toast.error('Failed to resend invitation');
                },
            },
        );
    };

    const handleRevoke = (invitation: Invitation) => {
        if (!confirm('Are you sure you want to revoke this invitation?')) {
            return;
        }

        router.delete(`/admin/invitations/${invitation.id}`, {
            onSuccess: () => {
                toast.success('Invitation revoked successfully');
            },
            onError: () => {
                toast.error('Failed to revoke invitation');
            },
        });
    };

    const getStatusBadge = (status: Invitation['status']) => {
        const variants = {
            pending: { variant: 'default' as const, label: 'Pending', className: '' },
            accepted: { variant: 'default' as const, label: 'Accepted', className: 'bg-emerald-500 hover:bg-emerald-600' },
            expired: { variant: 'secondary' as const, label: 'Expired', className: '' },
            revoked: { variant: 'destructive' as const, label: 'Revoked', className: '' },
        };

        const config = variants[status];
        return (
            <Badge variant={config.variant} className={config.className}>
                {config.label}
            </Badge>
        );
    };

    const columns: ColumnDef<Invitation>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.original.name}</span>
                    <span className="text-muted-foreground text-sm">{row.original.email}</span>
                </div>
            ),
        },
        {
            accessorKey: 'role',
            header: 'Role',
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono">
                    {row.original.role_display}
                </Badge>
            ),
        },
        {
            accessorKey: 'invited_by',
            header: 'Invited By',
            cell: ({ row }) => <span className="text-sm">{row.original.invited_by.name}</span>,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => getStatusBadge(row.original.status),
        },
        {
            accessorKey: 'expires_at',
            header: 'Expires',
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-sm">{row.original.expires_at_human}</span>
                    <span className="text-muted-foreground text-xs">{new Date(row.original.expires_at).toLocaleDateString()}</span>
                </div>
            ),
        },
        {
            accessorKey: 'created_at',
            header: 'Sent',
            cell: ({ row }) => <span className="text-sm">{new Date(row.original.created_at).toLocaleDateString()}</span>,
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const invitation = row.original;
                const canResend = invitation.is_pending;
                const canRevoke = invitation.status === 'pending';

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <div className="px-1.5 py-1 text-xs font-medium text-muted-foreground">Actions</div>
                            <DropdownMenuSeparator />
                            {canResend && (
                                <DropdownMenuItem onClick={() => handleResend(invitation)}>
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Resend Invitation
                                </DropdownMenuItem>
                            )}
                            {canRevoke && (
                                <DropdownMenuItem onClick={() => handleRevoke(invitation)} className="text-destructive focus:text-destructive">
                                    <X className="mr-2 h-4 w-4" />
                                    Revoke Invitation
                                </DropdownMenuItem>
                            )}
                            {!canResend && !canRevoke && <DropdownMenuItem disabled>No actions available</DropdownMenuItem>}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    const table = useReactTable({
        data: invitations,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        state: {
            sorting,
            columnFilters,
            globalFilter,
        },
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard.url() },
                { title: 'User Invitations', href: '/admin/invitations' },
            ]}
        >
            <Head title="User Invitations" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    title="User Invitations"
                    description="Send and manage user invitations to join the Procuchain system"
                    icon={UserPlus}
                    actions={
                        <Button onClick={() => setShowSendInvitation(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Send Invitation
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat, index) => (
                        <Card key={index}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                                <stat.icon className={`h-4 w-4 ${stat.iconColor}`} />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stat.value}</div>
                                <p className="text-muted-foreground text-xs">{stat.description}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>All Invitations</CardTitle>
                            <div className="relative w-64">
                                <Search className="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input
                                    placeholder="Search invitations..."
                                    value={globalFilter}
                                    onChange={(e) => setGlobalFilter(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {invitations.length === 0 ? (
                            <Empty>
                                <EmptyMedia>
                                    <Mail className="text-muted-foreground/50 h-16 w-16" />
                                </EmptyMedia>
                                <EmptyHeader>
                                    <EmptyTitle>No invitations yet</EmptyTitle>
                                    <EmptyDescription>Send your first invitation to add users to the system</EmptyDescription>
                                </EmptyHeader>
                                <Button onClick={() => setShowSendInvitation(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Send First Invitation
                                </Button>
                            </Empty>
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            {table.getHeaderGroups().map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map((header) => (
                                                        <TableHead key={header.id}>
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(header.column.columnDef.header, header.getContext())}
                                                        </TableHead>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableHeader>
                                        <TableBody>
                                            {table.getRowModel().rows?.length ? (
                                                table.getRowModel().rows.map((row) => (
                                                    <TableRow key={row.id}>
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
                                </div>

                                <div className="flex items-center justify-between px-2 py-4">
                                    <div className="text-muted-foreground flex-1 text-sm">
                                        {table.getFilteredSelectedRowModel().rows.length} of {table.getFilteredRowModel().rows.length} row(s)
                                        selected.
                                    </div>
                                    <div className="flex items-center space-x-6 lg:space-x-8">
                                        <div className="flex items-center space-x-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => table.previousPage()}
                                                disabled={!table.getCanPreviousPage()}
                                            >
                                                Previous
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Dialogs */}
            <SendInvitationDialog open={showSendInvitation} onOpenChange={setShowSendInvitation} roles={roles} />
        </AppLayout>
    );
}
