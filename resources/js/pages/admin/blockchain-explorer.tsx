import { NetworkStatusCard } from '@/components/admin/blockchain-explorer/network-status-card';
import { OverviewStatsGrid } from '@/components/admin/blockchain-explorer/overview-stats-grid';
import { SearchBar } from '@/components/admin/blockchain-explorer/search-bar';
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatBlockchainDate, formatBytes, formatPingTime, getSyncStatus, truncateHash } from '@/lib/blockchain-explorer';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import blockchain from '@/routes/admin/blockchain';
import { PageProps } from '@inertiajs/core';
import { Head, router, usePoll } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import {
 Activity,
 AlertCircle,
 Blocks,
 CheckCircle,
 ChevronRight,
 Database,
 RefreshCw,
    Search,
    Shield,
    Users,
    Wallet,
    XCircle,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Spinner } from '@/components/ui/spinner';

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

interface SearchResults {
    block?: object;
    transaction?: object;
    address?: object;
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
    const [searchResults, setSearchResults] = useState<SearchResults | null>(null);
    const [expandedBlocks, setExpandedBlocks] = useState<Set<string>>(new Set());
    const [expandedPeers, setExpandedPeers] = useState<Set<number>>(new Set());
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);

    const isHealthy = health?.status === 'healthy';
    const isCircuitOpen = health?.circuit_breaker?.is_open ?? false;

    const tabOptions = [
        { value: 'overview', label: 'Overview', icon: Activity },
        { value: 'blocks', label: 'Blocks', icon: Blocks },
        { value: 'streams', label: 'Streams', icon: Database },
        { value: 'addresses', label: 'Addresses', icon: Wallet },
        { value: 'peers', label: 'Peers', icon: Users },
        { value: 'search', label: 'Search', icon: Search },
        { value: 'health', label: 'Health', icon: Shield },
    ] as const;

    const selectedTabLabel = tabOptions.find((tab) => tab.value === selectedTab)?.label ?? 'Select a tab';

    // Auto-refresh functionality using Inertia's usePoll
    const { stop, start } = usePoll(
        30000,
        {
            only: ['overview', 'latestBlocks', 'streams', 'addresses', 'peers', 'health'],
            onFinish: () => {
                toast.success('Data refreshed', {
                    description: 'Blockchain data has been updated',
                    duration: 2000,
                });
            },
        },
        {
            autoStart: false,
            keepAlive: false, // Throttle by 90% when tab is in background
        },
    );

    // Start/stop polling based on autoRefresh toggle
    useEffect(() => {
        if (autoRefresh) {
            start();
        } else {
            stop();
        }
        return () => stop();
    }, [autoRefresh, start, stop]);

    const handleSearch = async () => {
        if (!searchQuery.trim()) {
            toast.error('Please enter a search query');
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(`${blockchain.explorer.search.url()}?query=${encodeURIComponent(searchQuery)}`);
            const data = await response.json();

            if (data.success) {
                setSearchResults(data.results);
                setSelectedTab('search'); // Switch to search tab
                toast.success('Search completed');
            } else {
                toast.error('Search failed', {
                    description: data.error || 'Unable to complete the search. Please try again.',
                });
            }
        } catch {
            toast.error('Search failed', {
                description: 'Unable to complete the search. Please try again.',
            });
        } finally {
            setIsSearching(false);
        }
    };

    const handleResetCircuitBreaker = () => {
        router.post(
            blockchain.explorer.reset.url(),
            {},
            {
                onSuccess: () => {
                    toast.success('Circuit breaker reset', {
                        description: 'Blockchain requests will now resume normally',
                    });
                    router.reload({ only: ['health'] });
                    setIsResetDialogOpen(false);
                },
                onError: () => {
                    toast.error('Failed to reset circuit breaker');
                    setIsResetDialogOpen(false);
                },
            },
        );
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
                <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
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
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <NetworkStatusCard overview={overview} autoRefresh={autoRefresh} />

                {/* Hero Card Header */}
                <HeroCard
                    icon={Blocks}
                    title="Blockchain Explorer"
                    description="Browse blocks, transactions, streams, addresses and network peers. Every transaction is replicated across all nodes — deleted data remains on-chain and recoverable."
                    actions={
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button onClick={handleRefresh} variant="outline" disabled={isRefreshing}>
{isRefreshing ? <Spinner data-icon="inline-start" /> : <RefreshCw className="mr-2 h-4 w-4" />}
 {isRefreshing ? 'Refreshing...' : 'Refresh'}
                            </Button>
                            <Button onClick={() => setAutoRefresh(!autoRefresh)} variant={autoRefresh ? 'default' : 'outline'} size="default">
                                {autoRefresh ? (
                                    <>
<Spinner data-icon="inline-start" />
 Auto-refresh On
                                    </>
                                ) : (
                                    'Enable Auto-refresh'
                                )}
                            </Button>
                        </div>
                    }
                />

                <SearchBar searchQuery={searchQuery} isSearching={isSearching} onSearchQueryChange={setSearchQuery} onSearch={handleSearch} />

                <OverviewStatsGrid isRefreshing={isRefreshing} overview={overview} streams={streams} addresses={addresses} />

                {/* Main Content Tabs */}
                <Tabs value={selectedTab} onValueChange={setSelectedTab} className="flex-1 flex-col gap-4">
                    {/* Mobile Tab Navigation - Dropdown */}
                    <div className="md:hidden">
                        <Select value={selectedTab} onValueChange={(value) => value && setSelectedTab(value)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select a tab">{() => selectedTabLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {tabOptions.map((tab) => {
                                        const TabIcon = tab.icon;

                                        return (
                                            <SelectItem key={tab.value} value={tab.value}>
                                                <div className="flex items-center gap-2">
                                                    <TabIcon />
                                                    {tab.label}
                                                </div>
                                            </SelectItem>
                                        );
                                    })}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Desktop Tab Navigation - Tab List */}
                    <TabsList variant="line" className="hidden md:flex md:flex-wrap md:items-center md:gap-1">
                        {tabOptions.map((tab) => {
                            const TabIcon = tab.icon;

                            return (
                                <TabsTrigger key={tab.value} value={tab.value}>
                                    <TabIcon data-icon="inline-start" />
                                    {tab.label}
                                </TabsTrigger>
                            );
                        })}
                    </TabsList>

                    <div className="rounded-lg border p-4 sm:p-6">
                        {/* Overview Tab */}
                        <TabsContent value="overview">
                            {overview && (
                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Chain Summary - Following MultiChain Explorer pattern */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Chain Summary</CardTitle>
                                            <CardDescription>Current blockchain state and parameters</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <dl className="space-y-3 text-sm">
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Chain Name</dt>
                                                    <dd className="text-right sm:text-left">{overview.chain}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Protocol Version</dt>
                                                    <dd className="text-right sm:text-left">{overview.protocol}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Blocks</dt>
                                                    <dd className="text-right font-mono sm:text-left">{overview.blocks.toLocaleString()}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Difficulty</dt>
                                                    <dd className="text-right font-mono sm:text-left">{overview.difficulty.toFixed(8)}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Connections</dt>
                                                    <dd className="text-right sm:text-left">{overview.connections}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Streams</dt>
                                                    <dd className="text-right sm:text-left">{streams.length}</dd>
                                                </div>
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Addresses</dt>
                                                    <dd className="text-right sm:text-left">{addresses.length}</dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>

                                    {/* Node Information */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Node Information</CardTitle>
                                            <CardDescription>Local node details and configuration</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <dl className="space-y-3 text-sm">
                                                <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                                                    <dt className="text-muted-foreground font-medium">Version</dt>
                                                    <dd className="text-right sm:text-left">{overview.version}</dd>
                                                </div>
                                                <div className="flex flex-col gap-2 sm:grid sm:grid-cols-[140px_1fr] sm:gap-4">
                                                    <dt className="text-muted-foreground font-medium">Node Address</dt>
                                                    <dd className="font-mono text-xs break-all">{overview.nodeaddress}</dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>

                                    {/* Recent Blocks - Following MultiChain Explorer pattern */}
                                    <Card className="md:col-span-2">
                                        <CardHeader>
                                            <CardTitle>Recent Blocks</CardTitle>
                                            <CardDescription>Latest blocks added to the blockchain</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            {/* Mobile Card View */}
                                            <div className="space-y-3 md:hidden">
                                                {latestBlocks.slice(0, 10).map((block: BlockInfo) => (
                                                    <Card key={block.hash} className="p-3">
                                                        <div className="space-y-2">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <Badge variant="outline" className="text-xs">
                                                                    #{block.height}
                                                                </Badge>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })}
                                                                </span>
                                                            </div>
                                                            <div className="space-y-1">
                                                                <div className="text-muted-foreground text-xs">Hash</div>
                                                                <div className="font-mono text-xs break-all">{truncateHash(block.hash, 20)}</div>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Transactions</div>
                                                                    <div className="font-medium">{block.tx_count}</div>
                                                                </div>
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Size</div>
                                                                    <div className="font-medium">{formatBytes(block.size)}</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </Card>
                                                ))}
                                            </div>

                                            {/* Desktop Table View */}
                                            <div className="hidden overflow-x-auto md:block">
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
                                                                <TableCell className="text-muted-foreground text-xs">
                                                                    {formatBlockchainDate(block.time)}
                                                                </TableCell>
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
                        <TabsContent value="blocks">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent Blocks</CardTitle>
                                    <CardDescription>Latest blocks mined on the blockchain</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {/* Mobile Card View */}
                                    <div className="space-y-3 md:hidden">
                                        {latestBlocks.map((block: BlockInfo) => (
                                            <Card key={block.hash} className="p-4">
                                                <button onClick={() => toggleBlockExpansion(block.hash)} className="w-full touch-manipulation">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex-1 space-y-2 text-left">
                                                            <div className="flex items-center gap-2">
                                                                <Badge variant="outline">#{block.height}</Badge>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })}
                                                                </span>
                                                            </div>
                                                            <div className="space-y-1">
                                                                <div className="text-muted-foreground text-xs">Hash</div>
                                                                <div className="font-mono text-xs break-all">{truncateHash(block.hash, 20)}</div>
                                                            </div>
                                                            <div className="flex gap-4 text-sm">
                                                                <div>
                                                                    <span className="text-muted-foreground">Txs: </span>
                                                                    <span className="font-medium">{block.tx_count}</span>
                                                                </div>
                                                                <div>
                                                                    <span className="text-muted-foreground">Size: </span>
                                                                    <span className="font-medium">{formatBytes(block.size)}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <ChevronRight
                                                            className={cn(
                                                                'text-muted-foreground h-5 w-5 shrink-0 transition-transform',
                                                                expandedBlocks.has(block.hash) && 'rotate-90',
                                                            )}
                                                        />
                                                    </div>
                                                </button>
                                                {expandedBlocks.has(block.hash) && (
                                                    <div className="mt-3 space-y-3 border-t pt-3">
                                                        <div>
                                                            <div className="text-muted-foreground text-xs">Full Hash</div>
                                                            <div className="mt-1 font-mono text-xs break-all">{block.hash}</div>
                                                        </div>
                                                        <div>
                                                            <div className="text-muted-foreground text-xs">Miner Address</div>
                                                            <div className="mt-1 font-mono text-xs break-all">{block.miner}</div>
                                                        </div>
                                                        <div>
                                                            <div className="text-muted-foreground text-xs">Time</div>
                                                            <div className="mt-1 text-sm">{formatBlockchainDate(block.time)}</div>
                                                        </div>
                                                    </div>
                                                )}
                                            </Card>
                                        ))}
                                    </div>

                                    {/* Desktop Table View */}
                                    <div className="hidden overflow-x-auto md:block">
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
                                                            className="hover:bg-muted/50 cursor-pointer"
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
                                                            <TableCell className="font-mono text-xs">{truncateHash(block.hash, 16)}</TableCell>
                                                            <TableCell className="font-mono text-xs">{truncateHash(block.miner, 16)}</TableCell>
                                                            <TableCell>{block.tx_count}</TableCell>
                                                            <TableCell>{formatBytes(block.size)}</TableCell>
                                                            <TableCell className="text-muted-foreground text-xs">
                                                                <div className="flex flex-col gap-1">
                                                                    <span>{formatBlockchainDate(block.time)}</span>
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
                                                                                        <span className="font-mono break-all">{block.hash}</span>
                                                                                    </div>
                                                                                    <div className="flex gap-2">
                                                                                        <span className="text-muted-foreground font-medium">
                                                                                            Miner Address:
                                                                                        </span>
                                                                                        <span className="font-mono break-all">{block.miner}</span>
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
                        <TabsContent value="streams">
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
                                        <>
                                            {/* Mobile Card View */}
                                            <div className="space-y-3 md:hidden">
                                                {streams.map((stream: StreamInfo) => (
                                                    <Card key={stream.name} className="p-4">
                                                        <div className="space-y-3">
                                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                                <h3 className="font-medium break-all">{stream.name}</h3>
                                                                <div className="flex flex-wrap gap-1">
                                                                    {stream.subscribed && (
                                                                        <Badge variant="default" className="text-xs">
                                                                            Subscribed
                                                                        </Badge>
                                                                    )}
                                                                    {stream.synchronized && (
                                                                        <Badge variant="secondary" className="text-xs">
                                                                            Synced
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Items</div>
                                                                    <div className="font-medium">{stream.items.toLocaleString()}</div>
                                                                </div>
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Keys</div>
                                                                    <div className="font-medium">{stream.keys.toLocaleString()}</div>
                                                                </div>
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Publishers</div>
                                                                    <div className="font-medium">{stream.publishers}</div>
                                                                </div>
                                                                <div>
                                                                    <div className="text-muted-foreground text-xs">Confirmed</div>
                                                                    <div className="font-medium">{stream.confirmed.toLocaleString()}</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </Card>
                                                ))}
                                            </div>

                                            {/* Desktop Table View */}
                                            <div className="hidden overflow-x-auto md:block">
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
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Addresses Tab */}
                        <TabsContent value="addresses">
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
                                                    There are no wallet addresses configured for this node. Create a new address to start managing
                                                    blockchain transactions.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    ) : (
                                        <>
                                            {/* Mobile Card View */}
                                            <div className="space-y-3 md:hidden">
                                                {addresses.map((address: AddressInfo) => (
                                                    <Card key={address.address} className="p-4">
                                                        <div className="space-y-2">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="text-muted-foreground text-xs">Address</div>
                                                                {address.ismine && (
                                                                    <Badge variant="default" className="text-xs">
                                                                        Mine
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="font-mono text-sm break-all">{address.address}</div>
                                                        </div>
                                                    </Card>
                                                ))}
                                            </div>

                                            {/* Desktop Table View */}
                                            <div className="hidden overflow-x-auto md:block">
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
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Peers Tab */}
                        <TabsContent value="peers">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Network Peers</CardTitle>
                                    <CardDescription>Connected nodes in the network with detailed connection metrics</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {peers.length > 0 ? (
                                        <>
                                            {/* Mobile Card View */}
                                            <div className="space-y-3 md:hidden">
                                                {peers.map((peer: PeerInfo) => (
                                                    <Card key={peer.id} className="p-4">
                                                        <button
                                                            onClick={() => {
                                                                setExpandedPeers((prev) => {
                                                                    const next = new Set(prev);
                                                                    if (next.has(peer.id)) {
                                                                        next.delete(peer.id);
                                                                    } else {
                                                                        next.add(peer.id);
                                                                    }
                                                                    return next;
                                                                });
                                                            }}
                                                            className="w-full touch-manipulation"
                                                        >
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="flex-1 space-y-2 text-left">
                                                                    <div className="font-mono text-sm break-all">{peer.addr}</div>
                                                                    <div className="flex flex-wrap gap-2">
                                                                        <Badge variant={peer.inbound ? 'secondary' : 'default'} className="text-xs">
                                                                            {peer.inbound ? 'Inbound' : 'Outbound'}
                                                                        </Badge>
                                                                        <Badge
                                                                            variant={
                                                                                peer.synced_blocks >= (peer.startingheight || 0)
                                                                                    ? 'default'
                                                                                    : 'secondary'
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {getSyncStatus(peer.synced_blocks || 0, peer.startingheight || 0)}
                                                                        </Badge>
                                                                    </div>
                                                                    <div className="text-muted-foreground flex gap-4 text-xs">
                                                                        <span>Ping: {formatPingTime(peer.pingtime)}</span>
                                                                        <span>Score: {peer.banscore || 0}</span>
                                                                    </div>
                                                                </div>
                                                                <ChevronRight
                                                                    className={cn(
                                                                        'text-muted-foreground h-5 w-5 shrink-0 transition-transform',
                                                                        expandedPeers.has(peer.id) && 'rotate-90',
                                                                    )}
                                                                />
                                                            </div>
                                                        </button>
                                                        {expandedPeers.has(peer.id) && (
                                                            <div className="mt-3 space-y-3 border-t pt-3">
                                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Version</div>
                                                                        <div className="mt-1">{peer.subver}</div>
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Time Offset</div>
                                                                        <div className="mt-1">{peer.timeoffset || 0}s</div>
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Data Sent</div>
                                                                        <div className="mt-1">{formatBytes(peer.bytessent || 0)}</div>
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Data Received</div>
                                                                        <div className="mt-1">{formatBytes(peer.bytesrecv || 0)}</div>
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Connected</div>
                                                                        <div className="mt-1">
                                                                            {peer.conntime
                                                                                ? formatDistanceToNow(new Date(peer.conntime * 1000), {
                                                                                      addSuffix: true,
                                                                                  })
                                                                                : 'Unknown'}
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-muted-foreground text-xs">Starting Height</div>
                                                                        <div className="mt-1">{(peer.startingheight || 0).toLocaleString()}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </Card>
                                                ))}
                                            </div>

                                            {/* Desktop Table View */}
                                            <div className="hidden overflow-x-auto md:block">
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
                                                                    className="hover:bg-muted/50 cursor-pointer"
                                                                    onClick={() => {
                                                                        setExpandedPeers((prev) => {
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
                                                                        <Badge
                                                                            variant={
                                                                                peer.synced_blocks >= (peer.startingheight || 0)
                                                                                    ? 'default'
                                                                                    : 'secondary'
                                                                            }
                                                                        >
                                                                            {getSyncStatus(peer.synced_blocks || 0, peer.startingheight || 0)}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Badge variant={(peer.banscore || 0) > 0 ? 'destructive' : 'outline'}>
                                                                            {peer.banscore || 0}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-muted-foreground text-xs">
                                                                        {peer.conntime
                                                                            ? formatDistanceToNow(new Date(peer.conntime * 1000), { addSuffix: true })
                                                                            : 'Unknown'}
                                                                    </TableCell>
                                                                </TableRow>
                                                                {expandedPeers.has(peer.id) && (
                                                                    <TableRow>
                                                                        <TableCell colSpan={8} className="bg-muted/20">
                                                                            <Collapsible open={expandedPeers.has(peer.id)}>
                                                                                <CollapsibleContent className="px-4 py-3">
                                                                                    <div className="space-y-3">
                                                                                        <p className="text-sm font-medium">
                                                                                            Detailed Connection Information
                                                                                        </p>
                                                                                        <div className="grid gap-4 md:grid-cols-2">
                                                                                            <div className="space-y-2">
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Local Address:
                                                                                                    </span>
                                                                                                    <span className="font-mono text-xs">
                                                                                                        {peer.addrlocal || 'N/A'}
                                                                                                    </span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Services:
                                                                                                    </span>
                                                                                                    <span className="font-mono text-xs">
                                                                                                        {peer.services || 'N/A'}
                                                                                                    </span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Time Offset:
                                                                                                    </span>
                                                                                                    <span>{peer.timeoffset || 0}s</span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Min Ping:
                                                                                                    </span>
                                                                                                    <span>{formatPingTime(peer.minping)}</span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Starting Height:
                                                                                                    </span>
                                                                                                    <span>
                                                                                                        {(peer.startingheight || 0).toLocaleString()}
                                                                                                    </span>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div className="space-y-2">
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Last Send:
                                                                                                    </span>
                                                                                                    <span>
                                                                                                        {peer.lastsend
                                                                                                            ? formatDistanceToNow(
                                                                                                                  new Date(peer.lastsend * 1000),
                                                                                                                  { addSuffix: true },
                                                                                                              )
                                                                                                            : 'Never'}
                                                                                                    </span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Last Receive:
                                                                                                    </span>
                                                                                                    <span>
                                                                                                        {peer.lastrecv
                                                                                                            ? formatDistanceToNow(
                                                                                                                  new Date(peer.lastrecv * 1000),
                                                                                                                  { addSuffix: true },
                                                                                                              )
                                                                                                            : 'Never'}
                                                                                                    </span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Data Sent:
                                                                                                    </span>
                                                                                                    <span>{formatBytes(peer.bytessent || 0)}</span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Data Received:
                                                                                                    </span>
                                                                                                    <span>{formatBytes(peer.bytesrecv || 0)}</span>
                                                                                                </div>
                                                                                                <div className="flex justify-between text-sm">
                                                                                                    <span className="text-muted-foreground">
                                                                                                        Relay TX:
                                                                                                    </span>
                                                                                                    <span>{peer.relaytxes ? 'Yes' : 'No'}</span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        {peer.inflight.length > 0 && (
                                                                                            <div className="mt-3">
                                                                                                <p className="mb-2 text-sm font-medium">
                                                                                                    Blocks in Flight:
                                                                                                </p>
                                                                                                <div className="flex flex-wrap gap-1">
                                                                                                    {peer.inflight.map((block) => (
                                                                                                        <Badge
                                                                                                            key={block}
                                                                                                            variant="outline"
                                                                                                            className="text-xs"
                                                                                                        >
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
                                        </>
                                    ) : (
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <Users />
                                                </EmptyMedia>
                                                <EmptyTitle>No Connected Peers</EmptyTitle>
                                                <EmptyDescription>
                                                    There are currently no peers connected to the blockchain network. This may be temporary during
                                                    network synchronization.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Search Results Tab */}
                        <TabsContent value="search">
                            {searchResults ? (
                                <div className="space-y-6">
                                    {searchResults.block && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Block</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <pre className="text-sm whitespace-pre-wrap">{JSON.stringify(searchResults.block, null, 2)}</pre>
                                            </CardContent>
                                        </Card>
                                    )}
                                    {searchResults.transaction && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Transaction</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <pre className="text-sm whitespace-pre-wrap">
                                                    {JSON.stringify(searchResults.transaction, null, 2)}
                                                </pre>
                                            </CardContent>
                                        </Card>
                                    )}
                                    {searchResults.address && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Address</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <pre className="text-sm whitespace-pre-wrap">{JSON.stringify(searchResults.address, null, 2)}</pre>
                                            </CardContent>
                                        </Card>
                                    )}
                                    {!searchResults.block && !searchResults.transaction && !searchResults.address && (
                                        <Card>
                                            <CardContent>
                                                <p className="text-muted-foreground">No results found for "{searchQuery}"</p>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyTitle>Search Blockchain</EmptyTitle>
                                                <EmptyDescription>Enter a block hash, height, transaction ID, or address to search</EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        {/* Health & Monitoring Tab */}
                        <TabsContent value="health">
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
                                                        <CardTitle className="text-2xl">
                                                            {isHealthy ? 'System Healthy' : 'System Unhealthy'}
                                                        </CardTitle>
                                                        <CardDescription>
                                                            Last checked: {new Date(health.checked_at).toLocaleString()}
                                                        </CardDescription>
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
                                            <CardContent className="space-y-4 p-4 sm:p-6">
                                                <div className="space-y-2">
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">Status</span>
                                                        <span className="font-medium">
                                                            {isCircuitOpen ? 'Blocking Requests' : 'Allowing Requests'}
                                                        </span>
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
                                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 sm:p-4 dark:border-amber-900 dark:bg-amber-950/20">
                                                        <div className="flex gap-2">
                                                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                                            <div className="flex-1 space-y-2">
                                                                <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                                                    Circuit breaker is open
                                                                </p>
                                                                <p className="text-sm text-amber-800 dark:text-amber-200">
                                                                    All blockchain requests are currently blocked due to repeated failures. The system
                                                                    will automatically retry after the recovery time.
                                                                </p>
                                                                <AlertDialog open={isResetDialogOpen} onOpenChange={setIsResetDialogOpen}>
                                                                    <AlertDialogTrigger
                                                                        render={<Button variant="outline" size="sm" className="mt-2" />}
                                                                    >
                                                                        Reset Circuit Breaker
                                                                    </AlertDialogTrigger>
                                                                    <AlertDialogContent>
                                                                        <AlertDialogHeader>
                                                                            <AlertDialogTitle>Reset Circuit Breaker?</AlertDialogTitle>
                                                                            <AlertDialogDescription>
                                                                                This will allow blockchain requests to resume immediately. Are you
                                                                                sure you want to proceed?
                                                                            </AlertDialogDescription>
                                                                        </AlertDialogHeader>
                                                                        <AlertDialogFooter>
                                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                                            <AlertDialogAction onClick={handleResetCircuitBreaker}>
                                                                                Reset
                                                                            </AlertDialogAction>
                                                                        </AlertDialogFooter>
                                                                    </AlertDialogContent>
                                                                </AlertDialog>
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
                                            <CardContent className="space-y-3 p-4 sm:p-6">
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
                                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 wrap-break-word dark:border-red-900 dark:bg-red-950/20">
                                                        <p className="text-sm text-red-900 dark:text-red-100">
                                                            {health.queue.failed_jobs_24h} job{health.queue.failed_jobs_24h !== 1 ? 's' : ''} failed
                                                            in the last 24 hours. Check the failed jobs queue for details.
                                                        </p>
                                                    </div>
                                                )}
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
                                            <CardContent className="p-4 sm:p-6">
                                                <ul className="list-inside list-disc space-y-2 text-sm wrap-break-word">
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
                                                    Health monitoring data is currently not available. Please check your blockchain connection and try
                                                    refreshing the page.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
        </AppLayout>
    );
}
