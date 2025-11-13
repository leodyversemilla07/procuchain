import { router } from '@inertiajs/react';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type Column,
    type RowSelectionState,
    type SortingState,
    type VisibilityState,
} from '@tanstack/react-table';
import { ArrowDownIcon, ArrowUpDown, ArrowUpIcon } from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';

import { ErrorState } from '@/components/error-state';
import { Pagination } from '@/components/pagination';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { MobileCardView } from '@/components/procurements-list/mobile-card-view';
import { ProcurementBulkActionsBar } from '@/components/procurements-list/procurement-bulk-actions-bar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { exportProcurementsToCSV } from '@/lib/csv';
import { cn } from '@/lib/utils';
import { ProcurementListItem } from '@/types';

// Re-export cell components for backwards compatibility
export { IdCell, TitleCell, BadgeCell, StageCell, StatusCell, DocumentCountCell, LastUpdatedCell } from './cells';

interface DataTableCheckboxProps {
    checked: boolean | 'indeterminate';
    onCheckedChange: (value: boolean) => void;
    disabled?: boolean;
    title?: string;
}

interface DataTableColumnHeaderProps<TData, TValue> extends React.HTMLAttributes<HTMLDivElement> {
    column: Column<TData, TValue>;
    title: string;
}

export interface ProcurementsDataTableProps {
    columns: ColumnDef<ProcurementListItem>[];
    data: ProcurementListItem[];
    loading: boolean;
    error: string | null;
    userRole: string;
    onRowSelectionChange?: (selectedRows: ProcurementListItem[]) => void;
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
    // Optional server-side pagination controls
    serverTotal?: number;
    pageIndex?: number; // zero-based
    pageSize?: number;
    onNavigatePage?: (pageIndex: number) => void;
    onChangePageSize?: (pageSize: number) => void;
}

export function DataTableCheckbox({ checked, onCheckedChange, disabled = false, title }: DataTableCheckboxProps) {
    return (
        <div className="flex h-full w-full min-w-6 items-center justify-center">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(!!value)}
                disabled={disabled}
                aria-label={title || 'Select row'}
                className="data-[state=checked]:bg-primary data-[state=checked]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:border-primary border-input text-primary-foreground focus:ring-ring focus:ring-offset-background hover:border-primary/80 touch-manipulation rounded-sm transition-all duration-200 focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>
    );
}

export function DataTableColumnHeader<TData, TValue>({ column, title, className }: DataTableColumnHeaderProps<TData, TValue>) {
    if (!column.getCanSort()) {
        return <div className={cn('text-muted-foreground text-xs font-medium tracking-wide uppercase', className)}>{title}</div>;
    }

    const isSorted = column.getIsSorted();

    return (
        <div className={cn('flex items-center space-x-2', className)}>
            <div className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{title}</div>
            <Button variant="ghost" size="sm" onClick={() => column.toggleSorting(isSorted === 'asc')} className="h-8 w-8 p-0">
                {isSorted === 'desc' ? (
                    <ArrowDownIcon className="h-4 w-4" />
                ) : isSorted === 'asc' ? (
                    <ArrowUpIcon className="h-4 w-4" />
                ) : (
                    <ArrowUpDown className="h-4 w-4" />
                )}
                <span className="sr-only">Toggle sort</span>
            </Button>
        </div>
    );
}

