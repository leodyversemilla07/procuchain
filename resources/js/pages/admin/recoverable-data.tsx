import { HeroCard } from '@/components/hero-card';
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
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import adminRecoverableData from '@/routes/admin/recoverable-data';
import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ArchiveRestore, Database, Network, RotateCcw, ServerCrash, Shield, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
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
 is_purged: boolean;
 purged_at: string | null;
 resync_at: string | null;
 last_action: string | null;
 items: number;
}

interface RecoverableDataPageProps {
  deletedFiles: DeletedFile[];
  nodes: BlockchainNode[];
  flash?: { success?: string; error?: string };
}

/**
 * Polls a node operation job status until done/failed.
 * Returns the final status object.
 */
async function pollNodeOperationStatus(
  jobId: string,
  onStatus: (status: string, message: string) => void,
): Promise<{ status: string; message: string }> {
  return new Promise((resolve) => {
    const interval = setInterval(async () => {
      try {
        const res = await fetch(
          adminRecoverableData.nodeOperationStatus.url({ jobId }),
        );
        const data = await res.json();

        onStatus(data.status || 'pending', data.message || '');

        if (data.status === 'done' || data.status === 'failed') {
          clearInterval(interval);
          resolve(data);
        }
      } catch {
        // Network error — keep polling
      }
    }, 3000); // Poll every 3 seconds
  });
}

