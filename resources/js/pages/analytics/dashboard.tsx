import { useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, LabelList,
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
    TrendingUp, TrendingDown, Activity, FileText,
    Download, RefreshCw, BarChart3, CheckCircle
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { useAnalyticsExport } from '@/hooks/use-analytics';

export interface AnalyticsDashboardProps {
    procurement: ProcurementAnalytics;
    documents?: DocumentAnalytics; // deferred
    userActivity?: UserActivityAnalytics; // deferred
    blockchain?: BlockchainAnalytics; // deferred
    filters: { time_range: TimeRangeKey; role?: string };
    timeRangeOptions?: Array<{ value: TimeRangeKey; label: string }>; // new camelCase
    time_range_options?: Array<{ value: TimeRangeKey; label: string }>; // legacy snake_case (backward compat)
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Analytics Dashboard',
        href: '/analytics',
    },
];

// Analytics Types for ProcuChain System

export interface AnalyticsTimeRange {
    '7_days': '7 Days';
    '30_days': '30 Days';
    '90_days': '90 Days';
    '1_year': '1 Year';
}

export type TimeRangeKey = keyof AnalyticsTimeRange;

// Procurement Analytics Types
export interface ProcurementOverview {
    total_procurements: number;
    active_procurements: number;
    completed_procurements: number;
    stage_distribution: Record<string, number>;
    status_distribution: Record<string, number>;
    average_processing_time_days: number;
    completion_rate: number;
    total_value_change: number; // Added for dashboard
}

export interface StageAnalytics {
    stage_transitions: Record<string, number>;
    stage_duration: Record<string, number>;
    bottlenecks: string[];
    efficiency_scores: Record<string, number>;
}

export interface PerformanceMetrics {
    average_cycle_time: number;
    efficiency_rating: number;
    cost_per_procurement: number;
    time_savings: number;
    avg_completion_time: number; // Added for dashboard
    success_rate: number; // Added for dashboard
    on_time_rate: number; // Added for dashboard
}

export interface TimelineAnalytics {
    daily_activity: Record<string, number>;
    weekly_trends: Record<string, number>;
    monthly_patterns: Record<string, number>;
    seasonal_analysis: Record<string, number>;
}

export interface ValueDistribution {
    range: string;
    count: number;
    total_value: number;
}

export interface MonthlyTrend {
    month: string;
    count: number;
    total_value: number;
}

export interface ProcurementAnalytics {
    overview: ProcurementOverview;
    stage_analytics: StageAnalytics;
    performance_metrics: PerformanceMetrics;
    timeline_analytics: TimelineAnalytics;
    by_stage: Array<{ name: string; count: number }>; // Added for charts
    value_distribution: ValueDistribution[]; // Added for charts
    monthly_trend: MonthlyTrend[]; // Added for charts
    generated_at: string;
}

// Document Analytics Types
export interface DocumentOverview {
    total_documents: number;
    growth_rate: number;
}

export interface DocumentPerformance {
    avg_review_time: number;
    review_time_trend: number;
}

export interface DocumentViewStatistics {
    total_views: number;
    unique_viewers: number;
    average_view_duration_seconds: number;
    views_by_stage: Record<string, number>;
    views_by_document_type: Record<string, number>;
    engagement_rate: number;
}

export interface DocumentAccessPatterns {
    peak_access_hours: Array<{
        hour: number;
        count: number;
        formatted_hour: string;
    }>;
    access_by_role: Record<string, number>;
    device_breakdown: Record<string, number>;
}

export interface PopularDocument {
    file_key: string;
    document_type: string;
    procurement_title: string;
    view_count: number;
}

export interface DocumentEngagement {
    average_engagement_time: number;
    high_engagement_threshold: number;
    bounce_rate: number;
    return_visitor_rate: number;
}

export interface DocumentAnalytics {
    overview: DocumentOverview; // Added for dashboard
    performance: DocumentPerformance; // Added for dashboard
    view_statistics: DocumentViewStatistics;
    access_patterns: DocumentAccessPatterns;
    popular_documents: PopularDocument[];
    user_engagement: DocumentEngagement;
    by_status: Array<{ status: string; count: number }>; // Added for charts
    generated_at: string;
}

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
    overview: UserActivityOverview; // Added for dashboard
    login_patterns: LoginPatterns;
    role_activity: RoleActivity;
    session_analytics: SessionAnalytics;
    security_metrics: SecurityMetrics;
    daily_activity: Array<{ date: string; active_users: number }>; // Added for charts
    generated_at: string;
}

