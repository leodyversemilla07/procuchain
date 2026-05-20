import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
 Pagination,
 PaginationContent,
 PaginationEllipsis,
 PaginationItem,
 PaginationLink,
 PaginationNext,
 PaginationPrevious,
} from '@/components/ui/pagination';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { LedgerEntry, LedgerFilters, LedgerPagination, NodeOption, StreamOption } from '@/types/blockchain';
import { Head, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import {
 AlertTriangle,
 Archive,
 ArrowDownUp,
 BookOpen,
 BookOpenText,
 CalendarIcon,
 ChevronDown,
 ClipboardCopy,
 Download,
 ExternalLink,
 FileText,
 FilterX,
 GitBranch,
 Pencil,
 RotateCcw,
 ScrollText,
 Server,
 ServerCrash,
 Shield,
 Trash2,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { type DateRange } from 'react-day-picker';
import { toast } from 'sonner';
import { index as sharedLedgerRoutes } from '@/actions/App/Http/Controllers/SharedLedgerController';

interface NodePurgeState {
    is_purged: boolean;
    was_explicitly_purged: boolean;
    partially_purged: boolean;
    unsubscribed_streams: string[];
    purge_reason?: string | null;
    purge_timestamp?: number | null;
}

interface SharedLedgerPageProps {
    entries: LedgerEntry[];
    pagination: LedgerPagination;
    available_streams: StreamOption[];
    available_nodes: NodeOption[];
    stream_totals: Record<string, number>;
    selected_node: string;
    node_purge_state: NodePurgeState | null;
    filters: LedgerFilters;
    error?: string;
}

/**
 * Resolve the correct Wayfinder route function for the shared-ledger page
 * based on the current URL pathname. This avoids hardcoded basePath strings
 * that can go stale during Inertia client-side navigations.
 */
const resolveSharedLedgerRoute = (pathname: string) => {
 const routeKey = Object.keys(sharedLedgerRoutes).find(
   (key) => pathname === key || pathname.startsWith(key + '/'),
 ) as keyof typeof sharedLedgerRoutes | undefined;

 return routeKey ? sharedLedgerRoutes[routeKey] : sharedLedgerRoutes['/admin/shared-ledger'];
};

/** Stream badge configuration */
const STREAM_CONFIG: Record<string, { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
    'procurement.metadata': {
        label: 'Created / Updated',
        color: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
        icon: BookOpenText,
    },
    'procurement.status': {
        label: 'Status Change',
        color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        icon: GitBranch,
    },
    'procurement.documents': { label: 'Document', color: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300', icon: FileText },
    'procurement.corrections': {
        label: 'Document Correction',
        color: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        icon: AlertTriangle,
    },
    'procurement.metadata.corrections': {
        label: 'Metadata Correction',
        color: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        icon: Pencil,
    },
    'procurement.archive': { label: 'Archive', color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300', icon: Archive },
    'procurement.events': { label: 'Event', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300', icon: ScrollText },
    'file.data': { label: 'File Data', color: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300', icon: FileText },
    'file.metadata': { label: 'File Meta', color: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300', icon: FileText },
    'file.chunks': { label: 'File Chunk', color: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300', icon: FileText },
};

const getStreamConfig = (stream: string) =>
    STREAM_CONFIG[stream] ?? { label: stream, color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300', icon: ScrollText };

/**
 * Compute the diff fields between old and new values.
 */
function computeDiff(oldValues: Record<string, unknown>, newValues: Record<string, unknown>): Array<{ key: string; old: string; new: string }> {
    const diff: Array<{ key: string; old: string; new: string }> = [];
    const allKeys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)]);

    for (const key of allKeys) {
        const oldVal = oldValues[key];
        const newVal = newValues[key];
        const oldStr = oldVal !== undefined ? String(oldVal) : '';
        const newStr = newVal !== undefined ? String(newVal) : '';

        if (oldStr !== newStr) {
            diff.push({ key, old: oldStr, new: newStr });
        }
    }

    return diff;
}

export default function SharedLedger({
 entries,
 pagination,
 available_streams,
 available_nodes,
 stream_totals,
 selected_node,
 node_purge_state,
 filters,
 error,
}: SharedLedgerPageProps) {
 const page = usePage();
 const ledgerRoute = useMemo(() => resolveSharedLedgerRoute(new URL(page.url, window.location.origin).pathname), [page.url]);
 const breadcrumbs = useMemo(() => [{ title: 'Shared Ledger', href: ledgerRoute.url() }], [ledgerRoute]);

 const [prNumber, setPrNumber] = useState(filters.pr_number ?? '');
 const [stream, setStream] = useState(filters.stream ?? '');
 const [node, setNode] = useState(filters.node ?? selected_node ?? 'all');
 const [dateRange, setDateRange] = useState<DateRange | undefined>({
 from: filters.date_from ? parseISO(filters.date_from) : undefined,
 to: filters.date_to ? parseISO(filters.date_to) : undefined,
 });

 const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());
 const [isFiltering, setIsFiltering] = useState(false);

 // Clear filtering state when Inertia re-renders with new data
 useEffect(() => {
 setIsFiltering(false);
 }, [entries, pagination]);

 const hasActiveFilters = !!(
 filters.pr_number ||
 filters.stream ||
 filters.date_from ||
 filters.date_to ||
 (filters.node && filters.node !== 'all')
 );

 const toggleRow = (txid: string) => {
 setExpandedRows((prev) => {
 const next = new Set(prev);
 if (next.has(txid)) {
 next.delete(txid);
 } else {
 next.add(txid);
 }
 return next;
 });
 };

 const copyTxid = (txid: string) => {
 navigator.clipboard
 .writeText(txid)
 .then(() => {
 toast.success('TX ID copied to clipboard');
 })
 .catch(() => {
 toast.error('Failed to copy TX ID');
 });
 };

 /** Build query params object for the current filter state */
 const buildQuery = useCallback(
 (overrides: Record<string, string | undefined> = {}) => {
 const query: Record<string, string> = {};
 const effectivePr = overrides.pr_number ?? (prNumber || undefined);
 const effectiveStream = overrides.stream ?? (stream && stream !== 'all' ? stream : undefined);
 const effectiveNode = overrides.node ?? (node && node !== 'all' ? node : undefined);
 const effectiveDateFrom = overrides.date_from ?? (dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined);
 const effectiveDateTo = overrides.date_to ?? (dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined);

 if (effectivePr) query.pr_number = effectivePr;
 if (effectiveStream) query.stream = effectiveStream;
 if (effectiveNode) query.node = effectiveNode;
 if (effectiveDateFrom) query.date_from = effectiveDateFrom;
 if (effectiveDateTo) query.date_to = effectiveDateTo;

 return query;
 },
 [prNumber, stream, node, dateRange],
 );

 const applyFilters = () => {
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: buildQuery() }), { preserveState: true, replace: true });
 };

 const clearFilters = () => {
 setPrNumber('');
 setStream('');
 setNode('all');
 setDateRange(undefined);
 setIsFiltering(true);
 router.visit(ledgerRoute(), { preserveState: false, replace: true });
 };

    const selectedStreamLabel = stream && stream !== 'all' ? (STREAM_CONFIG[stream]?.label ?? stream) : 'All streams';

    /** Export ledger to CSV */
    const handleExport = () => {
        const headers = ['Timestamp', 'Stream', 'PR Number', 'Action', 'Summary', 'Actor', 'TX ID', 'Procurement Title'];
        const rows = entries.map((e) => [
            e.formatted_timestamp,
            e.stream_display,
            e.pr_number,
            e.action,
            e.summary,
            e.actor_address,
            e.txid,
            e.procurement_title ?? '',
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

    const totalTransactions = pagination.total;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shared Ledger" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={BookOpen}
                    title="Shared Ledger"
                    description="Every blockchain transaction, in order. This is the immutable, shared record of every action ever taken — across all roles, all procurements, all time. Deleted data remains on-chain and recoverable."
                >
                    <div className="mt-4 flex flex-wrap gap-4">
                        <div className="flex items-center gap-2 text-sm">
                            <Shield className="text-primary h-4 w-4" />
                            <span className="text-muted-foreground">
                                <strong className="text-foreground">{totalTransactions.toLocaleString()}</strong> total transactions
                            </span>
                        </div>
                        {Object.entries(stream_totals).map(([s, count]) => {
                            const cfg = STREAM_CONFIG[s];
                            if (!cfg) return null;
                            const Icon = cfg.icon;
                            return (
                                <div key={s} className="flex items-center gap-2 text-sm">
                                    <Icon className="h-4 w-4" />
                                    <span className="text-muted-foreground">
                                        <strong className="text-foreground">{count.toLocaleString()}</strong> {cfg.label.toLowerCase()}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </HeroCard>

                {error && <div className="bg-destructive/10 text-destructive rounded-md px-4 py-3 text-sm">{error}</div>}

                {/* Purge State Warning */}
                {node_purge_state?.is_purged && node_purge_state.was_explicitly_purged && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-900/20">
                        <p className="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">
                            <AlertTriangle className="h-4 w-4" />
                            This node has been purged — all stream subscriptions removed
                        </p>
                        <p className="mt-1 text-amber-700 dark:text-amber-400">
                            Data on this node was wiped via <strong>unsubscribe(purge=true)</strong>. The blockchain data still exists on other nodes
                            — use <strong>Recoverable Data → Resync</strong> to restore this node's local copy.
                        </p>
                        {node_purge_state.purge_reason && (
                            <p className="mt-1 text-xs text-amber-600 dark:text-amber-500">Reason: {node_purge_state.purge_reason}</p>
                        )}
                    </div>
                )}
                {node_purge_state?.is_purged && !node_purge_state.was_explicitly_purged && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900/20">
                        <p className="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-300">
                            <Server className="h-4 w-4" />
                            This node has no local blockchain data
                        </p>
                        <p className="mt-1 text-slate-600 dark:text-slate-400">
                            This node is not subscribed to any procurement streams. It may have never been populated, or its subscriptions were
                            removed without an on-chain record. Use <strong>Recoverable Data → Resync</strong> to subscribe and download the
                            blockchain data.
                        </p>
                    </div>
                )}
                {node_purge_state?.partially_purged && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-900/20">
                        <p className="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">
                            <AlertTriangle className="h-4 w-4" />
                            Partially purged — {node_purge_state.unsubscribed_streams.length} stream(s) unsubscribed
                        </p>
                        <p className="mt-1 text-amber-700 dark:text-amber-400">Missing streams: {node_purge_state.unsubscribed_streams.join(', ')}</p>
                    </div>
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
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <Input
                                type="text"
                                placeholder="PR Number (e.g. PR-2026-001)"
                                value={prNumber}
                                onChange={(e) => setPrNumber(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') applyFilters();
                                }}
                            />

                            <Select
                                value={node || 'all'}
 onValueChange={(value) => {
 if (!value) return;
 setNode(value);
 // Auto-apply: immediately navigate with the new node
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: buildQuery({ node: value !== 'all' ? value : undefined }) }), { preserveState: true, replace: true });
 }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All nodes">
                                        {() => (node && node !== 'all' ? (available_nodes.find((n) => n.id === node)?.name ?? node) : 'All nodes')}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            <div className="flex items-center gap-2">
                                                <Server className="h-3.5 w-3.5" />
                                                All nodes (shared)
                                            </div>
                                        </SelectItem>
                                        {available_nodes.map((n) => (
                                            <SelectItem key={n.id} value={n.id}>
                                                <div className="flex items-center gap-2">
                                                    {n.is_purged ? (
                                                        <ServerCrash className="h-3.5 w-3.5 text-red-500" />
                                                    ) : (
                                                        <Server className="h-3.5 w-3.5" />
                                                    )}
                                                    {n.name}
                                                    {n.is_purged && (
                                                        <Badge variant="destructive" className="ml-1 gap-0.5 px-1 py-0 text-[9px]">
                                                            Purged
                                                        </Badge>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <Select
                                value={stream || 'all'}
 onValueChange={(value) => {
 if (!value) return;
 setStream(value);
 // Auto-apply: immediately navigate with the new stream
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: buildQuery({ stream: value !== 'all' ? value : undefined }) }), { preserveState: true, replace: true });
 }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All transactions">{() => selectedStreamLabel}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">All transactions</SelectItem>
                                        {available_streams.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                <div className="flex items-center gap-2">
                                                    {React.createElement(STREAM_CONFIG[s.value]?.icon ?? ScrollText, { className: 'h-3.5 w-3.5' })}
                                                    {s.label}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <Popover>
                                <PopoverTrigger
                                    render={
                                        <Button
                                            variant="outline"
                                            className={cn('w-full justify-start text-left font-normal', !dateRange?.from && 'text-muted-foreground')}
                                        />
                                    }
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {dateRange?.from ? (
                                        dateRange.to ? (
                                            <>
                                                {format(dateRange.from, 'MMM d, yyyy')} - {format(dateRange.to, 'MMM d, yyyy')}
                                            </>
                                        ) : (
                                            format(dateRange.from, 'MMM d, yyyy')
                                        )
                                    ) : (
                                        <span>Date range</span>
                                    )}
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        autoFocus
                                        mode="range"
                                        defaultMonth={dateRange?.from}
                                        selected={dateRange}
                                        onSelect={setDateRange}
                                        numberOfMonths={2}
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </CardContent>
                    <CardFooter className="flex gap-2">
                        {hasActiveFilters && (
                            <Button variant="outline" onClick={clearFilters}>
                                <FilterX className="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                        <div className="ml-auto">
                            <Button variant="outline" onClick={handleExport} disabled={entries.length === 0}>
                                <Download className="mr-2 h-4 w-4" />
                                Export CSV
                            </Button>
                        </div>
                    </CardFooter>
                </Card>

                {/* Immutability Notice */}
                <div className="bg-primary/5 border-primary/20 rounded-lg border px-4 py-3 text-sm">
                    <p className="text-primary flex items-center gap-2 font-medium">
                        <Shield className="h-4 w-4" />
                        {node && node !== 'all'
                            ? `Viewing from ${available_nodes.find((n) => n.id === node)?.name ?? node} — Same blockchain, this node's RPC connection`
                            : 'Immutable & Shared — Every entry is a verified MultiChain transaction'}
                    </p>
                    <p className="text-muted-foreground mt-1">
                        {node && node !== 'all'
                            ? `This node queries the blockchain directly. All subscribed nodes see the same on-chain data — the difference appears after a purge (node shows empty) or during resync (data streams back in). Select "All nodes" to merge perspectives.`
                            : 'This ledger is shared across all roles — Admin, BAC Secretariat, BAC Chairman, and HOPE all see the exact same data. Each entry has a TX ID that cryptographically proves it exists on the blockchain.'}
                        Expand any row to see what changed and the raw blockchain data.
                    </p>
                </div>

                {/* Ledger Entries Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span>
                                {pagination.total.toLocaleString()} transaction{pagination.total !== 1 ? 's' : ''}
                            </span>
                            {hasActiveFilters && <Badge variant="secondary">Filtered</Badge>}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {isFiltering ? (
                            <div className="flex items-center justify-center py-16">
                                <Spinner className="h-8 w-8" />
                            </div>
                        ) : entries.length === 0 ? (
                            <Empty className="py-16">
                                <EmptyMedia>
                                    <BookOpen className="h-12 w-12" />
                                </EmptyMedia>
                                <EmptyHeader>
                                    <EmptyTitle>No transactions found</EmptyTitle>
                                    <EmptyDescription>
                                        {hasActiveFilters
                                            ? 'No entries match the current filters. Try adjusting your criteria.'
                                            : 'The ledger is empty. Transactions will appear once procurement activity begins on the blockchain.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8"></TableHead>
                                        <TableHead>Timestamp</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>Summary</TableHead>
                                        <TableHead>Actor</TableHead>
                                        <TableHead>TX ID</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.map((entry) => {
                                        const streamCfg = getStreamConfig(entry.stream);
                                        const isExpanded = expandedRows.has(entry.txid);
                                        const StreamIcon = streamCfg.icon;
                                        const isSystem = entry.pr_number === 'system';
                                        const hasChanges = Object.keys(entry.old_values).length > 0 || Object.keys(entry.new_values).length > 0;
                                        const diff = hasChanges ? computeDiff(entry.old_values, entry.new_values) : [];

                                        return (
                                            <React.Fragment key={entry.txid}>
                                                <TableRow className="hover:bg-muted/50 cursor-pointer" onClick={() => toggleRow(entry.txid)}>
                                                    <TableCell>
                                                        <ChevronDown
                                                            className={cn(
                                                                'text-muted-foreground h-4 w-4 transition-transform',
                                                                isExpanded && 'rotate-180',
                                                            )}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-xs whitespace-nowrap">
                                                        {entry.formatted_timestamp}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge className={cn('gap-1 font-normal whitespace-nowrap', streamCfg.color)}>
                                                            <StreamIcon className="h-3 w-3" />
                                                            {entry.stream_display}
                                                        </Badge>
                                                        {entry.action === 'deleted' && (
                                                            <Badge variant="destructive" className="gap-1 text-xs whitespace-nowrap">
                                                                <Trash2 className="h-3 w-3" />
                                                                Deleted
                                                            </Badge>
                                                        )}
                                                        {entry.action === 'restored' && (
                                                            <Badge className="gap-1 bg-emerald-100 whitespace-nowrap text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                                <RotateCcw className="h-3 w-3" />
                                                                Restored
                                                            </Badge>
                                                        )}
                                                        {entry.action === 'node_purged' && (
                                                            <Badge variant="destructive" className="gap-1 text-xs whitespace-nowrap">
                                                                <ServerCrash className="h-3 w-3" />
                                                                Node Purged
                                                            </Badge>
                                                        )}
                                                        {entry.action === 'node_resynced' && (
                                                            <Badge className="gap-1 bg-emerald-100 whitespace-nowrap text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                                <RotateCcw className="h-3 w-3" />
                                                                Node Resynced
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {isSystem ? (
                                                            <Badge variant="secondary" className="font-mono text-xs">
                                                                System
                                                            </Badge>
                                                        ) : (
                                                            <span className="font-mono text-xs font-medium">{entry.pr_number}</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="max-w-xs">
                                                        <div className="truncate text-sm" title={entry.summary}>
                                                            {entry.summary}
                                                        </div>
                                                        {entry.procurement_title && (
                                                            <div className="text-muted-foreground truncate text-xs">{entry.procurement_title}</div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs">
                                                        {entry.actor_address ? (
                                                            `${entry.actor_address.substring(0, 10)}...`
                                                        ) : (
                                                            <span className="text-muted-foreground italic">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1">
                                                            <span className="font-mono text-xs">{entry.txid.substring(0, 8)}...</span>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    copyTxid(entry.txid);
                                                                }}
                                                                title="Copy TX ID"
                                                            >
                                                                <ClipboardCopy className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                                {isExpanded && (
                                                    <TableRow>
                                                        <TableCell colSpan={7} className="bg-muted/20 p-0">
                                                            <Collapsible open={isExpanded}>
                                                                <CollapsibleContent className="px-6 py-4">
                                                                    <div className="space-y-4">
                                                                        {/* Diff View */}
                                                                        {diff.length > 0 && (
                                                                            <div>
                                                                                <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                                                                                    <ArrowDownUp className="h-4 w-4" />
                                                                                    Changes
                                                                                </h4>
                                                                                <div className="overflow-x-auto rounded-lg border">
                                                                                    <Table>
                                                                                        <TableHeader>
                                                                                            <TableRow>
                                                                                                <TableHead className="w-1/4">Field</TableHead>
                                                                                                <TableHead className="w-1/3">Old Value</TableHead>
                                                                                                <TableHead className="w-1/3">New Value</TableHead>
                                                                                            </TableRow>
                                                                                        </TableHeader>
                                                                                        <TableBody>
                                                                                            {diff.map((d) => (
                                                                                                <TableRow key={d.key}>
                                                                                                    <TableCell className="font-mono text-xs font-medium">
                                                                                                        {d.key}
                                                                                                    </TableCell>
                                                                                                    <TableCell className="bg-red-50/50 font-mono text-xs break-all dark:bg-red-950/20">
                                                                                                        {d.old || (
                                                                                                            <span className="text-muted-foreground italic">
                                                                                                                empty
                                                                                                            </span>
                                                                                                        )}
                                                                                                    </TableCell>
                                                                                                    <TableCell className="bg-green-50/50 font-mono text-xs break-all dark:bg-green-950/20">
                                                                                                        {d.new || (
                                                                                                            <span className="text-muted-foreground italic">
                                                                                                                empty
                                                                                                            </span>
                                                                                                        )}
                                                                                                    </TableCell>
                                                                                                </TableRow>
                                                                                            ))}
                                                                                        </TableBody>
                                                                                    </Table>
                                                                                </div>
                                                                            </div>
                                                                        )}

                                                                        {/* Original TX ID link */}
                                                                        {entry.original_txid && (
                                                                            <div className="flex items-center gap-2 text-sm">
                                                                                <ExternalLink className="text-muted-foreground h-4 w-4" />
                                                                                <span className="text-muted-foreground">References original TX:</span>
                                                                                <code className="bg-muted rounded px-2 py-0.5 font-mono text-xs">
                                                                                    {entry.original_txid.substring(0, 16)}...
                                                                                </code>
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="icon"
                                                                                    className="h-6 w-6"
                                                                                    onClick={(e) => {
                                                                                        e.stopPropagation();
                                                                                        copyTxid(entry.original_txid!);
                                                                                    }}
                                                                                    title="Copy original TX ID"
                                                                                >
                                                                                    <ClipboardCopy className="h-3 w-3" />
                                                                                </Button>
                                                                            </div>
                                                                        )}

                                                                        {/* Raw Blockchain Data */}
                                                                        <div>
                                                                            <div className="mb-2 flex items-center justify-between">
                                                                                <h4 className="text-sm font-medium">Raw Blockchain Data</h4>
                                                                                <Badge variant="outline" className="font-mono text-xs">
                                                                                    TX: {entry.txid}
                                                                                </Badge>
                                                                            </div>
                                                                            <pre className="bg-muted max-h-64 overflow-x-auto rounded-lg p-4 text-xs leading-relaxed">
                                                                                {JSON.stringify(entry.raw_json, null, 2)}
                                                                            </pre>
                                                                        </div>
                                                                    </div>
                                                                </CollapsibleContent>
                                                            </Collapsible>
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
 if (pagination.current_page <= 1) {
 e.preventDefault();
 return;
 }
 e.preventDefault();
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: { ...buildQuery(), page: String(pagination.current_page - 1) } }), { preserveState: true });
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
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: { ...buildQuery(), page: String(page) } }), { preserveState: true });
 }}
                                                >
                                                    {page}
                                                </PaginationLink>
                                            </PaginationItem>
                                        ),
                                    )}
                                    <PaginationItem>
                                        <PaginationNext
                                            href={pagination.current_page < pagination.last_page ? `?page=${pagination.current_page + 1}` : undefined}
 onClick={(e) => {
 if (pagination.current_page >= pagination.last_page) {
 e.preventDefault();
 return;
 }
 e.preventDefault();
 setIsFiltering(true);
 router.visit(ledgerRoute({ query: { ...buildQuery(), page: String(pagination.current_page + 1) } }), { preserveState: true });
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
