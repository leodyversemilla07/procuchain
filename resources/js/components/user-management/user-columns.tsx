import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types/user';
import { formatDate, getRoleBadgeColor, getRoleDisplayName } from '@/types/user';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, Edit, History, KeyRound, MoreHorizontal, QrCode, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

interface UserColumnsProps {
    hasPermission: (permission: string) => boolean;
    setSelectedUser: (user: User | null) => void;
    setIsDetailsDialogOpen: (open: boolean) => void;
    setIsLoginHistoryDialogOpen: (open: boolean) => void;
    setIsResetPasswordDialogOpen: (open: boolean) => void;
    openEditModal: (user: User) => void;
    handleDeleteUser: (user: User) => void;
}

function SortableHeader({
    label,
    column,
}: {
    label: string;
    column: { toggleSorting: (asc: boolean) => void; getIsSorted: () => false | 'asc' | 'desc' };
}) {
    return (
        <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')} className="-ml-4 h-10 md:h-8">
            {label}
            {column.getIsSorted() === 'asc' ? (
                <ArrowUp className="ml-2 h-4 w-4" />
            ) : column.getIsSorted() === 'desc' ? (
                <ArrowDown className="ml-2 h-4 w-4" />
            ) : (
                <ArrowUpDown className="ml-2 h-4 w-4 opacity-50" />
            )}
        </Button>
    );
}

export function getUserColumns({
    hasPermission,
    setSelectedUser,
    setIsDetailsDialogOpen,
    setIsLoginHistoryDialogOpen,
    setIsResetPasswordDialogOpen,
    openEditModal,
    handleDeleteUser,
}: UserColumnsProps): ColumnDef<User>[] {
    return [
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
            header: ({ column }) => <SortableHeader label="Name" column={column} />,
            cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
        },
        {
            accessorKey: 'email',
            header: ({ column }) => <SortableHeader label="Email" column={column} />,
            cell: ({ row }) => <div className="text-muted-foreground">{row.getValue('email')}</div>,
        },
        {
            accessorKey: 'role',
            header: ({ column }) => <SortableHeader label="Role" column={column} />,
            cell: ({ row }) => {
                const role = row.getValue('role') as string;
                return (
                    <span className={`${getRoleBadgeColor(role)} inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium`}>
                        {getRoleDisplayName(role)}
                    </span>
                );
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
            meta: { hideBelow: 'xl' },
        },
        {
            accessorKey: 'email_verified_at',
            header: 'Email Verified',
            cell: ({ row }) => {
                const verifiedAt = row.getValue('email_verified_at') as string;
                return verifiedAt ? (
                    <span className="inline-flex items-center rounded-md border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                        Verified
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-md border border-yellow-200 bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:border-yellow-800/30 dark:bg-yellow-900/20 dark:text-yellow-200">
                        Pending
                    </span>
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
                                <span className="inline-flex items-center rounded-md border border-green-200 bg-green-100 px-2 py-1 text-xs text-green-800 dark:border-green-800/30 dark:bg-green-900/20 dark:text-green-200">
                                    <QrCode className="mr-1 h-3 w-3" />
                                    Enabled
                                </span>
                                {backupCodesCount > 0 && (
                                    <span className="text-muted-foreground text-xs" title={`${backupCodesCount} backup codes remaining`}>
                                        ({backupCodesCount} codes)
                                    </span>
                                )}
                            </div>
                        ) : (
                            <span className="inline-flex items-center rounded-md border border-gray-200 bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-300">
                                Disabled
                            </span>
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
                return (
                    <div className="text-muted-foreground text-sm">
                        {d ? (
                            d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                        ) : (
                            <span className="text-muted-foreground/50">No date</span>
                        )}
                    </div>
                );
            },
            meta: { hideBelow: 'xl' },
        },
        {
            accessorKey: 'updated_at',
            header: 'Updated',
            cell: ({ row }) => {
                const d = formatDate(row.getValue('updated_at') as string);
                return (
                    <div className="text-muted-foreground text-sm">
                        {d ? (
                            d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                        ) : (
                            <span className="text-muted-foreground/50">No date</span>
                        )}
                    </div>
                );
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
                        <DropdownMenuTrigger render={<Button variant="ghost" size="icon" className="size-10 md:size-8" />}>
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuItem
                                    onClick={async () => {
                                        try {
                                            await navigator.clipboard.writeText(user.email);
                                            toast.success('Email copied to clipboard', { duration: 3000 });
                                        } catch (error) {
                                            toast.error('Failed to copy email', { description: String(error), duration: 5000 });
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
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelectedUser(user);
                                        setIsLoginHistoryDialogOpen(true);
                                    }}
                                >
                                    <History />
                                    Login History
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelectedUser(user);
                                        setIsResetPasswordDialogOpen(true);
                                    }}
                                >
                                    <KeyRound />
                                    Reset Password
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                {hasPermission('edit users') && (
                                    <DropdownMenuItem onClick={() => openEditModal(user)}>
                                        <Edit />
                                        Edit user
                                    </DropdownMenuItem>
                                )}
                                {hasPermission('delete users') && (
                                    <DropdownMenuItem onClick={() => handleDeleteUser(user)} variant="destructive">
                                        <Trash2 />
                                        Delete user
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];
}
