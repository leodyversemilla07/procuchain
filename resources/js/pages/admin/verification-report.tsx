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
import { CheckCircle2, Clock, FileSearch, Shield, Wrench, XCircle } from 'lucide-react';
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

const SEVERITY_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    critical: 'destructive',
    high: 'default',
    medium: 'secondary',
    low: 'outline',
};

const RECOVERY_STATUS_STYLES: Record<
    string,
    { variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof CheckCircle2; label: string }
> = {
    pending: { variant: 'secondary', icon: Clock, label: 'Pending' },
    restored: { variant: 'default', icon: CheckCircle2, label: 'Restored' },
    failed: { variant: 'destructive', icon: XCircle, label: 'Failed' },
    skipped: { variant: 'outline', icon: Shield, label: 'Skipped' },
};

const VIOLATION_TYPE_LABELS: Record<string, string> = {
    hash_mismatch: 'Hash Mismatch',
    content_mismatch: 'Content Mismatch',
    unauthorized_publisher: 'Unauthorized Publisher',
    row_deleted: 'Row Deleted',
    user_address_tampered: 'User Address Tampered',
    unauthorized_record: 'Unauthorized Record',
};

export default function VerificationReportPage({ runId, report, error }: VerificationReportPageProps) {
    const [expandedViolation, setExpandedViolation] = useState<number | null>(null);

    const handleRepair = (violationId: number) => {
        router.post(
            integrityAuditLogs.repair.url(violationId),
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
                    <div className="flex flex-col gap-6">
                        {/* Summary Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-muted-foreground text-sm font-medium">Total Violations</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold">{report.summary.total_violations}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {report.summary.high + report.summary.medium + report.summary.low} other severity levels
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-destructive text-sm font-medium">Critical</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-destructive text-3xl font-bold">{report.summary.critical}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Requires immediate attention</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-primary text-sm font-medium">Restored</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-primary text-3xl font-bold">{report.summary.restored}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Successfully recovered from chain</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-secondary-foreground text-sm font-medium">Pending</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-secondary-foreground text-3xl font-bold">{report.summary.pending}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Awaiting manual repair</p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Severity Breakdown */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Violation Breakdown</CardTitle>
                                <CardDescription>Distribution by type and severity</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <h4 className="text-muted-foreground mb-2 text-sm font-medium">By Type</h4>
                                        <div className="flex flex-col gap-2">
                                            {Object.entries(report.summary.by_type).map(([type, count]) => (
                                                <div key={type} className="flex items-center justify-between">
                                                    <span className="text-sm">{VIOLATION_TYPE_LABELS[type] ?? type}</span>
                                                    <Badge variant="outline" className="font-mono">
                                                        {count}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="text-muted-foreground mb-2 text-sm font-medium">By Severity</h4>
                                        <div className="flex flex-col gap-2">
                                            {[
                                                { label: 'Critical', value: report.summary.critical, variant: 'destructive' as const },
                                                { label: 'High', value: report.summary.high, variant: 'default' as const },
                                                { label: 'Medium', value: report.summary.medium, variant: 'secondary' as const },
                                                { label: 'Low', value: report.summary.low, variant: 'outline' as const },
                                            ]
                                                .filter((s) => s.value > 0)
                                                .map((severity) => (
                                                    <div key={severity.label} className="flex items-center justify-between">
                                                        <span className="text-sm">{severity.label}</span>
                                                        <Badge variant={severity.variant} className="font-mono">
                                                            {severity.value}
                                                        </Badge>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
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
                                            <CheckCircle2 className="text-primary h-12 w-12" />
                                        </EmptyMedia>
                                        <EmptyHeader>
                                            <EmptyTitle>No Violations</EmptyTitle>
                                        </EmptyHeader>
                                        <EmptyDescription>This verification run found no integrity violations.</EmptyDescription>
                                    </Empty>
                                ) : (
                                    <div className="flex flex-col gap-4">
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
                                                            <Badge variant={SEVERITY_VARIANTS[violation.severity] ?? 'secondary'}>
                                                                {violation.severity}
                                                            </Badge>
                                                            <span className="font-medium">
                                                                {VIOLATION_TYPE_LABELS[violation.violation_type] ?? violation.violation_type}
                                                            </span>
                                                            <span className="text-muted-foreground text-sm">PR: {violation.stream_key}</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                variant={RECOVERY_STATUS_STYLES[violation.recovery_status]?.variant ?? 'secondary'}
                                                            >
                                                                <RecoveryIcon data-icon="inline-start" />
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
                                                                                        <TableCell className="bg-primary/5 text-xs">
                                                                                            {typeof diff.old_value === 'object'
                                                                                                ? JSON.stringify(diff.old_value)
                                                                                                : String(diff.old_value ?? '—')}
                                                                                        </TableCell>
                                                                                        <TableCell className="bg-destructive/5 text-xs">
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
                                                                        <h4 className="text-primary mb-2 text-sm font-medium">
                                                                            Chain Snapshot (Blockchain - Source of Truth)
                                                                        </h4>
                                                                        <pre className="bg-primary/5 max-h-48 overflow-auto rounded-lg p-3 text-xs">
                                                                            {JSON.stringify(violation.chain_snapshot, null, 2)}
                                                                        </pre>
                                                                    </div>
                                                                )}
                                                                {violation.mirror_snapshot && (
                                                                    <div>
                                                                        <h4 className="text-destructive mb-2 text-sm font-medium">
                                                                            Mirror Snapshot (Database - Tampered)
                                                                        </h4>
                                                                        <pre className="bg-destructive/5 max-h-48 overflow-auto rounded-lg p-3 text-xs">
                                                                            {JSON.stringify(violation.mirror_snapshot, null, 2)}
                                                                        </pre>
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Actions */}
                                                            <div className="mt-4 flex gap-2">
                                                                {violation.recovery_status === 'pending' && (
                                                                    <Button variant="outline" size="sm" onClick={() => handleRepair(violation.id)}>
                                                                        <Wrench data-icon="inline-start" />
                                                                        Repair from Blockchain
                                                                    </Button>
                                                                )}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    render={
                                                                        <Link
                                                                            href={integrityAuditLogs.index.url({
                                                                                query: { stream_key: violation.stream_key },
                                                                            })}
                                                                        />
                                                                    }
                                                                    nativeButton={false}
                                                                >
                                                                    View All for PR {violation.stream_key}
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
                    <div className="flex flex-col gap-4">
                        <Skeleton className="h-32 w-full" />
                        <Skeleton className="h-64 w-full" />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
