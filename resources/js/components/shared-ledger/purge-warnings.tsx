import { Alert } from '@/components/ui/alert';
import { AlertTriangle, Server, ServerCrash } from 'lucide-react';

interface NodePurgeState {
    is_purged: boolean;
    was_explicitly_purged: boolean;
    partially_purged: boolean;
    unsubscribed_streams: string[];
    purge_reason?: string | null;
    purge_timestamp?: number | null;
    connection_error?: boolean;
    connection_error_message?: string | null;
}

export function PurgeWarnings({ purgeState }: { purgeState: NodePurgeState | null }) {
    if (!purgeState) return null;

    return (
        <>
            {purgeState.is_purged && purgeState.was_explicitly_purged && (
                <div className="rounded-lg border border-amber-200 bg-muted/50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-muted/20">
                    <p className="flex items-center gap-2 font-medium text-muted-foreground dark:text-muted-foreground">
                        <AlertTriangle />
                        This node has been purged — all stream subscriptions removed
                    </p>
                    <p className="mt-1 text-muted-foreground dark:text-muted-foreground">
                        Data on this node was wiped via <strong>unsubscribe(purge=true)</strong>. The blockchain data still exists on other nodes
                        — use <strong>Recoverable Data → Resync</strong> to restore this node's local copy.
                    </p>
                    {purgeState.purge_reason && (
                        <p className="mt-1 text-xs text-muted-foreground dark:text-muted-foreground">Reason: {purgeState.purge_reason}</p>
                    )}
                </div>
            )}
            {purgeState.is_purged && !purgeState.was_explicitly_purged && (
                <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900/20">
                    <p className="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-300">
                        <Server />
                        This node has no local blockchain data
                    </p>
                    <p className="mt-1 text-slate-600 dark:text-slate-400">
                        This node is not subscribed to any procurement streams. It may have never been populated, or its subscriptions were
                        removed without an on-chain record. Use <strong>Recoverable Data → Resync</strong> to subscribe and download the
                        blockchain data.
                    </p>
                </div>
            )}
            {purgeState.partially_purged && (
                <div className="rounded-lg border border-amber-200 bg-muted/50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-muted/20">
                    <p className="flex items-center gap-2 font-medium text-muted-foreground dark:text-muted-foreground">
                        <AlertTriangle />
                        Partially purged — {purgeState.unsubscribed_streams.length} stream(s) unsubscribed
                    </p>
                    <p className="mt-1 text-muted-foreground dark:text-muted-foreground">Missing streams: {purgeState.unsubscribed_streams.join(', ')}</p>
                </div>
            )}
            {purgeState.connection_error && (
                <Alert variant="destructive" className="px-4 py-3 text-sm">
                    <p className="flex items-center gap-2 font-medium">
                        <ServerCrash />
                        Unable to connect to this node
                    </p>
                    <p className="mt-1 text-destructive">
                        The blockchain node could not be reached. It may be temporarily offline or have a network configuration issue.
                    </p>
                    {purgeState.connection_error_message && (
                        <p className="text-muted-foreground mt-1 text-xs">{purgeState.connection_error_message}</p>
                    )}
                </Alert>
            )}
        </>
    );
}