export default function RecoverableDataPage({ deletedFiles, nodes, flash }: RecoverableDataPageProps) {
  const [restoringKey, setRestoringKey] = useState<string | null>(null);
  const [reasons, setReasons] = useState<Record<string, string>>({});

  // Full-node purge state (demo: wipe all data from one node)
  const [fullPurgeNodeId, setFullPurgeNodeId] = useState<string>('');
  const [fullPurgeReason, setFullPurgeReason] = useState<string>('');
  const [isFullPurging, setIsFullPurging] = useState(false);
  const [purgeStatusMessage, setPurgeStatusMessage] = useState<string>('');

 // (Resync is automatic after purge — no manual UI needed)

  // Dialog open states
  const [purgeDialogOpen, setPurgeDialogOpen] = useState(false);

  // Keep a ref for polling cleanup on unmount
  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
  useEffect(() => {
    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current);
    };
  }, []);

  // Handle flash messages from Inertia redirects
  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  // ── Restore (fast — synchronous POST) ──
  const handleRestore = (fileKey: string) => {
    setRestoringKey(fileKey);

    router.post(
      adminRecoverableData.restore.url(),
      { file_key: fileKey },
      {
        onSuccess: () => {
          router.reload({ only: ['deletedFiles'] });
        },
        onError: (errors) => {
          const msg = Object.values(errors).join(' ') || 'Failed to restore file on blockchain.';
          toast.error(String(msg));
        },
        onFinish: () => {
          setRestoringKey(null);
        },
      },
    );
  };

  // ── Full Purge (async — dispatches queue job, then polls) ──
  const handleFullPurgeFromNode = useCallback(async () => {
    if (!fullPurgeNodeId) {
      toast.error('Select a target node to purge');
      return;
    }

    setIsFullPurging(true);
    setPurgeStatusMessage('Dispatching purge request...');
    setPurgeDialogOpen(false);

    try {
      // Get CSRF cookie first
      await fetch('/sanctum/csrf-cookie');

      const res = await fetch(adminRecoverableData.purgeAllFromNode.url(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          node_id: fullPurgeNodeId,
          reason: fullPurgeReason || 'Demo: full node purge — all data removed from single node',
        }),
      });

 if (!res.ok && res.status !== 202) {
 const errorData = await res.json().catch(() => ({}));
 if (res.status === 409) {
 toast.warning(errorData.message || 'An operation is already in progress on this node.');
 return;
 }
 throw new Error(errorData.message || `Server returned ${res.status}`);
 }

 const data = await res.json();
 const jobId = data.job_id;

 if (!jobId) {
 throw new Error('No job ID returned from server');
 }

 toast.info('Purge request queued. SSM command is running in the background...');

      // Poll for completion
      const result = await pollNodeOperationStatus(
        jobId,
        (status, message) => {
          const label = status === 'running' ? 'SSM command executing...' : message;
          setPurgeStatusMessage(label);
        },
      );

      if (result.status === 'done') {
        toast.success(result.message || 'Node purged successfully.');
        setFullPurgeNodeId('');
        setFullPurgeReason('');
        // Reload nodes to reflect purged state
        router.reload({ only: ['nodes'] });
      } else {
        toast.error(result.message || 'Purge operation failed.');
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to dispatch purge request.';
      toast.error(message);
    } finally {
      setIsFullPurging(false);
      setPurgeStatusMessage('');
    }
 }, [fullPurgeNodeId, fullPurgeReason]);

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
              <li>
                • <strong className="text-foreground">Deletion</strong> is an on-chain marker — file content persists across all 4
                nodes
              </li>
              <li>
                • <strong className="text-foreground">Restoration</strong> publishes a counter-marker — no data is rewritten or moved
              </li>
              <li>
                • Every action is <strong className="text-foreground">audit-logged</strong> and visible in the Shared Ledger
              </li>
              <li>• Data survives any single node failure — replicated across the full mesh</li>
            </ul>
          </CardContent>
        </Card>

        {/* ─── Node Status Grid ─── */}
        <Card className="border-slate-500/20 bg-slate-500/5">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-sm font-medium">
              <Network className="h-4 w-4" />
              Node Status — Live
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {nodes.map((node) => (
                <div
                  key={node.id}
                  className={`rounded-lg border p-3 transition-colors ${
                    node.is_purged ? 'border-red-500/30 bg-red-500/5' : 'border-emerald-500/30 bg-emerald-500/5'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold">{node.name}</span>
                    {node.is_purged ? (
                      <Badge variant="destructive" className="gap-1 text-[10px]">
                        <ServerCrash className="h-3 w-3" />
                        Purged
                      </Badge>
                    ) : (
                      <Badge className="gap-1 bg-emerald-100 text-[10px] text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <Shield className="h-3 w-3" />
                        Healthy
                      </Badge>
                    )}
                  </div>
                  <p className="text-muted-foreground mt-1 text-xs">{node.role}</p>
                  <div className="mt-2 flex items-center justify-between">
                    <span className="font-mono text-xs">
                      {node.is_purged ? (
                        <span className="text-red-600 dark:text-red-400">0 items</span>
                      ) : (
                        <span className="text-emerald-600 dark:text-emerald-400">{node.items.toLocaleString()} items</span>
                      )}
                    </span>
                    {node.purged_at && (
                      <span className="text-muted-foreground text-[10px]">{format(parseISO(node.purged_at), 'HH:mm')}</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
            <p className="text-muted-foreground mt-3 text-xs">
              Purged nodes show <strong className="text-foreground">0 items</strong> because their local data was wiped. Data survives
              on remaining nodes — resync to restore.
            </p>
          </CardContent>
        </Card>

        {/* ─── DEMO: Purge All Data from Node ─── */}
        <Card className="border-amber-500/20 bg-amber-500/5">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-sm font-medium">
              <ServerCrash className="h-4 w-4 text-amber-600" />
              Demo: Purge All Data from a Single Node
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <FieldDescription>
 Simulate <strong className="text-foreground">catastrophic data loss</strong> on a single node by purging{' '}
 <strong className="text-foreground">all stream data</strong> from its local storage. After purging, the node automatically reconnects to peers and <strong className="text-foreground">resyncs all data</strong> — demonstrating blockchain resilience. Every purge is recorded on-chain as an audit event (RA 12009 NGPA).
            </FieldDescription>

            {/* Step-by-step demo flow */}
            <div className="bg-muted/50 space-y-2 rounded-md p-3 text-sm">
              <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Demo Flow</p>
 <ol className="text-muted-foreground list-decimal space-y-1 pl-4 text-sm">
 <li>
 <strong className="text-foreground">Purge</strong> — Wipe all blockchain data from one node (e.g.{' '}
 <code className="font-mono text-xs">hope</code>)
 </li>
 <li>
 <strong className="text-foreground">Verify</strong> — Check the Blockchain Explorer to see the purge event
 recorded on-chain
 </li>
 <li>
 <strong className="text-foreground">Auto-Resync</strong> — The purged node automatically reconnects to peers
 and restores all data — <em>no manual action needed</em>
 </li>
 <li>
 <strong className="text-foreground">Confirm</strong> — Both the purge and auto-resync events are on-chain;
 data is fully restored, proving blockchain recoverability
 </li>
 </ol>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="full-purge-node">Target Node</FieldLabel>
                <Select value={fullPurgeNodeId} onValueChange={(v) => v && setFullPurgeNodeId(v)}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select node to purge..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
 {nodes.map((node) => (
 <SelectItem key={node.id} value={node.id} disabled={node.is_purged}>
 {node.name} ({node.role})
 {node.is_purged ? ` — 🔴 Purged${node.last_action === 'auto_resync' ? ' (resyncing…)' : ''}` : ` — ${node.items.toLocaleString()} items`}
 </SelectItem>
 ))}
                    </SelectGroup>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel htmlFor="full-purge-reason">Reason (RA 12009 audit)</FieldLabel>
                <Input
                  id="full-purge-reason"
                  type="text"
                  placeholder="Reason for full node purge..."
                  value={fullPurgeReason}
                  onChange={(e) => setFullPurgeReason(e.target.value)}
                  disabled={isFullPurging}
                />
              </Field>
            </div>

            <div className="flex items-center gap-3">
              <AlertDialog open={purgeDialogOpen} onOpenChange={setPurgeDialogOpen}>
                <AlertDialogTrigger
                  render={<Button variant="destructive" className="gap-2" disabled={isFullPurging || !fullPurgeNodeId} />}
                >
                  {isFullPurging ? <Spinner className="h-4 w-4" /> : <ServerCrash className="h-4 w-4" />}
                  Purge All from Node
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                      <ServerCrash className="text-destructive h-5 w-5" />
                      Confirm Full Node Purge
                    </AlertDialogTitle>
 <AlertDialogDescription>
 This will remove <strong>ALL blockchain data</strong> from{' '}
 <strong className="mx-1">{nodes.find((n) => n.id === fullPurgeNodeId)?.name || fullPurgeNodeId}</strong>.
 Every stream item (metadata, files, events, status changes) will be purged from this node&apos;s local
 storage. The node will then auto-resync from peers — proving that blockchain data cannot be permanently destroyed.
 Both the purge and auto-resync events are recorded on-chain for audit compliance (RA 12009).
 </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel disabled={isFullPurging}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                      variant="destructive"
                      onClick={handleFullPurgeFromNode}
                      disabled={isFullPurging}
                      className="gap-2"
                    >
                      {isFullPurging ? <Spinner className="h-4 w-4" /> : <ServerCrash className="h-4 w-4" />}
                      {isFullPurging ? 'Purging...' : 'Purge All Data'}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>

              {/* Live status message while purging */}
              {isFullPurging && purgeStatusMessage && (
                <span className="text-muted-foreground animate-pulse text-xs">{purgeStatusMessage}</span>
              )}
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
                <Trash2 className="text-destructive h-5 w-5" />
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
                      <TableCell className="font-mono text-xs">{file.file_key.replace(`${file.pr_number}/`, '')}</TableCell>
                      <TableCell className="text-muted-foreground text-sm">{file.reason || '—'}</TableCell>
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
                            render={
                              <Button
                                variant="outline"
                                size="sm"
                                className="gap-1"
                                disabled={restoringKey === file.file_key}
                              />
                            }
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
                                This will publish a restoration marker on the blockchain. The file content was never
                                removed — it remains replicated across all 4 nodes.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <div className="space-y-3 py-2">
                              <div className="bg-muted space-y-1 rounded-md p-3">
                                <FieldLabel className="text-muted-foreground text-xs uppercase">
                                  Procurement
                                </FieldLabel>
                                <Badge variant="secondary" className="font-mono">
                                  {file.pr_number}
                                </Badge>
                                <p className="mt-1 font-mono text-sm">{file.file_key}</p>
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
                                className="border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 gap-2"
                                disabled={restoringKey === file.file_key}
                              >
                                {restoringKey === file.file_key ? <Spinner className="mr-2 h-4 w-4" /> : <RotateCcw className="mr-2 h-4 w-4" />}
                                {restoringKey === file.file_key ? 'Restoring...' : 'Restore On-Chain'}
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
