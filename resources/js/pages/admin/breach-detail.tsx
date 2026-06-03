import { RevisionTree, type RevisionNode } from '@/components/admin/revision-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityAuditLogs from '@/routes/admin/integrity-audit-logs';
import integrityBreaches from '@/routes/admin/integrity-breaches';
import { Head, Link, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { AlertTriangle, ArrowLeft, CheckCircle2, Database, ShieldAlert, Wrench } from 'lucide-react';

interface BreachDetail {
    id: number;
    stream: string;
    stream_key: string;
    txid: string;
    publisher_address: string;
    is_authorized: boolean;
    breach_type: string;
    breach_data: Record<string, unknown> | null;
    breach_detected_at: string | null;
    repaired_at: string | null;
    verified_at: string | null;
    synced_at: string;
    revision_number: number | null;
    parent_txid: string | null;
    is_latest_revision: boolean | null;
    data_json: Record<string, unknown>;
    data_hash: string;
    revision_history: RevisionNode[];
}

interface BreachDetailPageProps {
    breachId: number;
    breach: BreachDetail | null;
    error?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Breaches', href: integrityBreaches.index.url() },
    { title: 'Breach Detail', href: '#' },
];

const SEVERITY_COLORS: Record<string, string> = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
};

const BREACH_SEVERITY: Record<string, string> = {
    hash_mismatch: 'critical',
    content_mismatch: 'critical',
    user_address_tampered: 'high',
    unauthorized_publisher: 'medium',
    row_deleted: 'low',
};

const BREACH_TYPE_LABELS: Record<string, string> = {
    hash_mismatch: 'Hash Mismatch',
    content_mismatch: 'Content Mismatch',
    unauthorized_publisher: 'Unauthorized Publisher',
    row_deleted: 'Row Deleted',
    user_address_tampered: 'User Address Tampered',
};

function truncateHash(hash: string, len = 12): string {
    if (!hash) return '—';
    if (hash.length <= len * 2 + 3) return hash;
    return `${hash.slice(0, len)}...${hash.slice(-len)}`;
}

export default function BreachDetailPage({ breachId, breach, error }: BreachDetailPageProps) {
    const handleRepair = () => {
        if (!breach) return;
        router.post(
            integrityBreaches.repair.url(breach.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    router.reload({ only: ['breach'] });
                },
            },
        );
    };

    const severity = breach ? (BREACH_SEVERITY[breach.breach_type] ?? 'medium') : 'medium';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Breach #${breachId}`} />

            <div className="p-4 sm:p-6">
                {/* Header */}
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={integrityBreaches.index.url()}>
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <ShieldAlert className="h-6 w-6" />
                            Breach Detail
                        </h1>
                        <p className="text-muted-foreground text-sm">Integrity breach #{breachId}</p>
                    </div>
                    {breach && !breach.repaired_at && (
                        <Button onClick={handleRepair} variant="destructive">
                            <Wrench className="mr-2 h-4 w-4" />
                            Repair from Blockchain
                        </Button>
                    )}
                </div>

                {error ? (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Error Loading Breach</CardTitle>
                            <CardDescription>{error}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button onClick={() => router.reload({ only: ['breach'] })} variant="outline">
                                Retry
                            </Button>
                        </CardContent>
                    </Card>
                ) : breach ? (
                    <div className="space-y-6">
                        {/* Status Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Severity</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Badge className={SEVERITY_COLORS[severity]}>{severity}</Badge>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Breach Type</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-medium">{BREACH_TYPE_LABELS[breach.breach_type] ?? breach.breach_type}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Status</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {breach.repaired_at ? (
                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            <CheckCircle2 className="mr-1 h-3 w-3" /> Repaired
                                        </Badge>
                                    ) : (
                                        <Badge variant="destructive">Unresolved</Badge>
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">Publisher</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        {!breach.is_authorized && <AlertTriangle className="h-4 w-4 text-red-500" />}
                                        <code className="text-xs">{truncateHash(breach.publisher_address, 8)}</code>
                                    </div>
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
                                    {breach.revision_history && breach.revision_history.length > 0 ? (
                                        <RevisionTree revisions={breach.revision_history} currentTxid={breach.txid} />
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
                                            <p className="font-medium">{breach.stream}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">PR Number:</span>
                                            <p className="font-medium">{breach.stream_key}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">Transaction ID:</span>
                                            <code className="block text-xs break-all">{breach.txid}</code>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground text-sm">Revision:</span>
                                            <p className="font-medium">#{breach.revision_number ?? '—'}</p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div>
                                        <span className="text-muted-foreground text-sm">Detected:</span>
                                        <p className="text-sm">
                                            {breach.breach_detected_at
                                                ? formatDistanceToNow(parseISO(breach.breach_detected_at), { addSuffix: true })
                                                : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground text-sm">Repaired:</span>
                                        <p className="text-sm">
                                            {breach.repaired_at
                                                ? formatDistanceToNow(parseISO(breach.repaired_at), { addSuffix: true })
                                                : 'Not repaired'}
                                        </p>
                                    </div>

                                    {breach.breach_data && (
                                        <>
                                            <Separator />
                                            <div>
                                                <span className="text-muted-foreground text-sm">Breach Data:</span>
                                                <pre className="bg-muted mt-1 max-h-40 overflow-auto rounded p-2 text-xs">
                                                    {JSON.stringify(breach.breach_data, null, 2)}
                                                </pre>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Current Data */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Database className="h-5 w-5" />
                                    Current Mirror Data
                                </CardTitle>
                                <CardDescription>
                                    This is the current data in the database mirror. Compare with blockchain to verify integrity.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <pre className="bg-muted max-h-96 overflow-auto rounded-lg p-4 text-xs leading-relaxed">
                                    {JSON.stringify(breach.data_json, null, 2)}
                                </pre>
                                <div className="bg-muted/50 mt-2 rounded p-2">
                                    <span className="text-muted-foreground text-xs">Data Hash:</span>
                                    <code className="ml-2 font-mono text-xs">{breach.data_hash}</code>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-3">
                                <Button variant="outline" asChild>
                                    <Link href={integrityAuditLogs.index.url({ stream_key: breach.stream_key })}>
                                        View Audit Logs for PR {breach.stream_key}
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={integrityBreaches.index.url()}>Back to Breaches List</Link>
                                </Button>
                            </CardContent>
                        </Card>
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