// Blockchain Analytics Types
export interface BlockchainTransactionVolume {
    total_transactions: number;
    transactions_by_stream: Record<string, number>;
    average_daily_transactions: number;
}

export interface BlockchainIntegrityMetrics {
    integrity_score: number;
    verified_documents: number;
    hash_mismatches: number;
    verification_success_rate: number;
}

export interface StreamAnalytics {
    documents_stream: {
        total_entries: number;
        average_size: number;
        growth_rate: number;
    };
    status_stream: {
        total_entries: number;
        update_frequency: number;
    };
    events_stream: {
        total_entries: number;
        event_types: Record<string, number>;
    };
}

export interface VerificationStatistics {
    total_verifications: number;
    successful_verifications: number;
    failed_verifications: number;
    verification_time_avg: number;
}

export interface BlockchainAnalytics {
    transaction_volume: BlockchainTransactionVolume;
    integrity_metrics: BlockchainIntegrityMetrics;
    stream_analytics: StreamAnalytics;
    verification_statistics: VerificationStatistics;
    generated_at: string;
}

// Combined Analytics Types
export interface ComprehensiveAnalytics {
    metadata: {
        generated_at: string;
        generated_by: string;
        time_range: TimeRangeKey;
        procurement_id?: string;
        format: string;
    };
    procurement_analytics: ProcurementAnalytics;
    document_analytics: DocumentAnalytics;
    user_activity_analytics: UserActivityAnalytics;
    blockchain_analytics: BlockchainAnalytics;
}

// Real-time Analytics Types
export interface RealtimeActivity {
    user: string;
    role: string;
    action: string;
    procurement_id: string;
    timestamp: string;
}

export interface RealtimeData {
    active_users: number;
    recent_activities: RealtimeActivity[];
    current_stage_distribution: Record<string, number>;
    pending_actions: number;
    last_updated: string;
}

// Analytics API Response Types
export interface AnalyticsApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    error?: string;
    message?: string;
}

// Chart Data Types for Visualization
export interface ChartDataPoint {
    label: string;
    value: number;
    color?: string;
    percentage?: number;
}

export interface TimeSeriesDataPoint {
    date: string;
    value: number;
    category?: string;
}

export interface AnalyticsChartData {
    labels: string[];
    datasets: Array<{
        label: string;
        data: number[];
        backgroundColor?: string | string[];
        borderColor?: string;
        borderWidth?: number;
    }>;
}

// Export Options
export interface AnalyticsExportOptions {
    type: 'procurement' | 'document' | 'user_activity' | 'blockchain';
    format: 'json' | 'csv' | 'excel' | 'pdf';
    sections: Array<'procurement' | 'document' | 'user_activity' | 'blockchain'>;
    filters: Partial<AnalyticsFilters>;
}

export interface AnalyticsExportResult {
    success: boolean;
    download_url?: string;
    export_url?: string;
    filename?: string;
    generated_at?: string;
    error?: string;
    message?: string;
}

// Filter Options
export interface AnalyticsFilters {
    time_range: TimeRangeKey;
    procurement_id?: string;
    stage?: string;
    status?: string;
    document_type?: string;
    user_role?: string;
    user_id?: number;
}

// Analytics Component Props
export interface AnalyticsCardProps {
    title: string;
    value: number | string;
    change?: number;
    changeType?: 'increase' | 'decrease' | 'neutral';
    icon?: React.ComponentType;
    loading?: boolean;
}

export interface AnalyticsChartProps {
    title: string;
    data: AnalyticsChartData | ChartDataPoint[] | TimeSeriesDataPoint[];
    type: 'bar' | 'line' | 'pie' | 'doughnut' | 'area';
    height?: number;
    loading?: boolean;
}

export interface AnalyticsTableProps {
    title: string;
    columns: Array<{
        key: string;
        label: string;
        sortable?: boolean;
        render?: (value: unknown, row: Record<string, unknown>) => React.ReactNode;
    }>;
    data: Record<string, unknown>[];
    loading?: boolean;
    pagination?: boolean;
    pageSize?: number;
}

// Dashboard Layout Types
export interface AnalyticsDashboardSection {
    id: string;
    title: string;
    span?: number; // Grid span
    component: React.ComponentType<Record<string, unknown>>;
    props?: Record<string, unknown>;
    visible?: boolean;
    order?: number;
}

