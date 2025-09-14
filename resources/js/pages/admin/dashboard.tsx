import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState, useMemo } from 'react';
import { toast } from "sonner";
import { ArrowRight, Clock, FileText, Shield, Users, Bell, CheckCircle, FileIcon } from "lucide-react";
import { RecentActivities } from '@/components/dashboard/recent-activities';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { type BreadcrumbItem, type User } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Stage, Status } from '@/types/blockchain';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    LineChart, Line, XAxis, CartesianGrid,
} from 'recharts';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';


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

export interface DashboardProps extends PageProps, AnalyticsProps {
    recentProcurements: RecentProcurement[];
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

export default function Dashboard() {
    const { analytics, recentProcurements = [], recentActivities = [], stats, error, auth } = usePage<DashboardProps>().props;

    const userActivityAnalytics = analytics?.user_activity;

    useEffect(() => {
        if (error) {
            toast.error("Error loading dashboard", {
                description: error,
                duration: 5000,
            });
        }
    }, [error]);

    // StatsCards component logic (inline)
    const userRole = (auth as unknown as { user: User })?.user?.role;

    // Define all possible cards for stats
    const allStatsCards = [
        {
            label: "Ongoing Projects",
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: "text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
        },
        {
            label: "Pending Actions",
            value: stats?.pendingActions || 0,
            icon: Bell,
            colors: "text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20",
            roles: ['bac_secretariat'] // Only show for bac_secretariat
        },
        {
            label: "Completed Biddings",
            value: stats?.completedBiddings || 0,
            icon: CheckCircle,
            colors: "text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20"
        },
        {
            label: "Total Documents",
            value: stats?.totalDocuments || 0,
            icon: FileIcon,
            colors: "text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800"
        },
        {
            label: 'Total Logins',
            value: userActivityAnalytics.login_patterns?.total_logins || 0,
            icon: Users,
            colors: 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20',
        }
    ];

    // Filter cards based on the current user's role
    const statsCardsToShow = allStatsCards.filter(card => !card.roles || card.roles.includes(userRole));

    // Determine grid columns based on the number of cards to show
    const statsGridColsClass = statsCardsToShow.length === 4 ? "md:grid-cols-4" : "md:grid-cols-3";



    // State for interactive login chart
    const [activeLoginChart, setActiveLoginChart] = useState<"logins" | "success">("logins");

    // Prepare login chart data
    const loginChartData = useMemo(() => {
        if (!userActivityAnalytics?.login_patterns?.daily_login_trend) return [];

        return Object.entries(userActivityAnalytics.login_patterns.daily_login_trend).map(([date, count]) => ({
            date: date,
            logins: count as number,
            success: Math.floor((count as number) * (userActivityAnalytics.login_patterns.success_rate || 100) / 100),
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
        logins: loginChartData.reduce((acc, curr) => acc + (curr.logins as number), 0),
        success: loginChartData.reduce((acc, curr) => acc + (curr.success as number), 0),
    }), [loginChartData]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Admin Dashboard</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        System-wide overview and administrative controls
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className={`grid grid-cols-1 ${statsGridColsClass} gap-4`}>
                    {statsCardsToShow.map(({ label, value, icon: Icon, colors }) => (
                        <Card key={label} className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{value}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">{label}</p>
                                    </div>
                                    <div className={`p-2 rounded-full ${colors}`}>
                                        <Icon className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-1 space-y-6">
                        <div className="flex flex-row items-center justify-between space-y-0 pb-4">
                            <h3 className="text-base md:text-lg font-semibold flex items-center">
                                <Clock className="h-4 w-4 md:h-5 md:w-5 mr-2 text-purple-500" />
                                System Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                            </h3>
                            <Link href={route('admin.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                            </Link>
                        </div>

                        <RecentActivities activities={recentActivities} />

                    </div>

                    <div className="lg:col-span-2 space-y-6">
                        <div className="flex flex-row items-center justify-between space-y-0 pb-4">
                            <h3 className="text-base md:text-lg font-semibold flex items-center">
                                <FileText className="h-4 w-4 md:h-5 md:w-5 mr-2 text-blue-500" />
                                Recent Procurements {recentProcurements.length > 0 && `(${recentProcurements.length})`}
                            </h3>
                            <Link href={route('admin.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                            </Link>
                        </div>

                        <RecentProcurementsTable procurements={recentProcurements} />
                    </div>
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
            </div>
        </AppLayout>
    );
}
