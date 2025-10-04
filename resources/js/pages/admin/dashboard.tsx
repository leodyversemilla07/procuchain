import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Stage, Status } from '@/types/blockchain';
import { PageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText, Shield, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { CartesianGrid, Line, LineChart, XAxis } from 'recharts';

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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: route('admin.dashboard'),
    },
];

export default function AdminDashboard() {
    const { analytics, recentProcurements = [], procurementDistribution = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

    const userActivityAnalytics = analytics?.user_activity;

    const buildErrorState = (title: string) => {
        if (!error) {
            return undefined;
        }

        return {
            title,
            description: error,
            tone: 'destructive' as const,
            retryLabel: 'Retry',
            onRetry: () => router.reload(),
        };
    };

    const stageDistribution = useMemo(() => {
        return procurementDistribution.reduce<Record<string, number>>((distribution, procurement) => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;

            return distribution;
        }, {});
    }, [procurementDistribution]);

    const statsItems = useMemo(
        () => [
            {
                id: 'ongoing-projects',
                label: 'Ongoing Projects',
                value: stats?.ongoingProjects || 0,
                icon: FileText,
                iconClassName: 'text-primary bg-primary/10',
            },
            {
                id: 'completed-biddings',
                label: 'Completed Biddings',
                value: stats?.completedBiddings || 0,
                icon: CheckCircle,
                iconClassName: 'text-primary bg-primary/10',
            },
            {
                id: 'total-documents',
                label: 'Total Documents',
                value: stats?.totalDocuments || 0,
                icon: FileIcon,
                iconClassName: 'text-muted-foreground bg-muted',
            },
            {
                id: 'total-logins',
                label: 'Total Logins',
                value: userActivityAnalytics.login_patterns?.total_logins || 0,
                icon: Users,
                iconClassName: 'text-primary bg-primary/10',
            },
        ],
        [stats?.ongoingProjects, stats?.completedBiddings, stats?.totalDocuments, userActivityAnalytics?.login_patterns?.total_logins],
    );

    const recentActivityItems = useMemo(
        () =>
            recentActivities.map((activity) => ({
                id: activity.id,
                title: activity.title,
                action: activity.action,
                date: activity.date,
                user: activity.user,
                stage: activity.stage,
            })),
        [recentActivities],
    );

    const recentProcurementItems = useMemo(
        () =>
            recentProcurements.map((procurement) => ({
                id: procurement.id,
                title: procurement.title,
                stage: procurement.stage,
                status: procurement.status,
            })),
        [recentProcurements],
    );

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <HeroCard icon={Shield} title="Admin Dashboard" description="System-wide overview and administrative controls" />

                <StatsGrid items={statsItems} />

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                    <ProcurementDistributionCard
                        className="xl:col-span-3"
                        data={procurementDistribution}
                        title="Procurement Distribution"
                        description="Distribution of procurements across stages and statuses"
                        errorState={buildErrorState('Unable to load procurement distribution')}
                    />
                    <StageDistributionCard
                        className="xl:col-span-2"
                        stageDistribution={stageDistribution}
                        errorState={buildErrorState('Unable to load stage distribution')}
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <RecentActivitiesList
                        className="lg:col-span-1"
                        title="System Activities"
                        icon={Clock}
                        activities={recentActivityItems}
                        getActivityHref={(activity) => `/bac-secretariat/procurements-list/${activity.id}`}
                        viewAllHref={recentActivityItems.length > 0 ? route('admin.procurements-list.index') : undefined}
                        errorState={buildErrorState('Unable to load system activities')}
                    />

                    <RecentProcurementsTable
                        className="lg:col-span-2"
                        procurements={recentProcurementItems}
                        getViewProcurementHref={(procurement) => route('admin.procurements.show', { id: procurement.id })}
                        viewAllHref={recentProcurementItems.length > 0 ? route('admin.procurements-list.index') : undefined}
                        errorState={buildErrorState('Unable to load recent procurements')}
                    />
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
