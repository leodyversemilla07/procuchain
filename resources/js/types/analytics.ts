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

// Analytics Dashboard Props
export interface AnalyticsDashboardProps {
    procurement: ProcurementAnalytics;
    documents: DocumentAnalytics;
    user_activity: UserActivityAnalytics;
    blockchain?: BlockchainAnalytics;
    last_updated: string;
    error?: string;
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
