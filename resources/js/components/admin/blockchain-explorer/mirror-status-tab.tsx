import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CheckCircle2, Database, RefreshCw, Shield, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

interface MirrorStatus {
    total_records: number;
    unresolved_breaches: number;
    last_sync: string | null;
    last_verified: string | null;
    last_audit_run: string | null;
    pending_repairs: number;
    stream_counts: Record<string, number>;
    breach_counts: Record<string, number>;
}

const STREAM_DISPLAY_NAMES: Record<string, string> = {
    'procurement.metadata': 'Procurement Metadata',
    'procurement.documents': 'Procurement Documents',
    'procurement.status': 'Procurement Status',
    'procurement.events': 'Procurement Events',
    'procurement.corrections': 'Procurement Corrections',
    'procurement.metadata.corrections': 'Metadata Corrections',
    'procurement.archive': 'Procurement Archive',
    'file.data': 'File Data',
    'file.metadata': 'File Metadata',
    'file.chunks': 'File Chunks',
    'user.registrations': 'User Registrations',
};

const BREACH_TYPE_LABELS: Record<string, string> = {
    hash_mismatch: 'Hash Mismatch',
    content_mismatch: 'Content Mismatch',
    user_address_tampered: 'Address Tampered',
    unauthorized_publisher: 'Unauthorized Publisher',
    row_deleted: 'Row Deleted',
};

export function MirrorStatusTab() {
    const [status, setStatus] = useState<MirrorStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);

    const fetchStatus = async () => {
        try {
            setRefreshing(true);
            const res = await fetch('/admin/integrity-breaches/mirror-status', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setStatus(data);
            setError(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load mirror status');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchStatus();
    }, []);

    if (loading) {
        return (
            <div className="space-y-4">
                <Skeleton className="h-24 w-full" />
                <Skeleton className="h-48 w-full" />
            </div>
        );
    }

    if (error) {
        return (
            <Card className="border-destructive">
                <CardHeader>
                    <CardTitle className="text-destructive">Mirror Status Unavailable</CardTitle>
                    <CardDescription>{error}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Button onClick={fetchStatus} variant="outline">
                        <RefreshCw className="mr-2 h-4 w-4" /> Retry
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (!status) return null;

    const breachRatio = status.total_records > 0 ? (status.unresolved_breaches / status.total_records) * 100 : 0;
    const healthPercentage = Math.max(0, 100 - breachRatio);

    return (
        <div className="space-y-6">
            {/* Health Overview */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Database className="h-5 w-5" />
                        Mirror Health
                    </CardTitle>
                    <CardDescription>
                        The procurement mirror caches blockchain data in MySQL for fast queries. Health measures how well the mirror matches the
                        blockchain source of truth.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Total Records</p>
                            <p className="text-2xl font-bold">{status.total_records.toLocaleString()}</p>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Unresolved Breaches</p>
                            <p
                                className={`text-2xl font-bold ${status.unresolved_breaches > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}
                            >
                                {status.unresolved_breaches}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Last Sync</p>
                            <p className="text-sm font-medium">{status.last_sync ? new Date(status.last_sync).toLocaleString() : 'Never'}</p>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Last Verified</p>
                            <p className="text-sm font-medium">{status.last_verified ? new Date(status.last_verified).toLocaleString() : 'Never'}</p>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Last Audit Run</p>
                            <p className="text-sm font-medium">
                                {status.last_audit_run ? new Date(status.last_audit_run).toLocaleString() : 'Never'}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Pending Repairs</p>
                            <p className={`text-sm font-medium ${status.pending_repairs > 0 ? 'text-yellow-600 dark:text-yellow-400' : ''}`}>
                                {status.pending_repairs}
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span>Integrity Score</span>
                            <span className="font-medium">{healthPercentage.toFixed(1)}%</span>
                        </div>
                        <Progress value={healthPercentage} className="h-3" />
                        <p className="text-muted-foreground text-xs">
                            {healthPercentage >= 100
                                ? '✅ All mirror records match blockchain data'
                                : healthPercentage >= 95
                                  ? '⚠ Minor breaches detected — review recommended'
                                  : '🚨 Significant breaches detected — immediate action required'}
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Stream Breakdown */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Stream Breakdown
                            </CardTitle>
                            <CardDescription>Record counts per blockchain stream in the mirror</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" onClick={fetchStatus} disabled={refreshing}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {Object.keys(status.stream_counts).length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center justify-center py-8">
                            <Database className="mb-2 h-10 w-10" />
                            <p>No mirror records yet. Run `php artisan blockchain:sync` to populate.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Stream</TableHead>
                                    <TableHead className="text-right">Records</TableHead>
                                    <TableHead className="text-right">Share</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Object.entries(status.stream_counts)
                                    .sort(([, a], [, b]) => b - a)
                                    .map(([stream, count]) => (
                                        <TableRow key={stream}>
                                            <TableCell className="font-medium">{STREAM_DISPLAY_NAMES[stream] ?? stream}</TableCell>
                                            <TableCell className="text-right">{count.toLocaleString()}</TableCell>
                                            <TableCell className="text-right">
                                                {status.total_records > 0 ? `${((count / status.total_records) * 100).toFixed(1)}%` : '0%'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Breach Breakdown */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ShieldAlert className="h-5 w-5" />
                        Unresolved Breaches by Type
                    </CardTitle>
                    <CardDescription>
                        Breach types indicate how the mirror diverges from the blockchain. Critical breaches (hash/content mismatch) suggest database
                        tampering.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {Object.keys(status.breach_counts).length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center justify-center py-8">
                            <CheckCircle2 className="mb-2 h-10 w-10 text-green-500" />
                            <p>No unresolved breaches. Mirror is in sync with blockchain.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Breach Type</TableHead>
                                    <TableHead className="text-right">Count</TableHead>
                                    <TableHead>Severity</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Object.entries(status.breach_counts).map(([type, count]) => {
                                    const severity =
                                        type === 'hash_mismatch' || type === 'content_mismatch'
                                            ? 'critical'
                                            : type === 'user_address_tampered'
                                              ? 'high'
                                              : type === 'unauthorized_publisher'
                                                ? 'medium'
                                                : 'low';
                                    return (
                                        <TableRow key={type}>
                                            <TableCell className="font-medium">{BREACH_TYPE_LABELS[type] ?? type}</TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant={severity === 'critical' ? 'destructive' : 'secondary'}>{count}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        severity === 'critical'
                                                            ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                            : severity === 'high'
                                                              ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
                                                              : severity === 'medium'
                                                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                                : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                    }
                                                >
                                                    {severity}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Quick Actions */}
            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>Manual sync and verification commands</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2 text-sm">
                        <div className="flex items-center gap-2">
                            <code className="bg-muted rounded px-2 py-1">php artisan blockchain:sync</code>
                            <span className="text-muted-foreground">— Full rebuild from blockchain</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <code className="bg-muted rounded px-2 py-1">php artisan blockchain:audit</code>
                            <span className="text-muted-foreground">— Verify mirror integrity</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <code className="bg-muted rounded px-2 py-1">{"php artisan blockchain:repair {pr-number}"}</code>
                            <span className="text-muted-foreground">— Repair specific PR from chain (e.g. PR-2025-001)</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
