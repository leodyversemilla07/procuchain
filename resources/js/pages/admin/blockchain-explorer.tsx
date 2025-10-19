import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import blockchain from '@/routes/admin/blockchain';
import { PageProps } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    Blocks,
    CheckCircle,
    Database,
    Network,
    RefreshCw,
    Search,
    Shield,
    TrendingUp,
    Users,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface BlockchainOverview {
    chain: string;
    protocol: string;
    blocks: number;
    difficulty: number;
    connections: number;
    version: string;
    nodeaddress: string;
}

interface BlockInfo {
    height: number;
    hash: string;
    time: number;
    miner: string;
    tx_count: number;
    size: number;
}

interface StreamInfo {
    name: string;
    createtxid: string | null;
    streamref: string | null;
    items: number;
    confirmed: number;
    keys: number;
    publishers: number;
    subscribed: boolean;
    synchronized: boolean;
}

interface AddressInfo {
    address: string;
    ismine: boolean;
}

interface PeerInfo {
    id: number;
    addr: string;
    version: string;
    subver: string;
    inbound: boolean;
    conntime: number;
    bytessent: number;
    bytesrecv: number;
}

interface CircuitBreakerState {
    is_open: boolean;
    failures: number;
    recovery_time: string | null;
}

interface QueueMetrics {
    pending_jobs: number;
    failed_jobs_24h: number;
}

interface DocumentMetrics {
    pending_1h: number;
    failed_24h: number;
}

interface HealthStatus {
    status: 'healthy' | 'unhealthy';
    circuit_breaker: CircuitBreakerState;
    queue: QueueMetrics;
    documents: DocumentMetrics;
    checked_at: string;
}

interface BlockchainExplorerProps {
    overview: BlockchainOverview | null;
    latestBlocks: BlockInfo[];
    streams: StreamInfo[];
    addresses: AddressInfo[];
    peers: PeerInfo[];
    health: HealthStatus | null;
    error?: string;
}

