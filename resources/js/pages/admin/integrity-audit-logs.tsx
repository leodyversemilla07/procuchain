import { RevisionTree, type RevisionNode } from '@/components/admin/revision-tree';
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
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityAuditLogs from '@/routes/admin/integrity-audit-logs';
import { Head, usePage } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { AlertTriangle, CheckCircle2, Clock, FileSearch, ScrollText, Shield, ShieldAlert, Wrench, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

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
    revision_history: RevisionNode[] | null;
    created_at: string;
}

interface PaginatedAuditLogs {
    data: AuditLogRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

import { type SharedData } from '@/types';

interface AuditPageProps extends SharedData {
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

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function IntegrityAuditLogs() {
    const { violationTypes, recoveryStatuses, severityLevels, sources } = usePage<AuditPageProps>().props;

    const [logs, setLogs] = useState<PaginatedAuditLogs | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedLog, setSelectedLog] = useState<AuditLogRecord | null>(null);
    const [repairing, setRepairing] = useState<number | null>(null);
    const [repairResult, setRepairResult] = useState<{ success: boolean; items_restored?: number; error?: string } | null>(null);
    // Filters
    const [filterViolationType, setFilterViolationType] = useState<string>('');
    const [filterSeverity, setFilterSeverity] = useState<string>('');
    const [filterStatus, setFilterStatus] = useState<string>('');
    const [filterSource, setFilterSource] = useState<string>('');
    const [filterStreamKey, setFilterStreamKey] = useState<string>('');
    const [filterRunId, setFilterRunId] = useState<string>('');

