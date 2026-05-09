import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Archive,
    ArrowDownUp,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    Circle,
    Clock,
    Download,
    FileText,
    FilterX,
    GitBranch,
    Hash,
    MapPin,
    ScrollText,
    Shield,
    Wallet,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';

interface Procurement {
    pr_number: string;
    title: string;
    description: string;
    abc_amount: string;
    procurement_mode: string;
    category: string;
    office: string;
    funding_source: string;
    prepared_by: string;
    approved_by: string;
    approval_date: string | null;
    delivery_location: string;
    delivery_date: string | null;
    created_at: string | null;
    current_stage: string;
    current_status: string;
    document_count: number;
    event_count: number;
    correction_count: number;
    is_archived: boolean;
    metadata_txid: string;
    status_txid: string;
    blocktime: number | null;
    confirmations: number;
}

interface SharedLedgerPageProps {
    procurements: Procurement[];
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    available_stages: { value: string; label: string }[];
    available_statuses: { value: string; label: string }[];
    filters: Record<string, string | undefined>;
    error?: string;
}

const breadcrumbs = [
    { title: 'Shared Ledger', href: '/shared-ledger' },
];

const STAGE_LABELS: Record<string, string> = {
    procurement_initiation: 'Procurement Initiation',
    pre_procurement_conference: 'Pre-Procurement Conference',
    bidding_documents: 'Bidding Documents',
    pre_bid_conference: 'Pre-Bid Conference',
    bid_opening: 'Bid Opening',
    bid_evaluation: 'Bid Evaluation',
    post_qualification: 'Post-Qualification',
    bac_resolution: 'BAC Resolution',
    notice_of_award: 'Notice of Award',
    performance_bond: 'Performance Bond',
    notice_to_proceed: 'Notice to Proceed',
    post_procurement_monitoring: 'Post-Procurement Monitoring',
    completion: 'Completion',
};

