import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { LedgerEntry } from '@/types/blockchain';
import { ArrowDownUp, ChevronDown, ClipboardCopy, ExternalLink, RotateCcw, ServerCrash, Trash2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { computeDiff } from './utils';
import { getStreamConfig } from './stream-config';

function copyTxid(txid: string) {
    navigator.clipboard
        .writeText(txid)
        .then(() => toast.success('TX ID copied to clipboard'))
        .catch(() => toast.error('Failed to copy TX ID'));
}

function LedgerEntryDetail({ entry, diff }: { entry: LedgerEntry; diff: Array<{ key: string; old: string; new: string }> }) {
    return (
        <div className="flex flex-col gap-4">
            {diff.length > 0 && (
                <div>
                    <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                        <ArrowDownUp />
                        Changes
                    </h4>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-1/4">Field</TableHead>
                                    <TableHead className="w-1/3">Old Value</TableHead>
                                    <TableHead className="w-1/3">New Value</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {diff.map((d) => (
                                    <TableRow key={d.key}>
                                        <TableCell className="font-mono text-xs font-medium">{d.key}</TableCell>
                                        <TableCell className="bg-destructive/5 font-mono text-xs break-all">
                                            {d.old || <span className="text-muted-foreground italic">empty</span>}
                                        </TableCell>
                                        <TableCell className="bg-primary/5 font-mono text-xs break-all">
                                            {d.new || <span className="text-muted-foreground italic">empty</span>}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            )}

            {entry.original_txid && (
                <div className="flex items-center gap-2 text-sm">
                    <ExternalLink />
                    <span className="text-muted-foreground">References original TX:</span>
                    <code className="bg-muted rounded px-2 py-0.5 font-mono text-xs">{entry.original_txid.substring(0, 16)}...</code>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={(e) => {
                            e.stopPropagation();
                            copyTxid(entry.original_txid!);
                        }}
                        title="Copy original TX ID"
                    >
                        <ClipboardCopy />
                    </Button>
                </div>
            )}

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <h4 className="text-sm font-medium">Raw Blockchain Data</h4>
                    <Badge variant="outline" className="font-mono text-xs">
                        TX: {entry.txid}
                    </Badge>
                </div>
                <pre className="bg-muted max-h-64 overflow-x-auto rounded-lg p-4 text-xs leading-relaxed">
                    {JSON.stringify(entry.raw_json, null, 2)}
                </pre>
            </div>
        </div>
    );
}

export function LedgerEntryRow({
    entry,
    isExpanded,
    onToggle,
}: {
    entry: LedgerEntry;
    isExpanded: boolean;
    onToggle: (txid: string) => void;
}) {
    const streamCfg = getStreamConfig(entry.stream);
    const StreamIcon = streamCfg.icon;
    const isSystem = entry.pr_number === 'system';
    const hasChanges = Object.keys(entry.old_values).length > 0 || Object.keys(entry.new_values).length > 0;
    const diff = hasChanges ? computeDiff(entry.old_values, entry.new_values) : [];

    return (
        <React.Fragment key={entry.txid}>
            <TableRow className="hover:bg-muted/50 cursor-pointer" onClick={() => onToggle(entry.txid)}>
                <TableCell>
                    <ChevronDown className={cn('text-muted-foreground h-4 w-4 transition-transform', isExpanded && 'rotate-180')} />
                </TableCell>
                <TableCell className="text-muted-foreground text-xs whitespace-nowrap">{entry.formatted_timestamp}</TableCell>
                <TableCell>
                    <Badge variant={streamCfg.variant} className="gap-1 font-normal whitespace-nowrap">
                        <StreamIcon />
                        {entry.stream_display}
                    </Badge>
                    {entry.action === 'deleted' && (
                        <Badge variant="destructive" className="gap-1 text-xs whitespace-nowrap">
                            <Trash2 />
                            Deleted
                        </Badge>
                    )}
                    {entry.action === 'restored' && (
                        <Badge variant="default" className="gap-1 whitespace-nowrap">
                            <RotateCcw />
                            Restored
                        </Badge>
                    )}
                    {entry.action === 'node_purged' && (
                        <Badge variant="destructive" className="gap-1 text-xs whitespace-nowrap">
                            <ServerCrash />
                            Node Purged
                        </Badge>
                    )}
                    {entry.action === 'node_resynced' && (
                        <Badge variant="default" className="gap-1 whitespace-nowrap">
                            <RotateCcw />
                            Node Resynced
                        </Badge>
                    )}
                </TableCell>
                <TableCell>
                    {isSystem ? (
                        <Badge variant="secondary" className="font-mono text-xs">
                            System
                        </Badge>
                    ) : (
                        <span className="font-mono text-xs font-medium">{entry.pr_number}</span>
                    )}
                </TableCell>
                <TableCell className="max-w-xs">
                    <div className="truncate text-sm" title={entry.summary}>
                        {entry.summary}
                    </div>
                    {entry.procurement_title && (
                        <div className="text-muted-foreground truncate text-xs">{entry.procurement_title}</div>
                    )}
                </TableCell>
                <TableCell className="font-mono text-xs">
                    {entry.actor_address ? (
                        `${entry.actor_address.substring(0, 10)}...`
                    ) : (
                        <span className="text-muted-foreground italic">—</span>
                    )}
                </TableCell>
                <TableCell>
                    <div className="flex items-center gap-1">
                        <span className="font-mono text-xs">{entry.txid.substring(0, 8)}...</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6"
                            onClick={(e) => {
                                e.stopPropagation();
                                copyTxid(entry.txid);
                            }}
                            title="Copy TX ID"
                        >
                            <ClipboardCopy />
                        </Button>
                    </div>
                </TableCell>
            </TableRow>
            {isExpanded && (
                <TableRow>
                    <TableCell colSpan={7} className="bg-muted/20 p-0">
                        <Collapsible open={isExpanded}>
                            <CollapsibleContent className="px-6 py-4">
                                <LedgerEntryDetail entry={entry} diff={diff} />
                            </CollapsibleContent>
                        </Collapsible>
                    </TableCell>
                </TableRow>
            )}
        </React.Fragment>
    );
}
