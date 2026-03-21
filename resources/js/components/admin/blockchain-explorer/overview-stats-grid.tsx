import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { AddressInfo, BlockchainOverview, StreamInfo } from '@/types';
import { Database, Network, TrendingUp, Wallet } from 'lucide-react';

interface OverviewStatsGridProps {
    isRefreshing: boolean;
    overview: BlockchainOverview | null;
    streams: StreamInfo[];
    addresses: AddressInfo[];
}

export function OverviewStatsGrid({ isRefreshing, overview, streams, addresses }: OverviewStatsGridProps) {
    const showSkeleton = isRefreshing || !overview;

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Chain Height</CardTitle>
                    <TrendingUp className="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    {showSkeleton ? (
                        <>
                            <Skeleton className="mb-2 h-8 w-24" />
                            <Skeleton className="h-3 w-32" />
                        </>
                    ) : (
                        <>
                            <div className="text-xl font-bold sm:text-2xl">{overview.blocks.toLocaleString()}</div>
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
                    {showSkeleton ? (
                        <>
                            <Skeleton className="mb-2 h-8 w-16" />
                            <Skeleton className="h-3 w-24" />
                        </>
                    ) : (
                        <>
                            <div className="text-xl font-bold sm:text-2xl">{overview.connections}</div>
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
                    {showSkeleton ? (
                        <>
                            <Skeleton className="mb-2 h-8 w-16" />
                            <Skeleton className="h-3 w-28" />
                        </>
                    ) : (
                        <>
                            <div className="text-xl font-bold sm:text-2xl">{streams.length}</div>
                            <p className="text-muted-foreground mt-1 text-xs">{streams.filter((stream) => stream.subscribed).length} subscribed</p>
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
                    {showSkeleton ? (
                        <>
                            <Skeleton className="mb-2 h-8 w-16" />
                            <Skeleton className="h-3 w-28" />
                        </>
                    ) : (
                        <>
                            <div className="text-xl font-bold sm:text-2xl">{addresses.length}</div>
                            <p className="text-muted-foreground mt-1 text-xs">{addresses.filter((address) => address.ismine).length} owned by node</p>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
