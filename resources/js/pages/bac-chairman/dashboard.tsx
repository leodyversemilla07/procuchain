import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState, useMemo } from 'react';
import { toast } from "sonner";
import {
    ArrowRight, Clock, FileText, CheckCircle, FileIcon, EyeIcon, FileUpIcon,
    FileTextIcon, ExternalLinkIcon, CheckIcon, PlusIcon, ActivityIcon,
} from "lucide-react";
import { Stage, Status } from '@/types/blockchain';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid,
    PieChart, Pie,
} from 'recharts';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    ChartLegend,
    ChartLegendContent,
} from '@/components/ui/chart';
import type { BreadcrumbItem, SharedData } from '@/types';

import {

} from "lucide-react";

const ACTION_ICON_MAP = {
    upload: FileUpIcon,
    document: FileUpIcon,
    stage: ArrowRight,
    transition: ArrowRight,
    'pre-procurement': FileTextIcon,
    decision: CheckCircle,
    publish: ExternalLinkIcon,
    complete: CheckIcon,
    submit: PlusIcon,
    add: PlusIcon,
    review: FileTextIcon,
    evaluate: FileTextIcon,
} as const;

const getActionIcon = (action: string) => {
    const IconComponent = Object.entries(ACTION_ICON_MAP).find(
        ([key]) => action.toLowerCase().includes(key)
    )?.[1] || ActivityIcon;

    return IconComponent;
};

interface DashboardStats {
    ongoingProjects: number;
    completedBiddings: number;
    totalDocuments: number;
}

interface RecentActivity {
    id: string;
    title: string;
    action: string;
    date: string;
    user: string;
    stage?: string;
}

interface RecentProcurement {
    id: string;
    title: string;
    stage: Stage;
    status: Status;
}

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bids and Awards Committee Chairman Dashboard',
        href: route('bac-chairman.dashboard'),
    },
];

