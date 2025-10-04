import type { ErrorStateProps } from '@/components/error-state';
import { ErrorState } from '@/components/error-state';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { FileText } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

export interface ProcurementDistributionItem {
    stage: string;
    status: string;
}

export type DistributionKey = 'stage' | 'status';

export interface ProcurementDistributionCardProps {
    data: ProcurementDistributionItem[];
    className?: string;
    title?: string;
    description?: string;
    initialView?: DistributionKey;
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    errorState?: ErrorStateProps;
}

const DEFAULT_EMPTY_TITLE = 'No procurement data';
const DEFAULT_EMPTY_DESCRIPTION = 'Distribution charts will populate once procurements have been recorded.';

const DEFAULT_CHART_CONFIG: ChartConfig = {
    count: {
        label: 'Count',
        color: 'var(--chart-1)',
    },
};

const calculateDistribution = (items: ProcurementDistributionItem[], key: DistributionKey) => {
    return items.reduce<Record<string, number>>((accumulator, item) => {
        const bucket = item[key];
        if (!bucket) {
            return accumulator;
        }

        accumulator[bucket] = (accumulator[bucket] || 0) + 1;

        return accumulator;
    }, {});
};

export const ProcurementDistributionCard = ({
    data,
    className,
    title = 'Procurement Distribution',
    description = 'Distribution of procurements across stages and statuses',
    initialView = 'stage',
    emptyStateIcon,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    errorState,
}: ProcurementDistributionCardProps) => {
    const [activeView, setActiveView] = useState<DistributionKey>(initialView);

    const stageDistribution = useMemo(() => calculateDistribution(data, 'stage'), [data]);
    const statusDistribution = useMemo(() => calculateDistribution(data, 'status'), [data]);

    const activeDistribution = activeView === 'stage' ? stageDistribution : statusDistribution;
    const totalForActiveView = Object.values(activeDistribution).reduce((sum, count) => sum + count, 0);

    if (errorState) {
        return (
            <Card className={cn('shadow-sm', className)}>
                <CardContent className="p-6">
                    <ErrorState {...errorState} />
                </CardContent>
            </Card>
        );
    }

    if (data.length === 0 || totalForActiveView === 0) {
        const Icon = emptyStateIcon ?? FileText;
        return (
            <Card className={cn('shadow-sm', className)}>
                <CardContent className="p-6">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Icon className="h-8 w-8" />
                            </EmptyMedia>
                        </EmptyHeader>
                        <EmptyTitle>{emptyStateTitle}</EmptyTitle>
                        <EmptyDescription>{emptyStateDescription}</EmptyDescription>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('py-0 shadow-sm', className)}>
            <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-6 pt-4 pb-3 sm:!py-0">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <div className="flex">
                    {(['stage', 'status'] as DistributionKey[]).map((key) => {
                        const distribution = key === 'stage' ? stageDistribution : statusDistribution;
                        const total = Object.values(distribution).reduce((sum, count) => sum + count, 0);

                        return (
                            <button
                                key={key}
                                data-active={activeView === key}
                                className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                onClick={() => setActiveView(key)}
                                type="button"
                            >
                                <span className="text-muted-foreground text-xs capitalize">{key} Distribution</span>
                                <span className="text-lg leading-none font-bold sm:text-3xl">{total.toLocaleString()}</span>
                            </button>
                        );
                    })}
                </div>
            </CardHeader>
            <CardContent className="px-2 sm:p-6">
                <ChartContainer config={DEFAULT_CHART_CONFIG} className="aspect-auto h-[300px] w-full">
                    <BarChart
                        accessibilityLayer
                        data={Object.entries(activeDistribution).map(([key, count]) => ({
                            name: key,
                            count: count,
                        }))}
                        margin={{
                            left: 12,
                            right: 12,
                        }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="name"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            tickFormatter={(value: string) => (value.length > 15 ? `${value.slice(0, 15)}...` : value)}
                        />
                        <YAxis />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    className="w-[200px]"
                                    nameKey="count"
                                    labelFormatter={(value) => `${activeView.charAt(0).toUpperCase() + activeView.slice(1)}: ${value}`}
                                />
                            }
                        />
                        <Bar dataKey="count" fill="var(--color-count)" radius={4} />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
};
