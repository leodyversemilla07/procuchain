import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ProcurementListItem } from '@/types';

import { ActionButtons } from './action-buttons';
import { DataTableCheckbox, DataTableColumnHeader, DocumentCountCell, IdCell, LastUpdatedCell, ModeCell, StageCell, StatusCell, TitleCell } from './data-table';

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
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
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
        cell: ({ row }) => (
            <ModeCell
                mode={row.original.procurement_mode}
                modeLabel={row.original.procurement_mode_label}
            />
        ),
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
        cell: ({ row }) => {
            const procurement = row.original;
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
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
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