export function ProcurementsDataTable({
    columns,
    data,
    loading,
    error,
    userRole,
    onRowSelectionChange,
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
    serverTotal,
    pageIndex,
    pageSize,
    onNavigatePage,
    onChangePageSize,
}: ProcurementsDataTableProps) {
    const [sorting, setSorting] = useState<SortingState>([{ id: 'last_updated', desc: true }]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    const table = useReactTable({
        data: data as ProcurementListItem[],
        columns,
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
        },
        enableRowSelection: true,
        initialState: {
            pagination: {
                pageSize: pageSize ?? 10,
            },
        },
    });

    // Keep table page index in sync with server-controlled page index if provided
    useEffect(() => {
        if (typeof pageIndex === 'number') {
            table.setPageIndex(pageIndex);
        }
        if (typeof pageSize === 'number') {
            table.setPageSize(pageSize);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageIndex, pageSize]);

    useEffect(() => {
        if (onRowSelectionChange) {
            const selectedRows = table.getSelectedRowModel().rows.map((row) => row.original);
            onRowSelectionChange(selectedRows as ProcurementListItem[]);
        }
    }, [rowSelection, onRowSelectionChange, table]);

    const handleRetry = React.useCallback(() => {
        router.reload({ only: ['procurements'] });
    }, []);

    const { selectedRows, selectedRowCount } = useMemo(() => {
        const selectedRowModel = table.getSelectedRowModel();
        const rows = selectedRowModel.rows.map((row) => row.original) as ProcurementListItem[];
        const count = Object.keys(rowSelection).length || selectedRowModel.rows.length;

        return {
            selectedRows: rows,
            selectedRowCount: count,
        };
    }, [rowSelection, table]);

    // Render content based on state
    if (loading) return <LoadingSkeleton />;
    if (error)
        return (
            <ErrorState
                title="Unable to load procurements"
                description={error}
                tone="destructive"
                retryLabel="Try again"
                onRetry={handleRetry}
                className="py-10"
            />
        );
    if (data.length === 0) {
        // Check URL params for active filters
        const params = new URLSearchParams(window.location.search);
        const hasSearch = Boolean(params.get('search'));
        const hasStatus = params.get('status') && params.get('status') !== 'all';
        const hasStage = params.get('stage') && params.get('stage') !== 'all';
        const hasFilters = hasSearch || hasStatus || hasStage;
        
        const title = hasFilters ? 'No procurements match your search' : 'No procurements available yet';
        const description = hasFilters
            ? 'Try adjusting your search or filters to find the procurements you need.'
            : userRole === 'bac_secretariat'
              ? 'Create your first procurement record to start tracking progress across every stage.'
              : 'Once procurements are created, they will appear here with full stage tracking.';

        return (
            <Card className="overflow-hidden">
                <CardContent className="flex justify-center px-6 py-12">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {/* You can use any icon here, e.g. Lucide Inbox */}
                                <svg
                                    width="32"
                                    height="32"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="lucide lucide-inbox text-muted-foreground h-8 w-8"
                                >
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                                    <path d="M5.45 5.11A2 2 0 0 1 7.17 4h9.66a2 2 0 0 1 1.72 1.11l3.1 6.2A2 2 0 0 1 22 12v5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5c0-.28.06-.56.18-.81Z" />
                                </svg>
                            </EmptyMedia>
                        </EmptyHeader>
                        <EmptyTitle>{title}</EmptyTitle>
                        <EmptyDescription>{description}</EmptyDescription>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="w-full">
            <ProcurementBulkActionsBar
                selectedCount={selectedRowCount}
                onExport={selectedRows.length > 0 ? () => exportProcurementsToCSV(selectedRows) : undefined}
                disabled={selectedRows.length === 0}
                className="mb-4"
            />

            {/* Mobile Card View (visible on small screens) */}
            <div className="space-y-3 md:hidden">
                {table.getRowModel().rows.map((row) => (
                    <MobileCardView
                        key={row.id}
                        procurement={row.original}
                        selected={row.getIsSelected()}
                        onSelect={(checked) => row.toggleSelected(checked)}
                        onOpenPreProcurementDialog={onOpenPreProcurementDialog}
                        onOpenPreBidDialog={onOpenPreBidDialog}
                        onOpenSupplementalBidBulletinDialog={onOpenSupplementalBidBulletinDialog}
                        userRole={userRole}
                    />
                ))}
                
                {/* Mobile Pagination */}
                {table.getRowModel().rows.length > 0 && (
                    <div className="flex justify-center pt-4">
                        <Pagination
                            pageIndex={typeof pageIndex === 'number' ? pageIndex : table.getState().pagination.pageIndex}
                            pageSize={typeof pageSize === 'number' ? pageSize : table.getState().pagination.pageSize}
                            pageCount={serverTotal ? Math.max(1, Math.ceil(serverTotal / (typeof pageSize === 'number' ? pageSize : table.getState().pagination.pageSize))) : table.getPageCount()}
                            totalItems={serverTotal ?? table.getFilteredRowModel().rows.length}
                            onPageChange={onNavigatePage ?? table.setPageIndex}
                            onPageSizeChange={onChangePageSize ?? table.setPageSize}
                        />
                    </div>
                )}
            </div>

            {/* Desktop Table View (hidden on small screens) */}
            <Card className="hidden overflow-hidden md:block">
                <CardContent className="px-0 py-0">
                    <div className="overflow-x-auto">
                        <Table role="table" aria-label="Procurements table">
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => {
                                            const columnMeta = header.column.columnDef.meta as { className?: string } | undefined;
                                            return (
                                                <TableHead key={header.id} className={columnMeta?.className}>
                                                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
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
                                            data-state={row.getIsSelected() && 'selected'}
                                            className={row.getIsSelected() ? 'bg-muted/50' : ''}
                                        >
                                            {row.getVisibleCells().map((cell) => {
                                                const columnMeta = cell.column.columnDef.meta as { className?: string } | undefined;
                                                return (
                                                    <TableCell key={cell.id} className={columnMeta?.className}>
                                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                    </TableCell>
                                                );
                                            })}
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
                </CardContent>
                <CardFooter className="justify-end border-t">
                    <Pagination
                        pageIndex={typeof pageIndex === 'number' ? pageIndex : table.getState().pagination.pageIndex}
                        pageSize={typeof pageSize === 'number' ? pageSize : table.getState().pagination.pageSize}
                        pageCount={serverTotal ? Math.max(1, Math.ceil(serverTotal / (typeof pageSize === 'number' ? pageSize : table.getState().pagination.pageSize))) : table.getPageCount()}
                        totalItems={serverTotal ?? table.getFilteredRowModel().rows.length}
                        onPageChange={onNavigatePage ?? table.setPageIndex}
                        onPageSizeChange={onChangePageSize ?? table.setPageSize}
                    />
                </CardFooter>
            </Card>
        </div>
    );
}
