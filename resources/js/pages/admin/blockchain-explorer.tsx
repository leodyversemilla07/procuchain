import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import blockchain from '@/routes/admin/blockchain';
import { PageProps } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import {
    Activity,
    AlertCircle,
    Blocks,
    CheckCircle,
    ChevronRight,
    Database,
    Loader2,
    Network,
    RefreshCw,
    Search,
    Shield,
    TrendingUp,
    Users,
    Wallet,
    XCircle,
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';

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
    addrlocal?: string;
    services: string;
    relaytxes: boolean;
    lastsend: number;
    lastrecv: number;
    bytessent: number;
    bytesrecv: number;
    conntime: number;
    timeoffset: number;
    pingtime?: number;
    minping?: number;
    pingwait?: number;
    version: number;
    subver: string;
    inbound: boolean;
    startingheight: number;
    banscore: number;
    synced_headers: number;
    synced_blocks: number;
    inflight: number[];
    whitelisted: boolean;
    minfeefilter: number;
    bytesrecv_per_msg: Record<string, number>;
    bytesent_per_msg: Record<string, number>;
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
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [isSearching, setIsSearching] = useState(false);
    const [expandedBlocks, setExpandedBlocks] = useState<Set<string>>(new Set());
    const [expandedPeers, setExpandedPeers] = useState<Set<number>>(new Set());

    const isHealthy = health?.status === 'healthy';
    const isCircuitOpen = health?.circuit_breaker?.is_open ?? false;

    // Auto-refresh functionality
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['overview', 'latestBlocks', 'streams', 'addresses', 'peers', 'health'],
                onFinish: () => {
                    toast.success('Data refreshed', {
                        description: 'Blockchain data has been updated',
                        duration: 2000,
                    });
                },
            });
        }, 30000); // 30 seconds

        return () => clearInterval(interval);
    }, [autoRefresh]);

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

    // Helper function to format ping time
    const formatPingTime = (pingtime?: number) => {
        if (!pingtime) return 'N/A';
        return `${(pingtime * 1000).toFixed(2)}ms`;
    };

    // Helper function to get sync status
    const getSyncStatus = (synced_blocks: number, startingheight: number) => {
        if (synced_blocks >= startingheight) return 'Fully Synced';
        return `${synced_blocks}/${startingheight} blocks`;
    };

    const handleSearch = () => {
        if (!searchQuery.trim()) {
            toast.error('Please enter a search query');
            return;
        }

        setIsSearching(true);
        router.get(
            route('admin.blockchain.explorer.search'),
            { query: searchQuery },
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success('Search completed');
                    setIsSearching(false);
                },
                onError: () => {
                    toast.error('Search failed', {
                        description: 'Unable to complete the search. Please try again.',
                    });
                    setIsSearching(false);
                },
            },
        );
    };

    const handleResetCircuitBreaker = () => {
        if (
            confirm(
                'Are you sure you want to reset the circuit breaker? This will allow blockchain requests to resume immediately.',
            )
        ) {
            router.post(
                route('admin.blockchain.explorer.reset'),
                {},
                {
                    onSuccess: () => {
                        toast.success('Circuit breaker reset', {
                            description: 'Blockchain requests will now resume normally',
                        });
                        router.reload({ only: ['health'] });
                    },
                    onError: () => {
                        toast.error('Failed to reset circuit breaker');
                    },
                },
            );
        }
    };

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => {
                setIsRefreshing(false);
                toast.success('Data refreshed');
            },
        });
    };

    const toggleBlockExpansion = (hash: string) => {
        setExpandedBlocks((prev) => {
            const next = new Set(prev);
            if (next.has(hash)) {
                next.delete(hash);
            } else {
                next.add(hash);
            }
            return next;
        });
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
                {/* Network Status Indicator */}
                <Card className="border-emerald-500/20 bg-emerald-500/5">
                    <CardContent className="py-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="flex items-center gap-3">
                                <div className="relative">
                                    <div className="bg-emerald-500 h-3 w-3 rounded-full" />
                                    <div className="bg-emerald-500 absolute inset-0 h-3 w-3 animate-ping rounded-full opacity-75" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">Network Status: Online</p>
                                    <p className="text-muted-foreground text-xs">
                                        {overview?.connections || 0} active connections • Last updated{' '}
                                        {overview?.blocks
                                            ? formatDistanceToNow(new Date(), { addSuffix: true })
                                            : 'N/A'}
                                    </p>
                                </div>
                            </div>
                            {autoRefresh && (
                                <Badge variant="secondary" className="self-start sm:self-auto">
                                    Auto-refresh enabled
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Hero Card Header */}
                <HeroCard
                    icon={Blocks}
                    title="Blockchain Explorer"
                    description="Browse blocks, transactions, streams, addresses and network peers on the ProcuChain blockchain network"
                    actions={
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button onClick={handleRefresh} variant="outline" disabled={isRefreshing}>
                                <RefreshCw className={cn('mr-2 h-4 w-4', isRefreshing && 'animate-spin')} />
                                {isRefreshing ? 'Refreshing...' : 'Refresh'}
                            </Button>
                            <Button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                variant={autoRefresh ? 'default' : 'outline'}
                                size="default"
                            >
                                {autoRefresh ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Auto-refresh On
                                    </>
                                ) : (
                                    'Enable Auto-refresh'
                                )}
                            </Button>
                        </div>
                    }
                />

                {/* Search Bar */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                <Input
                                    placeholder="Search by block height, hash, transaction ID, or address..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                    className="pl-9"
                                    disabled={isSearching}
                                />
                            </div>
                            <Button onClick={handleSearch} disabled={isSearching} className="sm:w-auto">
                                {isSearching ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Searching...
                                    </>
                                ) : (
                                    <>
                                        <Search className="mr-2 h-4 w-4" />
                                        Search
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Overview Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Chain Height</CardTitle>
                            <TrendingUp className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            {isRefreshing || !overview ? (
                                <>
                                    <Skeleton className="mb-2 h-8 w-24" />
                                    <Skeleton className="h-3 w-32" />
                                </>
                            ) : (
                                <>
                                    <div className="text-2xl font-bold">{overview.blocks.toLocaleString()}</div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {overview.chain} | {overview.protocol}
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Connections</CardTitle>
                            <Network className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            {isRefreshing || !overview ? (
                                <>
                                    <Skeleton className="mb-2 h-8 w-16" />
                                    <Skeleton className="h-3 w-24" />
                                </>
                            ) : (
                                <>
                                    <div className="text-2xl font-bold">{overview.connections}</div>
                                    <p className="text-muted-foreground mt-1 text-xs">Active peers</p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Streams</CardTitle>
                            <Database className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            {isRefreshing || !overview ? (
                                <>
                                    <Skeleton className="mb-2 h-8 w-16" />
                                    <Skeleton className="h-3 w-28" />
                                </>
                            ) : (
                                <>
                                    <div className="text-2xl font-bold">{streams.length}</div>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {streams.filter((s: StreamInfo) => s.subscribed).length} subscribed
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Wallet Addresses</CardTitle>
                            <Wallet className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            {isRefreshing || !overview ? (
                                <>
                                    <Skeleton className="mb-2 h-8 w-16" />
                                    <Skeleton className="h-3 w-32" />
                                </>
                            ) : (
                                <>
                                    <div className="text-2xl font-bold">{addresses.length}</div>
                                    <p className="text-muted-foreground mt-1 text-xs">Managed addresses</p>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Tabs */}
                <Tabs value={selectedTab} onValueChange={setSelectedTab} className="flex-1">
                    {/* Mobile Tab Navigation - Dropdown */}
                    <div className="mb-4 md:hidden">
                        <Select value={selectedTab} onValueChange={setSelectedTab}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select a tab" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="overview">
                                    <div className="flex items-center">
                                        <Activity className="mr-2 h-4 w-4" />
                                        Overview
                                    </div>
                                </SelectItem>
                                <SelectItem value="blocks">
                                    <div className="flex items-center">
                                        <Blocks className="mr-2 h-4 w-4" />
                                        Blocks
                                    </div>
                                </SelectItem>
                                <SelectItem value="streams">
                                    <div className="flex items-center">
                                        <Database className="mr-2 h-4 w-4" />
                                        Streams
                                    </div>
                                </SelectItem>
                                <SelectItem value="addresses">
                                    <div className="flex items-center">
                                        <Wallet className="mr-2 h-4 w-4" />
                                        Addresses
                                    </div>
                                </SelectItem>
                                <SelectItem value="peers">
                                    <div className="flex items-center">
                                        <Users className="mr-2 h-4 w-4" />
                                        Peers
                                    </div>
                                </SelectItem>
                                <SelectItem value="health">
                                    <div className="flex items-center">
                                        <Shield className="mr-2 h-4 w-4" />
                                        Health
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Desktop Tab Navigation - Tab List */}
                    <TabsList className="hidden w-full grid-cols-6 md:grid">
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
                                        <div className="overflow-x-auto">
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
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Node Information */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Node Information</CardTitle>
                                        <CardDescription>Local node details and configuration</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="overflow-x-auto">
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
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Recent Blocks - Following MultiChain Explorer pattern */}
                                <Card className="md:col-span-2">
                                    <CardHeader>
                                        <CardTitle>Recent Blocks</CardTitle>
                                        <CardDescription>Latest blocks added to the blockchain</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="overflow-x-auto">
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
                                        </div>
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
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-12"></TableHead>
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
                                                <React.Fragment key={block.hash}>
                                                    <TableRow
                                                        className="cursor-pointer hover:bg-muted/50"
                                                        onClick={() => toggleBlockExpansion(block.hash)}
                                                    >
                                                        <TableCell>
                                                            <ChevronRight
                                                                className={cn(
                                                                    'text-muted-foreground h-4 w-4 transition-transform',
                                                                    expandedBlocks.has(block.hash) && 'rotate-90',
                                                                )}
                                                            />
                                                        </TableCell>
                                                        <TableCell className="font-medium">{block.height}</TableCell>
                                                        <TableCell className="font-mono text-xs">
                                                            {truncateHash(block.hash, 16)}
                                                        </TableCell>
                                                        <TableCell className="font-mono text-xs">
                                                            {truncateHash(block.miner, 16)}
                                                        </TableCell>
                                                        <TableCell>{block.tx_count}</TableCell>
                                                        <TableCell>{formatBytes(block.size)}</TableCell>
                                                        <TableCell className="text-muted-foreground text-xs">
                                                            <div className="flex flex-col gap-1">
                                                                <span>{formatDate(block.time)}</span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    ({formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })})
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                    {expandedBlocks.has(block.hash) && (
                                                        <TableRow>
                                                            <TableCell colSpan={7} className="bg-muted/20">
                                                                <Collapsible open={expandedBlocks.has(block.hash)}>
                                                                    <CollapsibleContent className="px-4 py-3">
                                                                        <div className="space-y-2">
                                                                            <p className="text-sm font-medium">Block Details</p>
                                                                            <div className="grid gap-2 text-sm">
                                                                                <div className="flex gap-2">
                                                                                    <span className="text-muted-foreground font-medium">
                                                                                        Full Hash:
                                                                                    </span>
                                                                                    <span className="font-mono break-all">
                                                                                        {block.hash}
                                                                                    </span>
                                                                                </div>
                                                                                <div className="flex gap-2">
                                                                                    <span className="text-muted-foreground font-medium">
                                                                                        Miner Address:
                                                                                    </span>
                                                                                    <span className="font-mono break-all">
                                                                                        {block.miner}
                                                                                    </span>
                                                                                </div>
                                                                                <div className="flex gap-2">
                                                                                    <span className="text-muted-foreground font-medium">
                                                                                        Block Size:
                                                                                    </span>
                                                                                    <span>{formatBytes(block.size)}</span>
                                                                                </div>
                                                                                <div className="flex gap-2">
                                                                                    <span className="text-muted-foreground font-medium">
                                                                                        Transaction Count:
                                                                                    </span>
                                                                                    <span>{block.tx_count} transactions</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </CollapsibleContent>
                                                                </Collapsible>
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </React.Fragment>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
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
                                {streams.length === 0 ? (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Database />
                                            </EmptyMedia>
                                            <EmptyTitle>No Streams Found</EmptyTitle>
                                            <EmptyDescription>
                                                There are no blockchain streams configured yet. Create your first stream to start storing data.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <div className="overflow-x-auto">
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
                                                            <div className="flex flex-wrap gap-1">
                                                                {stream.subscribed && <Badge variant="default">Subscribed</Badge>}
                                                                {stream.synchronized && <Badge variant="secondary">Synced</Badge>}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
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
                                {addresses.length === 0 ? (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Wallet />
                                            </EmptyMedia>
                                            <EmptyTitle>No Wallet Addresses</EmptyTitle>
                                            <EmptyDescription>
                                                There are no wallet addresses configured for this node. Create a new address to start managing blockchain transactions.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <div className="overflow-x-auto">
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
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Peers Tab */}
                    <TabsContent value="peers" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Network Peers</CardTitle>
                                <CardDescription>Connected nodes in the network with detailed connection metrics</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {peers.length > 0 ? (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-12"></TableHead>
                                                    <TableHead>Address</TableHead>
                                                    <TableHead>Version</TableHead>
                                                    <TableHead>Direction</TableHead>
                                                    <TableHead>Ping</TableHead>
                                                    <TableHead>Sync Status</TableHead>
                                                    <TableHead>Ban Score</TableHead>
                                                    <TableHead>Connected</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {peers.map((peer: PeerInfo) => (
                                                    <React.Fragment key={peer.id}>
                                                        <TableRow
                                                            className="cursor-pointer hover:bg-muted/50"
                                                            onClick={() => {
                                                                setExpandedPeers(prev => {
                                                                    const next = new Set(prev);
                                                                    if (next.has(peer.id)) {
                                                                        next.delete(peer.id);
                                                                    } else {
                                                                        next.add(peer.id);
                                                                    }
                                                                    return next;
                                                                });
                                                            }}
                                                        >
                                                            <TableCell>
                                                                <ChevronRight
                                                                    className={cn(
                                                                        'text-muted-foreground h-4 w-4 transition-transform',
                                                                        expandedPeers.has(peer.id) && 'rotate-90',
                                                                    )}
                                                                />
                                                            </TableCell>
                                                            <TableCell className="font-mono text-sm">{peer.addr}</TableCell>
                                                            <TableCell>{peer.subver}</TableCell>
                                                            <TableCell>
                                                                <Badge variant={peer.inbound ? 'secondary' : 'default'}>
                                                                    {peer.inbound ? 'Inbound' : 'Outbound'}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="font-mono text-sm">
                                                                {formatPingTime(peer.pingtime)}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge variant={peer.synced_blocks >= (peer.startingheight || 0) ? 'default' : 'secondary'}>
                                                                    {getSyncStatus(peer.synced_blocks || 0, peer.startingheight || 0)}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge variant={(peer.banscore || 0) > 0 ? 'destructive' : 'outline'}>
                                                                    {peer.banscore || 0}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-muted-foreground text-xs">
                                                                {peer.conntime ? formatDistanceToNow(new Date(peer.conntime * 1000), { addSuffix: true }) : 'Unknown'}
                                                            </TableCell>
                                                        </TableRow>
                                                        {expandedPeers.has(peer.id) && (
                                                            <TableRow>
                                                                <TableCell colSpan={8} className="bg-muted/20">
                                                                    <Collapsible open={expandedPeers.has(peer.id)}>
                                                                        <CollapsibleContent className="px-4 py-3">
                                                                            <div className="space-y-3">
                                                                                <p className="text-sm font-medium">Detailed Connection Information</p>
                                                                                <div className="grid gap-4 md:grid-cols-2">
                                                                                    <div className="space-y-2">
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Local Address:</span>
                                                                                            <span className="font-mono text-xs">{peer.addrlocal || 'N/A'}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Services:</span>
                                                                                            <span className="font-mono text-xs">{peer.services || 'N/A'}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Time Offset:</span>
                                                                                            <span>{peer.timeoffset || 0}s</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Min Ping:</span>
                                                                                            <span>{formatPingTime(peer.minping)}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Starting Height:</span>
                                                                                            <span>{(peer.startingheight || 0).toLocaleString()}</span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div className="space-y-2">
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Last Send:</span>
                                                                                            <span>{peer.lastsend ? formatDistanceToNow(new Date(peer.lastsend * 1000), { addSuffix: true }) : 'Never'}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Last Receive:</span>
                                                                                            <span>{peer.lastrecv ? formatDistanceToNow(new Date(peer.lastrecv * 1000), { addSuffix: true }) : 'Never'}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Data Sent:</span>
                                                                                            <span>{formatBytes(peer.bytessent || 0)}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Data Received:</span>
                                                                                            <span>{formatBytes(peer.bytesrecv || 0)}</span>
                                                                                        </div>
                                                                                        <div className="flex justify-between text-sm">
                                                                                            <span className="text-muted-foreground">Relay TX:</span>
                                                                                            <span>{peer.relaytxes ? 'Yes' : 'No'}</span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                {peer.inflight.length > 0 && (
                                                                                    <div className="mt-3">
                                                                                        <p className="text-sm font-medium mb-2">Blocks in Flight:</p>
                                                                                        <div className="flex flex-wrap gap-1">
                                                                                            {peer.inflight.map(block => (
                                                                                                <Badge key={block} variant="outline" className="text-xs">
                                                                                                    {block}
                                                                                                </Badge>
                                                                                            ))}
                                                                                        </div>
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </CollapsibleContent>
                                                                    </Collapsible>
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </React.Fragment>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ) : (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Users />
                                            </EmptyMedia>
                                            <EmptyTitle>No Connected Peers</EmptyTitle>
                                            <EmptyDescription>
                                                There are currently no peers connected to the blockchain network. This may be
                                                temporary during network synchronization.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
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
                                <CardContent>
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Shield />
                                            </EmptyMedia>
                                            <EmptyTitle>Health Data Unavailable</EmptyTitle>
                                            <EmptyDescription>
                                                Health monitoring data is currently not available. Please check your blockchain
                                                connection and try refreshing the page.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
