import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Stage, Status } from '@/types/blockchain';
import { PageProps } from '@inertiajs/core';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ActivityIcon,
    ArrowRight,
    CheckCircle,
    CheckIcon,
    Clock,
    ExternalLinkIcon,
    EyeIcon,
    FileIcon,
    FileText,
    FileTextIcon,
    FileUpIcon,
    PlusIcon,
    Shield,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

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
    const IconComponent = Object.entries(ACTION_ICON_MAP).find(([key]) => action.toLowerCase().includes(key))?.[1] || ActivityIcon;

    return IconComponent;
};

export type TimeRangeKey = '7_days' | '30_days' | '90_days' | '1_year';

// User Activity Analytics Types
export interface UserActivityOverview {
    total_active_users: number;
    growth_rate: number;
}

export interface LoginPatterns {
    total_logins: number;
    successful_logins: number;
    failed_logins: number;
    success_rate: number;
    peak_hours: Array<{
        hour: number;
        count: number;
        formatted_hour: string;
    }>;
    daily_login_trend: Record<string, number>;
}

export interface RoleActivity {
    [role: string]: number;
}

export interface SessionAnalytics {
    average_session_duration: number;
    total_sessions: number;
    active_sessions: number;
    session_breakdown_by_hour: Record<string, number>;
}

export interface SecurityMetrics {
    security_score: number;
    failed_login_rate: number;
    suspicious_ip_count: number;
    mfa_adoption_rate: number;
}

export interface UserActivityAnalytics {
    overview: UserActivityOverview;
    login_patterns: LoginPatterns;
    role_activity: RoleActivity;
    session_analytics: SessionAnalytics;
    security_metrics: SecurityMetrics;
    daily_activity: Array<{ date: string; active_users: number }>;
    generated_at: string;
}

// Analytics Props Interface
export interface AnalyticsProps {
    analytics: {
        user_activity: UserActivityAnalytics;
    };
}

// Dashboard Props Interface
export interface DashboardStats {
    ongoingProjects: number;
    pendingActions: number;
    completedBiddings: number;
    totalDocuments: number;
}

export interface RecentActivity {
    id: string;
    title: string;
    action: string;
    date: string;
    user: string;
    stage?: string;
}

export interface RecentProcurement {
    id: string;
    title: string;
    stage: Stage;
    status: Status;
}

export interface DashboardProps extends PageProps, AnalyticsProps, SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    error?: string;
}

// Utility functions
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
        minute: '2-digit',
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: route('admin.dashboard'),
    },
];

