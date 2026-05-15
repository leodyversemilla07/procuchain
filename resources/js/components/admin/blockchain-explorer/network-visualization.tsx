import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Spinner } from '@/components/ui/spinner';
import { formatDistanceToNow } from 'date-fns';
import { Activity, ArrowDownUp, Cpu, Database, GitBranch, Globe, HardDrive, Network, Server, Wifi } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

const { cos, min, sin, PI, max } = Math;

// ─────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────

interface NodeInfo {
    name: string;
    ip: string;
    p2pPort: number;
    rpcPort: number;
    role: string;
    blocks: number;
    connected: boolean;
    peers: number;
    lastSeen: number;
    subver: string;
}

interface ConnectionLine {
    from: string;
    to: string;
    status: 'active' | 'syncing' | 'inactive';
    bytesSent: number;
    bytesRecv: number;
    pingTime?: number;
}

interface NetworkVisualizationProps {
    nodes: NodeInfo[];
    connections: ConnectionLine[];
    onRefresh: () => void;
    isRefreshing: boolean;
}

// ─────────────────────────────────────────────────
// Node identity config
// ─────────────────────────────────────────────────

interface NodeIdentity {
    id: string;
    label: string;
    role: string;
    color: string; // Tailwind border/ring color
    rgb: string; // for SVG glow filters
    icon: React.ElementType;
}

const NODE_IDENTITIES: Record<string, NodeIdentity> = {
    admin: {
        id: 'admin',
        label: 'ADMIN',
        role: 'Primary Node',
        color: 'border-cyan-500',
        rgb: '6, 182, 212',
        icon: Cpu,
    },
    'bac-secretariat': {
        id: 'bac-secretariat',
        label: 'SEC',
        role: 'BAC Secretariat',
        color: 'border-emerald-500',
        rgb: '16, 185, 129',
        icon: Database,
    },
    'bac-chairman': {
        id: 'bac-chairman',
        label: 'CHAIR',
        role: 'BAC Chairman',
        color: 'border-amber-500',
        rgb: '245, 158, 11',
        icon: Globe,
    },
    hope: {
        id: 'hope',
        label: 'HOPE',
        role: 'HOPE',
        color: 'border-violet-500',
        rgb: '139, 92, 246',
        icon: HardDrive,
    },
};

const NODE_LABEL_MAP: Record<string, string> = {
    'procuchain-admin': 'admin',
    'procuchain-bac-secretariat': 'bac-secretariat',
    'procuchain-bac-chairman': 'bac-chairman',
    'procuchain-hope': 'hope',
};

function getIdentity(name: string): NodeIdentity {
    const key = NODE_LABEL_MAP[name] || name;
    return NODE_IDENTITIES[key] || { id: key, label: key.toUpperCase(), role: '', color: 'border-slate-500', rgb: '100, 116, 139', icon: Server };
}

// ─────────────────────────────────────────────────
// SVG Topology Canvas
// ─────────────────────────────────────────────────

