import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type Column,
    type ColumnFiltersState,
    type RowSelectionState,
    type SortingState,
    type VisibilityState,
} from '@tanstack/react-table';
import { ArrowDownIcon, ArrowUpDown, ArrowUpIcon, CalendarIcon, FileIcon } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';

// Import Wayfinder route helpers for each role
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import { show as adminShow } from '@/routes/admin/procurements';

import { ErrorState } from '@/components/error-state';
import { Pagination } from '@/components/pagination';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { ProcurementBulkActionsBar } from '@/components/procurements-list/procurement-bulk-actions-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getStageBadgeStyle, getStatusBadgeStyle } from '@/constants/procurement-badges';
import { exportProcurementsToCSV } from '@/lib/csv';
import { cn } from '@/lib/utils';
import { ProcurementListItem, SharedData, Stage, Status } from '@/types';

// Helper function to format snake_case to readable label
const formatLabel = (value: string): string => {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

// Helper function to get the correct Wayfinder route based on user role
const getProcurementShowUrl = (role: string, id: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return bacSecretariatShow.url(id);
        case 'bac_chairman':
            return bacChairmanShow.url(id);
        case 'hope':
            return hopeShow.url(id);
        case 'admin':
            return adminShow.url(id);
        default:
            return `/procurements-list/${id}`;
    }
};

// Local hook: detect horizontal truncation efficiently
function useIsTruncated<T extends HTMLElement>(ref: React.RefObject<T | null>, depKey?: unknown) {
    const [isTruncated, setIsTruncated] = useState(false);
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const check = () => setIsTruncated(el.scrollWidth > el.clientWidth);
        check();
        let ro: ResizeObserver | null = null;
        if (typeof ResizeObserver !== 'undefined') {
            ro = new ResizeObserver(() => check());
            ro.observe(el);
        }
        const onResize = () => check();
        window.addEventListener('resize', onResize);
        return () => {
            window.removeEventListener('resize', onResize);
            if (ro) ro.disconnect();
        };
    }, [ref, depKey]);
    return isTruncated;
}

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
    searchValue: string;
    onRowSelectionChange?: (selectedRows: ProcurementListItem[]) => void;
}

export const IdCell = ({ id }: { id: string }) => {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const procurementUrl = getProcurementShowUrl(userRole, id);
    
    return (
        <div className="font-medium text-blue-600 dark:text-blue-400">
            <Link href={procurementUrl} className="flex items-center transition-all duration-150 hover:underline" prefetch="hover" cacheFor="5m">
                <span className="rounded border border-blue-100 bg-blue-50 px-1.5 py-0.5 font-mono text-xs dark:border-blue-800/60 dark:bg-blue-900/30">
                    {id}
                </span>
            </Link>
        </div>
    );
};

export const TitleCell = ({ procurement }: { procurement: ProcurementListItem }) => {
    const textRef = useRef<HTMLDivElement>(null);
    const isTruncated = useIsTruncated(textRef, procurement.title);
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const procurementUrl = getProcurementShowUrl(userRole, procurement.id);
    
    const titleContent = (
        <div ref={textRef} className="max-w-[280px] truncate font-medium" title={procurement.title}>
            <Link
                href={procurementUrl}
                className="text-gray-900 transition-colors duration-150 hover:text-blue-600 hover:underline dark:text-gray-100"
                prefetch="hover"
                cacheFor="5m"
            >
                {procurement.title}
            </Link>
        </div>
    );
    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{titleContent}</TooltipTrigger>
            <TooltipContent className="font-medium">{procurement.title}</TooltipContent>
        </Tooltip>
    ) : (
        titleContent
    );
};

export const BadgeCell = <T extends string>({ value, getStyle }: { value: T; getStyle: (value: T) => string }) => {
    const textRef = useRef<HTMLSpanElement>(null);
    const displayValue = formatLabel(value);
    const isTruncated = useIsTruncated(textRef, displayValue);
    const badge = (
        <Badge
            variant="outline"
            className={cn(
                getStyle(value),
                'inline-flex items-center gap-1.5 overflow-hidden px-2 py-0.5 text-ellipsis whitespace-nowrap',
                'border font-medium shadow-sm transition-all duration-150',
                'max-w-[180px]',
            )}
        >
            <span ref={textRef} className="min-w-0 truncate" title={displayValue}>
                {displayValue}
            </span>
        </Badge>
    );
    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent className="font-medium">{displayValue}</TooltipContent>
        </Tooltip>
    ) : (
        badge
    );
};

export const StageCell = ({ stage }: { stage: Stage }) => (
    <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} />
);

export const StatusCell = ({ status }: { status: Status }) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} />
);

export const DocumentCountCell = ({ count }: { count: number }) => (
    <div className="flex items-center gap-1.5">
        {count !== undefined ? (
            <div className="flex items-center rounded-full bg-blue-50 py-0.5 pr-2 pl-1 dark:bg-blue-900/20">
                <FileIcon className="mr-1 h-3.5 w-3.5 text-blue-500 dark:text-blue-400" />
                <span className="text-xs font-medium text-blue-700 dark:text-blue-300">{count}</span>
            </div>
        ) : (
            // Skeleton loader for deferred document counts
            <div className="flex animate-pulse items-center rounded-full bg-gray-100 py-0.5 pr-2 pl-1 dark:bg-gray-800">
                <div className="mr-1 h-3.5 w-3.5 rounded bg-gray-300 dark:bg-gray-600"></div>
                <div className="h-3 w-4 rounded bg-gray-300 dark:bg-gray-600"></div>
            </div>
        )}
    </div>
);

export const LastUpdatedCell = ({ date }: { date: string }) => {
    const formattedDate = new Date(date);
    const displayDate = !isNaN(formattedDate.getTime())
        ? formattedDate.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          })
        : date;
    return (
        <div className="flex items-center gap-1.5">
            <CalendarIcon className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
            <span className="text-sm font-medium text-gray-600 dark:text-gray-300">{displayDate}</span>
        </div>
    );
};

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

export function ProcurementsDataTable({ columns, data, loading, error, userRole, searchValue, onRowSelectionChange }: ProcurementsDataTableProps) {
    const [sorting, setSorting] = useState<SortingState>([{ id: 'last_updated', desc: true }]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

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

    useEffect(() => {
        if (onRowSelectionChange) {
            const selectedRows = table.getSelectedRowModel().rows.map((row) => row.original);
            onRowSelectionChange(selectedRows as ProcurementListItem[]);
        }
    }, [rowSelection, onRowSelectionChange, table]);

    useEffect(() => {
        const searchColumnId = 'title';
        table.getColumn(searchColumnId)?.setFilterValue(searchValue);
    }, [searchValue, table]);

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
        const hasSearch = Boolean(searchValue.trim());
        const title = hasSearch ? 'No procurements match your search' : 'No procurements available yet';
        const description = hasSearch
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

            {/* Table */}
            <Card className="overflow-hidden">
                <CardContent className="px-0 py-0">
                    <div className="overflow-x-auto">
                        <Table>
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
                        pageIndex={table.getState().pagination.pageIndex}
                        pageSize={table.getState().pagination.pageSize}
                        pageCount={table.getPageCount()}
                        totalItems={table.getFilteredRowModel().rows.length}
                        onPageChange={table.setPageIndex}
                        onPageSizeChange={table.setPageSize}
                    />
                </CardFooter>
            </Card>
        </div>
    );
}
