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
        <div className="space-y-4 w-full max-w-[100vw]">
            <div className="flex justify-end items-center gap-4">
                {bulkActions.length > 0 && (
                    <div className="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        {selectedRowCount > 0 && (
                            <Badge variant="secondary" className="bg-blue-50 text-blue-700 border border-blue-200 
                                dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800 px-2.5 py-1">
                                {selectedRowCount} selected
                            </Badge>
                        )}
                        <Button
                            variant="default"
                            size="sm"
                            disabled={selectedRows.length === 0}
                            className="ml-auto whitespace-nowrap bg-blue-600 hover:bg-blue-700 text-white"
                            onClick={() => bulkActions[0]?.action(selectedRows)}
                        >
                            {bulkActions[0]?.icon}
                            Export Selected to CSV
                        </Button>
                    </div>
                )}
            </div>

            <div className="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700/80 shadow-sm">
                <div className="overflow-x-auto">
                    <Table className="w-full min-w-[640px]">
                        <TableHeader className="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700/80">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow
                                    key={headerGroup.id}
                                    className="hover:bg-transparent border-b-0"
                                >
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className="font-semibold text-xs text-gray-600 dark:text-gray-300 
                                                uppercase tracking-wider py-4 px-4 whitespace-nowrap first:pl-6 last:pr-6
                                                bg-gray-50/80 dark:bg-gray-800/80 backdrop-blur-sm"
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
                                        className={`border-b border-gray-100 dark:border-gray-700/50 
                                            ${row.getIsSelected()
                                                ? "bg-blue-50/50 dark:bg-blue-900/20 text-gray-900 dark:text-gray-100"
                                                : "hover:bg-gray-50/80 dark:hover:bg-gray-800/30"
                                            } transition-colors duration-200`}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className="py-4 px-4 text-sm text-gray-700 dark:text-gray-300 first:pl-6 last:pr-6"
                                            >
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={columns.length} className="h-24 text-center text-gray-500 dark:text-gray-400">
                                        No results found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <DataTablePagination table={table} />
        </div>
    );
}
