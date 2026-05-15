import { NetworkVisualization } from '@/components/admin/blockchain-explorer/network-visualization';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import network from '@/routes/admin/network';
import { Head, router, usePoll } from '@inertiajs/react';
import { Share2 } from 'lucide-react';
import { useCallback } from 'react';
import { toast } from 'sonner';

interface NodeConfig {
    id: string;
    name: string;
    role: string;
    ip: string;
    p2p_port: number;
    rpc_port: number;
    blocks: number;
    connected: boolean;
    peers: number;
    lastSeen: number;
    subver: string;
}

interface ConnectionData {
    id: string;
    from: string;
    to: string;
    status: 'active' | 'inactive';
    bytes_sent: number;
    bytes_recv: number;
    ping_time: number;
    connected_since: number;
    subver: string;
}

interface OverviewData {
    blocks: number;
    connected_nodes: number;
    total_nodes: number;
    total_peers: number;
    chain_name: string;
    version: string;
    all_connected: boolean;
}

interface NetworkVisualizationPageProps {
    nodes: NodeConfig[];
    connections: ConnectionData[];
    overview: OverviewData;
}

export default function NetworkVisualizationPage({ nodes, connections, overview }: NetworkVisualizationPageProps) {
    usePoll(
        15000,
        {
            only: ['nodes', 'connections', 'overview'],
            onFinish: () => {
                toast.success('Network data refreshed', { duration: 1000 });
            },
        },
        {
            autoStart: true,
            keepAlive: true,
        },
    );

    const handleRefresh = useCallback(() => {
        router.reload({
            only: ['nodes', 'connections', 'overview'],
            onFinish: () => toast.success('Network refreshed'),
        });
    }, []);

    const nodeInfos = nodes.map((node) => ({
        name: `procuchain-${node.id}`,
        ip: node.ip,
        p2pPort: node.p2p_port,
        rpcPort: node.rpc_port,
        role: node.name,
        blocks: node.blocks,
        connected: node.connected,
        peers: node.peers,
        lastSeen: node.lastSeen,
        subver: node.subver,
    }));

    const connectionLines = connections.map((conn) => ({
        from: `procuchain-${conn.from}`,
        to: `procuchain-${conn.to}`,
        status: conn.status as 'active' | 'syncing' | 'inactive',
        bytesSent: conn.bytes_sent,
        bytesRecv: conn.bytes_recv,
        pingTime: conn.ping_time > 0 ? conn.ping_time : undefined,
    }));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: dashboard.url() },
                { title: 'Blockchain Network', href: network.index.url() },
            ]}
        >
            <Head title="Blockchain Network" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Share2}
                    title="Blockchain Network"
                    description={`Full mesh topology — ${overview.total_nodes} decentralized MultiChain nodes, actively replicating every transaction. All data survives any single node failure — deleted records remain on-chain and recoverable across ${overview.total_nodes} copies.`}
                    actions={
                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2">
                                <div className={`h-3 w-3 rounded-full ${overview.all_connected ? 'animate-pulse bg-green-500' : 'bg-amber-500'}`} />
                                <span className="text-sm font-medium">{overview.all_connected ? 'All Nodes Connected' : 'Partial Network'}</span>
                            </div>
                            <Badge variant="secondary" className="font-mono">
                                Block #{overview.blocks}
                            </Badge>
                            <Badge className="gap-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <Share2 className="h-3 w-3" />
                                {overview.connected_nodes}/{overview.total_nodes} Replicated
                            </Badge>
                        </div>
                    }
                />

                <NetworkVisualization nodes={nodeInfos} connections={connectionLines} onRefresh={handleRefresh} isRefreshing={false} />
            </div>
        </AppLayout>
    );
}
