import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityBreaches from '@/routes/admin/integrity-breaches';
import { Head, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { ShieldAlert } from 'lucide-react';

interface BreachDetail {
    id: number;
    stream: string;
    stream_key: string;
    txid: string;
    violation_type: string;
    severity: string;
    database_snapshot: Record<string, unknown> | null;
    blockchain_snapshot: Record<string, unknown> | null;
    field_differences: Array<{ field: string; old_value: unknown; new_value: unknown }> | null;
    recovery_status: string;
    created_at: string;
    blockchain_data: Record<string, unknown> | null;
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

function truncateHash(hash: string, len = 12): string {
    if (!hash) return '—';
    if (hash.length <= len * 2 + 3) return hash;
    return `${hash.slice(0, len)}...${hash.slice(-len)}`;
}

export default function BreachDetailPage({ breachId, breach, error }: BreachDetailPageProps) {
    const severity = breach?.severity ?? 'medium';

    const handleRepair = () => {
        if (!breach) return;
        router.post(integrityBreaches.repair.url(breach.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Breach #${breachId}`} />

            <div className="p-4 sm:p-6">
                {/* Hero Header */}
                <HeroCard
                    icon={ShieldAlert}
                    title={
                        <span className="flex items-center gap-2">
                            Breach Detail
                            {breach && <Badge className={SEVERITY_COLORS[severity]}>{severity}</Badge>}
                        </span>
                    }
                    description={`Integrity breach #${breachId}${breach ? ` • ${breach.stream_key}` : ''}`}
                    actions={
                        breach && breach.recovery_status === 'pending' ? (
                            <Button onClick={handleRepair} variant="destructive">
                                Repair from Blockchain
                            </Button>
                        ) : undefined
                    }
                />

                {error ? (
                    <Card className="border-destructive mt-4">
                        <CardHeader>
                            <CardTitle className="text-destructive">Error</CardTitle>
                            <CardDescription>{error}</CardDescription>
                        </CardHeader>
                    </Card>
                ) : breach ? (
                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {/* Breach Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Breach Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <span className="text-muted-foreground">Violation Type</span>
                                        <p className="font-medium">{breach.violation_type}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Severity</span>
                                        <p>
                                            <Badge className={SEVERITY_COLORS[severity]}>{severity}</Badge>
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Stream</span>
                                        <p className="font-medium">{breach.stream}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Status</span>
                                        <p>
                                            <Badge variant={breach.recovery_status === 'restored' ? 'default' : 'destructive'}>
                                                {breach.recovery_status}
                                            </Badge>
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">PR Number</span>
                                    <p className="font-medium">{breach.stream_key}</p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Transaction ID</span>
                                    <code className="block text-xs break-all">{breach.txid}</code>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Detected</span>
                                    <p>{formatDistanceToNow(parseISO(breach.created_at), { addSuffix: true })}</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Field Differences */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Field Differences</CardTitle>
                                <CardDescription>What was changed in the database</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {breach.field_differences && breach.field_differences.length > 0 ? (
                                    <div className="space-y-3">
                                        {breach.field_differences.map((diff, i) => (
                                            <div key={i} className="rounded-md border p-3">
                                                <p className="mb-2 font-medium">{diff.field}</p>
                                                <div className="grid grid-cols-2 gap-2 text-xs">
                                                    <div className="rounded bg-green-50 p-2 dark:bg-green-950/30">
                                                        <span className="text-green-700 dark:text-green-400">Blockchain:</span>
                                                        <p className="mt-1 break-all">{JSON.stringify(diff.old_value)}</p>
                                                    </div>
                                                    <div className="rounded bg-red-50 p-2 dark:bg-red-950/30">
                                                        <span className="text-red-700 dark:text-red-400">Database:</span>
                                                        <p className="mt-1 break-all">{JSON.stringify(diff.new_value)}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">No field differences recorded.</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Database Snapshot */}
                        {breach.database_snapshot && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Database Snapshot</CardTitle>
                                    <CardDescription>Current state in database</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <pre className="bg-muted max-h-60 overflow-auto rounded p-3 text-xs">
                                        {JSON.stringify(breach.database_snapshot, null, 2)}
                                    </pre>
                                </CardContent>
                            </Card>
                        )}

                        {/* Blockchain Snapshot */}
                        {breach.blockchain_data && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Blockchain Data</CardTitle>
                                    <CardDescription>Source of truth from blockchain</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <pre className="bg-muted max-h-60 overflow-auto rounded p-3 text-xs">
                                        {JSON.stringify(breach.blockchain_data, null, 2)}
                                    </pre>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