const MODE_LABELS: Record<string, string> = {
    competitive_bidding: 'Competitive Bidding',
    small_value_procurement: 'Small Value Procurement',
    direct_contracting: 'Direct Contracting',
    negotiated_procurement: 'Negotiated Procurement',
};

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
    procurement_initiated: { label: 'Initiated', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
    stage_completed: { label: 'Stage Complete', color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
};

export default function SharedLedger({ procurements, pagination, available_stages, available_statuses, filters, error }: SharedLedgerPageProps) {
    const [prNumber, setPrNumber] = useState(filters.pr_number ?? '');
    const [stage, setStage] = useState(filters.stage ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

    const hasActiveFilters = !!(filters.pr_number || filters.stage || filters.status || filters.date_from || filters.date_to);

    const toggleRow = (prNumber: string) => {
        setExpandedRows((prev) => {
            const next = new Set(prev);
            if (next.has(prNumber)) {
                next.delete(prNumber);
            } else {
                next.add(prNumber);
            }
            return next;
        });
    };

    const applyFilters = () => {
        router.get(
            '/shared-ledger',
            {
                ...(prNumber ? { pr_number: prNumber } : {}),
                ...(stage ? { stage } : {}),
                ...(status ? { status } : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setPrNumber('');
        setStage('');
        setStatus('');
        router.get('/shared-ledger', {}, { preserveState: false, replace: true });
    };

    const handleExport = () => {
        const headers = ['PR Number', 'Title', 'Office', 'Stage', 'Status', 'ABC Amount', 'Mode', 'Documents', 'Events', 'Corrections', 'Archived', 'Created', 'TX ID'];
        const rows = procurements.map((p) => [
            p.pr_number,
            p.title,
            p.office,
            STAGE_LABELS[p.current_stage] ?? p.current_stage,
            STATUS_CONFIG[p.current_status]?.label ?? p.current_status,
            p.abc_amount,
            MODE_LABELS[p.procurement_mode] ?? p.procurement_mode,
            String(p.document_count),
            String(p.event_count),
            String(p.correction_count),
            p.is_archived ? 'Yes' : 'No',
            p.created_at ?? '',
            p.metadata_txid,
        ]);

        const csv = [headers.join(','), ...rows.map((r) => r.map((v) => `"${v.replace(/"/g, '""')}"`).join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `shared-ledger-${format(new Date(), 'yyyy-MM-dd')}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success('Ledger exported as CSV');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shared Ledger" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={BookOpen}
                    title="Shared Ledger"
                    description="Immutable blockchain record of every procurement. Each row is a single procurement showing its full lifecycle — metadata, documents, status changes, and corrections — all backed by MultiChain."
                    actions={
                        <Button variant="outline" onClick={handleExport} disabled={procurements.length === 0}>
                            <Download className="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                    }
                />

                {error && (
                    <div className="bg-destructive/10 text-destructive rounded-md px-4 py-3 text-sm">{error}</div>
                )}

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ArrowDownUp className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Input
                                type="text"
                                placeholder="PR Number (e.g. PR-2026-001)"
                                value={prNumber}
                                onChange={(e) => setPrNumber(e.target.value)}
                            />

                            <Select value={stage || ''} onValueChange={(v) => v && setStage(v)}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All stages">{() =>
                                        stage ? (STAGE_LABELS[stage] ?? stage) : 'All stages'
                                    }</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="">All stages</SelectItem>
                                        {available_stages.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <Select value={status || ''} onValueChange={(v) => v && setStatus(v)}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All statuses">{() =>
                                        status ? (STATUS_CONFIG[status]?.label ?? status) : 'All statuses'
                                    }</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="">All statuses</SelectItem>
                                        {available_statuses.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                {STATUS_CONFIG[s.value]?.label ?? s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                    <CardFooter className="flex gap-2">
                        <Button onClick={applyFilters}>
                            <ArrowDownUp className="mr-2 h-4 w-4" />
                            Apply Filters
                        </Button>
                        {hasActiveFilters && (
                            <Button variant="outline" onClick={clearFilters}>
                                <FilterX className="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </CardFooter>
                </Card>

                {/* Immutability Notice */}
                <div className="bg-primary/5 border-primary/20 rounded-lg border px-4 py-3 text-sm">
                    <p className="text-primary flex items-center gap-2 font-medium">
                        <Shield className="h-4 w-4" />
                        Blockchain-Verified Records — Every procurement is backed by MultiChain
                    </p>
                    <p className="text-muted-foreground mt-1">
                        Expand any row to see the blockchain TX IDs and raw data. Each document, status change, and correction
                        is an immutable entry on the chain. The counts below (documents, events, corrections) reflect actual
                        blockchain transactions.
                    </p>
                </div>

                {/* Procurement Ledger Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span>
                                {pagination.total.toLocaleString()} procurement{pagination.total !== 1 ? 's' : ''}
                            </span>
                            {hasActiveFilters && <Badge variant="secondary">Filtered</Badge>}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {procurements.length === 0 ? (
                            <Empty className="py-16">
                                <EmptyMedia>
                                    <BookOpen className="h-12 w-12" />
                                </EmptyMedia>
                                <EmptyHeader>
                                    <EmptyTitle>No procurements found</EmptyTitle>
                                    <EmptyDescription>
                                        {hasActiveFilters
                                            ? 'No procurements match the current filters.'
                                            : 'Blockchain records will appear here once procurement activity begins.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8"></TableHead>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>Title / Office</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Activity</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {procurements.map((proc) => {
                                        const isExpanded = expandedRows.has(proc.pr_number);
                                        const stageLabel = STAGE_LABELS[proc.current_stage] ?? proc.current_stage;
                                        const statusCfg = STATUS_CONFIG[proc.current_status] ?? {
                                            label: proc.current_status,
                                            color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                                        };
                                        const modeLabel = MODE_LABELS[proc.procurement_mode] ?? proc.procurement_mode;
                                        const formattedAmount = Number(proc.abc_amount).toLocaleString('en-PH', {
                                            style: 'currency',
                                            currency: 'PHP',
                                            minimumFractionDigits: 2,
                                        });

                                        return (
                                            <React.Fragment key={proc.pr_number}>
                                                <TableRow
                                                    className="hover:bg-muted/50 cursor-pointer"
                                                    onClick={() => toggleRow(proc.pr_number)}
                                                >
                                                    <TableCell>
                                                        <ChevronDown
                                                            className={cn(
                                                                'text-muted-foreground h-4 w-4 transition-transform',
                                                                isExpanded && 'rotate-180',
                                                            )}
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-mono text-sm font-medium">{proc.pr_number}</span>
                                                    </TableCell>
                                                    <TableCell className="max-w-xs">
                                                        <div className="truncate text-sm font-medium" title={proc.title}>
                                                            {proc.title}
                                                        </div>
                                                        <div className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                                            <MapPin className="h-3 w-3" />
                                                            {proc.office}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-col gap-1">
                                                            <span className="text-sm">{stageLabel}</span>
                                                            {proc.current_stage && (
                                                                <span className="text-muted-foreground text-[10px] uppercase tracking-wide">
                                                                    {proc.current_stage.replace(/_/g, ' ')}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1 text-sm font-medium">
                                                            <Wallet className="text-muted-foreground h-3 w-3" />
                                                            {formattedAmount}
                                                        </div>
                                                        <span className="text-muted-foreground text-[10px] uppercase tracking-wide">
                                                            {modeLabel}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            <Badge variant="outline" className="gap-1 text-xs">
                                                                <FileText className="h-3 w-3" />
                                                                {proc.document_count} docs
                                                            </Badge>
                                                            <Badge variant="outline" className="gap-1 text-xs">
                                                                <GitBranch className="h-3 w-3" />
                                                                {proc.event_count} events
                                                            </Badge>
                                                            {proc.correction_count > 0 && (
                                                                <Badge variant="outline" className="gap-1 text-xs">
                                                                    <ScrollText className="h-3 w-3" />
                                                                    {proc.correction_count} corrections
                                                                </Badge>
                                                            )}
                                                            {proc.is_archived && (
                                                                <Badge variant="secondary" className="gap-1 text-xs">
                                                                    <Archive className="h-3 w-3" />
                                                                    Archived
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge className={cn('gap-1 font-normal', statusCfg.color)}>
                                                            {proc.current_status === 'procurement_initiated' ? (
                                                                <Circle className="h-3 w-3 fill-current" />
                                                            ) : (
                                                                <CheckCircle2 className="h-3 w-3" />
                                                            )}
                                                            {statusCfg.label}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                                {isExpanded && (
                                                    <TableRow>
                                                        <TableCell colSpan={7} className="bg-muted/20 p-0">
                                                            <div className="px-6 py-4">
                                                                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                                                    {/* Details Card */}
                                                                    <div className="space-y-3">
                                                                        <h4 className="flex items-center gap-2 text-sm font-medium">
                                                                            <BookOpen className="h-4 w-4" />
                                                                            Procurement Details
                                                                        </h4>
                                                                        <div className="space-y-2 text-sm">
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Category</span>
                                                                                <span>{proc.category.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</span>
                                                                            </div>
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Funding</span>
                                                                                <span>{proc.funding_source}</span>
                                                                            </div>
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Delivery</span>
                                                                                <span>{proc.delivery_location || 'N/A'}</span>
                                                                            </div>
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Prepared By</span>
                                                                                <span>{proc.prepared_by}</span>
                                                                            </div>
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Approved By</span>
                                                                                <span>{proc.approved_by}</span>
                                                                            </div>
                                                                            {proc.created_at && (
                                                                                <div className="flex justify-between">
                                                                                    <span className="text-muted-foreground">Created</span>
                                                                                    <span className="text-xs">{format(new Date(proc.created_at), 'MMM d, yyyy')}</span>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>

                                                                    {/* Blockchain Proof Card */}
                                                                    <div className="space-y-3">
                                                                        <h4 className="flex items-center gap-2 text-sm font-medium">
                                                                            <Hash className="h-4 w-4" />
                                                                            Blockchain Proof
                                                                        </h4>
                                                                        <div className="space-y-2 text-sm">
                                                                            <div>
                                                                                <div className="text-muted-foreground text-xs">Metadata TX</div>
                                                                                <code className="mt-0.5 block truncate rounded bg-muted px-2 py-1 font-mono text-xs">
                                                                                    {proc.metadata_txid || 'Pending...'}
                                                                                </code>
                                                                            </div>
                                                                            {proc.status_txid && (
                                                                                <div>
                                                                                    <div className="text-muted-foreground text-xs">Latest Status TX</div>
                                                                                    <code className="mt-0.5 block truncate rounded bg-muted px-2 py-1 font-mono text-xs">
                                                                                        {proc.status_txid}
                                                                                    </code>
                                                                                </div>
                                                                            )}
                                                                            {proc.blocktime && (
                                                                                <div className="flex justify-between">
                                                                                    <span className="text-muted-foreground">Block</span>
                                                                                    <span className="font-mono text-xs">#{proc.blocktime}</span>
                                                                                </div>
                                                                            )}
                                                                            <div className="flex justify-between">
                                                                                <span className="text-muted-foreground">Confirmations</span>
                                                                                <span className={cn('font-mono text-xs', proc.confirmations === 0 && 'text-amber-500')}>
                                                                                    {proc.confirmations > 0 ? proc.confirmations : 'Pending'}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    {/* Description Card */}
                                                                    <div className="space-y-3 sm:col-span-2 lg:col-span-1">
                                                                        <h4 className="flex items-center gap-2 text-sm font-medium">
                                                                            <Clock className="h-4 w-4" />
                                                                            Timeline & Description
                                                                        </h4>
                                                                        <p className="text-muted-foreground text-sm leading-relaxed">
                                                                            {proc.description || 'No description available.'}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                    {pagination.last_page > 1 && (
                        <CardFooter className="flex items-center justify-between pt-4">
                            <span className="text-muted-foreground text-sm">
                                Page {pagination.current_page} of {pagination.last_page}
                            </span>
                            <Pagination className="mx-0 w-auto">
                                <PaginationContent>
                                    <PaginationItem>
                                        <PaginationPrevious
                                            href={pagination.current_page > 1 ? `?page=${pagination.current_page - 1}` : undefined}
                                            onClick={(e) => {
                                                if (pagination.current_page <= 1) { e.preventDefault(); return; }
                                                e.preventDefault();
                                                const params = new URLSearchParams(window.location.search);
                                                params.set('page', String(pagination.current_page - 1));
                                                router.get(`/shared-ledger?${params.toString()}`, {}, { preserveState: true });
                                            }}
                                            className={pagination.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                                        />
                                    </PaginationItem>
                                    {getPaginationPages(pagination.current_page, pagination.last_page).map((page, i) =>
                                        page === '...' ? (
                                            <PaginationItem key={`ellipsis-${i}`}>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem key={page}>
                                                <PaginationLink
                                                    isActive={pagination.current_page === page}
                                                    href={`?page=${page}`}
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        const params = new URLSearchParams(window.location.search);
                                                        params.set('page', String(page));
                                                        router.get(`/shared-ledger?${params.toString()}`, {}, { preserveState: true });
                                                    }}
                                                >
                                                    {page}
                                                </PaginationLink>
                                            </PaginationItem>
                                        )
                                    )}
                                    <PaginationItem>
                                        <PaginationNext
                                            href={pagination.current_page < pagination.last_page ? `?page=${pagination.current_page + 1}` : undefined}
                                            onClick={(e) => {
                                                if (pagination.current_page >= pagination.last_page) { e.preventDefault(); return; }
                                                e.preventDefault();
                                                const params = new URLSearchParams(window.location.search);
                                                params.set('page', String(pagination.current_page + 1));
                                                router.get(`/shared-ledger?${params.toString()}`, {}, { preserveState: true });
                                            }}
                                            className={pagination.current_page >= pagination.last_page ? 'pointer-events-none opacity-50' : ''}
                                        />
                                    </PaginationItem>
                                </PaginationContent>
                            </Pagination>
                        </CardFooter>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

function getPaginationPages(current: number, last: number): (number | string)[] {
    const pages: (number | string)[] = [];

    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
        return pages;
    }

    pages.push(1);

    if (current > 3) pages.push('...');

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let i = start; i <= end; i++) pages.push(i);

    if (current < last - 2) pages.push('...');

    pages.push(last);

    return pages;
}