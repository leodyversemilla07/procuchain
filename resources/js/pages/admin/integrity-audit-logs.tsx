import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityAuditLogs from '@/routes/admin/integrity-audit-logs';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { CheckCircle2, Clock, FileSearch, ScrollText, Shield, ShieldAlert, Wrench, XCircle } from 'lucide-react';
import { useState } from 'react';

interface AuditLogRecord {
    id: number;
    stream: string;
    stream_key: string;
    txid: string | null;
    violation_type: string;
    severity: string;
    field_differences: Record<string, unknown>[] | null;
    mirror_snapshot: Record<string, unknown> | null;
    chain_snapshot: Record<string, unknown> | null;
    recovery_status: string;
    recovered_at: string | null;
    recovery_result: Record<string, unknown> | null;
    record_id: number | null;
    verification_run_id: string;
    source: string;
    revision_number: number | null;
    parent_txid: string | null;
    revision_lineage: string[] | null;
    created_at: string;
}

interface PaginatedLogs {
    data: AuditLogRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface AuditPageFilters {
    violation_type?: string;
    stream_key?: string;
    verification_run_id?: string;
    severity?: string;
    recovery_status?: string;
    source?: string;
}

import { type SharedData } from '@/types';

interface AuditPageProps extends SharedData {
    logs: PaginatedLogs;
    filters: AuditPageFilters;
    violationTypes: Record<string, string>;
    recoveryStatuses: Record<string, string>;
    severityLevels: Record<string, string>;
    sources: Record<string, string>;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Audit Logs', href: '#' },
];

const SEVERITY_COLORS: Record<string, string> = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
};

