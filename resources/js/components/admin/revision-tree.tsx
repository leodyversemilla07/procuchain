import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { AlertTriangle, CheckCircle2, Circle, Clock, GitBranch, User } from 'lucide-react';
import { useState } from 'react';

export interface RevisionNode {
    txid: string;
    revision_number: number;
    parent_txid: string | null;
    is_latest_revision: boolean;
    blocktime: string | null;
    publisher_address: string | null;
    data_hash: string;
    breach_detected_at: string | null;
    breach_type: string | null;
    repaired_at: string | null;
}

interface RevisionTreeProps {
    revisions: RevisionNode[];
    currentTxid?: string;
    className?: string;
    compact?: boolean;
}

function truncateHash(hash: string, len = 8): string {
    if (!hash) return '—';
    if (hash.length <= len * 2 + 3) return hash;
    return `${hash.slice(0, len)}...${hash.slice(-len)}`;
}

function getNodeStatus(revision: RevisionNode): 'current' | 'breached' | 'repaired' | 'normal' {
    if (revision.breach_detected_at && !revision.repaired_at) {
        return 'breached';
    }
    if (revision.repaired_at) {
        return 'repaired';
    }
    return revision.is_latest_revision ? 'current' : 'normal';
}

const STATUS_STYLES = {
    current: {
        border: 'border-primary',
        bg: 'bg-primary/10',
        icon: CheckCircle2,
        iconColor: 'text-primary',
        label: 'Latest',
        badgeClass: 'bg-primary/20 text-primary',
    },
    breached: {
        border: 'border-destructive',
        bg: 'bg-destructive/10',
        icon: AlertTriangle,
        iconColor: 'text-destructive',
        label: 'Breached',
        badgeClass: 'bg-destructive/20 text-destructive'
    },
    repaired: {
        border: 'border-muted-foreground',
        bg: 'bg-muted/50',
        icon: CheckCircle2,
        iconColor: 'text-muted-foreground',
        label: 'Repaired',
        badgeClass: 'bg-muted text-muted-foreground'
    },
    normal: {
        border: 'border-border',
        bg: 'bg-muted',
        icon: Circle,
        iconColor: 'text-muted-foreground',
        label: 'Historical',
        badgeClass: 'bg-muted text-muted-foreground'
    },
};

function RevisionNodeCard({
    revision,
    isCurrent,
    compact = false,
    isSelected,
    onSelect,
}: {
    revision: RevisionNode;
    isCurrent?: boolean;
    compact?: boolean;
    isSelected?: boolean;
    onSelect?: () => void;
}) {
    const status = getNodeStatus(revision);
    const styles = STATUS_STYLES[status];
    const StatusIcon = styles.icon;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'relative min-w-[160px] cursor-pointer rounded-lg border-2 p-3 text-left transition-all hover:shadow-md',
                styles.border,
                styles.bg,
                isCurrent && 'ring-2 ring-green-500/20 ring-offset-2 dark:ring-offset-gray-900',
                isSelected && 'ring-2 ring-blue-500/40 ring-offset-2 dark:ring-offset-gray-900',
            )}
        >
            {/* Status Badge */}
            <div className="mb-2 flex items-center justify-between">
                <Badge variant="outline" className={cn('font-mono text-xs', styles.badgeClass)}>
                    Rev #{revision.revision_number}
                </Badge>
                <StatusIcon className={styles.iconColor} />
            </div>

            {/* TXID */}
            <div className="mb-1">
                <code className="bg-background/50 rounded px-1 font-mono text-xs">{truncateHash(revision.txid, 6)}</code>
            </div>

            {/* Timestamp */}
            {revision.blocktime && (
                <div className="flex items-center gap-1 text-xs text-gray-500">
                    <Clock />
                    <span>{formatDistanceToNow(parseISO(revision.blocktime), { addSuffix: true })}</span>
                </div>
            )}

            {/* Publisher (non-compact) */}
            {!compact && revision.publisher_address && (
                <div className="mt-1 flex items-center gap-1 text-xs text-gray-500">
                    <User />
                    <span className="truncate" title={revision.publisher_address}>
                        {truncateHash(revision.publisher_address, 4)}
                    </span>
                </div>
            )}

            {/* Hash (non-compact) */}
            {!compact && (
                <div className="bg-background/30 mt-2 rounded px-2 py-1">
                    <code className="font-mono text-[10px]">{truncateHash(revision.data_hash, 8)}</code>
                </div>
            )}
        </button>
    );
}

