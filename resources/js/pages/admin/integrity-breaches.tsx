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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import { Head, router, usePage } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { AlertTriangle, CheckCircle2, Database, Fingerprint, Shield, ShieldAlert, ShieldCheck, Wrench } from 'lucide-react';
import { useState } from 'react';

interface BreachRecord {
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
}

interface PaginatedBreaches {
    data: BreachRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface PageProps {
    breaches: PaginatedBreaches;
    filters: {
        breach_type: string | null;
        stream: string | null;
        status: string | null;
        pr_number: string | null;
        unauthorized: string | null;
    };
    breachTypes: Record<string, string>;
    streams: Record<string, string>;
    stats: {
        total: number;
        unresolved: number;
        critical: number;
        unauthorized: number;
    };
    success?: string;
    error?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Breaches', href: '#' },
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

function severityLabel(type: string): string {
    return BREACH_SEVERITY[type] ?? 'medium';
}

function truncateHash(hash: string, len = 12): string {
    if (hash.length <= len * 2 + 3) return hash;
    return `${hash.slice(0, len)}...${hash.slice(-len)}`;
}

export default function IntegrityBreaches() {
    const { breaches, filters, breachTypes, streams, stats, success, error } = usePage<PageProps>().props;
    const [selectedBreach, setSelectedBreach] = useState<BreachRecord | null>(null);
    const [repairing, setRepairing] = useState<number | null>(null);
    const [repairingPr, setRepairingPr] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [verifyAndRepairing, setVerifyAndRepairing] = useState(false);
    const [verifyResult, setVerifyResult] = useState<{ verified: number; breach_count: number } | null>(null);
    const [prRepairResult, setPrRepairResult] = useState<{ success: boolean; repaired_count?: number; error?: string } | null>(null);

    const handleRepair = async (id: number) => {
        setRepairing(id);
        try {
            await router.post(
                `/admin/integrity-breaches/${id}/repair`,
                {},
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        } finally {
            setRepairing(null);
        }
    };

    const handleVerify = async () => {
        setVerifying(true);
        setVerifyResult(null);
        try {
            const res = await fetch('/admin/integrity-breaches/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                setVerifyResult({ verified: data.verified, breach_count: data.breach_count });
            }
        } finally {
            setVerifying(false);
        }
    };

    const handleVerifyAndRepair = async () => {
        setVerifyAndRepairing(true);
        setVerifyResult(null);
        try {
            const res = await fetch('/admin/integrity-breaches/verify-and-repair', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                setVerifyResult({ verified: data.verified, breach_count: data.breach_count });
                router.reload({ preserveState: false });
            }
        } finally {
            setVerifyAndRepairing(false);
        }
    };

    const handleRepairPr = async () => {
        if (!filters.pr_number) return;
        setRepairingPr(true);
        setPrRepairResult(null);
        try {
            const res = await fetch('/admin/integrity-breaches/repair-pr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ pr_number: filters.pr_number }),
            });
            const data = await res.json();
            setPrRepairResult(data);
            if (data.success) {
                router.reload({ preserveState: false });
            }
        } finally {
            setRepairingPr(false);
        }
    };

    const handleFilter = (key: string, value: string | null) => {
        const params = new URLSearchParams(window.location.search);
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
        router.get(`/admin/integrity-breaches?${params.toString()}`, {}, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrity Breaches" />

            {/* Stats Cards */}
            <StatsGrid
                items={[
                    { label: 'Total Breaches', value: stats.total, icon: AlertTriangle, iconClassName: 'bg-muted' },
                    {
                        label: 'Unresolved',
                        value: stats.unresolved,
                        icon: ShieldAlert,
                        iconClassName: stats.unresolved > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-muted',
                    },
                    {
                        label: 'Critical',
                        value: stats.critical,
                        icon: Shield,
                        iconClassName: stats.critical > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-muted',
                    },
                    { label: 'Unauthorized', value: stats.unauthorized, icon: Fingerprint, iconClassName: 'bg-muted' },
                ]}
                className="p-4"
            />

            {/* Filters + Actions */}
            <div className="flex flex-wrap items-center gap-3 p-4">
                <Select value={filters.breach_type ?? ''} onValueChange={(v) => handleFilter('breach_type', v || null)}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Breach Type" />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(breachTypes).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={filters.stream ?? ''} onValueChange={(v) => handleFilter('stream', v || null)}>
                    <SelectTrigger className="w-52">
                        <SelectValue placeholder="Stream" />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(streams).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={filters.status ?? ''} onValueChange={(v) => handleFilter('status', v || null)}>
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="unresolved">Unresolved</SelectItem>
                        <SelectItem value="resolved">Resolved</SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    placeholder="PR number..."
                    value={filters.pr_number ?? ''}
                    onChange={(e) => handleFilter('pr_number', e.target.value || null)}
                    className="w-44"
                />

                <div className="ml-auto flex gap-2">
                    <Button variant="outline" size="sm" onClick={handleVerify} disabled={verifying || verifyAndRepairing}>
                        <ShieldCheck className="mr-2 h-4 w-4" />
                        {verifying ? 'Verifying...' : 'Run Verification'}
                    </Button>
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="destructive" size="sm" disabled={verifying || verifyAndRepairing}>
                                <Wrench className="mr-2 h-4 w-4" />
                                {verifyAndRepairing ? 'Running...' : 'Verify & Repair All'}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Verify & Repair All?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This will run a full verification and automatically repair all detected breaches from the blockchain. This action
                                    overwrites tampered mirror records with on-chain data.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction onClick={handleVerifyAndRepair}>Verify & Repair</AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                    {filters.pr_number && (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="outline" size="sm" disabled={repairingPr}>
                                    <Wrench className="mr-2 h-4 w-4" />
                                    {repairingPr ? 'Repairing...' : `Repair PR ${filters.pr_number}`}
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Repair PR {filters.pr_number}?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This will repair all breaches for PR <code>{filters.pr_number}</code> by re-syncing from the blockchain.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction onClick={handleRepairPr}>Repair PR</AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}
                    {(filters.breach_type || filters.stream || filters.status || filters.pr_number) && (
                        <Button variant="ghost" size="sm" onClick={() => router.get('/admin/integrity-breaches')}>
                            Clear Filters
                        </Button>
                    )}
                </div>
            </div>

            {/* Verify Result Toast */}
            {verifyResult && (
                <Card className="mx-4 border-blue-200 dark:border-blue-900">
                    <CardContent className="flex items-center gap-3 p-4">
                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                        <span>
                            Verification complete: <strong>{verifyResult.verified}</strong> records checked,{' '}
                            <strong>{verifyResult.breach_count}</strong> new breaches found.
                        </span>
                        <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setVerifyResult(null)}>
                            Dismiss
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* PR Repair Result Toast */}
            {prRepairResult && (
                <Card className={`mx-4 ${prRepairResult.success ? 'border-green-200 dark:border-green-900' : 'border-red-200 dark:border-red-900'}`}>
                    <CardContent className="flex items-center gap-3 p-4">
                        {prRepairResult.success ? (
                            <CheckCircle2 className="h-5 w-5 text-green-600" />
                        ) : (
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                        )}
                        <span>
                            {prRepairResult.success
                                ? `PR repair successful: ${prRepairResult.repaired_count} breach(es) repaired.`
                                : `PR repair failed: ${prRepairResult.error}`}
                        </span>
                        <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setPrRepairResult(null)}>
                            Dismiss
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* Flash Messages */}
            {success && (
                <Card className="mx-4 border-green-200 dark:border-green-900">
                    <CardContent className="p-4 text-green-700 dark:text-green-400">{success}</CardContent>
                </Card>
            )}
            {error && (
                <Card className="mx-4 border-red-200 dark:border-red-900">
                    <CardContent className="p-4 text-red-700 dark:text-red-400">{error}</CardContent>
                </Card>
            )}

            {/* Breach Table */}
            <div className="p-4">
                {breaches.data.length === 0 ? (
                    <Empty>
                        <EmptyMedia>
                            <ShieldCheck className="h-16 w-16 text-green-500" />
                        </EmptyMedia>
                        <EmptyHeader>
                            <EmptyTitle>No Integrity Breaches</EmptyTitle>
                        </EmptyHeader>
                        <EmptyDescription>All mirror data matches the blockchain. No tampering detected.</EmptyDescription>
                    </Empty>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldAlert className="h-5 w-5" />
                                Detected Breaches
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Breach Type</TableHead>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>Stream</TableHead>
                                        <TableHead>Rev</TableHead>
                                        <TableHead>TXID</TableHead>
                                        <TableHead>Publisher</TableHead>
                                        <TableHead>Detected</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {breaches.data.map((breach) => {
                                        const severity = severityLabel(breach.breach_type);
                                        return (
                                            <TableRow key={breach.id} className={!breach.repaired_at ? 'bg-red-50/50 dark:bg-red-950/20' : ''}>
                                                <TableCell>
                                                    <Badge className={SEVERITY_COLORS[severity]}>{severity}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">{breachTypes[breach.breach_type] ?? breach.breach_type}</TableCell>
                                                <TableCell>
                                                    <code className="text-xs">{breach.stream_key}</code>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {streams[breach.stream] ?? breach.stream}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {breach.revision_number !== null && breach.revision_number !== undefined ? (
                                                        <Badge variant="outline" className="font-mono text-xs">
                                                            #{breach.revision_number}
                                                        </Badge>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger>
                                                                <code className="text-xs">{truncateHash(breach.txid, 8)}</code>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <code className="text-xs break-all">{breach.txid}</code>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        {!breach.is_authorized && <AlertTriangle className="h-3 w-3 text-red-500" />}
                                                        <code className="text-xs">{truncateHash(breach.publisher_address, 6)}</code>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {breach.breach_detected_at
                                                        ? formatDistanceToNow(parseISO(breach.breach_detected_at), { addSuffix: true })
                                                        : '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {breach.repaired_at ? (
                                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                            <CheckCircle2 className="mr-1 h-3 w-3" /> Repaired
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive">Unresolved</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        <Button variant="ghost" size="sm" onClick={() => setSelectedBreach(breach)}>
                                                            <Database className="h-4 w-4" />
                                                        </Button>
                                                        {!breach.repaired_at && (
                                                            <AlertDialog>
                                                                <AlertDialogTrigger asChild>
                                                                    <Button variant="outline" size="sm" disabled={repairing === breach.id}>
                                                                        <Wrench className="mr-1 h-3 w-3" />
                                                                        {repairing === breach.id ? 'Repairing...' : 'Repair'}
                                                                    </Button>
                                                                </AlertDialogTrigger>
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>Repair this breach?</AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            This will re-sync the data from the blockchain, overwriting the tampered
                                                                            mirror record. The original blockchain data will be used as the source of
                                                                            truth.
                                                                        </AlertDialogDescription>
                                                                    </AlertDialogHeader>
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                                        <AlertDialogAction onClick={() => handleRepair(breach.id)}>
                                                                            Repair from Blockchain
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {breaches.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-center gap-2">
                                    {breaches.links.map((link, i) => (
                                        <Button
                                            key={i}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url)}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Breach Detail Dialog */}
            <Dialog open={!!selectedBreach} onOpenChange={() => setSelectedBreach(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ShieldAlert className="h-5 w-5" />
                            Breach Details
                        </DialogTitle>
                        <DialogDescription>Full details for breach #{selectedBreach?.id}</DialogDescription>
                    </DialogHeader>
                    {selectedBreach && (
                        <div className="space-y-3 text-sm">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground">Breach Type:</span>
                                    <br />
                                    <strong>{breachTypes[selectedBreach.breach_type] ?? selectedBreach.breach_type}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Severity:</span>
                                    <br />
                                    <Badge className={SEVERITY_COLORS[severityLabel(selectedBreach.breach_type)]}>
                                        {severityLabel(selectedBreach.breach_type)}
                                    </Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">PR Number:</span>
                                    <br />
                                    <code>{selectedBreach.stream_key}</code>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Stream:</span>
                                    <br />
                                    {streams[selectedBreach.stream] ?? selectedBreach.stream}
                                </div>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Transaction ID:</span>
                                <br />
                                <code className="text-xs break-all">{selectedBreach.txid}</code>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Publisher Address:</span>
                                <br />
                                <code className="text-xs break-all">{selectedBreach.publisher_address}</code>
                                {!selectedBreach.is_authorized && (
                                    <Badge variant="destructive" className="ml-2">
                                        Unauthorized
                                    </Badge>
                                )}
                            </div>
                            {selectedBreach.breach_data && (
                                <div>
                                    <span className="text-muted-foreground">Breach Data:</span>
                                    <br />
                                    <pre className="bg-muted mt-1 max-h-40 overflow-auto rounded p-2 text-xs">
                                        {JSON.stringify(selectedBreach.breach_data, null, 2)}
                                    </pre>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground">Detected:</span>
                                    <br />
                                    {selectedBreach.breach_detected_at
                                        ? formatDistanceToNow(parseISO(selectedBreach.breach_detected_at), { addSuffix: true })
                                        : '—'}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Repaired:</span>
                                    <br />
                                    {selectedBreach.repaired_at
                                        ? formatDistanceToNow(parseISO(selectedBreach.repaired_at), { addSuffix: true })
                                        : 'Not repaired'}
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