export default function BACChairmanDashboard() {
    const { recentProcurements = [], procurementDistribution = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

    // State for procurement distribution chart
    const [activeChart, setActiveChart] = useState<"stage" | "status">("stage");

    // Calculate distribution from procurementDistribution data (separate from recent procurements)
    const stageDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach(procurement => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    const statusDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach(procurement => {
            const status = procurement.status;
            distribution[status] = (distribution[status] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    // Chart configuration for Pie Chart - Dynamic based on actual stages
    const stageChartConfig: ChartConfig = useMemo(() => {
        const config: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        // If we have stage distribution data, create dynamic config
        if (stageDistribution && Object.keys(stageDistribution).length > 0) {
            Object.keys(stageDistribution).forEach((stage, index) => {
                config[stage] = {
                    label: stage,
                    color: `var(--chart-${(index % 5) + 1})`,
                };
            });
        } else {
            // Fallback static config
            config.stage1 = { label: "Stage 1", color: "var(--chart-1)" };
            config.stage2 = { label: "Stage 2", color: "var(--chart-2)" };
            config.stage3 = { label: "Stage 3", color: "var(--chart-3)" };
            config.stage4 = { label: "Stage 4", color: "var(--chart-4)" };
            config.stage5 = { label: "Stage 5", color: "var(--chart-5)" };
        }

        return config;
    }, [stageDistribution]);

    // Define all possible cards
    const allCards = [
        {
            label: "Ongoing Projects",
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: "text-primary bg-primary/10"
        },
        {
            label: "Completed Biddings",
            value: stats?.completedBiddings || 0,
            icon: CheckCircle,
            colors: "text-primary bg-primary/10"
        },
        {
            label: "Total Documents",
            value: stats?.totalDocuments || 0,
            icon: FileIcon,
            colors: "text-muted-foreground bg-muted/10"
        }
    ];

    // Determine grid columns based on the number of cards to show
    const gridColsClass = allCards.length === 4 ? "md:grid-cols-4" : "md:grid-cols-3";

    const renderStatsCards = () => {
        return (
            <div className={`grid grid-cols-1 sm:grid-cols-2 ${gridColsClass} gap-4`}>
                {allCards.map((card, index) => {
                    const IconComponent = card.icon;
                    return (
                        <Card key={index} className="shadow-sm">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">{card.label}</p>
                                        <p className="text-2xl font-bold">{card.value}</p>
                                    </div>
                                    <div className={`p-2 rounded-full ${card.colors}`}>
                                        <IconComponent className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        );
    };

    useEffect(() => {
        if (error) {
            toast.error("Error loading dashboard", {
                description: error,
                duration: 5000,
            });
        }
    }, [error]);

    const renderProcurementDistribution = () => {
        // Chart configuration
        const chartConfig: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        const data = activeChart === "stage" ? stageDistribution : statusDistribution;

        if (procurementDistribution.length === 0) {
            return (
                <Card className="shadow-sm">
                    <CardContent className="p-6 text-center">
                        <FileText className="mx-auto h-8 w-8 text-muted-foreground opacity-20 mb-2" />
                        <p className="text-muted-foreground">No procurement data available for distribution</p>
                    </CardContent>
                </Card>
            );
        }

        return (
            <Card className="py-0 shadow-sm">
                <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                    <div className="flex flex-1 flex-col justify-center gap-1 px-6 pt-4 pb-3 sm:!py-0">
                        <CardTitle>Procurement Distribution</CardTitle>
                        <CardDescription>
                            Distribution of procurements across stages and statuses
                        </CardDescription>
                    </div>
                    <div className="flex">
                        {["stage", "status"].map((key) => {
                            const chart = key as "stage" | "status";
                            const chartData = chart === "stage" ? stageDistribution : statusDistribution;
                            const chartTotal = Object.values(chartData as Record<string, number>).reduce((sum: number, count: number) => sum + count, 0);

                            return (
                                <button
                                    key={chart}
                                    data-active={activeChart === chart}
                                    className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                    onClick={() => setActiveChart(chart)}
                                >
                                    <span className="text-muted-foreground text-xs capitalize">
                                        {chart} Distribution
                                    </span>
                                    <span className="text-lg leading-none font-bold sm:text-3xl">
                                        {chartTotal.toLocaleString()}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </CardHeader>
                <CardContent className="px-2 sm:p-6">
                    <ChartContainer
                        config={chartConfig}
                        className="aspect-auto h-[300px] w-full"
                    >
                        <BarChart
                            accessibilityLayer
                            data={Object.entries(data).map(([key, count]) => ({
                                name: key,
                                count: count
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
                                tickFormatter={(value: string) => value.length > 15 ? `${value.slice(0, 15)}...` : value}
                            />
                            <YAxis />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        className="w-[200px]"
                                        nameKey="count"
                                        labelFormatter={(value) => `${activeChart.charAt(0).toUpperCase() + activeChart.slice(1)}: ${value}`}
                                    />
                                }
                            />
                            <Bar
                                dataKey="count"
                                fill={`var(--color-count)`}
                                radius={4}
                            />
                        </BarChart>
                    </ChartContainer>
                </CardContent>
            </Card>
        );
    };

    const renderStagePieChart = () => {
        if (Object.keys(stageDistribution).length === 0) {
            return (
                <Card className="shadow-sm">
                    <CardContent className="p-6 text-center">
                        <FileText className="mx-auto h-8 w-8 text-muted-foreground opacity-20 mb-2" />
                        <p className="text-muted-foreground">No stage data available</p>
                    </CardContent>
                </Card>
            );
        }

        return (
            <Card className="flex flex-col shadow-sm">
                <CardHeader className="items-center pb-0">
                    <CardTitle>Procurement by Stage</CardTitle>
                    <CardDescription>Distribution of procurements across stages</CardDescription>
                </CardHeader>
                <CardContent className="flex-1 pb-0">
                    <ChartContainer
                        config={stageChartConfig}
                        className="mx-auto aspect-square"
                    >
                        <PieChart>
                            <ChartTooltip
                                content={({ active, payload }) => {
                                    if (active && payload && payload.length) {
                                        const data = payload[0].payload;
                                        const total = Object.values(stageDistribution).reduce((sum, count) => sum + count, 0);
                                        const percentage = ((data.count / total) * 100).toFixed(1);

                                        return (
                                            <div className="border-border/50 bg-background rounded-lg border px-2.5 py-1.5 text-xs shadow-xl">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="flex flex-col">
                                                        <span className="text-[0.70rem] uppercase text-muted-foreground">
                                                            Stage
                                                        </span>
                                                        <span className="font-bold text-muted-foreground">
                                                            {data.stage}
                                                        </span>
                                                    </div>
                                                    <div className="flex flex-col">
                                                        <span className="text-[0.70rem] uppercase text-muted-foreground">
                                                            Count
                                                        </span>
                                                        <span className="font-bold">
                                                            {data.count} ({percentage}%)
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
                                data={Object.entries(stageDistribution).map(([stage, count], index) => ({
                                    stage: stage,
                                    count: count,
                                    fill: `var(--chart-${(index % 5) + 1})`
                                }))}
                                dataKey="count"
                                nameKey="stage"
                            />
                            <ChartLegend
                                content={<ChartLegendContent nameKey="stage" />}
                                className="-translate-y-2 flex flex-wrap justify-center gap-4 [&>*]:flex [&>*]:items-center"
                            />
                        </PieChart>
                    </ChartContainer>
                </CardContent>
                <CardFooter className="flex-col gap-2 text-sm">
                    <div className="flex items-center gap-2 leading-none font-medium">
                        Stage distribution overview
                    </div>
                    <div className="text-muted-foreground leading-none">
                        Showing current distribution across procurement stages
                    </div>
                </CardFooter>
            </Card>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bids and Awards Committee Chairman Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Bids and Awards Committee Chairman Dashboard</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        Overview of procurement activities and committee tasks
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Stats Cards Section */}
                {renderStatsCards()}

                {/* Procurement Distribution Section */}
                {renderProcurementDistribution()}

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column - Recent Activities */}
                    <div className="lg:col-span-2">
                        {/* Recent Activities Section */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                                    <Clock className="h-4 w-4 md:h-5 md:w-5 mr-2 text-primary" />
                                    Recent Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                </CardTitle>
                                <Link href={route('bac-chairman.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {(() => {
                                    const formatRelativeDate = (dateString: string) => {
                                        const date = new Date(dateString);
                                        const now = new Date();
                                        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

                                        if (diffInSeconds < 60) return 'Just now';
                                        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
                                        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
                                        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} day ago`;

                                        // For dates older than a week, show full date with year and time
                                        return date.toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                            hour: 'numeric',
                                            minute: '2-digit'
                                        });
                                    };

                                    if (recentActivities.length === 0) {
                                        return (
                                            <div className="text-center py-8">
                                                <Clock className="mx-auto h-8 w-8 text-muted-foreground opacity-20 mb-2" />
                                                <p>No recent activities found</p>
                                                <p className="text-xs text-muted-foreground mt-2">
                                                    Activities will appear here when procurement actions are taken.<br />
                                                    Try refreshing if you've recently performed actions.
                                                </p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-4"
                                                    onClick={() => window.location.reload()}
                                                >
                                                    Refresh Data
                                                </Button>
                                            </div>
                                        );
                                    }

                                    return (
                                        <div className="space-y-3">
                                            {recentActivities.map((activity, index) => {
                                                const ActionIcon = getActionIcon(activity.action);
                                                return (
                                                    <div key={index} className={`${index < recentActivities.length - 1 ? "border-b pb-3" : ""}`}>
                                                        <div className="flex items-center justify-between">
                                                            <Link
                                                                href={`/bac-secretariat/procurements-list/${activity.id}`}
                                                                className="font-medium text-primary hover:underline text-sm max-w-[70%] truncate"
                                                            >
                                                                {activity.title || `Procurement #${activity.id}`}
                                                            </Link>
                                                            <span className="text-xs text-muted-foreground">
                                                                {formatRelativeDate(activity.date)}
                                                            </span>
                                                        </div>
                                                        <div className="mt-1.5 flex items-center justify-between">
                                                            <div className="flex items-center">
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="text-xs mr-2 flex items-center gap-1"
                                                                >
                                                                    <ActionIcon className="h-3.5 w-3.5" />
                                                                    <span>{activity.action}</span>
                                                                </Badge>
                                                                {activity.stage && (
                                                                    <span className="text-xs text-muted-foreground ml-1">
                                                                        in {activity.stage} stage
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <span className="text-xs text-muted-foreground">by {activity.user}</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - Pie Chart */}
                    <div>
                        {/* Stage Distribution Pie Chart */}
                        {renderStagePieChart()}
                    </div>
                </div>

                {/* Recent Procurements Section */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                            <FileText className="h-4 w-4 md:h-5 md:w-5 mr-2 text-primary" />
                            Recent Procurements
                        </CardTitle>
                        <Link href={route('bac-chairman.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                            View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {recentProcurements.length === 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID</TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center py-8">
                                            No procurement data available
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID</TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentProcurements.map(procurement => (
                                        <TableRow key={procurement.id}>
                                            <TableCell className="font-medium">{procurement.id}</TableCell>
                                            <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                                {procurement.title}
                                            </TableCell>
                                            <TableCell>
                                                <Badge>
                                                    {procurement.stage}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {procurement.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 px-2"
                                                        >
                                                            <Link href={route('bac-chairman.procurements.show', { id: procurement.id })}>
                                                                <EyeIcon className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>View Procurement Details</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