    const fetchLogs = useCallback(
        async (page = 1) => {
            setLoading(true);
            setError(null);
            try {
                const params = new URLSearchParams();
                params.set('page', page.toString());
                if (filterViolationType) params.set('violation_type', filterViolationType);
                if (filterSeverity) params.set('severity', filterSeverity);
                if (filterStatus) params.set('recovery_status', filterStatus);
                if (filterSource) params.set('source', filterSource);
                if (filterStreamKey) params.set('stream_key', filterStreamKey);
                if (filterRunId) params.set('verification_run_id', filterRunId);

                const res = await fetch(`/admin/integrity-audit-logs/api?${params.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(`Failed to fetch audit logs (${res.status})`);
                const data = await res.json();
                setLogs(data);
            } catch (e) {
                setError(e instanceof Error ? e.message : 'Unknown error');
            } finally {
                setLoading(false);
            }
        },
        [filterViolationType, filterSeverity, filterStatus, filterSource, filterStreamKey, filterRunId],
    );

    useEffect(() => {
        fetchLogs(1);
    }, [fetchLogs]);

    const handleRepair = async (id: number) => {
        setRepairing(id);
        setRepairResult(null);
        try {
            const res = await fetch(`/admin/integrity-audit-logs/${id}/repair`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            setRepairResult(data);
            if (data.success) {
                fetchLogs(logs?.current_page ?? 1);
            }
        } catch {
            setRepairResult({ success: false, error: 'Network error' });
        } finally {
            setRepairing(null);
        }
    };

    const pendingCount = logs?.data.filter((l) => l.recovery_status === 'pending').length ?? 0;
    const criticalCount = logs?.data.filter((l) => l.severity === 'critical').length ?? 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrity Audit Logs" />

            {/* Stats Cards */}
            <StatsGrid
                items={[
                    { label: 'Total Records', value: logs?.total ?? 0, icon: ScrollText, iconClassName: 'bg-muted' },
                    {
                        label: 'Current Page Pending',
                        value: pendingCount,
                        icon: Clock,
                        iconClassName: pendingCount > 0 ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-muted',
                    },
                    {
                        label: 'Critical on Page',
                        value: criticalCount,
                        icon: ShieldAlert,
                        iconClassName: criticalCount > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-muted',
                    },
                    { label: 'Navigate to Breaches', value: '→', icon: AlertTriangle, iconClassName: 'bg-muted' },
                ]}
                className="p-4"
            />

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3 p-4">
                <Select value={filterViolationType} onValueChange={(v) => setFilterViolationType(v === '__all' ? '' : v)}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Violation Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">All Types</SelectItem>
                        {Object.entries(violationTypes).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={filterSeverity} onValueChange={(v) => setFilterSeverity(v === '__all' ? '' : v)}>
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Severity" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">All</SelectItem>
                        {Object.entries(severityLevels).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={filterStatus} onValueChange={(v) => setFilterStatus(v === '__all' ? '' : v)}>
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">All</SelectItem>
                        {Object.entries(recoveryStatuses).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={filterSource} onValueChange={(v) => setFilterSource(v === '__all' ? '' : v)}>
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Source" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">All</SelectItem>
                        {Object.entries(sources).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Input placeholder="PR number..." value={filterStreamKey} onChange={(e) => setFilterStreamKey(e.target.value)} className="w-44" />

                <Input placeholder="Run ID..." value={filterRunId} onChange={(e) => setFilterRunId(e.target.value)} className="w-44" />

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
                        }}
                    >
                        Clear Filters
                    </Button>
                )}
            </div>

            {/* Repair Result Banner */}
            {repairResult && (
                <Card className={`mx-4 ${repairResult.success ? 'border-green-200 dark:border-green-900' : 'border-red-200 dark:border-red-900'}`}>
                    <CardContent className="flex items-center gap-3 p-4">
                        {repairResult.success ? <CheckCircle2 className="h-5 w-5 text-green-600" /> : <XCircle className="h-5 w-5 text-red-600" />}
                        <span>
                            {repairResult.success
                                ? `Repair successful: ${repairResult.items_restored} item(s) restored from blockchain.`
                                : `Repair failed: ${repairResult.error}`}
                        </span>
                        <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setRepairResult(null)}>
                            Dismiss
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* Error Banner */}
            {error && (
                <Card className="mx-4 border-red-200 dark:border-red-900">
                    <CardContent className="p-4 text-red-700 dark:text-red-400">{error}</CardContent>
                </Card>
            )}

            {/* Audit Logs Table */}
            <div className="p-4">
                {loading && !logs ? (
                    <Card>
                        <CardContent className="space-y-4 p-6">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-10 w-full" />
                        </CardContent>
                    </Card>
                ) : logs && logs.data.length === 0 ? (
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
                    logs && (
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
                                        {logs.data.map((log) => {
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
                                                            <StatusIcon className="mr-1 h-3 w-3" />
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
                                                            <Button variant="ghost" size="sm" onClick={() => setSelectedLog(log)}>
                                                                <FileSearch className="h-4 w-4" />
                                                            </Button>
                                                            {log.recovery_status === 'pending' && (
                                                                <AlertDialog>
                                                                    <AlertDialogTrigger asChild>
                                                                        <Button variant="outline" size="sm" disabled={repairing === log.id}>
                                                                            <Wrench className="mr-1 h-3 w-3" />
                                                                            {repairing === log.id ? 'Repairing...' : 'Repair'}
                                                                        </Button>
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
                                                            <Button variant="ghost" size="sm" asChild>
                                                                <a href={integrityAuditLogs.detail.url(log.id)} title="View Details">
                                                                    <FileSearch className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                            <Button variant="ghost" size="sm" asChild>
                                                                <a href={integrityAuditLogs.report.url(log.verification_run_id)} title="View Report">
                                                                    <ScrollText className="h-4 w-4" />
                                                                </a>
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
                                    <div className="mt-4 flex items-center justify-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={logs.current_page <= 1}
                                            onClick={() => fetchLogs(logs.current_page - 1)}
                                        >
                                            ← Prev
                                        </Button>
                                        <span className="text-muted-foreground text-sm">
                                            Page {logs.current_page} of {logs.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={logs.current_page >= logs.last_page}
                                            onClick={() => fetchLogs(logs.current_page + 1)}
                                        >
                                            Next →
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )
                )}
            </div>

            {/* Audit Log Detail Dialog */}
            <Dialog open={!!selectedLog} onOpenChange={() => setSelectedLog(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ScrollText className="h-5 w-5" />
                            Audit Log #{selectedLog?.id}
                        </DialogTitle>
                        <DialogDescription>Permanent forensic record for this violation</DialogDescription>
                    </DialogHeader>
                    {selectedLog && (
                        <div className="space-y-3 text-sm">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground">Violation Type:</span>
                                    <br />
                                    <strong>{violationTypes[selectedLog.violation_type] ?? selectedLog.violation_type}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Severity:</span>
                                    <br />
                                    <Badge className={SEVERITY_COLORS[selectedLog.severity]}>{selectedLog.severity}</Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">PR Number:</span>
                                    <br />
                                    <code>{selectedLog.stream_key}</code>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Stream:</span>
                                    <br />
                                    {selectedLog.stream}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Recovery Status:</span>
                                    <br />
                                    <Badge className={RECOVERY_STATUS_BADGE[selectedLog.recovery_status]?.className ?? ''}>
                                        {recoveryStatuses[selectedLog.recovery_status] ?? selectedLog.recovery_status}
                                    </Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Source:</span>
                                    <br />
                                    <span className="capitalize">{selectedLog.source}</span>
                                </div>
                            </div>

                            <div>
                                <span className="text-muted-foreground">Transaction ID:</span>
                                <br />
                                <code className="text-xs break-all">{selectedLog.txid ?? '—'}</code>
                            </div>

                            {/* Revision Tracking - Visual Tree */}
                            {selectedLog.revision_number !== null && selectedLog.revision_number !== undefined && (
                                <div>
                                    <span className="text-muted-foreground">Revision Tracking:</span>
                                    <div className="mt-2">
                                        {selectedLog.revision_history && selectedLog.revision_history.length > 0 ? (
                                            <RevisionTree
                                                revisions={selectedLog.revision_history}
                                                currentTxid={selectedLog.txid ?? undefined}
                                                compact
                                            />
                                        ) : (
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="outline" className="font-mono">
                                                        Rev #{selectedLog.revision_number}
                                                    </Badge>
                                                    {selectedLog.parent_txid && (
                                                        <>
                                                            <span className="text-muted-foreground text-xs">← parent</span>
                                                            <code className="text-muted-foreground text-xs break-all">
                                                                {selectedLog.parent_txid.slice(0, 16)}…
                                                            </code>
                                                        </>
                                                    )}
                                                </div>
                                                {selectedLog.revision_lineage && selectedLog.revision_lineage.length > 1 && (
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        {selectedLog.revision_lineage.map((txid, i) => (
                                                            <span key={i} className="flex items-center gap-1">
                                                                {i > 0 && <span className="text-muted-foreground text-xs">→</span>}
                                                                <code className="text-muted-foreground text-xs" title={txid}>
                                                                    {txid.slice(0, 12)}…
                                                                </code>
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div>
                                <span className="text-muted-foreground">Verification Run ID:</span>
                                <br />
                                <code className="text-xs break-all">{selectedLog.verification_run_id}</code>
                            </div>

                            {selectedLog.field_differences && (
                                <div>
                                    <span className="text-muted-foreground">Field Differences:</span>
                                    <br />
                                    <pre className="bg-muted mt-1 max-h-40 overflow-auto rounded p-2 text-xs">
                                        {JSON.stringify(selectedLog.field_differences, null, 2)}
                                    </pre>
                                </div>
                            )}

                            {selectedLog.mirror_snapshot && (
                                <div>
                                    <span className="text-muted-foreground">Mirror Snapshot (DB):</span>
                                    <br />
                                    <pre className="mt-1 max-h-32 overflow-auto rounded bg-red-50 p-2 text-xs dark:bg-red-950/30">
                                        {JSON.stringify(selectedLog.mirror_snapshot, null, 2)}
                                    </pre>
                                </div>
                            )}

                            {selectedLog.chain_snapshot && (
                                <div>
                                    <span className="text-muted-foreground">Chain Snapshot (Blockchain):</span>
                                    <br />
                                    <pre className="mt-1 max-h-32 overflow-auto rounded bg-green-50 p-2 text-xs dark:bg-green-950/30">
                                        {JSON.stringify(selectedLog.chain_snapshot, null, 2)}
                                    </pre>
                                </div>
                            )}

                            {selectedLog.recovery_result && (
                                <div>
                                    <span className="text-muted-foreground">Recovery Result:</span>
                                    <br />
                                    <pre className="bg-muted mt-1 max-h-32 overflow-auto rounded p-2 text-xs">
                                        {JSON.stringify(selectedLog.recovery_result, null, 2)}
                                    </pre>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground">Detected:</span>
                                    <br />
                                    {formatDistanceToNow(parseISO(selectedLog.created_at), { addSuffix: true })}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Recovered:</span>
                                    <br />
                                    {selectedLog.recovered_at
                                        ? formatDistanceToNow(parseISO(selectedLog.recovered_at), { addSuffix: true })
                                        : 'Not recovered'}
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
