import { HeroCard } from '@/components/hero-card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import adminRecoverableData from '@/routes/admin/recoverable-data';
import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ArchiveRestore, Database, Network, RotateCcw, ServerCrash, Shield, Trash2, Zap } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface DeletedFile {
    file_key: string;
    reason: string;
    deleted_at: string;
}

interface BlockchainNode {
    id: string;
    name: string;
    role: string;
}

interface RecoverableDataPageProps {
    deletedFiles: DeletedFile[];
    nodes: BlockchainNode[];
}

export default function RecoverableDataPage({ deletedFiles, nodes }: RecoverableDataPageProps) {
    const [restoringKey, setRestoringKey] = useState<string | null>(null);
    const [reasons, setReasons] = useState<Record<string, string>>({});

    // Delete-from-node state
    const [purgeNodeId, setPurgeNodeId] = useState<string>('');
    const [purgeFileKey, setPurgeFileKey] = useState<string>('');
    const [purgeReason, setPurgeReason] = useState<string>('');
    const [isPurging, setIsPurging] = useState(false);

    // Resync state
    const [resyncNodeId, setResyncNodeId] = useState<string>('');
    const [isResyncing, setIsResyncing] = useState(false);

    // Result messages
    const [purgeResult, setPurgeResult] = useState<{ success: boolean; message: string } | null>(null);
    const [resyncResult, setResyncResult] = useState<{ success: boolean; message: string } | null>(null);

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    const handleRestore = async (fileKey: string) => {
        setRestoringKey(fileKey);
        const reason = reasons[fileKey] || 'Restored by admin';

        try {
            const response = await fetch(adminRecoverableData.restore.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ file_key: fileKey, reason }),
            });

            const data = await response.json();

            if (response.ok) {
                toast.success(data.message);
                router.reload({ only: ['deletedFiles'] });
            } else {
                toast.error(data.message || 'Failed to restore file');
            }
        } catch {
            toast.error('Network error — could not reach server');
        } finally {
            setRestoringKey(null);
        }
    };

    const handleDeleteFromNode = async () => {
        if (!purgeNodeId || !purgeFileKey) {
            toast.error('Select a node and enter a file key');
            return;
        }

        setIsPurging(true);
        setPurgeResult(null);

        try {
            const response = await fetch(adminRecoverableData.deleteFromNode.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    file_key: purgeFileKey,
                    node_id: purgeNodeId,
                    reason: purgeReason || 'Demo: single-node purge',
                }),
            });

            const data = await response.json();
            setPurgeResult(data);

            if (response.ok) {
                toast.success(data.message);
            } else {
                toast.error(data.message);
            }
        } catch {
            toast.error('Network error — could not reach server');
            setPurgeResult({ success: false, message: 'Network error' });
        } finally {
            setIsPurging(false);
        }
    };

    const handleResyncNode = async () => {
        if (!resyncNodeId) {
            toast.error('Select a node to resync');
            return;
        }

        setIsResyncing(true);
        setResyncResult(null);

        try {
            const response = await fetch(adminRecoverableData.resyncNode.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ node_id: resyncNodeId }),
            });

            const data = await response.json();
            setResyncResult(data);

            if (response.ok) {
                toast.success(data.message);
            } else {
                toast.error(data.message);
            }
        } catch {
            toast.error('Network error — could not reach server');
            setResyncResult({ success: false, message: 'Network error' });
        } finally {
            setIsResyncing(false);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: dashboard.url() },
                { title: 'Recoverable Data', href: adminRecoverableData.index.url() },
            ]}
        >
            <Head title="Recoverable Data — Blockchain Recovery" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Database}
                    title="Recoverable Data"
                    description="Deleted files are never permanently erased — they remain on the blockchain as immutable records. Every deletion and restoration is tracked on-chain and audit-logged per RA 12009 (NGPA)."
                    actions={
                        <div className="flex items-center gap-3">
                            <Badge className="gap-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <Shield className="h-3 w-3" />
                                On-chain & Recoverable
                            </Badge>
                            <Badge variant="secondary" className="font-mono">
                                {deletedFiles.length} deleted
                            </Badge>
                        </div>
                    }
                />

                {/* Explanation card */}
                <Card className="border-blue-500/20 bg-blue-500/5">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <ArchiveRestore className="h-4 w-4" />
                            How Blockchain Recovery Works
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            <li>• <strong className="text-foreground">Deletion</strong> is an on-chain marker — file content persists across all 4 nodes</li>
                            <li>• <strong className="text-foreground">Restoration</strong> publishes a counter-marker — no data is rewritten or moved</li>
                            <li>• Every action is <strong className="text-foreground">audit-logged</strong> and visible in the Shared Ledger</li>
                            <li>• Data survives any single node failure — replicated across the full mesh</li>
                        </ul>
                    </CardContent>
                </Card>

                {/* ─── DEMO: Delete from Node ─── */}
                <Card className="border-amber-500/20 bg-amber-500/5">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <ServerCrash className="h-4 w-4 text-amber-600" />
                            Demo: Delete Data from a Single Node
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            Purge a file's data from <strong className="text-foreground">one node only</strong>. The data survives on the remaining
                            3 nodes and will be automatically re-synced. The purge is recorded on-chain as an audit event (RA 12009).
                        </p>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium uppercase text-muted-foreground">Target Node</label>
                                <Select value={purgeNodeId} onValueChange={(v) => v && setPurgeNodeId(v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select node..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nodes.map((node) => (
                                            <SelectItem key={node.id} value={node.id}>
                                                {node.name} ({node.role})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-medium uppercase text-muted-foreground">File Key</label>
                                <input
                                    type="text"
                                    className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="e.g. phase-1/bac-resolution.pdf"
                                    value={purgeFileKey}
                                    onChange={(e) => setPurgeFileKey(e.target.value)}
                                />
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-medium uppercase text-muted-foreground">Reason (RA 12009 audit)</label>
                                <input
                                    type="text"
                                    className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Reason for single-node purge..."
                                    value={purgeReason}
                                    onChange={(e) => setPurgeReason(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <AlertDialog>
                                <AlertDialogTrigger
                                    render={
                                        <Button
                                            variant="destructive"
                                            className="gap-2"
                                            disabled={isPurging || !purgeNodeId || !purgeFileKey}
                                        />
                                    }
                                >
                                    {isPurging ? <Spinner className="h-4 w-4" /> : <ServerCrash className="h-4 w-4" />}
                                    Delete from Node
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle className="flex items-center gap-2">
                                            <ServerCrash className="h-5 w-5 text-destructive" />
                                            Confirm Single-Node Purge
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This will remove the file's data from <strong>one node only</strong>. The data remains
                                            on the other 3 nodes and will be re-synced automatically. This event is recorded on-chain.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <div className="rounded-md bg-muted p-3 space-y-1 text-sm">
                                        <p><span className="text-muted-foreground font-medium">Node:</span> {nodes.find(n => n.id === purgeNodeId)?.name || purgeNodeId}</p>
                                        <p><span className="text-muted-foreground font-medium">File:</span> <code className="font-mono">{purgeFileKey}</code></p>
                                    </div>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction onClick={handleDeleteFromNode} className="bg-destructive hover:bg-destructive/90">
                                            <ServerCrash className="mr-2 h-4 w-4" />
                                            Purge from Node
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>

                        {purgeResult && (
                            <div className={`rounded-md p-3 text-sm ${purgeResult.success ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-destructive/10 text-destructive'}`}>
                                {purgeResult.message}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ─── DEMO: Resync Node ─── */}
                <Card className="border-violet-500/20 bg-violet-500/5">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Zap className="h-4 w-4 text-violet-600" />
                            Demo: Resync Node from Peers
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            After a single-node purge, trigger the node to <strong className="text-foreground">re-download all stream data</strong> from
                            its connected peers. The resync event is also recorded on-chain.
                        </p>

                        <div className="flex items-end gap-4">
                            <div className="space-y-1.5 flex-1">
                                <label className="text-xs font-medium uppercase text-muted-foreground">Node to Resync</label>
                                <Select value={resyncNodeId} onValueChange={(v) => v && setResyncNodeId(v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select node..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nodes.map((node) => (
                                            <SelectItem key={node.id} value={node.id}>
                                                {node.name} ({node.role})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                variant="outline"
                                className="gap-2 border-violet-500/30 hover:bg-violet-500/10"
                                disabled={isResyncing || !resyncNodeId}
                                onClick={handleResyncNode}
                            >
                                {isResyncing ? <Spinner className="h-4 w-4" /> : <Network className="h-4 w-4" />}
                                Resync from Peers
                            </Button>
                        </div>

                        {resyncResult && (
                            <div className={`rounded-md p-3 text-sm ${resyncResult.success ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-destructive/10 text-destructive'}`}>
                                {resyncResult.message}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ─── Deleted Files Table ─── */}
                {deletedFiles.length === 0 ? (
                    <Empty>
                        <EmptyMedia>
                            <Shield className="h-16 w-16 text-emerald-500" />
                        </EmptyMedia>
                        <EmptyHeader>
                            <EmptyTitle>No Deleted Files</EmptyTitle>
                            <EmptyDescription>
                                All blockchain data is currently active. No files have been marked as deleted.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trash2 className="h-5 w-5 text-destructive" />
                                Deleted Files — Pending Recovery
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>File Key</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Deleted At</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {deletedFiles.map((file) => (
                                        <TableRow key={file.file_key}>
                                            <TableCell className="font-mono text-sm">
                                                {file.file_key}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {file.reason || '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {file.deleted_at ? format(parseISO(file.deleted_at), 'MMM d, yyyy HH:mm') : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="destructive" className="gap-1">
                                                    <Trash2 className="h-3 w-3" />
                                                    Deleted
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <AlertDialog>
                                                    <AlertDialogTrigger
                                                        render={<Button variant="outline" size="sm" className="gap-1" disabled={restoringKey === file.file_key} />}
                                                    >
                                                        {restoringKey === file.file_key ? (
                                                            <Spinner className="mr-2 h-3 w-3" />
                                                        ) : (
                                                            <RotateCcw className="mr-2 h-3 w-3" />
                                                        )}
                                                        Restore
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle className="flex items-center gap-2">
                                                                <RotateCcw className="h-5 w-5 text-emerald-600" />
                                                                Restore File from Blockchain
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This will publish a restoration marker on the blockchain.
                                                                The file content was never removed — it remains replicated across all 4 nodes.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <div className="space-y-3 py-2">
                                                            <div className="rounded-md bg-muted p-3">
                                                                <p className="text-muted-foreground text-xs font-medium uppercase">
                                                                    File Key
                                                                </p>
                                                                <p className="font-mono text-sm">
                                                                    {file.file_key}
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <label className="text-muted-foreground mb-1 text-xs font-medium uppercase">
                                                                    Restoration Reason (RA 12009 audit)
                                                                </label>
                                                                <Textarea
                                                                    placeholder="Reason for restoration..."
                                                                    value={reasons[file.file_key] || ''}
                                                                    onChange={(e) =>
                                                                        setReasons((prev) => ({
                                                                            ...prev,
                                                                            [file.file_key]: e.target.value,
                                                                        }))
                                                                    }
                                                                    className="mt-1"
                                                                    rows={2}
                                                                />
                                                            </div>
                                                        </div>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() => handleRestore(file.file_key)}
                                                                className="bg-emerald-600 hover:bg-emerald-700"
                                                            >
                                                                <RotateCcw className="mr-2 h-4 w-4" />
                                                                Restore On-Chain
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
