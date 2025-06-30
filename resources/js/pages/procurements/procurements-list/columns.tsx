import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import { ProcurementListItem } from '@/types/blockchain';
import { Button } from '@/components/ui/button';
import { 
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTableCheckbox, DataTableColumnHeader } from './data-table';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import {
    DocumentCountCell,
    IdCell,
    LastUpdatedCell,
    StageCell,
    StatusCell,
    TitleCell,
} from '@/components/procurements-list/table-cells';

interface ColumnsProps {
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
                onCheckedChange={value => table.toggleAllPageRowsSelected(!!value)}
                title="Select all"
            />
        ),
        cell: ({ row }) => (
            <DataTableCheckbox
                checked={row.getIsSelected()}
                onCheckedChange={value => row.toggleSelected(!!value)}
                title="Select row"
            />
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
                <span className="text-sm text-muted-foreground md:hidden truncate max-w-[150px]">
                    {row.getValue('title')}
                </span>
            </div>
        ),
        size: 120,
    },
    {
        accessorKey: 'title',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
        cell: ({ row }) => <TitleCell procurement={row.original} />,
        meta: {
            className: "hidden md:table-cell",
        },
        minSize: 200,
    },
    {
        accessorKey: 'stage',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Stage" />,
        cell: ({ row }) => <StageCell stage={row.getValue('stage')} />,
        filterFn: (row, id, value) => value.includes(row.getValue(id)),
        meta: {
            className: "hidden lg:table-cell",
        },
        size: 150,
    },
    
    {
        accessorKey: 'current_status',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => <StatusCell status={row.getValue('current_status')} />,
        filterFn: (row, id, value) => value.includes(row.getValue(id)),
        size: 120,
    },
    {
        accessorKey: 'document_count',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Docs" />,
        cell: ({ row }) => <DocumentCountCell count={row.getValue('document_count')} />,
        meta: {
            className: "hidden sm:table-cell",
        },
        size: 80,
    },
    {
        accessorKey: 'last_updated',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Updated" />,
        cell: ({ row }) => <LastUpdatedCell date={row.getValue('last_updated')} />,
        meta: {
            className: "hidden lg:table-cell",
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
                            onClick={() => navigator.clipboard.writeText(procurement.id)}
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
