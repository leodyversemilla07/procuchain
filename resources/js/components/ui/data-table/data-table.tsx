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
import { Input } from "@/components/ui/input";
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { ChevronDownIcon, CircleXIcon, SearchIcon, XIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: readonly TData[];
    searchColumn?: string;
    searchPlaceholder?: string;
    onRowSelectionChange?: (selectedRows: TData[]) => void;
    bulkActions?: { label: string; action: (selectedRows: TData[]) => void; icon?: React.ReactNode }[];
}

export function DataTable<TData, TValue>({
    columns,
    data,
    searchColumn,
    searchPlaceholder = "Filter...",
    onRowSelectionChange,
    bulkActions = []
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [searchValue, setSearchValue] = useState<string>("");

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

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchValue(value);
        if (searchColumn) {
            table.getColumn(searchColumn)?.setFilterValue(value);
        }
    };

    const clearSearch = () => {
        setSearchValue("");
        if (searchColumn) {
            table.getColumn(searchColumn)?.setFilterValue("");
        }
    };

    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((row) => row.original) as TData[];

    const selectedRowCount = table.getSelectedRowModel().rows.length;

    return (
        <div className="space-y-4 w-full max-w-[100vw]">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                {searchColumn && (
                    <div className="relative w-full sm:w-auto sm:min-w-[300px] max-w-full">
                        <SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-500 dark:text-gray-400" />
                        <Input
                            placeholder={searchPlaceholder}
                            value={searchValue}
                            onChange={handleSearchChange}
                            className="pl-10 pr-10 w-full border-sidebar-border/70 dark:border-sidebar-border rounded-lg shadow-sm 
                                dark:placeholder-gray-400 focus:border-primary dark:focus:border-primary 
                                focus:ring-primary dark:focus:ring-primary"
                        />
                        {searchValue && (
                            <button
                                onClick={clearSearch}
                                type="button"
                                aria-label="Clear search"
                                className="absolute right-3 top-1/2 transform -translate-y-1/2 
                                    text-gray-400 hover:text-gray-600 dark:text-gray-500 
                                    dark:hover:text-gray-300"
                            >
                                <XIcon className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                )}

                {bulkActions.length > 0 && (
                    <div className="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        {selectedRowCount > 0 && (
                            <Badge variant="secondary" className="bg-blue-50 text-blue-700 border border-blue-200 
                                dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800">
                                {selectedRowCount} selected
                            </Badge>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={selectedRows.length === 0}
                                    className={`ml-auto text-gray-700 dark:text-gray-200 
                                        hover:text-gray-700 dark:hover:text-gray-200 whitespace-nowrap
                                        ${selectedRows.length === 0
                                            ? 'opacity-50 cursor-not-allowed'
                                            : 'hover:bg-gray-100 dark:hover:bg-gray-800 border-sidebar-border/70 dark:border-sidebar-border'
                                        }`}
                                >
                                    Actions <ChevronDownIcon className="ml-2 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="min-w-[160px] border-sidebar-border/70 dark:border-sidebar-border">
                                {bulkActions.map((action, index) => (
                                    <DropdownMenuItem
                                        key={index}
                                        onClick={() => action.action(selectedRows)}
                                        className="cursor-pointer flex items-center gap-2 text-gray-700 
                                            dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                    >
                                        {action.icon && <span className="text-gray-500 dark:text-gray-400">{action.icon}</span>}
                                        {action.label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}
            </div>

            <div className="rounded-lg overflow-hidden border border-sidebar-border/70 
                dark:border-sidebar-border shadow-sm">
                <div className="overflow-x-auto">
                    <Table className="w-full min-w-[640px]">
                        <TableHeader className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow
                                    key={headerGroup.id}
                                    className="hover:bg-transparent"
                                >
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className="font-semibold text-xs text-gray-700 dark:text-gray-200 
                                                uppercase tracking-wider py-3 px-4 whitespace-nowrap"
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
                                        className={`border-b border-sidebar-border/30 dark:border-sidebar-border/70 
                                            ${row.getIsSelected()
                                                ? "bg-primary/5 dark:bg-primary/10 text-gray-900 dark:text-gray-100"
                                                : "hover:bg-muted/30 dark:hover:bg-muted/10"
                                            }`}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className="py-3 px-4"
                                            >
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={columns.length} className="h-32 text-center">
                                        <div className="flex flex-col items-center justify-center text-gray-500 
                                            dark:text-gray-400 py-8">
                                            <CircleXIcon className="h-12 w-12 text-gray-300 dark:text-gray-600 mb-2" />
                                            <p className="text-base">No records found</p>
                                            <p className="text-sm mt-1">Try adjusting your search or filter to find what you're looking for.</p>
                                        </div>
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
