import { HeroCard } from '@/components/hero-card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import adminRecoverableData from '@/routes/admin/recoverable-data';
import { Head, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ArchiveRestore, Database, FileSearch, Network, RotateCcw, ServerCrash, Shield, Trash2, Zap } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface DeletedFile {
    file_key: string;
    pr_number: string;
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
    prNumbers: string[];
    nodes: BlockchainNode[];
    flash?: { success?: string; error?: string };
}

export default function RecoverableDataPage({ deletedFiles, prNumbers, nodes, flash }: RecoverableDataPageProps) {
    const [restoringKey, setRestoringKey] = useState<string | null>(null);
    const [reasons, setReasons] = useState<Record<string, string>>({});

    // Delete-from-node state (procurement-centric)
    const [purgeNodeId, setPurgeNodeId] = useState<string>('');
    const [purgePrNumber, setPurgePrNumber] = useState<string>('');
    const [purgeReason, setPurgeReason] = useState<string>('');
    const [isPurging, setIsPurging] = useState(false);

    // Resync state
    const [resyncNodeId, setResyncNodeId] = useState<string>('');
    const [isResyncing, setIsResyncing] = useState(false);

    // Filter deleted files by selected PR number (for the purge card)
    const filesForSelectedPr = useMemo(
        () => deletedFiles.filter((f) => f.pr_number === purgePrNumber),
        [deletedFiles, purgePrNumber],
    );

    // Handle flash messages from Inertia redirects
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const handleRestore = (fileKey: string) => {
        const reason = reasons[fileKey] || 'Restored by admin';
        setRestoringKey(fileKey);

        router.post(
            adminRecoverableData.restore.url(),
            { file_key: fileKey, reason },
            {
                onSuccess: () => {
                    toast.success('File restored on blockchain. The restoration event is now on-chain and audit-logged.');
                },
                onError: () => {
                    toast.error('Failed to restore file on blockchain.');
                },
                onFinish: () => {
                    setRestoringKey(null);
                },
            },
        );
    };

    const handleDeleteFromNode = () => {
        if (!purgeNodeId || !purgePrNumber) {
            toast.error('Select a procurement (PR number) and a target node');
            return;
        }

        setIsPurging(true);

        // Purge all files for this PR from the selected node
        const purgePromises = filesForSelectedPr.map((file) => {
            return new Promise<void>((resolve) => {
                router.post(
                    adminRecoverableData.deleteFromNode.url(),
                    {
                        file_key: file.file_key,
                        node_id: purgeNodeId,
                        reason: purgeReason || `Single-node purge for ${purgePrNumber}`,
                    },
                    {
                        onSuccess: () => resolve(),
                        onError: () => resolve(), // Don't block others on failure
                        onFinish: () => resolve(),
                    },
                );
            });
        });

        Promise.all(purgePromises).finally(() => {
            setIsPurging(false);
            toast.success(`Purged ${filesForSelectedPr.length} file(s) for ${purgePrNumber} from ${nodes.find((n) => n.id === purgeNodeId)?.name || purgeNodeId}. Data survives on remaining nodes.`);
        });
    };

    const handleResyncNode = () => {
        if (!resyncNodeId) {
            toast.error('Select a node to resync');
            return;
        }

        setIsResyncing(true);

        router.post(
            adminRecoverableData.resyncNode.url(),
            { node_id: resyncNodeId },
            {
                onSuccess: () => {
                    toast.success('Node resync initiated. The node will re-download missing data from its peers.');
                },
                onError: () => {
                    toast.error('Failed to initiate node resync.');
                },
                onFinish: () => {
                    setIsResyncing(false);
                },
            },
        );
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
                    description="Deleted procurement files are never permanently erased — they remain on the blockchain as immutable records. Every deletion and restoration is tracked on-chain and audit-logged per RA 12009 (NGPA)."
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

                {/* ─── DEMO: Delete from Node (by PR Number) ─── */}
                <Card className="border-amber-500/20 bg-amber-500/5">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <ServerCrash className="h-4 w-4 text-amber-600" />
                            Demo: Purge Procurement from a Single Node
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FieldDescription>
                            Purge all files for a <strong className="text-foreground">specific procurement</strong> from one node only.
                            The data survives on the remaining 3 nodes and will be automatically re-synced.
                            The purge is recorded on-chain as an audit event (RA 12009).
                        </FieldDescription>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field>
                                <FieldLabel htmlFor="purge-pr">Procurement (PR Number)</FieldLabel>
                                <Select value={purgePrNumber} onValueChange={(v) => v && setPurgePrNumber(v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select PR number..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {prNumbers.length > 0 ? (
                                                prNumbers.map((pr) => (
                                                    <SelectItem key={pr} value={pr}>
                                                        {pr}
                                                    </SelectItem>
                                                ))
                                            ) : (
                                                <SelectItem value="_none" disabled>
                                                    No deleted procurements
                                                </SelectItem>
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="purge-node">Target Node</FieldLabel>
                                <Select value={purgeNodeId} onValueChange={(v) => v && setPurgeNodeId(v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select node..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {nodes.map((node) => (
                                                <SelectItem key={node.id} value={node.id}>
                                                    {node.name} ({node.role})
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="purge-reason">Reason (RA 12009 audit)</FieldLabel>
                                <Input
                                    id="purge-reason"
                                    type="text"
                                    placeholder="Reason for single-node purge..."
                                    value={purgeReason}
                                    onChange={(e) => setPurgeReason(e.target.value)}
                                />
                            </Field>
                        </div>

                        {/* Show files that will be purged for the selected PR */}
                        {purgePrNumber && filesForSelectedPr.length > 0 && (
                            <div className="rounded-md bg-muted p-3 space-y-1 text-sm">
                                <p className="text-muted-foreground font-medium text-xs uppercase">
                                    Files to purge for {purgePrNumber}
                                </p>
                                {filesForSelectedPr.map((f) => (
                                    <p key={f.file_key} className="font-mono text-xs">
                                        {f.file_key}
                                    </p>
                                ))}
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            <AlertDialog>
                                <AlertDialogTrigger
                                    render={
                                        <Button
                                            variant="destructive"
                                            className="gap-2"
                                            disabled={isPurging || !purgeNodeId || !purgePrNumber || filesForSelectedPr.length === 0}
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
                                            This will remove <strong>{filesForSelectedPr.length} file(s)</strong> for procurement
                                            <code className="mx-1 font-mono">{purgePrNumber}</code> from
                                            <strong className="mx-1">{nodes.find((n) => n.id === purgeNodeId)?.name || purgeNodeId}</strong>.
                                            The data remains on the other 3 nodes and will be re-synced automatically.
                                            This event is recorded on-chain.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction variant="destructive" onClick={handleDeleteFromNode}>
                                            <ServerCrash className="mr-2 h-4 w-4" />
                                            Purge from Node
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
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
                        <FieldDescription>
                            After a single-node purge, trigger the node to <strong className="text-foreground">re-download all stream data</strong> from
                            its connected peers. The resync event is also recorded on-chain.
                        </FieldDescription>

                        <div className="flex items-end gap-4">
                            <Field className="flex-1">
                                <FieldLabel htmlFor="resync-node">Node to Resync</FieldLabel>
                                <Select value={resyncNodeId} onValueChange={(v) => v && setResyncNodeId(v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select node..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {nodes.map((node) => (
                                                <SelectItem key={node.id} value={node.id}>
                                                    {node.name} ({node.role})
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>

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
                    </CardContent>
                </Card>

                {/* ─── Deleted Files Table ─── */}
                {deletedFiles.length === 0 ? (
                    <Empty>
                        <EmptyMedia variant="icon">
                            <Shield className="h-8 w-8 text-emerald-500" />
                        </EmptyMedia>
                        <EmptyHeader>
                            <EmptyTitle>No Deleted Files</EmptyTitle>
                            <EmptyDescription>
                                All blockchain data is currently active. No procurement files have been marked as deleted.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trash2 className="h-5 w-5 text-destructive" />
                                Deleted Procurement Files — Pending Recovery
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>PR Number</TableHead>
                                        <TableHead>File</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Deleted At</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {deletedFiles.map((file) => (
                                        <TableRow key={file.file_key}>
                                            <TableCell>
                                                <Badge variant="secondary" className="font-mono">
                                                    {file.pr_number}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {file.file_key.replace(`${file.pr_number}/`, '')}
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
                                                                Restore Procurement File from Blockchain
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This will publish a restoration marker on the blockchain.
                                                                The file content was never removed — it remains replicated across all 4 nodes.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <div className="space-y-3 py-2">
                                                            <div className="rounded-md bg-muted p-3 space-y-1">
                                                                <FieldLabel className="text-muted-foreground text-xs uppercase">
                                                                    Procurement
                                                                </FieldLabel>
                                                                <Badge variant="secondary" className="font-mono">
                                                                    {file.pr_number}
                                                                </Badge>
                                                                <p className="font-mono text-sm mt-1">
                                                                    {file.file_key}
                                                                </p>
                                                            </div>
                                                            <Field>
                                                                <FieldLabel htmlFor={`restore-reason-${file.file_key}`}>
                                                                    Restoration Reason (RA 12009 audit)
                                                                </FieldLabel>
                                                                <Textarea
                                                                    id={`restore-reason-${file.file_key}`}
                                                                    placeholder="Reason for restoration..."
                                                                    value={reasons[file.file_key] || ''}
                                                                    onChange={(e) =>
                                                                        setReasons((prev) => ({
                                                                            ...prev,
                                                                            [file.file_key]: e.target.value,
                                                                        }))
                                                                    }
                                                                    rows={2}
                                                                />
                                                            </Field>
                                                        </div>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                variant="outline"
                                                                onClick={() => handleRestore(file.file_key)}
                                                                className="border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700"
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
