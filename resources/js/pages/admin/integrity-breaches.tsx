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
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import integrityBreaches from '@/routes/admin/integrity-breaches';
import { Head, router, usePage } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { AlertTriangle, CheckCircle2, Database, Shield, ShieldAlert, ShieldCheck, Wrench } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface BreachRecord {
    id: number;
    record_id: number | null;
    stream: string;
    stream_key: string;
    txid: string;
    violation_type: string;
    severity: string;
    database_snapshot: Record<string, unknown> | null;
    blockchain_snapshot: Record<string, unknown> | null;
    field_differences: Array<{ field: string; old_value: unknown; new_value: unknown }> | null;
    recovery_status: string;
    recovered_at: string | null;
    verification_run_id: string | null;
    created_at: string;
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
        violation_type: string | null;
        stream: string | null;
        status: string | null;
        pr_number: string | null;
    };
    breachTypes: Record<string, string>;
    streams: Record<string, string>;
    stats: {
        total: number;
        unresolved: number;
        critical: number;
        high: number;
    };
    success?: string;
    error?: string;
    [key: string]: unknown;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Breaches', href: '#' },
];

const SEVERITY_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    critical: 'destructive',
    high: 'default',
    medium: 'secondary',
    low: 'outline',
};

function truncateHash(hash: string | null | undefined, len = 12): string {
    if (!hash) return '—';
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
    const [verifyDialogOpen, setVerifyDialogOpen] = useState(false);

    const pollVerificationStatus = (toastId: string | number) => {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(integrityBreaches.verifyStatus.url());
                const data = await res.json();

                if (data.status === 'completed') {
                    clearInterval(interval);
                    setVerifying(false);
                    setVerifyAndRepairing(false);
                    toast.dismiss(toastId);

                    const result = data.result;
                    const breachCount = Object.values((result?.violations ?? {}) as Record<string, number>).reduce((a, b) => a + b, 0);
                    toast.success('Verification complete', {
                        description: `${result?.verified ?? 0} records checked, ${breachCount} breaches found, ${result?.restored ?? 0} restored.`,
                    });

                    // Reload the page to show fresh data
                    router.reload({
                        only: ['breaches', 'stats'],
                        onError: () => window.location.reload(),
                    });
                } else if (data.status === 'failed') {
                    clearInterval(interval);
                    setVerifying(false);
                    setVerifyAndRepairing(false);
                    toast.dismiss(toastId);
                    toast.error('Verification failed', {
                        description: data.error ?? 'An unexpected error occurred.',
                    });
                }
                // 'running' → keep polling
            } catch {
                // Ignore polling errors — retry on next interval
            }
        }, 3000);
    };

    const handleRepair = (id: number) => {
        setRepairing(id);
        router.post(
            integrityBreaches.repair.url(id),
            {},
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setRepairing(null),
            },
        );
    };

    const handleVerify = () => {
        setVerifying(true);
        const toastId = toast.info('Verification in progress…', {
            description: 'Checking all records against the blockchain.',
            duration: Infinity,
        });
        router.post(
            integrityBreaches.verify.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => pollVerificationStatus(toastId),
            },
        );
    };

    const handleVerifyAndRepair = () => {
        setVerifyDialogOpen(false);
        setVerifyAndRepairing(true);
        const toastId = toast.info('Verifying and repairing all breaches…', {
            description: 'Checking records, repairing tampered data from the blockchain.',
            duration: Infinity,
        });
        router.post(
            integrityBreaches.verifyAndRepair.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => pollVerificationStatus(toastId as string),
            },
        );
    };

    const handleRepairPr = () => {
        if (!filters.pr_number) return;
        setRepairingPr(true);
        router.post(
            integrityBreaches.repairPr.url(),
            { pr_number: filters.pr_number },
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setRepairingPr(false),
            },
        );
    };

    const handleFilter = (key: string, value: string | null) => {
        const params = new URLSearchParams(window.location.search);
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
        router.get(integrityBreaches.index.url({ query: Object.fromEntries(params) }), {}, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrity Breaches" />

            {/* Hero Header */}
            <div className="p-4">
                <HeroCard
                    icon={ShieldAlert}
                    title="Integrity Breaches"
                    description="Monitor and repair blockchain data integrity breaches. All anomalies are detected by comparing mirror data against on-chain records."
                />
            </div>

            {/* Stats Cards */}
            <StatsGrid
                items={[
                    { label: 'Total Breaches', value: stats.total, icon: AlertTriangle },
                    { label: 'Unresolved', value: stats.unresolved, icon: ShieldAlert },
                    { label: 'Critical', value: stats.critical, icon: Shield },
                ]}
                className="p-4"
            />

            {/* Filters + Actions */}
            <div className="flex flex-wrap items-center gap-3 p-4">
                <Select value={filters.violation_type ?? ''} onValueChange={(v) => handleFilter('violation_type', v || null)}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Breach Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {Object.entries(breachTypes).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Select value={filters.stream ?? ''} onValueChange={(v) => handleFilter('stream', v || null)}>
                    <SelectTrigger className="w-52">
                        <SelectValue placeholder="Stream" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {Object.entries(streams).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Select value={filters.status ?? ''} onValueChange={(v) => handleFilter('status', v || null)}>
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="unresolved">Unresolved</SelectItem>
                            <SelectItem value="resolved">Resolved</SelectItem>
                        </SelectGroup>
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
                        <ShieldCheck data-icon="inline-start" />
                        {verifying ? 'Verifying...' : 'Run Verification'}
                    </Button>
                    <AlertDialog open={verifyDialogOpen} onOpenChange={setVerifyDialogOpen}>
                        <AlertDialogTrigger
                            render={<Button variant="destructive" size="sm" disabled={verifying || verifyAndRepairing} />}
                            nativeButton={false}
                        >
                            <Wrench data-icon="inline-start" />
                            {verifyAndRepairing ? 'Running...' : 'Verify & Repair All'}
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
                            <AlertDialogTrigger render={<Button variant="outline" size="sm" disabled={repairingPr} />} nativeButton={false}>
                                <Wrench data-icon="inline-start" />
                                {repairingPr ? 'Repairing...' : `Repair PR ${filters.pr_number}`}
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
                    {(filters.violation_type || filters.stream || filters.status || filters.pr_number) && (
                        <Button variant="ghost" size="sm" onClick={() => router.get(integrityBreaches.index.url())}>
                            Clear Filters
                        </Button>
                    )}
                </div>
            </div>

            {/* Flash Messages */}
            {success && (
                <Card className="border-primary/20 bg-primary/5 mx-4">
                    <CardContent className="text-primary p-4">{success}</CardContent>
                </Card>
            )}
            {error && (
                <Card className="border-destructive/20 bg-destructive/5 mx-4">
                    <CardContent className="text-destructive p-4">{error}</CardContent>
                </Card>
            )}

            {/* Breach Table */}
            <div className="p-4">
                {!breaches?.data || breaches.data.length === 0 ? (
                    <Empty>
                        <EmptyMedia>
                            <ShieldCheck className="text-primary h-16 w-16" />
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
                                        <TableHead>Violation Type</TableHead>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>Stream</TableHead>
                                        <TableHead>TXID</TableHead>
                                        <TableHead>Detected</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(breaches?.data ?? []).map((breach) => {
                                        const severity = breach.severity ?? 'medium';
                                        return (
                                            <TableRow key={breach.id} className={breach.recovery_status === 'pending' ? 'bg-destructive/5' : ''}>
                                                <TableCell>
                                                    <Badge variant={SEVERITY_VARIANTS[severity] ?? 'secondary'}>{severity}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {breachTypes[breach.violation_type] ?? breach.violation_type}
                                                </TableCell>
                                                <TableCell>
                                                    <code className="text-xs">{breach.stream_key}</code>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">{breach.stream}</TableCell>
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
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {breach.created_at ? formatDistanceToNow(parseISO(breach.created_at), { addSuffix: true }) : '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {breach.recovery_status === 'restored' ? (
                                                        <Badge>
                                                            <CheckCircle2 data-icon="inline-start" /> Restored
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive">{breach.recovery_status}</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            render={<a href={integrityBreaches.detail.url(breach.id)} />}
                                                            nativeButton={false}
                                                            title="View Details"
                                                        >
                                                            <Database data-icon="inline-start" />
                                                        </Button>
                                                        {breach.recovery_status === 'pending' && (
                                                            <AlertDialog>
                                                                <AlertDialogTrigger
                                                                    render={<Button variant="outline" size="sm" disabled={repairing === breach.id} />}
                                                                    nativeButton={false}
                                                                >
                                                                    <Wrench data-icon="inline-start" />
                                                                    {repairing === breach.id ? 'Repairing...' : 'Repair'}
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
                            {(breaches?.last_page ?? 0) > 1 && (
                                <div className="mt-4">
                                    <Pagination>
                                        <PaginationContent>
                                            {/* First */}
                                            <PaginationItem>
                                                <PaginationLink
                                                    href="#"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        if (breaches.current_page > 1) {
                                                            router.get(integrityBreaches.index.url({ query: { page: 1 } }));
                                                        }
                                                    }}
                                                    className={breaches.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
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
                                                        if (breaches.current_page > 1) {
                                                            router.get(integrityBreaches.index.url({ query: { page: breaches.current_page - 1 } }));
                                                        }
                                                    }}
                                                    className={breaches.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                                                />
                                            </PaginationItem>

                                            {/* Page Numbers */}
                                            {(() => {
                                                const pages: (number | 'ellipsis')[] = [];
                                                const current = breaches.current_page;
                                                const last = breaches.last_page;

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
                                                                    router.get(integrityBreaches.index.url({ query: { page } }));
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
                                                        if (breaches.current_page < breaches.last_page) {
                                                            router.get(integrityBreaches.index.url({ query: { page: breaches.current_page + 1 } }));
                                                        }
                                                    }}
                                                    className={breaches.current_page >= breaches.last_page ? 'pointer-events-none opacity-50' : ''}
                                                />
                                            </PaginationItem>

                                            {/* Last */}
                                            <PaginationItem>
                                                <PaginationLink
                                                    href="#"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        if (breaches.current_page < breaches.last_page) {
                                                            router.get(integrityBreaches.index.url({ query: { page: breaches.last_page } }));
                                                        }
                                                    }}
                                                    className={breaches.current_page >= breaches.last_page ? 'pointer-events-none opacity-50' : ''}
                                                >
                                                    Last
                                                </PaginationLink>
                                            </PaginationItem>
                                        </PaginationContent>
                                    </Pagination>
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
                                    <span className="text-muted-foreground">Violation Type:</span>
                                    <br />
                                    <strong>{breachTypes[selectedBreach.violation_type] ?? selectedBreach.violation_type}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Severity:</span>
                                    <br />
                                    <Badge variant={SEVERITY_VARIANTS[selectedBreach.severity ?? 'medium'] ?? 'secondary'}>
                                        {selectedBreach.severity ?? 'medium'}
                                    </Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">PR Number:</span>
                                    <br />
                                    <code>{selectedBreach.stream_key}</code>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Table:</span>
                                    <br />
                                    {selectedBreach.stream}
                                </div>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Transaction ID:</span>
                                <br />
                                <code className="text-xs break-all">{selectedBreach.txid}</code>
                            </div>
                            {selectedBreach.field_differences && selectedBreach.field_differences.length > 0 && (
                                <div>
                                    <span className="text-muted-foreground">Field Differences:</span>
                                    <pre className="bg-muted mt-1 max-h-40 overflow-auto rounded p-2 text-xs">
                                        {JSON.stringify(selectedBreach.field_differences, null, 2)}
                                    </pre>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground">Detected:</span>
                                    <br />
                                    {selectedBreach.created_at ? formatDistanceToNow(parseISO(selectedBreach.created_at), { addSuffix: true }) : '—'}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Restored:</span>
                                    <br />
                                    {selectedBreach.recovered_at
                                        ? formatDistanceToNow(parseISO(selectedBreach.recovered_at), { addSuffix: true })
                                        : 'Not restored'}
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