export default function AdminDashboard() {
    const { analytics, recentProcurements = [], procurementDistribution = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

    const userActivityAnalytics = analytics?.user_activity;

    useEffect(() => {
        if (error) {
            toast.error('Error loading dashboard', {
                description: error,
                duration: 5000,
            });
        }
    }, [error]);

    // State for procurement distribution chart
    const [activeChart, setActiveChart] = useState<'stage' | 'status'>('stage');

    // Calculate distribution from procurementDistribution data (separate from recent procurements)
    const stageDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach((procurement) => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    const statusDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach((procurement) => {
            const status = procurement.status;
            distribution[status] = (distribution[status] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    // Define all possible cards for stats
    const allStatsCards = [
        {
            label: 'Ongoing Projects',
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: 'text-primary bg-primary/10',
        },
        {
            label: 'Completed Biddings',
            value: stats?.completedBiddings || 0,
            icon: CheckCircle,
            colors: 'text-primary bg-primary/10',
        },
        {
            label: 'Total Documents',
            value: stats?.totalDocuments || 0,
            icon: FileIcon,
            colors: 'text-muted-foreground bg-muted',
        },
        {
            label: 'Total Logins',
            value: userActivityAnalytics.login_patterns?.total_logins || 0,
            icon: Users,
            colors: 'text-primary bg-primary/10',
        },
    ];

    // Determine grid columns based on the number of cards to show
    const statsGridColsClass = allStatsCards.length === 4 ? 'md:grid-cols-4' : 'md:grid-cols-3';

    // State for interactive login chart
    const [activeLoginChart, setActiveLoginChart] = useState<'logins' | 'success'>('logins');

    // Prepare login chart data
    const loginChartData = useMemo(() => {
        if (!userActivityAnalytics?.login_patterns?.daily_login_trend) return [];

        return Object.entries(userActivityAnalytics.login_patterns.daily_login_trend).map(([date, count]) => ({
            date: date,
            logins: count as number,
            success: Math.floor(((count as number) * (userActivityAnalytics.login_patterns.success_rate || 100)) / 100),
        }));
    }, [userActivityAnalytics?.login_patterns]);

    // Interactive login chart config
    const interactiveLoginChartConfig = {
        views: {
            label: 'Login Activity',
        },
        logins: {
            label: 'Total Logins',
            color: 'var(--chart-1)',
        },
        success: {
            label: 'Successful Logins',
            color: 'var(--chart-2)',
        },
    };

    // Calculate totals for login chart
    const loginTotals = useMemo(
        () => ({
            logins: loginChartData.reduce((acc, curr) => acc + (curr.logins as number), 0),
            success: loginChartData.reduce((acc, curr) => acc + (curr.success as number), 0),
        }),
        [loginChartData],
    );

    const renderProcurementDistribution = () => {
        // Chart configuration
        const chartConfig: ChartConfig = {
            count: {
                label: 'Count',
                color: 'var(--chart-1)',
            },
        };

        const data = activeChart === 'stage' ? stageDistribution : statusDistribution;

        if (procurementDistribution.length === 0) {
            return (
                <Card className="shadow-sm">
                    <CardContent className="p-6 text-center">
                        <FileText className="text-muted-foreground mx-auto mb-2 h-8 w-8 opacity-20" />
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
                        <CardDescription>Distribution of procurements across stages and statuses</CardDescription>
                    </div>
                    <div className="flex">
                        {['stage', 'status'].map((key) => {
                            const chart = key as 'stage' | 'status';
                            const chartData = chart === 'stage' ? stageDistribution : statusDistribution;
                            const chartTotal = Object.values(chartData as Record<string, number>).reduce(
                                (sum: number, count: number) => sum + count,
                                0,
                            );

                            return (
                                <button
                                    key={chart}
                                    data-active={activeChart === chart}
                                    className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                    onClick={() => setActiveChart(chart)}
                                >
                                    <span className="text-muted-foreground text-xs capitalize">{chart} Distribution</span>
                                    <span className="text-lg leading-none font-bold sm:text-3xl">{chartTotal.toLocaleString()}</span>
                                </button>
                            );
                        })}
                    </div>
                </CardHeader>
                <CardContent className="px-2 sm:p-6">
                    <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
                        <BarChart
                            accessibilityLayer
                            data={Object.entries(data).map(([key, count]) => ({
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
                                        labelFormatter={(value) => `${activeChart.charAt(0).toUpperCase() + activeChart.slice(1)}: ${value}`}
                                    />
                                }
                            />
                            <Bar dataKey="count" fill={`var(--color-count)`} radius={4} />
                        </BarChart>
                    </ChartContainer>
                </CardContent>
            </Card>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="bg-primary/10 rounded-lg p-2">
                                    <Shield className="text-primary h-6 w-6" />
                                </div>
                                <div>
                                    <h1 className="text-foreground text-2xl font-bold">Admin Dashboard</h1>
                                    <p className="text-muted-foreground mt-1 text-sm">System-wide overview and administrative controls</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className={`grid grid-cols-1 sm:grid-cols-2 ${statsGridColsClass} gap-4`}>
                    {allStatsCards.map((card, index) => {
                        const IconComponent = card.icon;
                        return (
                            <Card key={index} className="shadow-sm">
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-sm font-medium">{card.label}</p>
                                            <p className="text-2xl font-bold">{card.value}</p>
                                        </div>
                                        <div className={`rounded-full p-2 ${card.colors}`}>
                                            <IconComponent className="h-5 w-5" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Procurement Distribution Section */}
                {renderProcurementDistribution()}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-1">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <div className="flex flex-row items-center justify-between">
                                    <h3 className="flex items-center text-base font-semibold md:text-lg">
                                        <Clock className="text-primary mr-2 h-4 w-4 md:h-5 md:w-5" />
                                        System Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                    </h3>
                                    <Link
                                        href={route('admin.procurements-list.index')}
                                        className="text-primary ml-2 flex shrink-0 items-center text-xs hover:underline md:text-sm"
                                    >
                                        View all <ArrowRight className="ml-1 h-3 w-3 md:h-4 md:w-4" />
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {recentActivities.map((activity, index) => {
                                        const ActionIcon = getActionIcon(activity.action);
                                        return (
                                            <div key={index} className={`${index < recentActivities.length - 1 ? 'border-b pb-3' : ''}`}>
                                                <div className="flex items-center justify-between">
                                                    <Link
                                                        href={`/bac-secretariat/procurements-list/${activity.id}`}
                                                        className="text-primary max-w-[70%] truncate text-sm font-medium hover:underline"
                                                    >
                                                        {activity.title || `Procurement #${activity.id}`}
                                                    </Link>
                                                    <span className="text-muted-foreground text-xs">{formatRelativeDate(activity.date)}</span>
                                                </div>
                                                <div className="mt-1.5 flex items-center justify-between">
                                                    <div className="flex items-center">
                                                        <Badge variant="secondary" className="mr-2 flex items-center gap-1 text-xs">
                                                            <ActionIcon className="h-3.5 w-3.5" />
                                                            <span>{activity.action}</span>
                                                        </Badge>
                                                        {activity.stage && (
                                                            <span className="text-muted-foreground ml-1 text-xs">in {activity.stage} stage</span>
                                                        )}
                                                    </div>
                                                    <span className="text-muted-foreground text-xs">by {activity.user}</span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex flex-row items-center justify-between">
                                    <h3 className="flex items-center text-base font-semibold md:text-lg">
                                        <FileText className="text-primary mr-2 h-4 w-4 md:h-5 md:w-5" />
                                        Recent Procurements {recentProcurements.length > 0 && `(${recentProcurements.length})`}
                                    </h3>
                                    <Link
                                        href={route('admin.procurements-list.index')}
                                        className="text-primary ml-2 flex shrink-0 items-center text-xs hover:underline md:text-sm"
                                    >
                                        View all <ArrowRight className="ml-1 h-3 w-3 md:h-4 md:w-4" />
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
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
                                        {recentProcurements.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="py-8 text-center">
                                                    No procurement data available
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            recentProcurements.map((procurement) => (
                                                <TableRow key={procurement.id}>
                                                    <TableCell className="font-medium">{procurement.id}</TableCell>
                                                    <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                                        {procurement.title}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge>{procurement.stage}</Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{procurement.status}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" asChild className="h-8 px-2">
                                                                    <Link href={route('admin.procurements.show', { id: procurement.id })}>
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
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Login Activity Trend */}
                {userActivityAnalytics && userActivityAnalytics.login_patterns?.daily_login_trend && (
                    <Card className="py-4 sm:py-0">
                        <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                            <div className="flex flex-1 flex-col justify-center gap-1 px-6 pb-3 sm:pb-0">
                                <CardTitle>Login Activity Trend</CardTitle>
                                <CardDescription>Daily login activity over selected time period</CardDescription>
                            </div>
                            <div className="flex">
                                {(['logins', 'success'] as const).map((key) => {
                                    return (
                                        <button
                                            key={key}
                                            data-active={activeLoginChart === key}
                                            className="data-[active=true]:bg-muted/50 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                            onClick={() => setActiveLoginChart(key)}
                                        >
                                            <span className="text-muted-foreground text-xs">{interactiveLoginChartConfig[key].label}</span>
                                            <span className="text-lg leading-none font-bold sm:text-3xl">{loginTotals[key].toLocaleString()}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </CardHeader>
                        <CardContent className="px-2 sm:p-6">
                            <ChartContainer config={interactiveLoginChartConfig} className="aspect-auto h-[250px] w-full">
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
                                            return date.toLocaleDateString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                            });
                                        }}
                                    />
                                    <ChartTooltip
                                        content={
                                            <ChartTooltipContent
                                                className="w-[150px]"
                                                nameKey="views"
                                                labelFormatter={(value) => {
                                                    return new Date(value).toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                        year: 'numeric',
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

                {/* User Activity Section */}
                <div className="space-y-6">
                    <div className="mb-4 flex items-center space-x-2">
                        <h2 className="text-xl font-semibold">User Activity Analytics</h2>
                    </div>

                    {userActivityAnalytics && (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Login Statistics</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span>Total Logins</span>
                                        <Badge variant="secondary">{formatValue(userActivityAnalytics.login_patterns.total_logins)}</Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Success Rate</span>
                                        <Badge variant="default">{(userActivityAnalytics.login_patterns.success_rate || 0).toFixed(1)}%</Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Failed Logins</span>
                                        <Badge variant="destructive">{formatValue(userActivityAnalytics.login_patterns.failed_logins)}</Badge>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Security Metrics</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span>Security Score</span>
                                        <Badge variant="default">
                                            {(userActivityAnalytics.security_metrics?.security_score || 0).toFixed(1)}/100
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
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
            </div>
        </AppLayout>
    );
}
