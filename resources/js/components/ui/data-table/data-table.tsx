import * as React from "react";
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
    ColumnFiltersState,
    getFilteredRowModel,
    RowSelectionState
} from "@tanstack/react-table";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { DataTablePagination } from "@/components/ui/data-table/data-table-pagination";
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: readonly TData[];
    searchValue?: string;
    onRowSelectionChange?: (selectedRows: TData[]) => void;
    bulkActions?: { label: string; action: (selectedRows: TData[]) => void; icon?: React.ReactNode }[];
    initialSorting?: SortingState;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    searchValue = "",
    onRowSelectionChange,
    bulkActions = [],
    initialSorting = []
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>(initialSorting);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    const table = useReactTable({
        data: data as TData[],
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnFilters,
            rowSelection,
        },
        enableRowSelection: true,
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    useEffect(() => {
        if (onRowSelectionChange) {
            const selectedRows = table
                .getSelectedRowModel()
                .rows.map((row) => row.original);
            onRowSelectionChange(selectedRows as TData[]);
        }
    }, [rowSelection, onRowSelectionChange, table]);

    useEffect(() => {
        const searchColumnId = 'title';
        table.getColumn(searchColumnId)?.setFilterValue(searchValue);
    }, [searchValue, table]);

    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((row) => row.original) as TData[];

    const selectedRowCount = table.getSelectedRowModel().rows.length;

    return (
        <div className="space-y-4 w-full">
            {/* Bulk Actions - Responsive */}
            {bulkActions.length > 0 && selectedRowCount > 0 && (
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 p-3 bg-muted/30 rounded-lg border">
                    <Badge variant="secondary" className="bg-primary/10 text-primary border-primary/20 px-2.5 py-1">
                        {selectedRowCount} row{selectedRowCount > 1 ? 's' : ''} selected
                    </Badge>
                    <Button
                        variant="default"
                        size="sm"
                        disabled={selectedRows.length === 0}
                        className="w-full sm:w-auto whitespace-nowrap"
                        onClick={() => bulkActions[0]?.action(selectedRows)}
                    >
                        {bulkActions[0]?.icon}
                        <span className="ml-2">Export Selected to CSV</span>
                    </Button>
                </div>
            )}

            {/* Responsive Table Container */}
            <div className="rounded-lg overflow-hidden border shadow-sm">
                {/* Desktop/Tablet Table View - Hidden on Mobile */}
                <div className="hidden md:block">
                    <div className="overflow-x-auto">
                        <Table className="w-full min-w-[640px]">
                            <TableHeader className="bg-muted/50 border-b">
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow
                                        key={headerGroup.id}
                                        className="hover:bg-transparent border-b-0"
                                    >
                                        {headerGroup.headers.map((header) => (
                                            <TableHead
                                                key={header.id}
                                                className="font-semibold text-xs text-muted-foreground 
                                                    uppercase tracking-wider py-4 px-4 whitespace-nowrap first:pl-6 last:pr-6
                                                    bg-muted/50 backdrop-blur-sm"
                                            >
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                        header.column.columnDef.header,
                                                        header.getContext()
                                                    )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow
                                            key={row.id}
                                            data-state={row.getIsSelected() ? "selected" : undefined}
                                            className={`border-b 
                                                ${row.getIsSelected()
                                                    ? "bg-muted/50 text-foreground"
                                                    : "hover:bg-muted/50"
                                                } transition-colors duration-200`}
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell
                                                    key={cell.id}
                                                    className="py-4 px-4 text-sm text-foreground first:pl-6 last:pr-6"
                                                >
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                                            No results found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                {/* Mobile Card View - Visible on Mobile Only */}
                <div className="block md:hidden">
                    {table.getRowModel().rows?.length ? (
                        <div className="space-y-4 p-4">
                            {table.getRowModel().rows.map((row) => (
                                <div
                                    key={row.id}
                                    className={`rounded-xl border p-4 space-y-4 transition-all duration-200
                                        ${row.getIsSelected()
                                            ? "bg-primary/5 border-primary/30 shadow-lg ring-2 ring-primary/20"
                                            : "bg-card hover:bg-muted/30 border-border shadow-sm"
                                        }`}
                                >
                                    {/* Header with ID and Selection */}
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            {/* ID and Title Section */}
                                            {row.getVisibleCells().map((cell) => {
                                                if (cell.column.id === 'id') {
                                                    return (
                                                        <div key={cell.id} className="mb-2">
                                                            <div className="text-sm font-semibold text-primary">
                                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                            </div>
                                                        </div>
                                                    );
                                                }
                                                if (cell.column.id === 'title') {
                                                    return (
                                                        <div key={cell.id}>
                                                            <div className="text-base font-medium text-foreground leading-tight">
                                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                            </div>
                                                        </div>
                                                    );
                                                }
                                                return null;
                                            })}
                                        </div>
                                        
                                        {/* Selection Checkbox */}
                                        <div className="flex items-center ml-3">
                                            {row.getVisibleCells().find(cell => cell.column.id === 'select') && (
                                                flexRender(
                                                    row.getVisibleCells().find(cell => cell.column.id === 'select')!.column.columnDef.cell,
                                                    row.getVisibleCells().find(cell => cell.column.id === 'select')!.getContext()
                                                )
                                            )}
                                        </div>
                                    </div>

                                    {/* Status and Stage Pills */}
                                    <div className="flex flex-wrap gap-2">
                                        {row.getVisibleCells().map((cell) => {
                                            if (cell.column.id === 'stage') {
                                                return (
                                                    <div key={cell.id} className="flex items-center">
                                                        <span className="text-xs text-muted-foreground mr-1">Stage:</span>
                                                        <div className="text-xs">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </div>
                                                    </div>
                                                );
                                            }
                                            if (cell.column.id === 'current_status') {
                                                return (
                                                    <div key={cell.id} className="flex items-center">
                                                        <span className="text-xs text-muted-foreground mr-1">Status:</span>
                                                        <div className="text-xs">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </div>
                                                    </div>
                                                );
                                            }
                                            return null;
                                        })}
                                    </div>

                                    {/* Bottom Row - Documents, Date, Actions */}
                                    <div className="flex flex-col gap-3 pt-3 border-t border-border/30">
                                        {/* Documents and Date Row */}
                                        <div className="flex items-center space-x-4 text-xs text-muted-foreground">
                                            {/* Documents Count */}
                                            {row.getVisibleCells().map((cell) => {
                                                if (cell.column.id === 'document_count') {
                                                    return (
                                                        <div key={cell.id} className="flex items-center space-x-1">
                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span>{flexRender(cell.column.columnDef.cell, cell.getContext())} docs</span>
                                                        </div>
                                                    );
                                                }
                                                return null;
                                            })}
                                            
                                            {/* Last Updated */}
                                            {row.getVisibleCells().map((cell) => {
                                                if (cell.column.id === 'last_updated') {
                                                    return (
                                                        <div key={cell.id} className="flex items-center space-x-1">
                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span>{flexRender(cell.column.columnDef.cell, cell.getContext())}</span>
                                                        </div>
                                                    );
                                                }
                                                return null;
                                            })}
                                        </div>

                                        {/* Actions Row - Full width for better visibility */}
                                        <div className="flex items-center justify-center w-full">
                                            {row.getVisibleCells().map((cell) => {
                                                if (cell.column.id === 'actions') {
                                                    return (
                                                        <div key={cell.id} className="w-full flex justify-center">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </div>
                                                    );
                                                }
                                                return null;
                                            })}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <div className="space-y-3">
                                <div className="w-12 h-12 mx-auto rounded-full bg-muted/50 flex items-center justify-center">
                                    <svg className="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-foreground">No results found</p>
                                    <p className="text-xs text-muted-foreground mt-1">Try adjusting your search criteria</p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <DataTablePagination table={table} />
        </div>
    );
}
