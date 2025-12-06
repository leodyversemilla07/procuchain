import type { ErrorStateProps } from '@/components/error-state';
import { ErrorState } from '@/components/error-state';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip } from '@/components/ui/chart';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { FileText, Scale, Zap } from 'lucide-react';
import { useMemo } from 'react';
import { Pie, PieChart } from 'recharts';

/**
 * Mode distribution item from the API
 * Contains data about a specific procurement mode's usage
 */
export interface ModeDistributionItem {
    mode: string;
    label: string;
    count: number;
    percentage: number;
}

/**
 * Mode type breakdown (competitive vs alternative)
 * Per NGPA IRR Sections 27-37
 */
export interface ModeTypeBreakdown {
    competitive: {
        label: string;
        description: string;
        ngpa_reference: string;
        count: number;
        percentage: number;
    };
    alternative: {
        label: string;
        description: string;
        ngpa_reference: string;
        count: number;
        percentage: number;
    };
    unknown: {
        label: string;
        count: number;
        percentage: number;
    };
    total: number;
}

/**
 * Complete mode statistics from the API
 */
export interface ModeStatistics {
    distribution: ModeDistributionItem[];
    type_breakdown: ModeTypeBreakdown;
    by_mode: Array<{
        mode: string;
        label: string;
        count: number;
        procurements: unknown[];
    }>;
}

export interface ModeDistributionCardProps {
    modeStatistics?: ModeStatistics;
    className?: string;
    title?: string;
    description?: string;
    footerTitle?: string;
    footerDescription?: string;
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    errorState?: ErrorStateProps;
    variant?: 'chart' | 'breakdown' | 'both';
}

const DEFAULT_EMPTY_TITLE = 'No procurement mode data available';
const DEFAULT_EMPTY_DESCRIPTION = 'Once procurements are initiated, their mode distribution will appear here.';

/**
 * Mode Distribution Card Component
 *
 * Displays procurement mode statistics with support for:
 * - Pie chart distribution
 * - Competitive vs Alternative breakdown (NGPA compliance)
 * - Mode-by-mode breakdown
 *
 * @ngpa NGPA IRR Sections 27-37 - Procurement Modes
 */
export const ModeDistributionCard = ({
    modeStatistics,
    className,
    title = 'Procurement by Mode',
    description = 'Distribution of procurements across NGPA modes',
    footerTitle = 'Mode distribution overview',
    footerDescription = 'Per NGPA IRR Sections 27-37',
    emptyStateIcon,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    errorState,
    variant = 'both',
}: ModeDistributionCardProps) => {
    const distribution = useMemo(() => modeStatistics?.distribution ?? [], [modeStatistics?.distribution]);
    const typeBreakdown = modeStatistics?.type_breakdown;

    const totalCount = useMemo(() => distribution.reduce((sum, item) => sum + item.count, 0), [distribution]);

    const modeChartConfig = useMemo(() => {
        const baseConfig = {
            count: {
                label: 'Count',
                color: 'var(--chart-1)',
            },
        };

        if (distribution.length === 0) {
            return baseConfig;
        }

        return distribution.reduce<Record<string, { label: string; color: string }>>((config, item, index) => {
            const colorIndex = (index % 5) + 1;
            config[item.mode] = {
                label: item.label,
                color: `var(--chart-${colorIndex})`,
            };

            return config;
        }, baseConfig);
    }, [distribution]);

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
        <Card className={cn('flex flex-col shadow-sm transition-shadow duration-300 hover:shadow-md', className)}>
            <CardHeader className="items-center pb-0">
                <CardTitle className="text-base sm:text-lg">{title}</CardTitle>
                <CardDescription className="text-xs sm:text-sm">{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex-1 pb-0">
                {(variant === 'chart' || variant === 'both') && (
                    <ChartContainer config={modeChartConfig} className="mx-auto aspect-square max-h-[200px] sm:max-h-[250px] md:max-h-[300px]">
                        <PieChart>
                            <ChartTooltip
                                content={({ active, payload }) => {
                                    if (active && payload && payload.length) {
                                        const payloadItem = payload[0].payload as ModeDistributionItem & { fill: string };

                                        return (
                                            <div className="border-border/50 bg-background rounded-lg border px-2.5 py-1.5 text-xs shadow-xl">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="flex flex-col">
                                                        <span className="text-muted-foreground text-[0.70rem] uppercase">Mode</span>
                                                        <span className="text-muted-foreground font-bold">{payloadItem.label}</span>
                                                    </div>
                                                    <div className="flex flex-col">
                                                        <span className="text-muted-foreground text-[0.70rem] uppercase">Count</span>
                                                        <span className="font-bold">
                                                            {payloadItem.count} ({payloadItem.percentage}%)
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
                                data={distribution.map((item, index) => ({
                                    ...item,
                                    fill: `var(--chart-${(index % 5) + 1})`,
                                }))}
                                dataKey="count"
                                nameKey="label"
                            />
                            <ChartLegend
                                content={<ChartLegendContent nameKey="label" />}
                                className="flex -translate-y-2 flex-wrap justify-center gap-4 *:flex *:items-center"
                            />
                        </PieChart>
                    </ChartContainer>
                )}

                {(variant === 'breakdown' || variant === 'both') && typeBreakdown && (
                    <div className="mt-4 space-y-3">
                        <ModeTypeBar
                            icon={Scale}
                            label={typeBreakdown.competitive.label}
                            count={typeBreakdown.competitive.count}
                            percentage={typeBreakdown.competitive.percentage}
                            reference={typeBreakdown.competitive.ngpa_reference}
                            colorClass="bg-primary"
                        />
                        <ModeTypeBar
                            icon={Zap}
                            label={typeBreakdown.alternative.label}
                            count={typeBreakdown.alternative.count}
                            percentage={typeBreakdown.alternative.percentage}
                            reference={typeBreakdown.alternative.ngpa_reference}
                            colorClass="bg-secondary"
                        />
                    </div>
                )}
            </CardContent>
            <CardFooter className="flex-col gap-2 text-sm">
                <div className="flex items-center gap-2 leading-none font-medium">{footerTitle}</div>
                <div className="text-muted-foreground leading-none">{footerDescription}</div>
            </CardFooter>
        </Card>
    );
};

/**
 * Mode Type Bar Component
 * Shows a progress bar for competitive/alternative mode breakdown
 */
interface ModeTypeBarProps {
    icon: LucideIcon;
    label: string;
    count: number;
    percentage: number;
    reference: string;
    colorClass: string;
}

const ModeTypeBar = ({ icon: Icon, label, count, percentage, reference, colorClass }: ModeTypeBarProps) => {
    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-1.5">
                    <Icon className="text-muted-foreground h-3.5 w-3.5" />
                    <span className="font-medium">{label}</span>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-[10px]">
                        {reference}
                    </Badge>
                    <span className="text-muted-foreground">
                        {count} ({percentage}%)
                    </span>
                </div>
            </div>
            <Progress value={percentage} className={cn('h-2', `[&>div]:${colorClass}`)} />
        </div>
    );
};
