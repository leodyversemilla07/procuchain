import { ExplorerTabs } from '@/components/admin/blockchain-explorer/explorer-tabs';
import { HealthTab } from '@/components/admin/blockchain-explorer/health-tab';
import { MirrorStatusTab } from '@/components/admin/blockchain-explorer/mirror-status-tab';
import { NetworkStatusCard } from '@/components/admin/blockchain-explorer/network-status-card';
import { OverviewStatsGrid } from '@/components/admin/blockchain-explorer/overview-stats-grid';
import { SearchBar } from '@/components/admin/blockchain-explorer/search-bar';
import { HeroCard } from '@/components/hero-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useBlockchainExplorer } from '@/hooks/use-blockchain-explorer';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import blockchain from '@/routes/admin/blockchain';
import type { AddressInfo, BlockchainOverview, BlockInfo, HealthStatus, PeerInfo, StreamInfo } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { Blocks, Database, RefreshCw } from 'lucide-react';

const tabOptions = [
    { value: 'overview', label: 'Overview', icon: Blocks },
    { value: 'blocks', label: 'Blocks', icon: Blocks },
    { value: 'streams', label: 'Streams', icon: Blocks },
    { value: 'addresses', label: 'Addresses', icon: Blocks },
    { value: 'peers', label: 'Peers', icon: Blocks },
    { value: 'search', label: 'Search', icon: Blocks },
    { value: 'health', label: 'Health', icon: Blocks },
    { value: 'mirror', label: 'Mirror Status', icon: Database },
] as const;

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
    const {
        searchQuery,
        setSearchQuery,
        selectedTab,
        setSelectedTab,
        isRefreshing,
        autoRefresh,
        setAutoRefresh,
        isSearching,
        searchResults,
        expandedBlocks,
        expandedPeers,
        isResetDialogOpen,
        setIsResetDialogOpen,
        isHealthy,
        isCircuitOpen,
        handleSearch,
        handleResetCircuitBreaker,
        handleRefresh,
        toggleBlockExpansion,
        togglePeerExpansion,
    } = useBlockchainExplorer({ overview, latestBlocks, streams, addresses, peers, health });

    const selectedTabLabel = tabOptions.find((tab) => tab.value === selectedTab)?.label ?? 'Select a tab';

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

                <Tabs value={selectedTab} onValueChange={setSelectedTab} className="flex-1 flex-col gap-4">
                    {/* Mobile Tab Navigation */}
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

                    {/* Desktop Tab Navigation */}
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
                        <ExplorerTabs
                            overview={overview}
                            latestBlocks={latestBlocks}
                            streams={streams}
                            addresses={addresses}
                            peers={peers}
                            searchResults={searchResults}
                            searchQuery={searchQuery}
                            expandedBlocks={expandedBlocks}
                            expandedPeers={expandedPeers}
                            onToggleBlockExpansion={toggleBlockExpansion}
                            onTogglePeerExpansion={togglePeerExpansion}
                        />
                        <TabsContent value="health">
                            <HealthTab
                                health={health}
                                isHealthy={isHealthy}
                                isCircuitOpen={isCircuitOpen}
                                isResetDialogOpen={isResetDialogOpen}
                                setIsResetDialogOpen={setIsResetDialogOpen}
                                handleResetCircuitBreaker={handleResetCircuitBreaker}
                            />
                        </TabsContent>
                        <TabsContent value="mirror">
                            <MirrorStatusTab />
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
        </AppLayout>
    );
}
