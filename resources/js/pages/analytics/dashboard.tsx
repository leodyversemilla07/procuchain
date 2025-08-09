import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, LabelList, LineChart, Line
} from 'recharts';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    ChartLegend,
    ChartLegendContent,
} from '@/components/ui/chart';
import {
    TrendingUp, TrendingDown, Activity, FileText, Users,
    Download, RefreshCw, BarChart3, CheckCircle
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { useAnalyticsExport } from '@/hooks/use-analytics';
import type {
    ProcurementAnalytics,
    DocumentAnalytics,
    UserActivityAnalytics,
    BlockchainAnalytics,
    TimeRangeKey
} from '@/types/analytics';

interface AnalyticsDashboardProps {
    analytics: {
        procurement: ProcurementAnalytics;
        documents: DocumentAnalytics;
        user_activity: UserActivityAnalytics;
        blockchain: BlockchainAnalytics;
    };
    filters: {
        time_range: TimeRangeKey;
        role?: string;
    };
    time_range_options: Array<{
        value: TimeRangeKey;
        label: string;
    }>;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Analytics Dashboard',
        href: '/analytics',
    },
];

export default function AnalyticsDashboard({
    analytics,
    filters,
    time_range_options,
    error
}: AnalyticsDashboardProps) {
    const { exportData, loading: exportLoading } = useAnalyticsExport();

    // Use analytics data directly from props
    const procurementAnalytics = analytics.procurement;
    const documentAnalytics = analytics.documents;
    const userActivityAnalytics = analytics.user_activity;

    // State for interactive login chart
    const [activeLoginChart, setActiveLoginChart] = useState<"logins" | "success">("logins");

    // Prepare login chart data
    const loginChartData = useMemo(() => {
        if (!userActivityAnalytics?.login_patterns?.daily_login_trend) return [];

        return Object.entries(userActivityAnalytics.login_patterns.daily_login_trend).map(([date, count]) => ({
            date: date,
            logins: count,
            success: Math.floor(count * (userActivityAnalytics.login_patterns.success_rate || 100) / 100),
        }));
    }, [userActivityAnalytics?.login_patterns]);

    // Interactive login chart config
    const interactiveLoginChartConfig = {
        views: {
            label: "Login Activity",
        },
        logins: {
            label: "Total Logins",
            color: "var(--chart-1)",
        },
        success: {
            label: "Successful Logins",
            color: "var(--chart-2)",
        },
    };

    // Calculate totals for login chart
    const loginTotals = useMemo(() => ({
        logins: loginChartData.reduce((acc, curr) => acc + curr.logins, 0),
        success: loginChartData.reduce((acc, curr) => acc + curr.success, 0),
    }), [loginChartData]);

    // Chart configuration for Status Distribution - Dynamic based on actual statuses
    const statusChartConfig: ChartConfig = useMemo(() => {
        const config: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        // If we have status distribution data, create dynamic config
        if (analytics.procurement?.overview?.status_distribution) {
            Object.keys(analytics.procurement.overview.status_distribution).forEach((status, index) => {
                config[status] = {
                    label: status,
                    color: `var(--chart-${(index % 5) + 1})`,
                };
            });
        } else {
            // Fallback static config
            config.status1 = { label: "Status 1", color: "var(--chart-1)" };
            config.status2 = { label: "Status 2", color: "var(--chart-2)" };
            config.status3 = { label: "Status 3", color: "var(--chart-3)" };
            config.status4 = { label: "Status 4", color: "var(--chart-4)" };
            config.status5 = { label: "Status 5", color: "var(--chart-5)" };
        }

        return config;
    }, [analytics.procurement?.overview?.status_distribution]);

    // Chart configuration for Pie Chart - Dynamic based on actual stages
    const stageChartConfig: ChartConfig = useMemo(() => {
        const config: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        // If we have stage distribution data, create dynamic config
        if (analytics.procurement?.overview?.stage_distribution) {
            Object.keys(analytics.procurement.overview.stage_distribution).forEach((stage, index) => {
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
    }, [analytics.procurement?.overview?.stage_distribution]);

    // Loading state (false since data comes from props)
    const isLoading = false;

    // Handle filter changes by navigating with new parameters
    const handleFilterChange = (newTimeRange: TimeRangeKey) => {
        router.get('/analytics', { time_range: newTimeRange }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    // Overview metrics
    const overviewMetrics = useMemo(() => {
        if (error || !procurementAnalytics || !documentAnalytics || !userActivityAnalytics) {
            return [];
        }

        return [
            {
                title: 'Total Procurements',
                value: procurementAnalytics.overview?.total_procurements || 0,
                change: procurementAnalytics.overview?.total_value_change || 0,
                icon: BarChart3,
                color: 'text-blue-600',
            },
            {
                title: 'Total Views',
                value: documentAnalytics.view_statistics?.total_views || 0,
                change: 5.2, // Placeholder growth rate
                icon: FileText,
                color: 'text-green-600',
            },
            {
                title: 'Total Logins',
                value: userActivityAnalytics.login_patterns?.total_logins || 0,
                change: 2.1, // Placeholder growth rate
                icon: Users,
                color: 'text-purple-600',
            },
            {
                title: 'Completion Rate',
                value: `${(procurementAnalytics.overview?.completion_rate || 0).toFixed(1)}%`,
                change: 1.2, // Placeholder improvement
                icon: CheckCircle,
                color: 'text-emerald-600',
            },
            {
                title: 'Avg Processing Time',
                value: `${procurementAnalytics.overview?.average_processing_time_days || 0} days`,
                change: -3.4, // Placeholder improvement
                icon: Activity,
                color: 'text-orange-600',
            },
        ];
    }, [error, procurementAnalytics, documentAnalytics, userActivityAnalytics]);

    // Show error state if there's an error from backend
    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Analytics Dashboard" />
                <div className="space-y-6">
                    <div className="text-center py-12">
                        <div className="text-red-600 text-lg font-medium">
                            {error}
                        </div>
                        <Button
                            variant="outline"
                            onClick={() => window.location.reload()}
                            className="mt-4"
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Try Again
                        </Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const handleExport = async (format: 'csv' | 'pdf') => {
        try {
            const result = await exportData({
                type: 'procurement',
                format,
                sections: ['procurement', 'document', 'user_activity', 'blockchain'],
                filters,
            });

            if (result.success && result.download_url) {
                // Create download link
                const link = document.createElement('a');
                link.href = result.download_url;
                link.download = result.filename || `analytics-export.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        } catch (error) {
            console.error('Export failed:', error);
        }
    };

    const formatValue = (value: number, type: 'currency' | 'number' | 'percentage' = 'number') => {
        switch (type) {
            case 'currency':
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                }).format(value);
            case 'percentage':
                return `${value.toFixed(1)}%`;
            default:
                return new Intl.NumberFormat('en-US').format(value);
        }
    };

    const renderChangeIndicator = (change: number) => {
        const isPositive = change > 0;
        const Icon = isPositive ? TrendingUp : TrendingDown;
        const colorClass = isPositive ? 'text-green-600' : 'text-red-600';

        return (
            <div className={`flex items-center gap-1 text-sm ${colorClass}`}>
                <Icon className="h-4 w-4" />
                {Math.abs(change).toFixed(1)}%
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics Dashboard" />
            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                {/* Page Header */}
                <div className="border-b pb-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-xl md:text-2xl lg:text-3xl font-bold tracking-tight">Analytics Dashboard</h1>
                            <p className="text-muted-foreground mt-1 md:mt-2 text-xs md:text-sm lg:text-base">Monitor procurement performance and system metrics</p>
                        </div>
                        <div className="flex items-center gap-2 md:gap-3">
                            <Select
                                value={filters.time_range}
                                onValueChange={(value) => handleFilterChange(value as TimeRangeKey)}
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {time_range_options.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                variant="outline"
                                onClick={() => window.location.reload()}
                                disabled={isLoading}
                            >
                                <RefreshCw className={`h-4 w-4 mr-2 ${isLoading ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => handleExport('csv')}
                                disabled={exportLoading}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => handleExport('pdf')}
                                disabled={exportLoading}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Export PDF
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Overview Metrics */}
                <div className="grid gap-3 md:gap-4 grid-cols-2 lg:grid-cols-5">
                    {overviewMetrics.map((metric, index) => {
                        const Icon = metric.icon;
                        return (
                            <Card key={index}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-xs md:text-sm font-medium">{metric.title}</CardTitle>
                                    <Icon className={`h-3 w-3 md:h-4 md:w-4 ${metric.color}`} />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-lg md:text-2xl font-bold">
                                        {typeof metric.value === 'number'
                                            ? formatValue(metric.value)
                                            : metric.value
                                        }
                                    </div>
                                    {typeof metric.change === 'number' && renderChangeIndicator(metric.change)}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Main Analytics Content */}
                <div className="pb-4 space-y-8">

                    {/* Overview Section */}
                    <div className="space-y-6">
                        <div className="flex items-center space-x-2 mb-4">
                            <h2 className="text-xl font-semibold">Overview</h2>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Procurement Stages Chart */}
                            {procurementAnalytics && procurementAnalytics.overview?.stage_distribution && (
                                <Card className="flex flex-col">
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
                                                    content={<ChartTooltipContent nameKey="stage" hideLabel />}
                                                />
                                                <Pie
                                                    data={Object.entries(procurementAnalytics.overview.stage_distribution).map(([stage, count], index) => ({
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
                                            Stage distribution overview <TrendingUp className="h-4 w-4" />
                                        </div>
                                        <div className="text-muted-foreground leading-none">
                                            Showing current distribution across procurement stages
                                        </div>
                                    </CardFooter>
                                </Card>
                            )}

                            {/* Document Status Chart */}
                            {documentAnalytics && documentAnalytics.view_statistics?.views_by_document_type && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Document Types</CardTitle>
                                        <CardDescription>Distribution by document type</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={300}>
                                            <BarChart data={Object.entries(documentAnalytics.view_statistics.views_by_document_type).map(([type, count]) => ({
                                                type: type,
                                                count: count
                                            }))}>
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis dataKey="type" />
                                                <YAxis />
                                                <Tooltip />
                                                <Bar dataKey="count" fill="#10b981" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>

                    {/* Procurement Section */}
                    <div className="space-y-6">
                        <div className="flex items-center space-x-2 mb-4">
                            <h2 className="text-xl font-semibold">Procurement Analytics</h2>
                        </div>

                        {procurementAnalytics && (
                            <>
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    {/* Stage Distribution */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Stage Distribution</CardTitle>
                                            <CardDescription>Distribution of procurements across stages</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <ChartContainer config={stageChartConfig}>
                                                <BarChart
                                                    accessibilityLayer
                                                    data={Object.entries(procurementAnalytics.overview.stage_distribution).map(([stage, count]) => ({
                                                        stage: stage,
                                                        count: count
                                                    }))}
                                                    layout="vertical"
                                                    margin={{
                                                        right: 16,
                                                    }}
                                                >
                                                    <CartesianGrid horizontal={false} />
                                                    <YAxis
                                                        dataKey="stage"
                                                        type="category"
                                                        tickLine={false}
                                                        tickMargin={10}
                                                        axisLine={false}
                                                        tickFormatter={(value) => value.slice(0, 20)}
                                                        hide
                                                    />
                                                    <XAxis dataKey="count" type="number" hide />
                                                    <ChartTooltip
                                                        cursor={false}
                                                        content={<ChartTooltipContent indicator="line" />}
                                                    />
                                                    <Bar
                                                        dataKey="count"
                                                        layout="vertical"
                                                        fill="var(--color-count)"
                                                        radius={4}
                                                    >
                                                        <LabelList
                                                            dataKey="stage"
                                                            position="insideLeft"
                                                            offset={8}
                                                            className="fill-background"
                                                            fontSize={12}
                                                        />
                                                        <LabelList
                                                            dataKey="count"
                                                            position="right"
                                                            offset={8}
                                                            className="fill-foreground"
                                                            fontSize={12}
                                                        />
                                                    </Bar>
                                                </BarChart>
                                            </ChartContainer>
                                        </CardContent>
                                        <CardFooter className="flex-col items-start gap-2 text-sm">
                                            <div className="flex gap-2 leading-none font-medium">
                                                Stage distribution trending <TrendingUp className="h-4 w-4" />
                                            </div>
                                            <div className="text-muted-foreground leading-none">
                                                Showing distribution across procurement stages
                                            </div>
                                        </CardFooter>
                                    </Card>

                                    {/* Status Distribution Chart */}
                                    {procurementAnalytics.overview?.status_distribution && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Status Distribution</CardTitle>
                                                <CardDescription>Distribution by procurement status</CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <ChartContainer config={statusChartConfig}>
                                                    <BarChart
                                                        accessibilityLayer
                                                        data={Object.entries(procurementAnalytics.overview.status_distribution).map(([status, count]) => ({
                                                            status: status,
                                                            count: count
                                                        }))}
                                                        layout="vertical"
                                                        margin={{
                                                            right: 16,
                                                        }}
                                                    >
                                                        <CartesianGrid horizontal={false} />
                                                        <YAxis
                                                            dataKey="status"
                                                            type="category"
                                                            tickLine={false}
                                                            tickMargin={10}
                                                            axisLine={false}
                                                            tickFormatter={(value) => value.slice(0, 20)}
                                                            hide
                                                        />
                                                        <XAxis dataKey="count" type="number" hide />
                                                        <ChartTooltip
                                                            cursor={false}
                                                            content={<ChartTooltipContent indicator="line" />}
                                                        />
                                                        <Bar
                                                            dataKey="count"
                                                            layout="vertical"
                                                            fill="var(--color-count)"
                                                            radius={4}
                                                        >
                                                            <LabelList
                                                                dataKey="status"
                                                                position="insideLeft"
                                                                offset={8}
                                                                className="fill-background"
                                                                fontSize={12}
                                                            />
                                                            <LabelList
                                                                dataKey="count"
                                                                position="right"
                                                                offset={8}
                                                                className="fill-foreground"
                                                                fontSize={12}
                                                            />
                                                        </Bar>
                                                    </BarChart>
                                                </ChartContainer>
                                            </CardContent>
                                            <CardFooter className="flex-col items-start gap-2 text-sm">
                                                <div className="flex gap-2 leading-none font-medium">
                                                    Procurement status overview <TrendingUp className="h-4 w-4" />
                                                </div>
                                                <div className="text-muted-foreground leading-none">
                                                    Showing current distribution of procurement statuses
                                                </div>
                                            </CardFooter>
                                        </Card>
                                    )}
                                </div>

                                {/* Login Activity Trend */}
                                {userActivityAnalytics && userActivityAnalytics.login_patterns?.daily_login_trend && (
                                    <Card className="py-4 sm:py-0">
                                        <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                                            <div className="flex flex-1 flex-col justify-center gap-1 px-6 pb-3 sm:pb-0">
                                                <CardTitle>Login Activity Trend</CardTitle>
                                                <CardDescription>
                                                    Daily login activity over selected time period
                                                </CardDescription>
                                            </div>
                                            <div className="flex">
                                                {(["logins", "success"] as const).map((key) => {
                                                    return (
                                                        <button
                                                            key={key}
                                                            data-active={activeLoginChart === key}
                                                            className="data-[active=true]:bg-muted/50 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                                            onClick={() => setActiveLoginChart(key)}
                                                        >
                                                            <span className="text-muted-foreground text-xs">
                                                                {interactiveLoginChartConfig[key].label}
                                                            </span>
                                                            <span className="text-lg leading-none font-bold sm:text-3xl">
                                                                {loginTotals[key].toLocaleString()}
                                                            </span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="px-2 sm:p-6">
                                            <ChartContainer
                                                config={interactiveLoginChartConfig}
                                                className="aspect-auto h-[250px] w-full"
                                            >
                                                <LineChart
                                                    accessibilityLayer
                                                    data={loginChartData}
                                                    margin={{
                                                        left: 12,
                                                        right: 12,
                                                    }}
                                                >
                                                    <CartesianGrid vertical={false} />
                                                    <XAxis
                                                        dataKey="date"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tickMargin={8}
                                                        minTickGap={32}
                                                        tickFormatter={(value) => {
                                                            const date = new Date(value);
                                                            return date.toLocaleDateString("en-US", {
                                                                month: "short",
                                                                day: "numeric",
                                                            });
                                                        }}
                                                    />
                                                    <ChartTooltip
                                                        content={
                                                            <ChartTooltipContent
                                                                className="w-[150px]"
                                                                nameKey="views"
                                                                labelFormatter={(value) => {
                                                                    return new Date(value).toLocaleDateString("en-US", {
                                                                        month: "short",
                                                                        day: "numeric",
                                                                        year: "numeric",
                                                                    });
                                                                }}
                                                            />
                                                        }
                                                    />
                                                    <Line
                                                        dataKey={activeLoginChart}
                                                        type="monotone"
                                                        stroke={`var(--color-${activeLoginChart})`}
                                                        strokeWidth={2}
                                                        dot={false}
                                                    />
                                                </LineChart>
                                            </ChartContainer>
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        )}
                    </div>

                    {/* Documents Section */}
                    <div className="space-y-6">
                        <div className="flex items-center space-x-2 mb-4">
                            <h2 className="text-xl font-semibold">Document Analytics</h2>
                        </div>

                        {documentAnalytics && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Document View Statistics</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span>Total Views</span>
                                            <Badge variant="secondary">
                                                {formatValue(documentAnalytics.view_statistics.total_views)}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Unique Viewers</span>
                                            <Badge variant="secondary">
                                                {formatValue(documentAnalytics.view_statistics.unique_viewers)}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Engagement Rate</span>
                                            <Badge variant="default">
                                                {(documentAnalytics.view_statistics.engagement_rate || 0).toFixed(1)}%
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Popular Documents</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {documentAnalytics.popular_documents.slice(0, 5).map((doc, index) => (
                                                <div key={index} className="flex justify-between items-center p-2 bg-muted rounded">
                                                    <div>
                                                        <div className="font-medium text-sm">{doc.procurement_title}</div>
                                                        <div className="text-xs text-muted-foreground">{doc.document_type}</div>
                                                    </div>
                                                    <Badge variant="outline">{doc.view_count} views</Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </div>

                    {/* User Activity Section */}
                    <div className="space-y-6">
                        <div className="flex items-center space-x-2 mb-4">
                            <h2 className="text-xl font-semibold">User Activity Analytics</h2>
                        </div>

                        {userActivityAnalytics && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Login Statistics</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span>Total Logins</span>
                                            <Badge variant="secondary">
                                                {formatValue(userActivityAnalytics.login_patterns.total_logins)}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Success Rate</span>
                                            <Badge variant="default">
                                                {(userActivityAnalytics.login_patterns.success_rate || 0).toFixed(1)}%
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Failed Logins</span>
                                            <Badge variant="destructive">
                                                {formatValue(userActivityAnalytics.login_patterns.failed_logins)}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Security Metrics</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span>Security Score</span>
                                            <Badge variant="default">
                                                {(userActivityAnalytics.security_metrics?.security_score || 0).toFixed(1)}/100
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Failed Login Rate</span>
                                            <Badge variant="secondary">
                                                {(userActivityAnalytics.security_metrics?.failed_login_rate || 0).toFixed(1)}%
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </div>

                    {/* Loading State */}
                    {isLoading && (
                        <div className="flex items-center justify-center py-12">
                            <RefreshCw className="h-8 w-8 animate-spin text-muted-foreground" />
                            <span className="ml-2 text-muted-foreground">Loading analytics data...</span>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
