import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { BlockchainOverview } from '@/types';
import { formatDistanceToNow } from 'date-fns';

interface NetworkStatusCardProps {
    overview: BlockchainOverview | null;
    autoRefresh: boolean;
}

export function NetworkStatusCard({ overview, autoRefresh }: NetworkStatusCardProps) {
    return (
        <Card className="border-emerald-500/20 bg-emerald-500/5">
            <CardContent className="py-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <div className="h-3 w-3 rounded-full bg-emerald-500" />
                            <div className="absolute inset-0 h-3 w-3 animate-ping rounded-full bg-emerald-500 opacity-75" />
                        </div>
                        <div>
                            <p className="text-sm font-medium">Network Status: Online</p>
                            <p className="text-muted-foreground text-xs">
                                {overview?.connections || 0} peer connections • 4 nodes replicating • Last updated{' '}
                                {overview?.blocks ? formatDistanceToNow(new Date(), { addSuffix: true }) : 'N/A'}
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
    );
}
