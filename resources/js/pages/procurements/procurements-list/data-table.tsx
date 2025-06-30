import React from 'react';
import { Download } from 'lucide-react';
import { ColumnDef } from '@tanstack/react-table';
import {
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnFiltersState,
    type SortingState,
    type VisibilityState,
    type RowSelectionState,
    type Column,
} from '@tanstack/react-table';
import { ArrowUpDown, ArrowUpIcon, ArrowDownIcon, EyeOffIcon } from 'lucide-react';

import { ProcurementListItem } from '@/types/blockchain';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { ErrorState } from '@/components/procurements-list/error-state';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { exportProcurementsToCSV } from '@/lib/procurement-utils';
import { cn } from '@/lib/utils';
import { flexRender } from '@tanstack/react-table';
import { Pagination } from "@/components/pagination";

interface DataTableCheckboxProps {
    checked: boolean | "indeterminate";
    onCheckedChange: (value: boolean) => void;
    disabled?: boolean;
    title?: string;
}

export function DataTableCheckbox({
    checked,
    onCheckedChange,
    disabled = false,
    title
}: DataTableCheckboxProps) {
    return (
        <div className="flex items-center justify-center w-full h-full min-w-[24px]">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => {
                    onCheckedChange(value === true);
                }}
                disabled={disabled}
                aria-label={title || "Select row"}
                className="rounded-sm touch-manipulation
                    data-[state=checked]:bg-primary data-[state=checked]:border-primary 
                    data-[state=indeterminate]:bg-primary data-[state=indeterminate]:border-primary 
                    border-input 
                    text-primary-foreground
                    focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background
                    disabled:opacity-50 disabled:cursor-not-allowed
                    transition-all duration-200
                    hover:border-primary/80"
            />
        </div>
    );
}

interface DataTableColumnHeaderProps<TData, TValue>
    extends React.HTMLAttributes<HTMLDivElement> {
    column: Column<TData, TValue>;
    title: string;
}

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    if (!column.getCanSort()) {
        return (
            <div className={cn(
                "font-semibold text-xs text-foreground truncate max-w-[150px] sm:max-w-none",
                className
            )}>
                {title}
            </div>
        );
    }

    return (
        <div className={cn("flex items-center space-x-2", className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-3 h-8 font-semibold text-xs text-foreground 
                            hover:bg-accent hover:text-accent-foreground 
                            data-[state=open]:bg-accent data-[state=open]:text-accent-foreground
                            truncate max-w-[150px] sm:max-w-none justify-start"
                    >
                        <span className="truncate">{title}</span>
                        {column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-3.5 w-3.5 shrink-0 text-primary" />
                        ) : column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-3.5 w-3.5 shrink-0 text-primary" />
                        ) : (
                            <ArrowUpDown className="ml-2 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent 
                    align="start" 
                    className="min-w-[150px] p-1.5 bg-popover border-border shadow-md"
                >
                    <DropdownMenuItem 
                        onClick={() => column.toggleSorting(false)}
                        className={cn(
                            "flex items-center cursor-pointer rounded px-2.5 py-1.5 text-popover-foreground", 
                            column.getIsSorted() === "asc" 
                                ? "bg-accent text-accent-foreground" 
                                : "hover:bg-accent hover:text-accent-foreground"
                        )}
                    >
                        <ArrowUpIcon className="mr-2 h-3.5 w-3.5 text-muted-foreground" />
                        <span>Sort Ascending</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem 
                        onClick={() => column.toggleSorting(true)}
                        className={cn(
                            "flex items-center cursor-pointer rounded px-2.5 py-1.5 text-popover-foreground", 
                            column.getIsSorted() === "desc" 
                                ? "bg-accent text-accent-foreground" 
                                : "hover:bg-accent hover:text-accent-foreground"
                        )}
                    >
                        <ArrowDownIcon className="mr-2 h-3.5 w-3.5 text-muted-foreground" />
                        <span>Sort Descending</span>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="my-1 h-px bg-border" />
                    <DropdownMenuItem 
                        onClick={() => column.toggleVisibility(false)}
                        className="flex items-center cursor-pointer rounded px-2.5 py-1.5 
                            text-popover-foreground hover:bg-accent hover:text-accent-foreground"
                    >
                        <EyeOffIcon className="mr-2 h-3.5 w-3.5 text-muted-foreground" />
                        <span>Hide Column</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

interface ProcurementsDataTableProps {
    columns: ColumnDef<ProcurementListItem>[];
    data: ProcurementListItem[];
    loading: boolean;
    error: string | null;
    userRole: string;
    searchValue: string;
    onRowSelectionChange?: (selectedRows: ProcurementListItem[]) => void;
}

export function ProcurementsDataTable({
    columns,
    data,
    loading,
    error,
    userRole,
    searchValue,
    onRowSelectionChange,
}: ProcurementsDataTableProps) {
    const [sorting, setSorting] = React.useState<SortingState>([{ id: 'last_updated', desc: true }]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});

    const table = useReactTable({
        data: data as ProcurementListItem[],
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
        },
        enableRowSelection: true,
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    React.useEffect(() => {
        if (onRowSelectionChange) {
            const selectedRows = table
                .getSelectedRowModel()
                .rows.map((row) => row.original);
            onRowSelectionChange(selectedRows as ProcurementListItem[]);
        }
    }, [rowSelection, onRowSelectionChange, table]);

    React.useEffect(() => {
        const searchColumnId = 'title';
        table.getColumn(searchColumnId)?.setFilterValue(searchValue);
    }, [searchValue, table]);

    // Render content based on state
    if (loading) return <LoadingSkeleton />;
    if (error) return <ErrorState error={error} />;
    if (data.length === 0) return <EmptyState userRole={userRole} />;

    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((row) => row.original) as ProcurementListItem[];

    const selectedRowCount = table.getSelectedRowModel().rows.length;

    return (
        <div className="w-full">
            
        {/* Bulk Actions */}
            <div className="flex items-center justify-between mb-4">
                {selectedRowCount > 0 ? (
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 p-3 bg-muted/30 rounded-lg border flex-1">
                        <Badge variant="secondary" className="bg-primary/10 text-primary border-primary/20 px-2.5 py-1">
                            {selectedRowCount} row{selectedRowCount > 1 ? 's' : ''} selected
                        </Badge>
                        <Button
                            variant="default"
                            size="sm"
                            disabled={selectedRows.length === 0}
                            className="w-full sm:w-auto whitespace-nowrap"
                            onClick={() => exportProcurementsToCSV(selectedRows)}
                        >
                            <Download className="h-4 w-4" />
                            <span className="ml-2">Export to CSV</span>
                        </Button>
                    </div>
                ) : null}
            </div>

            {/* Table */}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const columnMeta = header.column.columnDef.meta as { className?: string } | undefined;
                                    return (
                                        <TableHead key={header.id} className={columnMeta?.className}>
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
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className={row.getIsSelected() ? "bg-muted/50" : ""}
                                >
                                    {row.getVisibleCells().map((cell) => {
                                        const columnMeta = cell.column.columnDef.meta as { className?: string } | undefined;
                                        return (
                                            <TableCell key={cell.id} className={columnMeta?.className}>
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext()
                                                )}
                                            </TableCell>
                                        );
                                    })}
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
            <div className="mt-4">
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
    );
}