const RECOVERY_STATUS_BADGE: Record<string, { className: string; icon: typeof CheckCircle2 }> = {
    pending: { className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', icon: Clock },
    restored: { className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', icon: CheckCircle2 },
    failed: { className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: XCircle },
    skipped: { className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400', icon: Shield },
};

function truncateHash(hash: string, len = 12): string {
    if (!hash) return '—';
    if (hash.length <= len * 2 + 3) return hash;
    return `${hash.slice(0, len)}...${hash.slice(-len)}`;
}

export default function IntegrityAuditLogs() {
    const { logs, filters, violationTypes, recoveryStatuses, severityLevels, sources } = usePage<AuditPageProps>().props;

    const [repairing, setRepairing] = useState<number | null>(null);

    // Local filter state — synced to URL via Inertia
    const [filterViolationType, setFilterViolationType] = useState<string>(filters.violation_type ?? '');
    const [filterSeverity, setFilterSeverity] = useState<string>(filters.severity ?? '');
    const [filterStatus, setFilterStatus] = useState<string>(filters.recovery_status ?? '');
    const [filterSource, setFilterSource] = useState<string>(filters.source ?? '');
    const [filterStreamKey, setFilterStreamKey] = useState<string>(filters.stream_key ?? '');
    const [filterRunId, setFilterRunId] = useState<string>(filters.verification_run_id ?? '');

    /** Push filters to URL via Inertia GET */
    const applyFilters = (overrides?: Partial<AuditPageFilters>) => {
        const params: Record<string, string> = {};
        const vt = overrides?.violation_type ?? filterViolationType;
        const sev = overrides?.severity ?? filterSeverity;
        const status = overrides?.recovery_status ?? filterStatus;
        const src = overrides?.source ?? filterSource;
        const sk = overrides?.stream_key ?? filterStreamKey;
        const rid = overrides?.verification_run_id ?? filterRunId;

        if (vt) params.violation_type = vt;
        if (sev) params.severity = sev;
        if (status) params.recovery_status = status;
        if (src) params.source = src;
        if (sk) params.stream_key = sk;
        if (rid) params.verification_run_id = rid;

        router.get(integrityAuditLogs.index.url(), params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleRepair = (id: number) => {
        setRepairing(id);
        router.post(
            integrityAuditLogs.repair.url(id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRepairing(null),
            },
        );
    };

    const pendingCount = (logs?.data ?? []).filter((l) => l.recovery_status === 'pending').length;
    const criticalCount = (logs?.data ?? []).filter((l) => l.severity === 'critical').length;
    const restoredCount = (logs?.data ?? []).filter((l) => l.recovery_status === 'restored').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrity Audit Logs" />

            {/* Hero Header */}
            <div className="p-4">
                <HeroCard
                    icon={ScrollText}
                    title="Integrity Audit Logs"
                    description="Detailed log of all integrity verification runs and detected violations across blockchain mirror records."
                />
            </div>

            <StatsGrid
                items={[
                    { label: 'Total Audit Entries', value: logs.total, icon: ScrollText, iconClassName: 'bg-muted' },
                    {
                        label: 'Pending (Page)',
                        value: pendingCount,
                        icon: Clock,
                        iconClassName: pendingCount > 0 ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-muted',
                    },
                    {
                        label: 'Critical (Page)',
                        value: criticalCount,
                        icon: ShieldAlert,
                        iconClassName: criticalCount > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-muted',
                    },
                    {
                        label: 'Restored (Page)',
                        value: restoredCount,
                        icon: CheckCircle2,
                        iconClassName: restoredCount > 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-muted',
                    },
                ]}
                className="p-4"
            />

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3 p-4">
                <Select
                    value={filterViolationType}
                    onValueChange={(v) => {
                        const next = v === '__all' ? '' : (v ?? '');
                        setFilterViolationType(next);
                        applyFilters({ violation_type: next || undefined });
                    }}
                >
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Violation Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="__all">All Types</SelectItem>
                            {Object.entries(violationTypes).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Select
                    value={filterSeverity}
                    onValueChange={(v) => {
                        const next = v === '__all' ? '' : (v ?? '');
                        setFilterSeverity(next);
                        applyFilters({ severity: next || undefined });
                    }}
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Severity" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="__all">All</SelectItem>
                            {Object.entries(severityLevels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Select
                    value={filterStatus}
                    onValueChange={(v) => {
                        const next = v === '__all' ? '' : (v ?? '');
                        setFilterStatus(next);
                        applyFilters({ recovery_status: next || undefined });
                    }}
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="__all">All</SelectItem>
                            {Object.entries(recoveryStatuses).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Select
                    value={filterSource}
                    onValueChange={(v) => {
                        const next = v === '__all' ? '' : (v ?? '');
                        setFilterSource(next);
                        applyFilters({ source: next || undefined });
                    }}
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Source" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="__all">All</SelectItem>
                            {Object.entries(sources).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Input
                    placeholder="PR number..."
                    value={filterStreamKey}
                    onChange={(e) => setFilterStreamKey(e.target.value)}
                    onBlur={() => applyFilters()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') applyFilters();
                    }}
                    className="w-44"
                />

                <Input
                    placeholder="Run ID..."
                    value={filterRunId}
                    onChange={(e) => setFilterRunId(e.target.value)}
                    onBlur={() => applyFilters()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') applyFilters();
                    }}
                    className="w-44"
                />

                {(filterViolationType || filterSeverity || filterStatus || filterSource || filterStreamKey || filterRunId) && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setFilterViolationType('');
                            setFilterSeverity('');
                            setFilterStatus('');
                            setFilterSource('');
                            setFilterStreamKey('');
                            setFilterRunId('');
                            applyFilters({
                                violation_type: undefined,
                                severity: undefined,
                                recovery_status: undefined,
                                source: undefined,
                                stream_key: undefined,
                                verification_run_id: undefined,
                            });
                        }}
                    >
                        Clear Filters
                    </Button>
                )}
            </div>

            {/* Table */}
            <div className="p-4">
                {!logs?.data || logs.data.length === 0 ? (
                    <Empty>
                        <EmptyMedia>
                            <FileSearch className="text-muted-foreground h-16 w-16" />
                        </EmptyMedia>
                        <EmptyHeader>
                            <EmptyTitle>No Audit Logs</EmptyTitle>
                        </EmptyHeader>
                        <EmptyDescription>No integrity audit log entries found matching your filters.</EmptyDescription>
                    </Empty>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ScrollText className="h-5 w-5" />
                                Integrity Audit Log
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Violation</TableHead>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>Stream</TableHead>
                                        <TableHead>Rev</TableHead>
                                        <TableHead>TXID</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>Run ID</TableHead>
                                        <TableHead>Detected</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(logs?.data ?? []).map((log) => {
                                        const severityColor = SEVERITY_COLORS[log.severity] ?? SEVERITY_COLORS.medium;
                                        const statusBadge = RECOVERY_STATUS_BADGE[log.recovery_status] ?? RECOVERY_STATUS_BADGE.pending;
                                        const StatusIcon = statusBadge.icon;

                                        return (
                                            <TableRow
                                                key={log.id}
                                                className={log.recovery_status === 'pending' ? 'bg-yellow-50/50 dark:bg-yellow-950/20' : ''}
                                            >
                                                <TableCell>
                                                    <Badge className={severityColor}>{log.severity}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {violationTypes[log.violation_type] ?? log.violation_type}
                                                </TableCell>
                                                <TableCell>
                                                    <code className="text-xs">{log.stream_key}</code>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">{log.stream}</TableCell>
                                                <TableCell className="text-center">
                                                    {log.revision_number !== null && log.revision_number !== undefined ? (
                                                        <Badge variant="outline" className="font-mono text-xs">
                                                            #{log.revision_number}
                                                        </Badge>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {log.txid ? (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger>
                                                                    <code className="text-xs">{truncateHash(log.txid, 8)}</code>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <code className="text-xs break-all">{log.txid}</code>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={statusBadge.className}>
                                                        <StatusIcon data-icon="inline-start" />
                                                        {recoveryStatuses[log.recovery_status] ?? log.recovery_status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm capitalize">{log.source}</TableCell>
                                                <TableCell>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger>
                                                                <code className="text-xs">{truncateHash(log.verification_run_id, 6)}</code>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <code className="text-xs break-all">{log.verification_run_id}</code>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {formatDistanceToNow(parseISO(log.created_at), { addSuffix: true })}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        {log.recovery_status === 'pending' && (
                                                            <AlertDialog>
                                                                <AlertDialogTrigger
                                                                    render={<Button variant="outline" size="sm" disabled={repairing === log.id} />}
                                                                    nativeButton={false}
                                                                >
                                                                    <Wrench data-icon="inline-start" />
                                                                    {repairing === log.id ? 'Repairing...' : 'Repair'}
                                                                </AlertDialogTrigger>
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>Repair this violation?</AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            This will re-sync the data from the blockchain, restoring the mirror
                                                                            record to match the on-chain truth. Violation:{' '}
                                                                            <strong>
                                                                                {violationTypes[log.violation_type] ?? log.violation_type}
                                                                            </strong>{' '}
                                                                            on PR <code>{log.stream_key}</code>
                                                                        </AlertDialogDescription>
                                                                    </AlertDialogHeader>
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                                        <AlertDialogAction onClick={() => handleRepair(log.id)}>
                                                                            Repair from Blockchain
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            render={<Link href={integrityAuditLogs.detail.url(log.id)} />}
                                                            nativeButton={false}
                                                            title="View Details"
                                                        >
                                                            <FileSearch data-icon="inline-start" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            render={<Link href={integrityAuditLogs.report.url(log.verification_run_id)} />}
                                                            nativeButton={false}
                                                            title="View Report"
                                                        >
                                                            <ScrollText data-icon="inline-start" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {logs.last_page > 1 && (
                                <Pagination className="mt-4">
                                    <PaginationContent>
                                        {/* First */}
                                        <PaginationItem>
                                            <PaginationLink
                                                href="#"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    if (logs.current_page > 1) {
                                                        router.get(integrityAuditLogs.index.url({ query: { ...filters, page: 1 } }));
                                                    }
                                                }}
                                                className={logs.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                                            >
                                                First
                                            </PaginationLink>
                                        </PaginationItem>

                                        {/* Previous */}
                                        <PaginationItem>
                                            <PaginationPrevious
                                                href="#"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    if (logs.current_page > 1) {
                                                        router.get(
                                                            integrityAuditLogs.index.url({ query: { ...filters, page: logs.current_page - 1 } }),
                                                        );
                                                    }
                                                }}
                                                className={logs.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                                            />
                                        </PaginationItem>

                                        {/* Page Numbers */}
                                        {(() => {
                                            const pages: (number | 'ellipsis')[] = [];
                                            const current = logs.current_page;
                                            const last = logs.last_page;

                                            if (last <= 7) {
                                                for (let i = 1; i <= last; i++) pages.push(i);
                                            } else {
                                                pages.push(1);
                                                if (current > 3) pages.push('ellipsis');
                                                const start = Math.max(2, current - 1);
                                                const end = Math.min(last - 1, current + 1);
                                                for (let i = start; i <= end; i++) pages.push(i);
                                                if (current < last - 2) pages.push('ellipsis');
                                                pages.push(last);
                                            }

                                            return pages.map((page, i) => {
                                                if (page === 'ellipsis') {
                                                    return (
                                                        <PaginationItem key={`ellipsis-${i}`}>
                                                            <PaginationEllipsis />
                                                        </PaginationItem>
                                                    );
                                                }
                                                return (
                                                    <PaginationItem key={page}>
                                                        <PaginationLink
                                                            href="#"
                                                            isActive={page === current}
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                router.get(integrityAuditLogs.index.url({ query: { ...filters, page } }));
                                                            }}
                                                        >
                                                            {page}
                                                        </PaginationLink>
                                                    </PaginationItem>
                                                );
                                            });
                                        })()}

                                        {/* Next */}
                                        <PaginationItem>
                                            <PaginationNext
                                                href="#"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    if (logs.current_page < logs.last_page) {
                                                        router.get(
                                                            integrityAuditLogs.index.url({ query: { ...filters, page: logs.current_page + 1 } }),
                                                        );
                                                    }
                                                }}
                                                className={logs.current_page >= logs.last_page ? 'pointer-events-none opacity-50' : ''}
                                            />
                                        </PaginationItem>

                                        {/* Last */}
                                        <PaginationItem>
                                            <PaginationLink
                                                href="#"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    if (logs.current_page < logs.last_page) {
                                                        router.get(integrityAuditLogs.index.url({ query: { ...filters, page: logs.last_page } }));
                                                    }
                                                }}
                                                className={logs.current_page >= logs.last_page ? 'pointer-events-none opacity-50' : ''}
                                            >
                                                Last
                                            </PaginationLink>
                                        </PaginationItem>
                                    </PaginationContent>
                                </Pagination>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
