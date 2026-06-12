import { index as sharedLedgerRoutes } from '@/actions/App/Http/Controllers/SharedLedgerController';
import { LedgerEntryRow, LedgerFilterBar, PurgeWarnings, STREAM_CONFIG } from '@/components/shared-ledger';
import { getPaginationPages } from '@/components/shared-ledger/utils';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { LedgerEntry, LedgerFilters, LedgerPagination, NodeOption, StreamOption } from '@/types/blockchain';
import { Head, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { BookOpen, Shield } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { type DateRange } from 'react-day-picker';

interface NodePurgeState {
    is_purged: boolean;
    was_explicitly_purged: boolean;
    partially_purged: boolean;
    unsubscribed_streams: string[];
    purge_reason?: string | null;
    purge_timestamp?: number | null;
    connection_error?: boolean;
    connection_error_message?: string | null;
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

const resolveSharedLedgerRoute = (pathname: string) => {
    const routeKey = Object.keys(sharedLedgerRoutes).find((key) => pathname === key || pathname.startsWith(key + '/')) as
        | keyof typeof sharedLedgerRoutes
        | undefined;

    return routeKey ? sharedLedgerRoutes[routeKey] : sharedLedgerRoutes['/admin/shared-ledger'];
};

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

    const clearFilters = () => {
        setPrNumber('');
        setStream('');
        setNode('all');
        setDateRange(undefined);
        setIsFiltering(true);
        router.visit(ledgerRoute(), { preserveState: false, replace: true });
    };

    const navigateFilter = (query: Record<string, string>) => {
        setIsFiltering(true);
        router.visit(ledgerRoute({ query }), {
            preserveState: true,
            replace: true,
            onFinish: () => setIsFiltering(false),
        });
    };

    const navigateToPage = (targetPage: number) => {
        setIsFiltering(true);
        router.visit(ledgerRoute({ query: { ...buildQuery(), page: String(targetPage) } }), {
            preserveState: true,
            onFinish: () => setIsFiltering(false),
        });
    };

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
                            <Shield />
                            <span className="text-muted-foreground">
                                <strong className="text-foreground">{pagination.total.toLocaleString()}</strong> total transactions
                            </span>
                        </div>
                        {Object.entries(stream_totals).map(([s, count]) => {
                            const cfg = STREAM_CONFIG[s];
                            if (!cfg) return null;
                            const Icon = cfg.icon;
                            return (
                                <div key={s} className="flex items-center gap-2 text-sm">
                                    <Icon />
                                    <span className="text-muted-foreground">
                                        <strong className="text-foreground">{count.toLocaleString()}</strong> {cfg.label.toLowerCase()}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </HeroCard>

                {error && <div className="bg-destructive/10 text-destructive rounded-md px-4 py-3 text-sm">{error}</div>}

                <PurgeWarnings purgeState={node_purge_state} />

                <LedgerFilterBar
                    prNumber={prNumber}
                    setPrNumber={setPrNumber}
                    stream={stream}
                    setStream={setStream}
                    node={node}
                    setNode={setNode}
                    dateRange={dateRange}
                    setDateRange={setDateRange}
                    available_streams={available_streams}
                    available_nodes={available_nodes}
                    entriesCount={entries.length}
                    buildQuery={buildQuery}
                    navigate={navigateFilter}
                    setIsFiltering={setIsFiltering}
                    hasActiveFilters={hasActiveFilters}
                    clearFilters={clearFilters}
                />

                <div className="bg-primary/5 border-primary/20 rounded-lg border px-4 py-3 text-sm">
                    <p className="text-primary flex items-center gap-2 font-medium">
                        <Shield />
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
                                    {entries.map((entry) => (
                                        <LedgerEntryRow
                                            key={entry.txid}
                                            entry={entry}
                                            isExpanded={expandedRows.has(entry.txid)}
                                            onToggle={toggleRow}
                                        />
                                    ))}
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
                                                navigateToPage(pagination.current_page - 1);
                                            }}
                                            className={pagination.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                                        />
                                    </PaginationItem>
                                    {getPaginationPages(pagination.current_page, pagination.last_page).map((pg, i) =>
                                        pg === '...' ? (
                                            <PaginationItem key={`ellipsis-${i}`}>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem key={pg}>
                                                <PaginationLink
                                                    isActive={pagination.current_page === pg}
                                                    href={`?page=${pg}`}
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        navigateToPage(pg as number);
                                                    }}
                                                >
                                                    {pg}
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
                                                navigateToPage(pagination.current_page + 1);
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
