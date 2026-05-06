import { router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { ProcurementListItem } from '@/types';

import procurementRoutes from '@/routes/procurement';
import { ActionButtons } from './action-buttons';
import {
    DataTableCheckbox,
    DataTableColumnHeader,
    DocumentCountCell,
    IdCell,
    LastUpdatedCell,
    ModeCell,
    StageCell,
    StatusCell,
    TitleCell,
} from './data-table';

export interface ColumnsProps {
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
}

export const createColumns = ({
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
}: ColumnsProps): ColumnDef<ProcurementListItem>[] => [
    {
        id: 'select',
        header: ({ table }) => (
            <DataTableCheckbox
                checked={table.getIsAllPageRowsSelected()}
                indeterminate={!table.getIsAllPageRowsSelected() && table.getIsSomePageRowsSelected()}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                title="Select all"
            />
        ),
        cell: ({ row }) => (
            <DataTableCheckbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} title="Select row" />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: 'id',
        header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }) => (
            <div className="flex flex-col">
                <IdCell id={row.getValue('id')} />
                <span className="text-muted-foreground max-w-[150px] truncate text-sm md:hidden">{row.getValue('title')}</span>
            </div>
        ),
        size: 120,
    },
    {
        accessorKey: 'title',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
        cell: ({ row }) => <TitleCell procurement={row.original} />,
        meta: {
            className: 'hidden md:table-cell',
        },
        minSize: 200,
    },
    {
        accessorKey: 'stage',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Stage" />,
        cell: ({ row }) => <StageCell stage={row.getValue('stage')} />,
        filterFn: (row, id, value) => Array.isArray(value) && value.includes(row.getValue(id)),
        meta: {
            className: 'hidden lg:table-cell',
        },
        size: 150,
    },
    {
        accessorKey: 'procurement_mode',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Mode" />,
        cell: ({ row }) => <ModeCell mode={row.original.procurement_mode} modeLabel={row.original.procurement_mode_label} />,
        filterFn: (row, id, value) => Array.isArray(value) && value.includes(row.getValue(id)),
        meta: {
            className: 'hidden xl:table-cell',
        },
        size: 80,
    },
    {
        accessorKey: 'current_status',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => <StatusCell status={row.getValue('current_status')} />,
        filterFn: (row, id, value) => Array.isArray(value) && value.includes(row.getValue(id)),
        size: 120,
    },
    {
        accessorKey: 'document_count',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Docs" />,
        cell: ({ row }) => <DocumentCountCell count={row.getValue('document_count')} />,
        meta: {
            className: 'hidden sm:table-cell',
        },
        size: 80,
    },
    {
        accessorKey: 'last_updated',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Updated" />,
        cell: ({ row }) => <LastUpdatedCell date={row.getValue('last_updated')} />,
        sortingFn: (a, b, id) => {
            const av = a.getValue<string>(id);
            const bv = b.getValue<string>(id);
            const at = Date.parse(av || '');
            const bt = Date.parse(bv || '');
            const an = Number.isNaN(at) ? 0 : at;
            const bn = Number.isNaN(bt) ? 0 : bt;
            return an === bn ? 0 : an > bn ? 1 : -1;
        },
        meta: {
            className: 'hidden lg:table-cell',
        },
        size: 140,
    },
    {
        id: 'actions',
        cell: ({ row }) => (
            <ActionsCell
                procurement={row.original}
                onOpenPreProcurementDialog={onOpenPreProcurementDialog}
                onOpenPreBidDialog={onOpenPreBidDialog}
                onOpenSupplementalBidBulletinDialog={onOpenSupplementalBidBulletinDialog}
            />
        ),
    },
];

interface ActionsCellProps extends ColumnsProps {
    procurement: ProcurementListItem;
}

const ActionsCell = ({ procurement, onOpenPreProcurementDialog, onOpenPreBidDialog, onOpenSupplementalBidBulletinDialog }: ActionsCellProps) => {
    const [showArchiveDialog, setShowArchiveDialog] = useState(false);
    const [showRestoreDialog, setShowRestoreDialog] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
                    <span className="sr-only">Open menu</span>
                    <MoreHorizontal className="h-4 w-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <div className="text-muted-foreground px-1.5 py-1 text-xs font-medium">Actions</div>
                    <DropdownMenuItem
                        onClick={async () => {
                            try {
                                await navigator.clipboard.writeText(procurement.id);
                                toast.success('Procurement ID copied');
                            } catch {
                                toast.error('Failed to copy ID');
                            }
                        }}
                    >
                        Copy procurement ID
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <ActionButtons
                        procurement={procurement}
                        onOpenPreProcurementDialog={onOpenPreProcurementDialog}
                        onOpenPreBidDialog={onOpenPreBidDialog}
                        onOpenSupplementalBidBulletinDialog={onOpenSupplementalBidBulletinDialog}
                    />
                    {(procurement.stage === 'COMPLETED' || procurement.stage === 'completed' || procurement.current_status === 'COMPLETED') &&
                        !procurement.is_archived && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={(e) => {
                                        e.preventDefault();
                                        setShowArchiveDialog(true);
                                    }}
                                    className="text-red-600 focus:text-red-600"
                                >
                                    Archive Procurement
                                </DropdownMenuItem>
                            </>
                        )}
                    {procurement.is_archived && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={(e) => {
                                    e.preventDefault();
                                    setShowRestoreDialog(true);
                                }}
                            >
                                Restore Procurement
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <AlertDialog open={showArchiveDialog} onOpenChange={setShowArchiveDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Archive Procurement?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will move <strong>{procurement.title}</strong> to the archived list. It will be hidden from the active view but
                            remains on the blockchain.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                router.post(
                                    procurementRoutes.archive.url({ pr_number: procurement.id }),
                                    {
                                        reason: 'User archived from list',
                                    },
                                    {
                                        onSuccess: () => {
                                            toast.success('Procurement archived');
                                            setShowArchiveDialog(false);
                                        },
                                        onError: () => toast.error('Failed to archive'),
                                    },
                                );
                            }}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Archive
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={showRestoreDialog} onOpenChange={setShowRestoreDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore Procurement?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will move <strong>{procurement.title}</strong> back to the active list.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                router.delete(procurementRoutes.restore.url({ pr_number: procurement.id }), {
                                    onSuccess: () => {
                                        toast.success('Procurement restored');
                                        setShowRestoreDialog(false);
                                    },
                                    onError: () => toast.error('Failed to restore'),
                                });
                            }}
                        >
                            Restore
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
};
