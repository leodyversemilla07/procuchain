import { RevisionTree, type RevisionNode } from '@/components/admin/revision-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityAuditLogs from '@/routes/admin/integrity-audit-logs';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Clock, FileSearch, Shield, Wrench, XCircle } from 'lucide-react';
import { useState } from 'react';

interface VerificationReport {
    run_id: string;
    summary: {
        total_violations: number;
        critical: number;
        high: number;
        medium: number;
        low: number;
        restored: number;
        failed: number;
        pending: number;
        by_type: Record<string, number>;
    };
    violations: Array<{
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
        revision_number: number | null;
        parent_txid: string | null;
        revision_lineage: string[] | null;
        revision_history: RevisionNode[] | null;
        created_at: string;
    }>;
}

interface VerificationReportPageProps {
    runId: string;
    report: VerificationReport | null;
    error?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Audit Logs', href: integrityAuditLogs.index.url() },
    { title: 'Verification Report', href: '#' },
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

export default function VerificationReportPage({ runId, report, error }: VerificationReportPageProps) {
    const [expandedViolation, setExpandedViolation] = useState<number | null>(null);

    const handleRepair = (violationId: number) => {
        router.post(
            integrityAuditLogs.api.repair.url(violationId),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    // Reload the page to get fresh data
                    router.reload({ only: ['report'] });
                },
            },
        );
    };

    const handleRefresh = () => {
        router.reload({ only: ['report'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Verification Report - ${runId}`} />

            <div className="p-4 sm:p-6">
                {/* Header */}
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={integrityAuditLogs.index.url()}>
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <FileSearch className="h-6 w-6" />
                            Verification Run Report
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Run ID: <code className="text-xs">{runId}</code>
                        </p>
                    </div>
                    <Button onClick={handleRefresh} variant="outline">
                        Refresh
                    </Button>
                </div>

                {error ? (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Error Loading Report</CardTitle>
                            <CardDescription>{error}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button onClick={handleRefresh} variant="outline">
                                Retry
                            </Button>
                        </CardContent>
                    </Card>
                ) : report ? (
                    <div className="space-y-6">
                        {/* Summary Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Total Violations</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold">{report.summary.total_violations}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-red-600">Critical</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold text-red-600">{report.summary.critical}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-green-600">Restored</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold text-green-600">{report.summary.restored}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-yellow-600">Pending</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold text-yellow-600">{report.summary.pending}</p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Severity Breakdown */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Violation Types</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-3">
                                    {Object.entries(report.summary.by_type).map(([type, count]) => (
                                        <Badge key={type} variant="outline" className="text-sm">
                                            {VIOLATION_TYPE_LABELS[type] ?? type}: {count}
                                        </Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Violations List */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Violations ({report.violations.length})</CardTitle>
                                <CardDescription>Click a violation to see details and revision history</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {report.violations.length === 0 ? (
                                    <Empty>
                                        <EmptyMedia>
                                            <CheckCircle2 className="h-12 w-12 text-green-500" />
                                        </EmptyMedia>
                                        <EmptyHeader>
                                            <EmptyTitle>No Violations</EmptyTitle>
                                        </EmptyHeader>
                                        <EmptyDescription>This verification run found no integrity violations.</EmptyDescription>
                                    </Empty>
                                ) : (
                                    <div className="space-y-4">
                                        {report.violations.map((violation) => {
                                            const RecoveryIcon = RECOVERY_STATUS_STYLES[violation.recovery_status]?.icon ?? Clock;
                                            const isExpanded = expandedViolation === violation.id;

                                            return (
                                                <div key={violation.id} className="rounded-lg border">
                                                    {/* Violation Header */}
                                                    <button
                                                        onClick={() => setExpandedViolation(isExpanded ? null : violation.id)}
                                                        className="hover:bg-muted/50 flex w-full items-center justify-between p-4 text-left"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <Badge className={SEVERITY_COLORS[violation.severity]}>{violation.severity}</Badge>
                                                            <span className="font-medium">
                                                                {VIOLATION_TYPE_LABELS[violation.violation_type] ?? violation.violation_type}
                                                            </span>
                                                            <span className="text-muted-foreground text-sm">PR: {violation.stream_key}</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge className={RECOVERY_STATUS_STYLES[violation.recovery_status]?.className}>
                                                                <RecoveryIcon className="mr-1 h-3 w-3" />
                                                                {RECOVERY_STATUS_STYLES[violation.recovery_status]?.label}
                                                            </Badge>
                                                            {violation.revision_number && (
                                                                <Badge variant="outline" className="font-mono text-xs">
                                                                    Rev #{violation.revision_number}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </button>

                                                    {/* Expanded Details */}
                                                    {isExpanded && (
                                                        <div className="border-t p-4">
                                                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                                                {/* Revision Tree */}
                                                                {violation.revision_history && violation.revision_history.length > 0 && (
                                                                    <div>
                                                                        <h4 className="mb-2 text-sm font-medium">Revision History</h4>
                                                                        <RevisionTree
                                                                            revisions={violation.revision_history}
                                                                            currentTxid={violation.txid ?? undefined}
                                                                            compact
                                                                        />
                                                                    </div>
                                                                )}

                                                                {/* Field Differences */}
                                                                <div>
                                                                    <h4 className="mb-2 text-sm font-medium">Field Differences</h4>
                                                                    {violation.field_differences && violation.field_differences.length > 0 ? (
                                                                        <Table>
                                                                            <TableHeader>
                                                                                <TableRow>
                                                                                    <TableHead>Field</TableHead>
                                                                                    <TableHead>Original (Chain)</TableHead>
                                                                                    <TableHead>Modified (DB)</TableHead>
                                                                                </TableRow>
                                                                            </TableHeader>
                                                                            <TableBody>
                                                                                {violation.field_differences.map((diff, i) => (
                                                                                    <TableRow key={i}>
                                                                                        <TableCell className="font-mono text-xs">
                                                                                            {diff.field}
                                                                                        </TableCell>
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
                                                                    ) : (
                                                                        <p className="text-muted-foreground text-sm">No field differences recorded</p>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {/* Snapshots */}
                                                            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                                                {violation.chain_snapshot && (
                                                                    <div>
                                                                        <h4 className="mb-2 text-sm font-medium text-green-700">
                                                                            Chain Snapshot (Blockchain - Source of Truth)
                                                                        </h4>
                                                                        <pre className="max-h-48 overflow-auto rounded-lg bg-green-50/50 p-3 text-xs dark:bg-green-950/20">
                                                                            {JSON.stringify(violation.chain_snapshot, null, 2)}
                                                                        </pre>
                                                                    </div>
                                                                )}
                                                                {violation.mirror_snapshot && (
                                                                    <div>
                                                                        <h4 className="mb-2 text-sm font-medium text-red-700">
                                                                            Mirror Snapshot (Database - Tampered)
                                                                        </h4>
                                                                        <pre className="max-h-48 overflow-auto rounded-lg bg-red-50/50 p-3 text-xs dark:bg-red-950/20">
                                                                            {JSON.stringify(violation.mirror_snapshot, null, 2)}
                                                                        </pre>
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Actions */}
                                                            <div className="mt-4 flex gap-2">
                                                                {violation.recovery_status === 'pending' && (
                                                                    <Button variant="outline" size="sm" onClick={() => handleRepair(violation.id)}>
                                                                        <Wrench className="mr-2 h-4 w-4" />
                                                                        Repair from Blockchain
                                                                    </Button>
                                                                )}
                                                                <Button variant="ghost" size="sm" asChild>
                                                                    <Link href={integrityAuditLogs.index.url({ stream_key: violation.stream_key })}>
                                                                        View All for PR {violation.stream_key}
                                                                    </Link>
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <Skeleton className="h-32 w-full" />
                        <Skeleton className="h-64 w-full" />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