function TopologyCanvas({
    nodes,
    connections,
    selectedNode,
    onNodeSelect,
}: {
    nodes: NodeInfo[];
    connections: ConnectionLine[];
    selectedNode: NodeInfo | null;
    onNodeSelect: (n: NodeInfo | null) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [dims, setDims] = useState({ w: 800, h: 400 });
    const [hoveredEdge, setHoveredEdge] = useState<string | null>(null);
    const [t, setT] = useState(0);
    useEffect(() => {
        const resize = () => {
            if (containerRef.current) {
                const rect = containerRef.current.getBoundingClientRect();
                setDims({ w: rect.width, h: max(340, rect.width * 0.42) });
            }
        };
        resize();
        window.addEventListener('resize', resize);
        return () => window.removeEventListener('resize', resize);
    }, []);

    useEffect(() => {
        let running = true;
        const tick = () => {
            if (running) setT((p) => p + 0.01);
        };
        const id = setInterval(tick, 16);
        return () => {
            running = false;
            clearInterval(id);
        };
    }, []);

    const { w, h } = dims;
    const cx = w / 2;
    const cy = h / 2;
    const radius = max(100, min(w, h) * 0.3);

    // Circular layout: admin top, then clockwise
    const order = ['admin', 'bac-secretariat', 'bac-chairman', 'hope'];
    const nodePositions: Record<string, { x: number; y: number }> = {};

    order.forEach((id, i) => {
        const name = `procuchain-${id}`;
        const angle = (i / order.length) * PI * 2 - PI / 2;
        nodePositions[name] = {
            x: cx + cos(angle) * radius,
            y: cy + sin(angle) * radius,
        };
    });

    const activeConnections = connections.filter((c) => c.status === 'active');

    return (
        <div ref={containerRef} className="bg-card relative w-full overflow-hidden rounded-lg border">
            <svg viewBox={`0 0 ${w} ${h}`} width={w} height={h} className="block">
                <defs>
                    {Object.values(NODE_IDENTITIES).map((v) => (
                        <filter key={v.id} id={`glow-${v.id}`}>
                            <feGaussianBlur stdDeviation="2.5" result="blur" />
                            <feMerge>
                                <feMergeNode in="blur" />
                                <feMergeNode in="SourceGraphic" />
                            </feMerge>
                        </filter>
                    ))}
                    <filter id="glow-dot">
                        <feGaussianBlur stdDeviation="1.5" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                    {/* Background grid */}
                    <pattern id="net-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                        <path d="M 32 0 L 0 0 0 32" fill="none" stroke="hsl(var(--border))" strokeWidth="0.3" opacity="0.5" />
                    </pattern>
                </defs>

                <rect width={w} height={h} fill="url(#net-grid)" />

                {/* Subtle orbital rings */}
                {[0.6, 0.8].map((r, i) => (
                    <ellipse
                        key={i}
                        cx={cx}
                        cy={cy}
                        rx={radius * r}
                        ry={radius * r * 0.7}
                        fill="none"
                        stroke="hsl(var(--border))"
                        strokeWidth={0.5}
                        strokeDasharray={i === 0 ? '3 5' : '2 7'}
                        opacity={0.3}
                        style={{ transform: `rotate(${30 + t * (i % 2 === 0 ? 8 : -6)}deg)`, transformOrigin: `${cx}px ${cy}px` }}
                    />
                ))}

                {/* Central indicator */}
                <circle cx={cx} cy={cy} r={radius * 0.04} fill="hsl(var(--primary))" opacity={0.08} />
                <circle cx={cx} cy={cy} r={2} fill="hsl(var(--primary))" opacity={0.4} />

                {/* Connection edges */}
                {activeConnections.map((conn) => {
                    const from = nodePositions[conn.from];
                    const to = nodePositions[conn.to];
                    if (!from || !to) return null;

                    const key = `${conn.from}-${conn.to}`;
                    const isHov = hoveredEdge === key;
                    const midX = (from.x + to.x) / 2;
                    const midY = (from.y + to.y) / 2;

                    return (
                        <g
                            key={key}
                            onMouseEnter={() => setHoveredEdge(key)}
                            onMouseLeave={() => setHoveredEdge(null)}
                            className="cursor-pointer"
                            opacity={isHov ? 1 : 0.8}
                        >
                            {/* Glow underlay */}
                            <line
                                x1={from.x}
                                y1={from.y}
                                x2={to.x}
                                y2={to.y}
                                stroke="hsl(var(--primary))"
                                strokeWidth={isHov ? 10 : 6}
                                opacity={0.12}
                                strokeLinecap="round"
                            />
                            {/* Main line - solid, visible in both modes */}
                            <line
                                x1={from.x}
                                y1={from.y}
                                x2={to.x}
                                y2={to.y}
                                stroke="hsl(var(--primary))"
                                strokeWidth={isHov ? 2.5 : 1.5}
                                strokeLinecap="round"
                                opacity={0.9}
                            />
                            {/* Animated particles */}
                            {[0.15, 0.35, 0.55, 0.75, 0.95].map((offset, i) => {
                                const phase = (t * 0.8 + i * 0.2) % 1;
                                const px = from.x + (to.x - from.x) * phase;
                                const py = from.y + (to.y - from.y) * phase;
                                return (
                                    <circle
                                        key={i}
                                        cx={px}
                                        cy={py}
                                        r={isHov ? 2 : 1.5}
                                        fill="hsl(var(--primary))"
                                        opacity={isHov ? 0.9 : 0.6}
                                        filter="url(#glow-dot)"
                                    />
                                );
                            })}
                            {/* Hover tooltip */}
                            {isHov && (
                                <g>
                                    <rect
                                        x={midX - 50}
                                        y={midY - 13}
                                        width={100}
                                        height={26}
                                        rx={5}
                                        fill="hsl(var(--background))"
                                        stroke="hsl(var(--border))"
                                        strokeWidth={0.5}
                                    />
                                    <text
                                        x={midX}
                                        y={midY + 4}
                                        textAnchor="middle"
                                        fill="hsl(var(--foreground))"
                                        fontSize={9}
                                        fontFamily="monospace"
                                        fontWeight={600}
                                    >
                                        ↕ {conn.pingTime?.toFixed(1)}ms
                                    </text>
                                </g>
                            )}
                        </g>
                    );
                })}

                {/* Node dots */}
                {nodes.map((node) => {
                    const pos = nodePositions[node.name];
                    if (!pos) return null;
                    const ident = getIdentity(node.name);
                    const isSel = selectedNode?.name === node.name;
                    const r = isSel ? 24 : 20;

                    return (
                        <g
                            key={node.name}
                            transform={`translate(${pos.x}, ${pos.y})`}
                            className="cursor-pointer"
                            onClick={() => onNodeSelect(isSel ? null : node)}
                        >
                            {/* Selection ring */}
                            {isSel && (
                                <circle
                                    r={r + 6}
                                    fill="none"
                                    stroke={ident.color.replace('border-', '')}
                                    strokeWidth={1.5}
                                    opacity={0.5}
                                    strokeDasharray="3 3"
                                    style={{ stroke: `rgb(${ident.rgb})` }}
                                />
                            )}
                            {/* Node circle */}
                            <circle
                                r={r}
                                fill="hsl(var(--background))"
                                style={{ stroke: `rgb(${ident.rgb})` }}
                                strokeWidth={isSel ? 2.5 : 1.5}
                                filter={`url(#glow-${ident.id})`}
                            />
                            {/* Icon */}
                            <foreignObject x={-10} y={-10} width={20} height={20}>
                                <div className="flex h-full w-full items-center justify-center">
                                    <ident.icon size={13} className="text-foreground/70" />
                                </div>
                            </foreignObject>
                            {/* Peer count badge */}
                            {node.connected && (
                                <g transform={`translate(${r - 5}, ${-r + 5})`}>
                                    <circle r={7} fill={`rgb(${ident.rgb})`} stroke="hsl(var(--background))" strokeWidth={1.5} />
                                    <text textAnchor="middle" dy={2.5} fill="#fff" fontSize={7} fontFamily="monospace" fontWeight={700}>
                                        {node.peers}
                                    </text>
                                </g>
                            )}
                            {/* Label */}
                            <text textAnchor="middle" y={r + 14} fill="hsl(var(--foreground))" fontSize={10} fontWeight={600} fontFamily="monospace">
                                {ident.label}
                            </text>
                            <text textAnchor="middle" y={r + 26} fill="hsl(var(--muted-foreground))" fontSize={8}>
                                Block #{node.blocks}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

// ─────────────────────────────────────────────────
// Stats bar
// ─────────────────────────────────────────────────

function StatsBar({ nodes, connections }: { nodes: NodeInfo[]; connections: ConnectionLine[] }) {
    const connected = nodes.filter((n) => n.connected).length;
    const activeEdges = connections.filter((c) => c.status === 'active').length;
    const maxBlocks = max(...nodes.map((n) => n.blocks));
    const allGood = connected === nodes.length;

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[
                { label: 'Nodes', value: `${connected}/${nodes.length}`, icon: Server, good: connected === nodes.length },
                { label: 'Edges', value: activeEdges, icon: Wifi, good: activeEdges > 0 },
                { label: 'Block', value: `#${maxBlocks}`, icon: GitBranch, good: true },
                { label: 'Mesh', value: allGood ? 'Full' : 'Partial', icon: Globe, good: allGood },
            ].map((s) => (
                <Card key={s.label} className={cn(s.good ? '' : 'opacity-60')}>
                    <CardContent className="flex items-center gap-3 p-3">
                        <div
                            className={cn(
                                'flex h-9 w-9 items-center justify-center rounded-lg',
                                s.good ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                            )}
                        >
                            <s.icon className="h-4 w-4" />
                        </div>
                        <div>
                            <p className="font-mono text-lg leading-none font-bold">{s.value}</p>
                            <p className="text-muted-foreground text-[11px]">{s.label}</p>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

// ─────────────────────────────────────────────────
// Node detail card
// ─────────────────────────────────────────────────

function NodeInfoPanel({ node }: { node: NodeInfo }) {
    const ident = getIdentity(node.name);

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg border', ident.color)}>
                            <ident.icon className="text-foreground h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle className="text-base">{ident.label}</CardTitle>
                            <CardDescription>{node.role}</CardDescription>
                        </div>
                    </div>
                    <Badge variant={node.connected ? 'default' : 'secondary'} className="gap-1.5">
                        <span className={cn('h-1.5 w-1.5 rounded-full', node.connected ? 'bg-primary-foreground' : 'bg-muted-foreground')} />
                        {node.connected ? 'Live' : 'Offline'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-2">
                    {[
                        { label: 'Blocks', value: `#${node.blocks.toLocaleString()}`, icon: GitBranch },
                        { label: 'Peers', value: `${node.peers} connected`, icon: Wifi },
                        { label: 'Address', value: `${node.ip}:${node.p2pPort}`, icon: Globe },
                        { label: 'Version', value: node.subver, icon: Activity },
                        {
                            label: 'Seen',
                            value: node.lastSeen > 0 ? formatDistanceToNow(new Date(node.lastSeen * 1000), { addSuffix: true }) : 'Now',
                            icon: Activity,
                        },
                        { label: 'Port', value: `${node.p2pPort} / ${node.rpcPort}`, icon: ArrowDownUp },
                    ].map((item) => (
                        <div key={item.label} className="bg-muted/50 flex items-center gap-2 rounded-md px-2.5 py-2">
                            <item.icon className="text-muted-foreground h-3 w-3 shrink-0" />
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-medium">{item.label}</p>
                                <p className="truncate font-mono text-xs font-semibold">{item.value}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

// ─────────────────────────────────────────────────
// Connection table
// ─────────────────────────────────────────────────

function ConnectionTable({ connections }: { connections: ConnectionLine[] }) {
    const active = connections.filter((c) => c.status === 'active');

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-sm">
                    <Wifi className="text-primary h-4 w-4" />
                    Network Connections
                </CardTitle>
                <CardDescription>
                    {active.length} active edge{active.length !== 1 ? 's' : ''} — {connections.length} total
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                <div className="divide-y">
                    {connections.map((conn, i) => {
                        const isActive = conn.status === 'active';
                        const fromLabel = (NODE_LABEL_MAP[conn.from] || conn.from).toUpperCase();
                        const toLabel = (NODE_LABEL_MAP[conn.to] || conn.to).toUpperCase();
                        const fromIdent = getIdentity(conn.from);
                        const toIdent = getIdentity(conn.to);

                        return (
                            <div
                                key={i}
                                className={cn('hover:bg-muted/30 flex items-center gap-4 px-4 py-2.5 transition-colors', !isActive && 'opacity-40')}
                            >
                                <span className={cn('h-2 w-2 shrink-0 rounded-full', isActive ? 'bg-primary' : 'bg-muted-foreground')} />
                                <div className="flex min-w-0 flex-1 items-center gap-2 font-mono text-xs">
                                    <span className="font-semibold" style={{ color: `rgb(${fromIdent.rgb})` }}>
                                        {fromLabel}
                                    </span>
                                    <span className="text-muted-foreground">→</span>
                                    <span className="font-semibold" style={{ color: `rgb(${toIdent.rgb})` }}>
                                        {toLabel}
                                    </span>
                                </div>
                                {isActive ? (
                                    <div className="hidden items-center gap-4 sm:flex">
                                        <span className="text-muted-foreground font-mono text-[10px]">↕ {conn.pingTime?.toFixed(1)}ms</span>
                                        <span className="text-muted-foreground font-mono text-[10px]">↑ {(conn.bytesSent / 1024).toFixed(1)}KB</span>
                                        <span className="text-muted-foreground font-mono text-[10px]">↓ {(conn.bytesRecv / 1024).toFixed(1)}KB</span>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground font-mono text-[10px]">—</span>
                                )}
                                <Badge variant={isActive ? 'outline' : 'secondary'} className="text-[9px]">
                                    {isActive ? 'LIVE' : 'DORMANT'}
                                </Badge>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

// ─────────────────────────────────────────────────
// Main export
// ─────────────────────────────────────────────────

export function NetworkVisualization({ nodes, connections, onRefresh, isRefreshing }: NetworkVisualizationProps) {
    const [selectedNode, setSelectedNode] = useState<NodeInfo | null>(null);

    return (
        <div className="space-y-4">
            <StatsBar nodes={nodes} connections={connections} />

            {/* Topology */}
            <TopologyCanvas nodes={nodes} connections={connections} selectedNode={selectedNode} onNodeSelect={setSelectedNode} />

            {/* Detail + Connections */}
            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-1">
                    {selectedNode ? (
                        <NodeInfoPanel node={selectedNode} />
                    ) : (
                        <Card className="h-full">
                            <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                                <Network className="text-muted-foreground/30 mb-3 h-8 w-8" />
                                <p className="text-muted-foreground text-sm">Click a node in the topology</p>
                                <p className="text-muted-foreground/50 text-xs">for detailed information</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
                <div className="lg:col-span-2">
                    <ConnectionTable connections={connections} />
                </div>
            </div>

            {/* Refresh */}
            <div className="flex justify-end">
                <button
                    onClick={onRefresh}
                    disabled={isRefreshing}
                    className={cn(
                        'hover:bg-muted inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                        isRefreshing && 'opacity-50',
                    )}
                >
<Spinner
 className={cn('size-3.5', isRefreshing && 'opacity-50')}
 data-icon="inline-start" />
 {isRefreshing ? 'Refreshing...' : 'Refresh'}
                </button>
            </div>
        </div>
    );
}
