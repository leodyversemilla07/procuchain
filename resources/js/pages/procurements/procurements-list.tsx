// External imports
import React, { useRef, useEffect, useState } from 'react';
import {
    ColumnDef,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    flexRender,
    type ColumnFiltersState,
    type SortingState,
    type VisibilityState,
    type RowSelectionState,
    type Column,
} from '@tanstack/react-table';
import { Download, ArrowUpDown, ArrowUpIcon, ArrowDownIcon, EyeOffIcon, MoreHorizontal, CalendarIcon, FileIcon, CheckCircle, Clock, AlertCircle, Milestone, FileText, Award, PlayCircle, Monitor, Check, ListChecks, FileCheck, FileQuestion, HelpCircle, Activity, Archive, RefreshCw, Plus, Search } from 'lucide-react';
import { Link, usePage, Head, router, usePoll } from '@inertiajs/react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

// Internal imports
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SharedData } from '@/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { ErrorState } from '@/components/procurements-list/error-state';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { Pagination } from '@/components/pagination';
import { toast } from 'sonner';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { PreBidConferenceDialog } from '@/components/pre-bid-conference-dialog';
import { PreProcurementDialog } from '@/components/pre-procurement-conference-dialog';
import { SupplementalBidBulletinDialog } from '@/components/supplemental-bid-bulletin-dialog';
import { useProcurementList } from '@/hooks/use-procurement-list';
import { BreadcrumbItem } from '@/types';

type CSVValue = string | number | null | undefined;

const formatCSVValue = (value: CSVValue): string => {
    if (value === null || value === undefined) return '';
    const stringValue = String(value);
    return stringValue.includes(',') || stringValue.includes('"') || /[\r\n]/.test(stringValue)
        ? `"${stringValue.replace(/"/g, '""')}"`
        : stringValue;
};

const getProcurementRowData = (procurement: ProcurementListItem): string[] => [
    procurement.id,
    procurement.title,
    procurement.stage,
    procurement.current_status,
    procurement.document_count?.toString() || '0',
    procurement.last_updated || '',
    procurement.timestamp || '',
];

const generateCSVContent = (procurements: ProcurementListItem[]): string => {
    const headers = ['ID', 'Title', 'Phase', 'State', 'Documents', 'Last Updated', 'Timestamp'];
    const rows = procurements.map(proc =>
        getProcurementRowData(proc).map(formatCSVValue).join(',')
    );
    // CRLF for better compatibility with Excel on Windows
    return [headers.join(','), ...rows].join('\r\n');
};

