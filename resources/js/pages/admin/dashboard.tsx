import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import AppLayout from '@/layouts/app-layout';
import { Stage, Status, type BreadcrumbItem, type SharedData } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText, Shield, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Spinner } from '@/components/ui/spinner';
import { index as procurementsListIndex, show as procurementsShow } from '@/routes/admin/procurements';
import { UserRole } from '@/types/enums';
import { getDashboardBreadcrumb } from '@/utils/breadcrumbs';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

/**
 * Format stage name from snake_case to Title Case
 */
const formatStageName = (stage: string): string => {
    if (!stage) return stage;

    return stage
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

/**
 * Format user name, handling unknown users
 */
const formatUserName = (user: string): string => {
    if (!user || user === 'Unknown' || user === 'System' || user.trim() === '') {
        return 'System Process';
    }
    return user;
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
    two_factor_adoption_rate: number;
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
    analytics?: {
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

const breadcrumbs: BreadcrumbItem[] = [getDashboardBreadcrumb(UserRole.ADMIN)];

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
                value: userActivityAnalytics?.login_patterns?.total_logins || 0,
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
                user: formatUserName(activity.user),
                stage: activity.stage ? formatStageName(activity.stage) : undefined,
            })),
        [recentActivities],
    );

    const recentProcurementItems = useMemo(() => {
        if (!Array.isArray(recentProcurements)) {
            return [];
        }

        const seen = new Set<string>();
        return recentProcurements
            .filter((procurement) => {
                if (!procurement || !procurement.id) {
                    return false;
                }
                if (seen.has(procurement.id)) {
                    return false;
                }
                seen.add(procurement.id);
                return true;
            })
            .map((procurement) => ({
                id: procurement.id!,
                title: procurement.title,
                stage: procurement.stage,
                status: procurement.status,
            }))
            .filter(Boolean); // Remove any falsy values from the final array
    }, [recentProcurements]);

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

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard icon={Shield} title="Admin Dashboard" description="System-wide overview and administrative controls" />

                <StatsGrid items={statsItems} />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-5">
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm lg:col-span-3">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <ProcurementDistributionCard
                            className="lg:col-span-3"
                            data={procurementDistribution}
                            title="Procurement Distribution"
                            description="Distribution of procurements across stages and statuses"
                            errorState={buildErrorState('Unable to load procurement distribution')}
                        />
                    </Deferred>
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm lg:col-span-2">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <StageDistributionCard
                            className="lg:col-span-2"
                            stageDistribution={stageDistribution}
                            errorState={buildErrorState('Unable to load stage distribution')}
                        />
                    </Deferred>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
                    <Deferred
                        data="recentActivities"
                        fallback={
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <RecentActivitiesList
                            title="System Activities"
                            icon={Clock}
                            activities={recentActivityItems}
                            getActivityHref={(activity) => `/bac-secretariat/procurements-list/${activity.id}`}
                            viewAllHref={recentActivityItems.length > 0 ? procurementsListIndex.url() : undefined}
                            errorState={buildErrorState('Unable to load system activities')}
                        />
                    </Deferred>

                    <Deferred
                        data="recentProcurements"
                        fallback={
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <RecentProcurementsTable
                            procurements={recentProcurementItems}
                            getViewProcurementHref={(procurement) => {
                                if (!procurement) {
                                    console.error('Undefined procurement in getViewProcurementHref');
                                    return '#';
                                }
                                if (!procurement.id) {
                                    console.error('Procurement without ID:', procurement);
                                    return '#';
                                }
                                return procurementsShow.url({ pr_number: procurement.id });
                            }}
                            viewAllHref={recentProcurementItems.length > 0 ? procurementsListIndex.url() : undefined}
                            errorState={buildErrorState('Unable to load recent procurements')}
                        />
                    </Deferred>
                </div>

                {/* Analytics Section - Deferred Loading */}
                <Deferred
                    data="analytics"
                    fallback={
                        <div className="flex items-center justify-center p-8">
                            <Spinner className="h-8 w-8" />
                        </div>
                    }
                >
                    {/* Login Activity Trend */}
                    {userActivityAnalytics && userActivityAnalytics.login_patterns?.daily_login_trend && (
                        <Card className="py-3 sm:py-4 md:py-0">
                            <CardHeader className="flex flex-col items-stretch border-b p-0! sm:flex-row">
                                <div className="flex flex-1 flex-col justify-center gap-1 px-3 pb-2 sm:px-4 sm:pb-3 md:px-6 md:pb-0">
                                    <CardTitle className="text-base sm:text-lg md:text-xl">Login Activity Trend</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">Daily login activity over selected time period</CardDescription>
                                </div>
                                <div className="flex">
                                    {(['logins', 'success'] as const).map((key) => {
                                        return (
                                            <button
                                                key={key}
                                                data-active={activeLoginChart === key}
                                                className="data-[active=true]:bg-muted/50 flex flex-1 flex-col justify-center gap-0.5 border-t px-3 py-2 text-left even:border-l sm:gap-1 sm:border-t-0 sm:border-l sm:px-4 sm:py-3 md:px-6 md:py-4 lg:px-8 lg:py-6"
                                                onClick={() => setActiveLoginChart(key)}
                                            >
                                                <span className="text-muted-foreground text-[10px] sm:text-xs">
                                                    {interactiveLoginChartConfig[key].label}
                                                </span>
                                                <span className="text-sm leading-none font-bold sm:text-base md:text-lg lg:text-2xl xl:text-3xl">
                                                    {loginTotals[key].toLocaleString()}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </CardHeader>
                            <CardContent className="px-2 py-3 sm:p-4 md:p-6">
                                <ChartContainer
                                    config={interactiveLoginChartConfig}
                                    className="aspect-auto h-[180px] w-full sm:h-[200px] md:h-[250px]"
                                >
                                    <LineChart
                                        accessibilityLayer
                                        data={loginChartData}
                                        margin={{
                                            left: 12,
                                            right: 12,
                                            top: 8,
                                            bottom: 8,
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
                    <div className="space-y-4 sm:space-y-6">
                        <div className="mb-2 flex items-center space-x-2 sm:mb-4">
                            <h2 className="text-base font-semibold sm:text-lg md:text-xl">User Activity Analytics</h2>
                        </div>

                        {userActivityAnalytics && (
                            <div className="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                                <Card>
                                    <CardHeader className="pb-2 sm:pb-3 md:pb-4">
                                        <CardTitle className="text-sm sm:text-base md:text-lg">Login Statistics</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2 sm:space-y-3 md:space-y-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs sm:text-sm md:text-base">Total Logins</span>
                                            <Badge variant="secondary" className="text-[10px] sm:text-xs md:text-sm">
                                                {formatValue(userActivityAnalytics.login_patterns?.total_logins || 0)}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs sm:text-sm md:text-base">Success Rate</span>
                                            <Badge variant="default" className="text-[10px] sm:text-xs md:text-sm">
                                                {(userActivityAnalytics.login_patterns?.success_rate || 0).toFixed(1)}%
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs sm:text-sm md:text-base">Failed Logins</span>
                                            <Badge variant="destructive" className="text-[10px] sm:text-xs md:text-sm">
                                                {formatValue(userActivityAnalytics.login_patterns?.failed_logins || 0)}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="pb-2 sm:pb-3 md:pb-4">
                                        <CardTitle className="text-sm sm:text-base md:text-lg">Security Metrics</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2 sm:space-y-3 md:space-y-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs sm:text-sm md:text-base">Security Score</span>
                                            <Badge variant="default" className="text-[10px] sm:text-xs md:text-sm">
                                                {(userActivityAnalytics.security_metrics?.security_score || 0).toFixed(1)}/100
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs sm:text-sm md:text-base">Failed Login Rate</span>
                                            <Badge variant="secondary" className="text-[10px] sm:text-xs md:text-sm">
                                                {(userActivityAnalytics.security_metrics?.failed_login_rate || 0).toFixed(1)}%
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="lg:col-span-1">
                                    <CardHeader className="pb-2 sm:pb-3 md:pb-4">
                                        <CardTitle className="text-sm sm:text-base md:text-lg">Role Activity</CardTitle>
                                        <CardDescription className="text-[10px] sm:text-xs md:text-sm">Login activity by user role</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ChartContainer
                                            config={{
                                                logins: {
                                                    label: 'Logins',
                                                    color: 'var(--chart-1)',
                                                },
                                            }}
                                            className="h-[150px] w-full sm:h-[180px] md:h-[200px]"
                                        >
                                            <BarChart
                                                accessibilityLayer
                                                data={Object.entries(userActivityAnalytics.role_activity || {}).map(([role, count]) => ({
                                                    role: role.charAt(0).toUpperCase() + role.slice(1).replace('_', ' '),
                                                    logins: count,
                                                }))}
                                                margin={{
                                                    left: 12,
                                                    right: 12,
                                                    top: 8,
                                                    bottom: 8,
                                                }}
                                            >
                                                <CartesianGrid vertical={false} />
                                                <XAxis
                                                    dataKey="role"
                                                    tickLine={false}
                                                    axisLine={false}
                                                    tickMargin={8}
                                                    minTickGap={20}
                                                    tickFormatter={(value) => (value.length > 8 ? value.substring(0, 8) + '...' : value)}
                                                    fontSize={11}
                                                />
                                                <YAxis hide />
                                                <ChartTooltip
                                                    content={
                                                        <ChartTooltipContent
                                                            className="w-[150px]"
                                                            nameKey="logins"
                                                            labelFormatter={(value) => `${value}`}
                                                        />
                                                    }
                                                />
                                                <Bar dataKey="logins" fill="var(--color-logins)" radius={[4, 4, 0, 0]} />
                                            </BarChart>
                                        </ChartContainer>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Daily Activity Chart */}
                        {userActivityAnalytics?.daily_activity && userActivityAnalytics.daily_activity.length > 0 && (
                            <Card>
                                <CardHeader className="pb-2 sm:pb-3 md:pb-4">
                                    <CardTitle className="text-base sm:text-lg md:text-xl">Daily Active Users</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">User activity over the selected time period</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ChartContainer
                                        config={{
                                            active_users: {
                                                label: 'Active Users',
                                                color: 'var(--chart-2)',
                                            },
                                        }}
                                        className="h-[180px] w-full sm:h-[200px] md:h-[250px]"
                                    >
                                        <AreaChart
                                            accessibilityLayer
                                            data={userActivityAnalytics.daily_activity}
                                            margin={{
                                                left: 12,
                                                right: 12,
                                                top: 8,
                                                bottom: 8,
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
                                                fontSize={12}
                                            />
                                            <ChartTooltip
                                                content={
                                                    <ChartTooltipContent
                                                        className="w-[150px]"
                                                        nameKey="active_users"
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
                                            <Area
                                                dataKey="active_users"
                                                type="monotone"
                                                fill="var(--color-active_users)"
                                                fillOpacity={0.4}
                                                stroke="var(--color-active_users)"
                                                strokeWidth={2}
                                            />
                                        </AreaChart>
                                    </ChartContainer>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </Deferred>
            </div>
        </AppLayout>
    );
}
