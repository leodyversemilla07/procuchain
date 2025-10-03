import { useMemo } from 'react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip } from '@/components/ui/chart';
import { EmptyState } from '@/components/empty-state';
import { ErrorState } from '@/components/error-state';
import type { ErrorStateProps } from '@/components/error-state';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { FileText } from 'lucide-react';
import { Pie, PieChart } from 'recharts';

export interface StageDistributionItem {
    stage: string;
    count: number;
}

export interface StageDistributionCardProps {
    stageDistribution?: Record<string, number>;
    data?: StageDistributionItem[];
    className?: string;
    title?: string;
    description?: string;
    footerTitle?: string;
    footerDescription?: string;
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    errorState?: ErrorStateProps;
}

const DEFAULT_EMPTY_TITLE = 'No stage data available';
const DEFAULT_EMPTY_DESCRIPTION = 'Once procurements progress through stages, their distribution will appear here.';

const normalizeDistribution = (distribution?: Record<string, number>, data?: StageDistributionItem[]) => {
    if (distribution && Object.keys(distribution).length > 0) {
        return Object.entries(distribution).map(([stage, count]) => ({ stage, count }));
    }

    if (data && data.length > 0) {
        return data;
    }

    return [];
};

export const StageDistributionCard = ({
    stageDistribution,
    data,
    className,
    title = 'Procurement by Stage',
    description = 'Distribution of procurements across stages',
    footerTitle = 'Stage distribution overview',
    footerDescription = 'Showing current distribution across procurement stages',
    emptyStateIcon,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    errorState,
}: StageDistributionCardProps) => {
    const normalizedDistribution = normalizeDistribution(stageDistribution, data);

    const totalCount = useMemo(
        () => normalizedDistribution.reduce((sum, item) => sum + item.count, 0),
        [normalizedDistribution],
    );

    const stageChartConfig = useMemo(() => {
        const baseConfig = {
            count: {
                label: 'Count',
                color: 'var(--chart-1)',
            },
        };

        if (normalizedDistribution.length === 0) {
            return baseConfig;
        }

        return normalizedDistribution.reduce<Record<string, { label: string; color: string }>>((config, item, index) => {
            const colorIndex = (index % 5) + 1;
            config[item.stage] = {
                label: item.stage,
                color: `var(--chart-${colorIndex})`,
            };

            return config;
        }, baseConfig);
    }, [normalizedDistribution]);

    if (errorState) {
        return (
            <Card className={cn('shadow-sm', className)}>
                <CardContent className="p-6">
                    <ErrorState {...errorState} />
                </CardContent>
            </Card>
        );
    }

    if (totalCount === 0) {
        return (
            <Card className={cn('shadow-sm', className)}>
                <CardContent className="p-6">
                    <EmptyState
                        icon={emptyStateIcon ?? FileText}
                        title={emptyStateTitle}
                        description={emptyStateDescription}
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('flex flex-col shadow-sm', className)}>
            <CardHeader className="items-center pb-0">
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex-1 pb-0">
                <ChartContainer config={stageChartConfig} className="mx-auto aspect-square">
                    <PieChart>
                        <ChartTooltip
                            content={({ active, payload }) => {
                                if (active && payload && payload.length) {
                                    const payloadItem = payload[0].payload as StageDistributionItem & { fill: string };
                                    const percentage = ((payloadItem.count / totalCount) * 100).toFixed(1);

                                    return (
                                        <div className="border-border/50 bg-background rounded-lg border px-2.5 py-1.5 text-xs shadow-xl">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div className="flex flex-col">
                                                    <span className="text-muted-foreground text-[0.70rem] uppercase">Stage</span>
                                                    <span className="text-muted-foreground font-bold">{payloadItem.stage}</span>
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-muted-foreground text-[0.70rem] uppercase">Count</span>
                                                    <span className="font-bold">
                                                        {payloadItem.count} ({percentage}%)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                }

                                return null;
                            }}
                        />
                        <Pie
                            data={normalizedDistribution.map((item, index) => ({
                                stage: item.stage,
                                count: item.count,
                                fill: `var(--chart-${(index % 5) + 1})`,
                            }))}
                            dataKey="count"
                            nameKey="stage"
                        />
                        <ChartLegend
                            content={<ChartLegendContent nameKey="stage" />}
                            className="flex -translate-y-2 flex-wrap justify-center gap-4 [&>*]:flex [&>*]:items-center"
                        />
                    </PieChart>
                </ChartContainer>
            </CardContent>
            <CardFooter className="flex-col gap-2 text-sm">
                <div className="flex items-center gap-2 leading-none font-medium">{footerTitle}</div>
                <div className="text-muted-foreground leading-none">{footerDescription}</div>
            </CardFooter>
        </Card>
    );
};