export default function BlockchainExplorer({
    overview,
    latestBlocks,
    streams,
    addresses,
    peers,
    health,
    error,
}: BlockchainExplorerProps & { auth: PageProps['auth'] }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTab, setSelectedTab] = useState('overview');

    const isHealthy = health?.status === 'healthy';
    const isCircuitOpen = health?.circuit_breaker?.is_open ?? false;

    // Format timestamp to human-readable date (following MultiChain Explorer pattern)
    const formatDate = (timestamp: number) => {
        const date = new Date(timestamp * 1000);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    // Format bytes to human-readable size
    const formatBytes = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    // Truncate hash for display (following MultiChain Explorer pattern)
    const truncateHash = (hash: string, length: number = 10) => {
        return hash.length > length ? hash.substring(0, length) + '...' : hash;
    };

    const handleSearch = () => {
        if (!searchQuery.trim()) return;

        router.get(
            route('admin.blockchain.explorer.search'),
            { query: searchQuery },
            {
                preserveState: true,
                onSuccess: () => {
                    // Search completed successfully
                },
            },
        );
    };

    const handleResetCircuitBreaker = () => {
        if (confirm('Are you sure you want to reset the circuit breaker? This will allow blockchain requests to resume immediately.')) {
            router.post(
                route('admin.blockchain.explorer.reset'),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.reload({ only: ['health'] });
                    },
                },
            );
        }
    };

    const handleRefresh = () => {
        router.reload();
    };

    if (error) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Admin', href: dashboard.url() },
                    { title: 'Blockchain Explorer', href: blockchain.explorer.index.url() },
                ]}
            >
                <Head title="Blockchain Explorer - Error" />
                <div className="flex h-full flex-1 flex-col gap-6 p-6 md:p-8">
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Connection Error</CardTitle>
                            <CardDescription>{error}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button onClick={() => router.reload()}>Retry Connection</Button>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: dashboard.url() },
                { title: 'Blockchain Explorer', href: blockchain.explorer.index.url() },
            ]}
        >
            <Head title="Blockchain Explorer" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Hero Card Header */}
                <HeroCard
                    icon={Blocks}
                    title="Blockchain Explorer"
                    description="Browse blocks, transactions, streams, addresses and network peers on the ProcuChain blockchain network"
                    actions={
                        <Button onClick={handleRefresh} variant="outline">
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                    }
                />

                {/* Search Bar */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                <Input
                                    placeholder="Search by block height, hash, transaction ID, or address..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                    className="pl-9"
                                />
                            </div>
                            <Button onClick={handleSearch}>Search</Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Overview Stats */}
                {overview && (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Chain Height</CardTitle>
                                <TrendingUp className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{overview.blocks.toLocaleString()}</div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {overview.chain} | {overview.protocol}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Connections</CardTitle>
                                <Network className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{overview.connections}</div>
                                <p className="text-muted-foreground mt-1 text-xs">Active peers</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Streams</CardTitle>
                                <Database className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{streams.length}</div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {streams.filter((s: StreamInfo) => s.subscribed).length} subscribed
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Wallet Addresses</CardTitle>
                                <Wallet className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{addresses.length}</div>
                                <p className="text-muted-foreground mt-1 text-xs">Managed addresses</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Main Content Tabs */}
                <Tabs value={selectedTab} onValueChange={setSelectedTab} className="flex-1">
                    <TabsList className="grid w-full grid-cols-6">
                        <TabsTrigger value="overview">
                            <Activity className="mr-2 h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger value="blocks">
                            <Blocks className="mr-2 h-4 w-4" />
                            Blocks
                        </TabsTrigger>
                        <TabsTrigger value="streams">
                            <Database className="mr-2 h-4 w-4" />
                            Streams
                        </TabsTrigger>
                        <TabsTrigger value="addresses">
                            <Wallet className="mr-2 h-4 w-4" />
                            Addresses
                        </TabsTrigger>
                        <TabsTrigger value="peers">
                            <Users className="mr-2 h-4 w-4" />
                            Peers
                        </TabsTrigger>
                        <TabsTrigger value="health">
                            <Shield className="mr-2 h-4 w-4" />
                            Health
                        </TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview" className="mt-6">
                        {overview && (
                            <div className="grid gap-6 md:grid-cols-2">
                                {/* Chain Summary - Following MultiChain Explorer pattern */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Chain Summary</CardTitle>
                                        <CardDescription>Current blockchain state and parameters</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableBody>
                                                <TableRow>
                                                    <TableCell className="font-medium">Chain Name</TableCell>
                                                    <TableCell className="text-right">{overview.chain}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Protocol Version</TableCell>
                                                    <TableCell className="text-right">{overview.protocol}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Blocks</TableCell>
                                                    <TableCell className="text-right font-mono">{overview.blocks.toLocaleString()}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Difficulty</TableCell>
                                                    <TableCell className="text-right font-mono">{overview.difficulty.toFixed(8)}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Connections</TableCell>
                                                    <TableCell className="text-right">{overview.connections}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Streams</TableCell>
                                                    <TableCell className="text-right">{streams.length}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Addresses</TableCell>
                                                    <TableCell className="text-right">{addresses.length}</TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>

                                {/* Node Information */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Node Information</CardTitle>
                                        <CardDescription>Local node details and configuration</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableBody>
                                                <TableRow>
                                                    <TableCell className="font-medium">Version</TableCell>
                                                    <TableCell className="text-right">{overview.version}</TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell className="font-medium">Node Address</TableCell>
                                                    <TableCell className="text-right font-mono text-xs break-all">{overview.nodeaddress}</TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>

                                {/* Recent Blocks - Following MultiChain Explorer pattern */}
                                <Card className="md:col-span-2">
                                    <CardHeader>
                                        <CardTitle>Recent Blocks</CardTitle>
                                        <CardDescription>Latest blocks added to the blockchain</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Height</TableHead>
                                                    <TableHead>Hash</TableHead>
                                                    <TableHead>Miner</TableHead>
                                                    <TableHead className="text-right">Transactions</TableHead>
                                                    <TableHead className="text-right">Size</TableHead>
                                                    <TableHead>Time</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {latestBlocks.slice(0, 10).map((block: BlockInfo) => (
                                                    <TableRow key={block.hash} className="hover:bg-accent">
                                                        <TableCell className="font-medium">{block.height}</TableCell>
                                                        <TableCell className="font-mono text-xs">{truncateHash(block.hash, 16)}</TableCell>
                                                        <TableCell className="font-mono text-xs">{truncateHash(block.miner, 16)}</TableCell>
                                                        <TableCell className="text-right">{block.tx_count}</TableCell>
                                                        <TableCell className="text-right">{formatBytes(block.size)}</TableCell>
                                                        <TableCell className="text-muted-foreground text-xs">{formatDate(block.time)}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </TabsContent>

                    {/* Blocks Tab */}
                    <TabsContent value="blocks" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent Blocks</CardTitle>
                                <CardDescription>Latest blocks mined on the blockchain</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Height</TableHead>
                                            <TableHead>Hash</TableHead>
                                            <TableHead>Miner</TableHead>
                                            <TableHead>Transactions</TableHead>
                                            <TableHead>Size</TableHead>
                                            <TableHead>Time</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {latestBlocks.map((block: BlockInfo) => (
                                            <TableRow key={block.hash}>
                                                <TableCell className="font-medium">{block.height}</TableCell>
                                                <TableCell className="font-mono text-xs">{truncateHash(block.hash, 16)}</TableCell>
                                                <TableCell className="font-mono text-xs">{truncateHash(block.miner, 16)}</TableCell>
                                                <TableCell>{block.tx_count}</TableCell>
                                                <TableCell>{formatBytes(block.size)}</TableCell>
                                                <TableCell className="text-muted-foreground text-xs">{formatDate(block.time)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Streams Tab */}
                    <TabsContent value="streams" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Blockchain Streams</CardTitle>
                                <CardDescription>Data streams configured on the blockchain</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Stream Name</TableHead>
                                            <TableHead>Items</TableHead>
                                            <TableHead>Keys</TableHead>
                                            <TableHead>Publishers</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {streams.map((stream: StreamInfo) => (
                                            <TableRow key={stream.name}>
                                                <TableCell className="font-medium">{stream.name}</TableCell>
                                                <TableCell>{stream.items.toLocaleString()}</TableCell>
                                                <TableCell>{stream.keys.toLocaleString()}</TableCell>
                                                <TableCell>{stream.publishers}</TableCell>
                                                <TableCell>
                                                    <div className="flex gap-2">
                                                        {stream.subscribed && <Badge variant="default">Subscribed</Badge>}
                                                        {stream.synchronized && <Badge variant="secondary">Synced</Badge>}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Addresses Tab */}
                    <TabsContent value="addresses" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Wallet Addresses</CardTitle>
                                <CardDescription>Blockchain addresses managed by this node</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Address</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {addresses.map((address: AddressInfo) => (
                                            <TableRow key={address.address}>
                                                <TableCell className="font-mono text-sm">{address.address}</TableCell>
                                                <TableCell>{address.ismine && <Badge variant="default">Mine</Badge>}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Peers Tab */}
                    <TabsContent value="peers" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Network Peers</CardTitle>
                                <CardDescription>Connected nodes in the network</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {peers.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Address</TableHead>
                                                <TableHead>Version</TableHead>
                                                <TableHead>Direction</TableHead>
                                                <TableHead>Data Sent</TableHead>
                                                <TableHead>Data Received</TableHead>
                                                <TableHead>Connected</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {peers.map((peer: PeerInfo) => (
                                                <TableRow key={peer.id}>
                                                    <TableCell className="font-mono text-sm">{peer.addr}</TableCell>
                                                    <TableCell>{peer.subver}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={peer.inbound ? 'secondary' : 'default'}>
                                                            {peer.inbound ? 'Inbound' : 'Outbound'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>{formatBytes(peer.bytessent)}</TableCell>
                                                    <TableCell>{formatBytes(peer.bytesrecv)}</TableCell>
                                                    <TableCell>{new Date(peer.conntime * 1000).toLocaleString()}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <div className="text-muted-foreground py-8 text-center">No connected peers found</div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Health & Monitoring Tab */}
                    <TabsContent value="health" className="mt-6">
                        {health ? (
                            <div className="space-y-6">
                                {/* Overall Health Status */}
                                <Card
                                    className={
                                        isHealthy
                                            ? 'border-green-200 bg-green-50/50 dark:border-green-900 dark:bg-green-950/20'
                                            : 'border-red-200 bg-red-50/50 dark:border-red-900 dark:bg-red-950/20'
                                    }
                                >
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                {isHealthy ? (
                                                    <CheckCircle className="h-8 w-8 text-green-600 dark:text-green-400" />
                                                ) : (
                                                    <XCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                                                )}
                                                <div>
                                                    <CardTitle className="text-2xl">{isHealthy ? 'System Healthy' : 'System Unhealthy'}</CardTitle>
                                                    <CardDescription>Last checked: {new Date(health.checked_at).toLocaleString()}</CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant={isHealthy ? 'default' : 'destructive'} className="px-4 py-2 text-lg">
                                                {health.status.toUpperCase()}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                </Card>

                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Circuit Breaker Status */}
                                    <Card>
                                        <CardHeader>
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Activity className="h-5 w-5" />
                                                    <CardTitle>Circuit Breaker</CardTitle>
                                                </div>
                                                <Badge variant={isCircuitOpen ? 'destructive' : 'secondary'}>
                                                    {isCircuitOpen ? 'OPEN' : 'CLOSED'}
                                                </Badge>
                                            </div>
                                            <CardDescription>Protects system from cascading failures</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="space-y-2">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">Status</span>
                                                    <span className="font-medium">{isCircuitOpen ? 'Blocking Requests' : 'Allowing Requests'}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">Consecutive Failures</span>
                                                    <span className="font-medium">{health.circuit_breaker.failures}</span>
                                                </div>
                                                {health.circuit_breaker.recovery_time && (
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">Recovery Time</span>
                                                        <span className="font-medium">
                                                            {new Date(health.circuit_breaker.recovery_time).toLocaleString()}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>

                                            {isCircuitOpen && (
                                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                                                    <div className="flex gap-2">
                                                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                                                        <div className="flex-1 space-y-2">
                                                            <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                                                Circuit breaker is open
                                                            </p>
                                                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                                                All blockchain requests are currently blocked due to repeated failures. The system
                                                                will automatically retry after the recovery time.
                                                            </p>
                                                            <Button onClick={handleResetCircuitBreaker} variant="outline" size="sm" className="mt-2">
                                                                Reset Circuit Breaker
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Queue Metrics */}
                                    <Card>
                                        <CardHeader>
                                            <div className="flex items-center gap-2">
                                                <Activity className="h-5 w-5" />
                                                <CardTitle>Queue Status</CardTitle>
                                            </div>
                                            <CardDescription>Background job processing metrics</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">Pending Jobs</span>
                                                <Badge variant="secondary">{health.queue.pending_jobs}</Badge>
                                            </div>
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">Failed Jobs (24h)</span>
                                                <Badge variant={health.queue.failed_jobs_24h > 0 ? 'destructive' : 'secondary'}>
                                                    {health.queue.failed_jobs_24h}
                                                </Badge>
                                            </div>

                                            {health.queue.failed_jobs_24h > 0 && (
                                                <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/20">
                                                    <p className="text-sm text-red-900 dark:text-red-100">
                                                        {health.queue.failed_jobs_24h} job{health.queue.failed_jobs_24h !== 1 ? 's' : ''} failed in
                                                        the last 24 hours. Check the failed jobs queue for details.
                                                    </p>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Document Metrics */}
                                    <Card className="md:col-span-2">
                                        <CardHeader>
                                            <div className="flex items-center gap-2">
                                                <Activity className="h-5 w-5" />
                                                <CardTitle>Document Blockchain Status</CardTitle>
                                            </div>
                                            <CardDescription>Blockchain publication status for procurement documents</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">Pending (Last Hour)</span>
                                                        <Badge variant={health.documents.pending_1h > 10 ? 'destructive' : 'secondary'}>
                                                            {health.documents.pending_1h}
                                                        </Badge>
                                                    </div>
                                                    {health.documents.pending_1h > 10 && (
                                                        <p className="text-muted-foreground text-xs">
                                                            Consider running the reconciliation command to check for stuck records
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">Failed (Last 24h)</span>
                                                        <Badge variant={health.documents.failed_24h > 0 ? 'destructive' : 'secondary'}>
                                                            {health.documents.failed_24h}
                                                        </Badge>
                                                    </div>
                                                    {health.documents.failed_24h > 0 && (
                                                        <p className="text-muted-foreground text-xs">
                                                            Review failed documents and retry if blockchain is now available
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Recommendations */}
                                {(!isHealthy || isCircuitOpen || health.queue.failed_jobs_24h > 0) && (
                                    <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20">
                                        <CardHeader>
                                            <div className="flex items-center gap-2">
                                                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                                <CardTitle>Recommended Actions</CardTitle>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="list-inside list-disc space-y-2 text-sm">
                                                {isCircuitOpen && (
                                                    <li>Circuit breaker is open - check blockchain node connectivity at 159.65.12.99:6487</li>
                                                )}
                                                {health.queue.failed_jobs_24h > 0 && (
                                                    <li>
                                                        Review failed jobs:{' '}
                                                        <code className="rounded bg-black/10 px-1 py-0.5 text-xs dark:bg-white/10">
                                                            php artisan queue:failed
                                                        </code>
                                                    </li>
                                                )}
                                                {health.documents.pending_1h > 10 && (
                                                    <li>
                                                        Run reconciliation:{' '}
                                                        <code className="rounded bg-black/10 px-1 py-0.5 text-xs dark:bg-white/10">
                                                            php artisan blockchain:reconcile
                                                        </code>
                                                    </li>
                                                )}
                                                {health.documents.failed_24h > 0 && (
                                                    <li>Investigate blockchain publication failures in application logs</li>
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="py-8 text-center">
                                    <p className="text-muted-foreground">Health monitoring data is not available</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
