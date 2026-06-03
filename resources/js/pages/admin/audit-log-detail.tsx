import { RevisionTree, type RevisionNode } from '@/components/admin/revision-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityAuditLogs from '@/routes/admin/integrity-audit-logs';
import { Head, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { CheckCircle2, Clock, FileSearch, Shield, XCircle } from 'lucide-react';

interface AuditLogDetail {
    id: number;
    stream: string;
    stream_key: string;
    txid: string | null;
    violation_type: string;
    severity: string;
    field_differences: Array<{ field: string; old_value: unknown; new_value: unknown }> | null;
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

interface AuditLogDetailPageProps {
    logId: number;
    log: AuditLogDetail | null;
    error?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Audit Logs', href: integrityAuditLogs.index.url() },
    { title: 'Audit Log Detail', href: '#' },
];

const SEVERITY_COLORS: Record<string, string> = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
};

const RECOVERY_STATUS_STYLES: Record<string, { className: string; icon: typeof CheckCircle2; label: string }> = {
    pending: { className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', icon: Clock, label: 'Pending' },
    restored: { className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', icon: CheckCircle2, label: 'Restored' },
    failed: { className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: XCircle, label: 'Failed' },
    skipped: { className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400', icon: Shield, label: 'Skipped' },
};

const VIOLATION_TYPE_LABELS: Record<string, string> = {
    hash_mismatch: 'Hash Mismatch',
    content_mismatch: 'Content Mismatch',
    unauthorized_publisher: 'Unauthorized Publisher',
    row_deleted: 'Row Deleted',
    user_address_tampered: 'User Address Tampered',
};

export default function AuditLogDetailPage({ logId, log, error }: AuditLogDetailPageProps) {
    const RecoveryIcon = log ? (RECOVERY_STATUS_STYLES[log.recovery_status]?.icon ?? Clock) : Clock;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Audit Log #${logId}`} />

            <div className="p-4 sm:p-6">
                {/* Header */}
                <div className="mb-6 flex items-center gap-4">
                    <div className="flex-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <FileSearch className="h-6 w-6" />
                            Audit Log #{logId}
                        </h1>
                        <p className="text-muted-foreground text-sm">Permanent forensic record for this violation</p>
                    </div>
                </div>

                {error ? (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Error Loading Audit Log</CardTitle>
                            <CardDescription>{error}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button onClick={() => router.reload({ only: ['log'] })} variant="outline">
                                Retry
                            </Button>
                        </CardContent>
                    </Card>
                ) : log ? (
                    <div className="space-y-6">
                        {/* Status Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Severity</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Badge className={SEVERITY_COLORS[log.severity]}>{log.severity}</Badge>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Violation Type</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-medium">{VIOLATION_TYPE_LABELS[log.violation_type] ?? log.violation_type}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Recovery Status</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Badge className={RECOVERY_STATUS_STYLES[log.recovery_status]?.className}>
                                        <RecoveryIcon data-icon="inline-start" />
                                        {RECOVERY_STATUS_STYLES[log.recovery_status]?.label}
                                    </Badge>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Source</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="capitalize">{log.source}</p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Main Content */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {/* Revision Tree */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Revision History</CardTitle>
                                    <CardDescription>Complete revision lineage for this record</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {log.revision_history && log.revision_history.length > 0 ? (
                                        <RevisionTree revisions={log.revision_history} currentTxid={log.txid ?? undefined} />
                                    ) : log.revision_lineage && log.revision_lineage.length > 0 ? (
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground text-sm">Revision Lineage:</p>
                                            <div className="flex flex-wrap items-center gap-1">
                                                {log.revision_lineage.map((txid, i) => (
                                                    <span key={i} className="flex items-center gap-1">
                                                        {i > 0 && <span className="text-muted-foreground text-xs">→</span>}
                                                        <code className="text-xs" title={txid}>
                                                            {txid.slice(0, 12)}…
                                                        </code>
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">No revision history available</p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Record Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Record Details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <span className="text-muted-foreground text-sm">Stream:</span>
                                            <p className="font-medium">{log.stream}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">PR Number:</span>
                                            <p className="font-medium">{log.stream_key}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">Transaction ID:</span>
                                            <code className="block text-xs break-all">{log.txid ?? '—'}</code>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">Revision:</span>
                                            <p className="font-medium">#{log.revision_number ?? '—'}</p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div>
                                        <span className="text-muted-foreground text-sm">Verification Run ID:</span>
                                        <code className="block text-xs break-all">{log.verification_run_id}</code>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <span className="text-muted-foreground text-sm">Detected:</span>
                                            <p className="text-sm">{formatDistanceToNow(parseISO(log.created_at), { addSuffix: true })}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">Recovered:</span>
                                            <p className="text-sm">
                                                {log.recovered_at
                                                    ? formatDistanceToNow(parseISO(log.recovered_at), { addSuffix: true })
                                                    : 'Not recovered'}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Field Differences */}
                        {log.field_differences && log.field_differences.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Field Differences</CardTitle>
                                    <CardDescription>Specific fields that were modified</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Field</TableHead>
                                                <TableHead>Original (Chain)</TableHead>
                                                <TableHead>Modified (DB)</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {log.field_differences.map((diff, i) => (
                                                <TableRow key={i}>
                                                    <TableCell className="font-mono text-xs">{diff.field}</TableCell>
                                                    <TableCell className="bg-green-50/50 text-xs dark:bg-green-950/20">
                                                        {typeof diff.old_value === 'object'
                                                            ? JSON.stringify(diff.old_value)
                                                            : String(diff.old_value ?? '—')}
                                                    </TableCell>
                                                    <TableCell className="bg-red-50/50 text-xs dark:bg-red-950/20">
                                                        {typeof diff.new_value === 'object'
                                                            ? JSON.stringify(diff.new_value)
                                                            : String(diff.new_value ?? '—')}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}

                        {/* Snapshots */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {log.chain_snapshot && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-green-700 dark:text-green-400">
                                            Chain Snapshot (Blockchain - Source of Truth)
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-96 overflow-auto rounded-lg bg-green-50/50 p-4 text-xs dark:bg-green-950/20">
                                            {JSON.stringify(log.chain_snapshot, null, 2)}
                                        </pre>
                                    </CardContent>
                                </Card>
                            )}
                            {log.mirror_snapshot && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-red-700 dark:text-red-400">Mirror Snapshot (Database - Tampered)</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-96 overflow-auto rounded-lg bg-red-50/50 p-4 text-xs dark:bg-red-950/20">
                                            {JSON.stringify(log.mirror_snapshot, null, 2)}
                                        </pre>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Recovery Result */}
                        {log.recovery_result && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recovery Result</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <pre className="bg-muted max-h-48 overflow-auto rounded-lg p-4 text-xs">
                                        {JSON.stringify(log.recovery_result, null, 2)}
                                    </pre>
                                </CardContent>
                            </Card>
                        )}


                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="h-32 w-full animate-pulse rounded bg-gray-200" />
                        <div className="h-64 w-full animate-pulse rounded bg-gray-200" />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