export interface AnalyticsDashboardLayout {
    sections: AnalyticsDashboardSection[];
    columns: number;
    gap: number;
}

// Hook Types for Analytics
export interface UseAnalyticsOptions {
    autoRefresh?: boolean;
    refreshInterval?: number; // in milliseconds
    filters?: AnalyticsFilters;
    enabled?: boolean;
}

export interface UseAnalyticsReturn<T> {
    data: T | null;
    loading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    lastUpdated: Date | null;
}

// Error Types
export interface AnalyticsError {
    code: string;
    message: string;
    details?: Record<string, unknown>;
}

export type AnalyticsErrorHandler = (error: AnalyticsError) => void;

export default function AnalyticsDashboard(props: AnalyticsDashboardProps) {
    const {
        procurement,
        documents,
        blockchain,
        filters,
        timeRangeOptions,
        time_range_options,
        error,
    } = props;

    // Backward compatible merged time range options
    const mergedTimeRangeOptions = (timeRangeOptions && timeRangeOptions.length
        ? timeRangeOptions
        : (time_range_options || []));
    const { exportData, loading: exportLoading } = useAnalyticsExport();

    // Use analytics data directly from props
    const procurementAnalytics = procurement;
    const documentAnalytics = documents;

    // Chart configuration for Status Distribution - Dynamic based on actual statuses
    const statusChartConfig: ChartConfig = useMemo(() => {
        const config: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        // If we have status distribution data, create dynamic config
        if (procurementAnalytics?.overview?.status_distribution) {
            Object.keys(procurementAnalytics.overview.status_distribution).forEach((status, index) => {
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
    }, [procurementAnalytics?.overview?.status_distribution]);

    // Chart configuration for Pie Chart - Dynamic based on actual stages
    const stageChartConfig: ChartConfig = useMemo(() => {
        const config: ChartConfig = {
            count: {
                label: "Count",
                color: "var(--chart-1)",
            },
        };

        // If we have stage distribution data, create dynamic config
        if (procurementAnalytics?.overview?.stage_distribution) {
            Object.keys(procurementAnalytics.overview.stage_distribution).forEach((stage, index) => {
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
    }, [procurementAnalytics?.overview?.stage_distribution]);

    // Loading state (false since data comes from props)
    const isLoading = false;

    // Handle filter changes by navigating with new parameters
    const handleFilterChange = (newTimeRange: TimeRangeKey) => {
        router.get(route('analytics.dashboard'), { time_range: newTimeRange }, {
            only: ['procurement'], // partial reload just procurement by default
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Overview metrics
    const overviewMetrics = useMemo(() => {
        if (error || !procurementAnalytics || !documentAnalytics) {
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
    }, [error, procurementAnalytics, documentAnalytics]);

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
                                    {mergedTimeRangeOptions.map((option) => (
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
                            {documentAnalytics && documentAnalytics.view_statistics?.views_by_document_type ? (
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
                            ) : (
                                <div className="p-4 text-sm text-muted-foreground bg-muted/30 rounded border border-dashed">Document analytics not loaded.</div>
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
                            </>
                        )}
                    </div>

                    {/* Documents Section */}
                    <div className="space-y-6">
                        <div className="flex items-center space-x-2 mb-4">
                            <h2 className="text-xl font-semibold">Document Analytics</h2>
                        </div>

                        {documentAnalytics ? (
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
                        ) : (
                            <div className="text-sm text-muted-foreground">No document analytics loaded.</div>
                        )}
                    </div>

                    {/* Blockchain Section */}
                    {blockchain && blockchain.transaction_volume && blockchain.integrity_metrics ? (
                        <div className="space-y-6">
                            <div className="flex items-center space-x-2 mb-4">
                                <h2 className="text-xl font-semibold">Blockchain Analytics</h2>
                            </div>
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <Card>
                                    <CardHeader><CardTitle>Total Transactions</CardTitle></CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{blockchain?.transaction_volume?.total_transactions ?? 0}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader><CardTitle>Integrity Score</CardTitle></CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{blockchain?.integrity_metrics?.integrity_score ?? 'N/A'}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader><CardTitle>Verification Success</CardTitle></CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{(blockchain?.integrity_metrics?.verification_success_rate || 0).toFixed(1)}%</div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    ) : (
                        <div className="text-sm text-muted-foreground p-4">Blockchain analytics not yet loaded.</div>
                    )}

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