const downloadCSV = (content: string, fileName: string): void => {
    // Prepend UTF-8 BOM for Excel
    const blob = new Blob(['\uFEFF' + content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.setAttribute('href', url);
    link.setAttribute('download', fileName);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

export const exportProcurementsToCSV = (procurements: ProcurementListItem[]): void => {
    try {
        const fileName = `procurements-export-${new Date().toISOString().slice(0, 10)}.csv`;
        const csvContent = generateCSVContent(procurements);
        downloadCSV(csvContent, fileName);

        toast.success(`Successfully exported ${procurements.length} procurements to CSV`, {
            description: `File: ${fileName}`,
            duration: 5000,
        });
    } catch (e) {
        console.error('Failed to export CSV:', e);
        toast.error('Failed to export data to CSV', {
            description: 'Please try again later',
            duration: 5000,
        });
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

// Utility constants and functions
const STATUS_BADGE_STYLES: Record<Status, string> = {
    [Status.PROCUREMENT_SUBMITTED]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#235E6F]',
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: 'bg-[#005F5F] text-white border border-[#005F5F] hover:bg-[#007C91]',
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: 'bg-[#4C9085] text-white border border-[#4C9085] hover:bg-[#3C9D9B]',
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: 'bg-[#008B84] text-white border border-[#008B84] hover:bg-[#2F8F89]',
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#93CCC6]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: 'bg-[#4F7CAC] text-white border border-[#4F7CAC] hover:bg-[#4B9EA1]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#B2DFDB]',
    [Status.PRE_BID_CONFERENCE_HELD]: 'bg-[#3C8B7D] text-white border border-[#3C8B7D] hover:bg-[#45B8AC]',
    [Status.PRE_BID_CONFERENCE_SKIPPED]: 'bg-[#3C7A6B] text-white border border-[#3C7A6B] hover:bg-[#266362]',
    [Status.PRE_BID_CONFERENCE_COMPLETED]: 'bg-[#1A4F4F] text-white border border-[#1A4F4F] hover:bg-[#235E6F]',
    [Status.BIDS_OPENED]: 'bg-[#017E7F] text-white border border-[#017E7F] hover:bg-[#5AC6B7]',
    [Status.BIDS_EVALUATED]: 'bg-[#4F6965] text-white border border-[#4F6965] hover:bg-[#468089]',
    [Status.POST_QUALIFICATION_VERIFIED]: 'bg-[#018F90] text-white border border-[#018F90] hover:bg-[#59A5A0]',
    [Status.POST_QUALIFICATION_FAILED]: 'bg-[#016B6C] text-white border border-[#016B6C] hover:bg-[#82CBB2]',
    [Status.RESOLUTION_RECORDED]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#266362]',
    [Status.AWARDED]: 'bg-[#2F8F89] text-white border border-[#2F8F89] hover:bg-[#225E63]',
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: 'bg-[#015C5D] text-white border border-[#015C5D] hover:bg-[#B2DFDB]',
    [Status.NTP_RECORDED]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#C6F1E7]',
    [Status.MONITORING_COMPLETED]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#93CCC6]',
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: 'bg-[#729B90] text-white border border-[#729B90] hover:bg-[#6D8C84]',
    [Status.COMPLETED]: 'bg-[#3AA9A3] text-white border border-[#3AA9A3] hover:bg-[#357C78]'
};

const getStatusBadgeStyle = (state: Status): string => {
    return STATUS_BADGE_STYLES[state] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
};

const STAGE_BADGE_STYLES: Record<Stage, string> = {
    [Stage.PROCUREMENT_INITIATION]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#3AA9A3]',
    [Stage.PRE_PROCUREMENT_CONFERENCE]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#468089]',
    [Stage.BIDDING_DOCUMENTS]: 'bg-[#017E7F] text-white border border-[#017E7F] hover:bg-[#59A5A0]',
    [Stage.PRE_BID_CONFERENCE]: 'bg-[#018F90] text-white border border-[#018F90] hover:bg-[#6EAF9C]',
    [Stage.SUPPLEMENTAL_BID_BULLETIN]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#8CC9BA]',
    [Stage.BID_OPENING]: 'bg-[#2F8F89] text-white border border-[#2F8F89] hover:bg-[#9FE2BF]',
    [Stage.BID_EVALUATION]: 'bg-[#3C7A6B] text-white border border-[#3C7A6B] hover:bg-[#5CD3B4]',
    [Stage.POST_QUALIFICATION]: 'bg-[#365C5C] text-white border border-[#365C5C] hover:bg-[#357C78]',
    [Stage.BAC_RESOLUTION]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#6D8C84]',
    [Stage.NOTICE_OF_AWARD]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#93CCC6]',
    [Stage.PERFORMANCE_BOND_CONTRACT_AND_PO]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#82CBB2]',
    [Stage.NOTICE_TO_PROCEED]: 'bg-[#266362] text-white border border-[#266362] hover:bg-[#225E63]',
    [Stage.MONITORING]: 'bg-[#095256] text-white border border-[#095256] hover:bg-[#014D4E]',
    [Stage.COMPLETION]: 'bg-[#43B3AE] text-white border border-[#43B3AE] hover:bg-[#3AA9A3]',
    [Stage.COMPLETED]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#A7DAD8]'
};

const getStageBadgeStyle = (phase: Stage): string => {
    return STAGE_BADGE_STYLES[phase] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
};

// Types and interfaces
interface DataTableCheckboxProps {
    checked: boolean | "indeterminate";
    onCheckedChange: (value: boolean) => void;
    disabled?: boolean;
    title?: string;
}

interface DataTableColumnHeaderProps<TData, TValue>
    extends React.HTMLAttributes<HTMLDivElement> {
    column: Column<TData, TValue>;
    title: string;
}

interface ColumnsProps {
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
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

// Cell components
export const IdCell = ({ id }: { id: string }) => {
    const { auth } = usePage<SharedData>().props;
    const userRole = (auth?.user?.role || 'guest').replace('_', '-');
    const baseRoute = `/${userRole}/procurements-list/${id}`;
    return (
        <div className="font-medium text-blue-600 dark:text-blue-400">
            <Link
                href={baseRoute}
                className="hover:underline transition-all duration-150 flex items-center"
                prefetch="hover"
                cacheFor="5m"
            >
                <span className="font-mono text-xs bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800/60">
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
    const userRole = (auth?.user?.role || 'guest').replace('_', '-');
    const baseRoute = `/${userRole}/procurements-list/${procurement.id}`;
    const titleContent = (
        <div ref={textRef} className="max-w-[280px] truncate font-medium" title={procurement.title}>
            <Link
                href={baseRoute}
                className="hover:text-blue-600 hover:underline transition-colors duration-150 text-gray-900 dark:text-gray-100"
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
    ) : titleContent;
};

export const BadgeCell = <T extends string>({
    value,
    getStyle,
    icon
}: {
    value: T,
    getStyle: (value: T) => string,
    icon?: React.ReactNode
}) => {
    const textRef = useRef<HTMLSpanElement>(null);
    const isTruncated = useIsTruncated(textRef, value);
    const badge = (
        <Badge
            variant="outline"
            className={cn(
                getStyle(value),
                "inline-flex items-center gap-1.5 overflow-hidden text-ellipsis whitespace-nowrap px-2 py-0.5",
                "shadow-sm border transition-all duration-150 font-medium",
                "max-w-[180px]"
            )}
        >
            {icon && <span className="flex-shrink-0">{icon}</span>}
            <span ref={textRef} className="truncate min-w-0" title={String(value)}>{value}</span>
        </Badge>
    );
    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent className="font-medium">{value}</TooltipContent>
        </Tooltip>
    ) : badge;
};

const stageIcons: Record<Stage, React.ReactNode> = {
    [Stage.PROCUREMENT_INITIATION]: <FileText className="h-3 w-3" />,
    [Stage.PRE_PROCUREMENT_CONFERENCE]: <Milestone className="h-3 w-3" />,
    [Stage.BIDDING_DOCUMENTS]: <FileText className="h-3 w-3" />,
    [Stage.PRE_BID_CONFERENCE]: <Milestone className="h-3 w-3" />,
    [Stage.SUPPLEMENTAL_BID_BULLETIN]: <FileQuestion className="h-3 w-3" />,
    [Stage.BID_OPENING]: <ListChecks className="h-3 w-3" />,
    [Stage.BID_EVALUATION]: <ListChecks className="h-3 w-3" />,
    [Stage.POST_QUALIFICATION]: <FileCheck className="h-3 w-3" />,
    [Stage.BAC_RESOLUTION]: <FileCheck className="h-3 w-3" />,
    [Stage.NOTICE_OF_AWARD]: <Award className="h-3 w-3" />,
    [Stage.PERFORMANCE_BOND_CONTRACT_AND_PO]: <FileText className="h-3 w-3" />,
    [Stage.NOTICE_TO_PROCEED]: <PlayCircle className="h-3 w-3" />,
    [Stage.MONITORING]: <Monitor className="h-3 w-3" />,
    [Stage.COMPLETION]: <CheckCircle className="h-3 w-3" />,
    [Stage.COMPLETED]: <CheckCircle className="h-3 w-3" />
};

const statusIcons: Record<Status, React.ReactNode> = {
    [Status.PROCUREMENT_SUBMITTED]: <Check className="h-3 w-3" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: <FileText className="h-3 w-3" />,
    [Status.PRE_BID_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />,
    [Status.PRE_BID_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />,
    [Status.PRE_BID_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: <Clock className="h-3 w-3" />,
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.BIDS_OPENED]: <ListChecks className="h-3 w-3" />,
    [Status.BIDS_EVALUATED]: <ListChecks className="h-3 w-3" />,
    [Status.POST_QUALIFICATION_VERIFIED]: <FileCheck className="h-3 w-3" />,
    [Status.POST_QUALIFICATION_FAILED]: <AlertCircle className="h-3 w-3" />,
    [Status.RESOLUTION_RECORDED]: <FileCheck className="h-3 w-3" />,
    [Status.AWARDED]: <Award className="h-3 w-3" />,
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: <FileCheck className="h-3 w-3" />,
    [Status.NTP_RECORDED]: <PlayCircle className="h-3 w-3" />,
    [Status.MONITORING_COMPLETED]: <Monitor className="h-3 w-3" />,
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: <FileCheck className="h-3 w-3" />,
    [Status.COMPLETED]: <CheckCircle className="h-3 w-3" />
};

export const StageCell = ({ stage }: { stage: Stage }) => (
    <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} icon={stageIcons[stage] || <HelpCircle className="h-3 w-3" />} />
);

export const StatusCell = ({ status }: { status: Status }) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} icon={statusIcons[status] || <HelpCircle className="h-3 w-3" />} />
);

export const DocumentCountCell = ({ count }: { count: number }) => (
    <div className="flex items-center gap-1.5">
        {count !== undefined ? (
            <div className="flex items-center bg-blue-50 dark:bg-blue-900/20 rounded-full pl-1 pr-2 py-0.5">
                <FileIcon className="h-3.5 w-3.5 text-blue-500 dark:text-blue-400 mr-1" />
                <span className="font-medium text-blue-700 dark:text-blue-300 text-xs">{count}</span>
            </div>
        ) : (
            // Skeleton loader for deferred document counts
            <div className="flex items-center bg-gray-100 dark:bg-gray-800 rounded-full pl-1 pr-2 py-0.5 animate-pulse">
                <div className="h-3.5 w-3.5 bg-gray-300 dark:bg-gray-600 rounded mr-1"></div>
                <div className="h-3 w-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
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
            year: 'numeric'
        })
        : date;
    return (
        <div className="flex items-center gap-1.5">
            <CalendarIcon className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
            <span className="text-sm text-gray-600 dark:text-gray-300 font-medium">{displayDate}</span>
        </div>
    );
};

// Utility components
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
// Column factory
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
            filterFn: (row, id, value) => Array.isArray(value) && value.includes(row.getValue(id)),
            meta: {
                className: "hidden lg:table-cell",
            },
            size: 150,
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
                className: "hidden sm:table-cell",
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

// Main table component
export function ProcurementsDataTable({
    columns,
    data,
    loading,
    error,
    userRole,
    searchValue,
    onRowSelectionChange,
}: ProcurementsDataTableProps) {
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
            const selectedRows = table
                .getSelectedRowModel()
                .rows.map((row) => row.original);
            onRowSelectionChange(selectedRows as ProcurementListItem[]);
        }
    }, [rowSelection, onRowSelectionChange, table]);

    useEffect(() => {
        const searchColumnId = 'title';
        table.getColumn(searchColumnId)?.setFilterValue(searchValue);
    }, [searchValue, table]);

    // Render content based on state
    if (loading) return <LoadingSkeleton />;
    if (error) return <ErrorState error={error} />;
    if (data.length === 0) return <EmptyState userRole={userRole} />;

    const selectedModel = table.getSelectedRowModel();
    const selectedRows = selectedModel.rows.map((row) => row.original) as ProcurementListItem[];
    const selectedRowCount = selectedModel.rows.length;

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

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

export const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Bids and Awards Committee Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Head of Procuring Entity Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
    }
};

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    const [searchValue, setSearchValue] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [stageFilter, setStageFilter] = useState('all');

    // Ref for search timeout to avoid TypeScript window property issues
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // Add polling for real-time updates every 30 seconds
    usePoll(30000, {
        only: ['procurements'], // Only reload procurement data
        onStart: () => console.log('Polling for procurement updates...'),
        onFinish: () => console.log('Procurement data updated'),
    });

    const {
        procurements,
        loading,
        error,
        preProcurementDialogOpen,
        preBidConferenceDialogOpen,
        supplementalBidBulletinDialogOpen,
        selectedProcurement,
        setSelectedRows,
        setPreProcurementDialogOpen,
        setPreBidConferenceDialogOpen,
        setSupplementalBidBulletinDialogOpen,
        handleOpenPreProcurementDialog,
        handleOpenPreBidDialog,
        handleOpenSupplementalBidBulletinDialog,
    } = useProcurementList({ initialProcurements, initialError });

    const getCompletedCount = () => {
        return procurements.filter(p =>
            p.current_status === Status.COMPLETED ||
            p.current_status === Status.COMPLETION_DOCUMENTS_UPLOADED
        ).length;
    };

    const getInProgressCount = () => {
        return procurements.filter(p => {
            const status = p.current_status;
            return status !== Status.COMPLETED &&
                status !== Status.COMPLETION_DOCUMENTS_UPLOADED &&
                status !== Status.PROCUREMENT_SUBMITTED;
        }).length;
    };

    const getTotalDocuments = () => {
        return procurements.reduce((sum, p) => {
            const count = Number(p.document_count) || 0;
            return sum + count;
        }, 0);
    };

    // Optimized filter function using Inertia partial reloads
    const handleFilterChange = (filterType: 'search' | 'status' | 'stage', value: string) => {
        const params = new URLSearchParams(window.location.search);

        if (value && value !== 'all') {
            params.set(filterType, value);
        } else {
            params.delete(filterType);
        }

        // Use Inertia to navigate with partial reload
        router.visit(`${window.location.pathname}?${params.toString()}`, {
            only: ['procurements'], // Only reload procurement data
            replace: true, // Replace history state
        });
    };

    const columns = createColumns({
        onOpenPreProcurementDialog: handleOpenPreProcurementDialog,
        onOpenPreBidDialog: handleOpenPreBidDialog,
        onOpenSupplementalBidBulletinDialog: handleOpenSupplementalBidBulletinDialog,
    });

    const filteredProcurements = procurements.filter(proc => {
        if (!searchValue.trim() && statusFilter === 'all' && stageFilter === 'all') return true;

        const searchLower = searchValue.toLowerCase();
        const matchesSearch = !searchValue.trim() || (
            proc.id.toLowerCase().includes(searchLower) ||
            proc.title.toLowerCase().includes(searchLower) ||
            proc.stage.toLowerCase().includes(searchLower) ||
            proc.current_status.toLowerCase().includes(searchLower)
        );

        const matchesStatus = statusFilter === 'all' || proc.current_status === statusFilter;
        const matchesStage = stageFilter === 'all' || proc.stage === stageFilter;

        return matchesSearch && matchesStatus && matchesStage;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Procurement List</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        View and manage procurement items across all stages
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 md:gap-3">
                                {userRole === 'bac_secretariat' && (
                                    <Button asChild>
                                        <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center space-x-2">
                                            <Plus className="h-4 w-4" />
                                            <span>New Procurement</span>
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {error && (
                    <Card className="border-destructive/50 bg-destructive/10 dark:border-destructive/20 dark:bg-destructive/5">
                        <CardContent className="p-4">
                            <ErrorState error={error} />
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-3 md:gap-4 grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Total</CardTitle>
                            <Archive className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{procurements.length}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">In Progress</CardTitle>
                            <Activity className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getInProgressCount()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Completed</CardTitle>
                            <Clock className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getCompletedCount()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Documents</CardTitle>
                            <FileText className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getTotalDocuments()}</div>
                        </CardContent>
                    </Card>
                </div>
                <div className="pb-4">
                    <div className="space-y-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                                <div className="relative flex-1 max-w-md">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="text"
                                        placeholder="Search procurements..."
                                        value={searchValue}
                                        onChange={(e) => {
                                            setSearchValue(e.target.value);
                                            // Debounce search for better UX
                                            if (searchTimeoutRef.current) {
                                                clearTimeout(searchTimeoutRef.current);
                                            }
                                            searchTimeoutRef.current = setTimeout(() => {
                                                handleFilterChange('search', e.target.value);
                                            }, 500);
                                        }}
                                        className="pl-10 h-10"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <Select value={statusFilter} onValueChange={(value) => {
                                    setStatusFilter(value);
                                    handleFilterChange('status', value);
                                }}>
                                    <SelectTrigger className="w-full sm:w-[180px] h-10">
                                        <SelectValue placeholder="All Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="PROCUREMENT_SUBMITTED">Submitted</SelectItem>
                                        <SelectItem value="PRE_PROCUREMENT_SCHEDULED">Pre-Procurement</SelectItem>
                                        <SelectItem value="BIDDING_DOCUMENTS_PREPARED">Bidding Docs</SelectItem>
                                        <SelectItem value="PRE_BID_CONFERENCE_SCHEDULED">Pre-Bid Conference</SelectItem>
                                        <SelectItem value="BID_SUBMISSION_ONGOING">Bid Submission</SelectItem>
                                        <SelectItem value="BID_OPENING_SCHEDULED">Bid Opening</SelectItem>
                                        <SelectItem value="BID_EVALUATION_ONGOING">Bid Evaluation</SelectItem>
                                        <SelectItem value="POST_QUALIFICATION_ONGOING">Post Qualification</SelectItem>
                                        <SelectItem value="NOTICE_OF_AWARD_ISSUED">Notice of Award</SelectItem>
                                        <SelectItem value="NOTICE_TO_PROCEED_ISSUED">Notice to Proceed</SelectItem>
                                        <SelectItem value="PERFORMANCE_BOND_RECEIVED">Performance Bond</SelectItem>
                                        <SelectItem value="MONITORING_ONGOING">Monitoring</SelectItem>
                                        <SelectItem value="COMPLETED">Completed</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select value={stageFilter} onValueChange={(value) => {
                                    setStageFilter(value);
                                    handleFilterChange('stage', value);
                                }}>
                                    <SelectTrigger className="w-full sm:w-[180px] h-10">
                                        <SelectValue placeholder="All Stages" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Stages</SelectItem>
                                        <SelectItem value="PROCUREMENT_INITIATION">Initiation</SelectItem>
                                        <SelectItem value="PRE_PROCUREMENT_CONFERENCE">Pre-Procurement</SelectItem>
                                        <SelectItem value="BIDDING_DOCUMENTS">Bidding Documents</SelectItem>
                                        <SelectItem value="PRE_BID_CONFERENCE">Pre-Bid Conference</SelectItem>
                                        <SelectItem value="BID_SUBMISSION">Bid Submission</SelectItem>
                                        <SelectItem value="BID_OPENING">Bid Opening</SelectItem>
                                        <SelectItem value="BID_EVALUATION">Bid Evaluation</SelectItem>
                                        <SelectItem value="POST_QUALIFICATION">Post Qualification</SelectItem>
                                        <SelectItem value="NOTICE_OF_AWARD">Notice of Award</SelectItem>
                                        <SelectItem value="NOTICE_TO_PROCEED">Notice to Proceed</SelectItem>
                                        <SelectItem value="PERFORMANCE_BOND_CONTRACT_AND_PO">Performance Bond</SelectItem>
                                        <SelectItem value="MONITORING">Monitoring</SelectItem>
                                        <SelectItem value="COMPLETION">Completion</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex justify-center sm:justify-end">
                                <Button
                                    onClick={() => {
                                        // Enhanced Inertia partial reload with progress indicators
                                        router.reload({
                                            only: ['procurements'],
                                            onStart: () => {
                                                toast.info('Refreshing procurement data...', {
                                                    description: 'Getting latest updates from the server'
                                                });
                                            },
                                            onProgress: (progress) => {
                                                if (progress && progress.percentage) {
                                                    console.log(`Loading: ${Math.round(progress.percentage)}%`);
                                                }
                                            },
                                            onSuccess: (page) => {
                                                const procurements = page.props.procurements as ProcurementListItem[] | undefined;
                                                toast.success('Data refreshed successfully', {
                                                    description: `Updated ${procurements?.length || 0} procurements`
                                                });
                                            },
                                            onError: (errors) => {
                                                toast.error('Failed to refresh data', {
                                                    description: Object.values(errors).flat().join(', ') || 'Please try again later'
                                                });
                                            },
                                            onFinish: () => {
                                                console.log('Refresh operation completed');
                                            }
                                        });
                                    }}
                                    disabled={loading}
                                    variant="outline"
                                    size="default"
                                    className="flex items-center space-x-2 w-full sm:w-auto h-10"
                                >
                                    <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    <span>Refresh</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <ProcurementsDataTable
                        columns={columns}
                        data={filteredProcurements}
                        loading={loading}
                        error={error || null}
                        userRole={userRole}
                        searchValue={searchValue}
                        onRowSelectionChange={setSelectedRows}
                    />
                </div>
            </div>

            {/* Dialogs */}
            {preProcurementDialogOpen && selectedProcurement && (
                <PreProcurementDialog
                    open={preProcurementDialogOpen}
                    onOpenChange={setPreProcurementDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {preBidConferenceDialogOpen && selectedProcurement && (
                <PreBidConferenceDialog
                    open={preBidConferenceDialogOpen}
                    onOpenChange={setPreBidConferenceDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {supplementalBidBulletinDialogOpen && selectedProcurement && (
                <SupplementalBidBulletinDialog
                    open={supplementalBidBulletinDialogOpen}
                    onOpenChange={setSupplementalBidBulletinDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
        </AppLayout>
    );
}