function RevisionDetails({ revision }: { revision: RevisionNode }) {
    const status = getNodeStatus(revision);
    const styles = STATUS_STYLES[status];

    return (
        <div className="flex flex-col gap-3 text-sm">
            <div className="flex items-center gap-2">
                <Badge variant="outline" className={cn('font-mono text-xs', styles.badgeClass)}>
                    Rev #{revision.revision_number}
                </Badge>
                <span className={cn('text-xs font-medium', styles.iconColor)}>{styles.label}</span>
            </div>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <span className="text-muted-foreground text-xs">Transaction ID</span>
                    <code className="mt-0.5 block text-xs break-all">{revision.txid}</code>
                </div>
                {revision.blocktime && (
                    <div>
                        <span className="text-muted-foreground text-xs">Block Time</span>
                        <p className="mt-0.5 text-xs">{new Date(revision.blocktime).toLocaleString()}</p>
                    </div>
                )}
                {revision.publisher_address && (
                    <div>
                        <span className="text-muted-foreground text-xs">Publisher</span>
                        <code className="mt-0.5 block text-xs break-all">{revision.publisher_address}</code>
                    </div>
                )}
                <div>
                    <span className="text-muted-foreground text-xs">Data Hash</span>
                    <code className="mt-0.5 block text-xs break-all">{revision.data_hash}</code>
                </div>
            </div>

            {revision.breach_type && (
                <div className="rounded-md bg-destructive/10 p-2 text-xs dark:bg-destructive/10/30">
                    <span className="font-medium text-destructive dark:text-destructive">Breach:</span>{' '}
                    <span className="text-destructive dark:text-destructive">{revision.breach_type}</span>
                </div>
            )}

            {revision.repaired_at && (
                <div className="rounded-md bg-primary/10 p-2 text-xs dark:bg-primary/10/30">
                    <span className="font-medium text-primary dark:text-primary">Repaired:</span>{' '}
                    <span className="text-primary dark:text-primary">
                        {formatDistanceToNow(parseISO(revision.repaired_at), { addSuffix: true })}
                    </span>
                </div>
            )}
        </div>
    );
}

export function RevisionTree({ revisions, currentTxid, className, compact = false }: RevisionTreeProps) {
    const [selectedRevision, setSelectedRevision] = useState<RevisionNode | null>(null);

    // Sort revisions by revision number
    const sortedRevisions = [...revisions].sort((a, b) => a.revision_number - b.revision_number);

    // Build the tree structure by following parent_txid links
    const buildTree = (): RevisionNode[][] => {
        if (sortedRevisions.length === 0) return [];

        // Find root revision (no parent)
        const root = sortedRevisions.find((r) => r.parent_txid === null);
        if (!root) return [sortedRevisions];

        // Build chains from root
        const chains: RevisionNode[][] = [];
        const visited = new Set<string>();

        const buildChain = (node: RevisionNode, currentChain: RevisionNode[]): void => {
            if (visited.has(node.txid)) return;
            visited.add(node.txid);

            currentChain.push(node);

            // Find children
            const children = sortedRevisions.filter((r) => r.parent_txid === node.txid);

            if (children.length === 0) {
                // End of chain
                chains.push([...currentChain]);
            } else if (children.length === 1) {
                // Single child - continue chain
                buildChain(children[0], currentChain);
            } else {
                // Multiple children - fork
                for (const child of children) {
                    buildChain(child, [...currentChain]);
                }
            }
        };

        buildChain(root, []);

        return chains;
    };

    const chains = buildTree();

    if (chains.length === 0) {
        return (
            <Card className={className}>
                <CardContent className="flex flex-col items-center justify-center py-8 text-center">
                    <GitBranch className="text-muted-foreground mb-3 h-10 w-10 opacity-50" />
                    <p className="text-muted-foreground text-sm">No revision history available</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <GitBranch />
                    Revision Tree
                </CardTitle>
                <CardDescription>
                    {revisions.length} revision{revisions.length !== 1 ? 's' : ''} • Click nodes for details
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {/* Scrollable tree */}
                <div className="overflow-x-auto pb-2">
                    {chains.map((chain, chainIndex) => (
                        <div key={chainIndex} className="relative">
                            {chainIndex > 0 && <div className="text-muted-foreground mb-2 text-xs italic">Alternative revision path:</div>}

                            <div className={cn('flex items-start', compact ? 'gap-1' : 'gap-2')}>
                                {chain.map((revision, index) => (
                                    <div key={revision.txid} className="flex items-start">
                                        {/* Node */}
                                        <RevisionNodeCard
                                            revision={revision}
                                            isCurrent={revision.txid === currentTxid || revision.is_latest_revision}
                                            compact={compact}
                                            isSelected={selectedRevision?.txid === revision.txid}
                                            onSelect={() => setSelectedRevision(selectedRevision?.txid === revision.txid ? null : revision)}
                                        />

                                        {/* Connector Arrow */}
                                        {index < chain.length - 1 && (
                                            <div className="flex flex-col items-center px-1 pt-4">
                                                <div className="bg-border h-0.5 w-6" />
                                                <svg
                                                   
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                >
                                                    <path d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Selected node details */}
                {selectedRevision && (
                    <div className="bg-muted/30 rounded-lg border p-4">
                        <RevisionDetails revision={selectedRevision} />
                    </div>
                )}

                {/* Legend */}
                <div className="border-t pt-3">
                    <p className="text-muted-foreground mb-2 text-xs font-medium">Legend:</p>
                    <div className="flex flex-wrap gap-3">
                        {Object.entries(STATUS_STYLES).map(([key, style]) => {
                            const Icon = style.icon;
                            return (
                                <div key={key} className="flex items-center gap-1 text-xs">
                                    <Icon className={cn('h-3 w-3', style.iconColor)} />
                                    <span>{style.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default RevisionTree;
