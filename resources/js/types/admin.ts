/**
 * Admin & User Management Types
 * Contains interfaces for admin features, user management, login logs, and blockchain explorer
 */

import type { User } from './auth';

// ============================================================================
// LOGIN LOGS & AUTHENTICATION
// ============================================================================

export interface LoginLog {
    id: number;
    user_id?: number;
    user?: {
        id: number;
        name: string;
        email: string;
        role: string;
        two_factor_enabled?: boolean;
        two_factor_confirmed_at?: string;
    };
    ip_address: string;
    user_agent?: string;
    device_type?: string;
    browser?: string;
    platform?: string;
    location?: string;
    successful: boolean;
    login_at: string;
    logout_at?: string;
}

export interface LoginStatistics {
    total_logins: number;
    successful_logins: number;
    failed_logins: number;
    unique_users: number;
    today_logins: number;
    this_week_logins: number;
    this_month_logins: number;
}

// ============================================================================
// USER ACTIVITY ANALYTICS
// ============================================================================

export type TimeRangeKey = '7_days' | '30_days' | '90_days' | '1_year';

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

// ============================================================================
// USER MANAGEMENT
// ============================================================================

export interface ExtendedUser extends User {
    roles?: Array<{ id: number; name: string }>;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string;
    two_factor_recovery_codes?: string;
    backup_codes?: string[];
    backup_codes_generated_at?: string;
}

export interface LockedAccount {
    id: number;
    user_id: number;
    locked_at: string;
    lock_expires_at: string;
    locked_reason: string;
    user: User;
}

// ============================================================================
// BLOCKCHAIN EXPLORER
// ============================================================================

export interface BlockchainOverview {
    chain: string;
    protocol: string;
    blocks: number;
    difficulty: number;
    connections: number;
    version: string;
    nodeaddress: string;
}

export interface BlockInfo {
    height: number;
    hash: string;
    time: number;
    miner: string;
    tx_count: number;
    size: number;
}

export interface StreamInfo {
    name: string;
    createtxid: string | null;
    streamref: string | null;
    items: number;
    confirmed: number;
    keys: number;
    publishers: number;
    subscribed: boolean;
    synchronized: boolean;
}

export interface AddressInfo {
    address: string;
    ismine: boolean;
}

export interface PeerInfo {
    id: number;
    addr: string;
    addrlocal?: string;
    services: string;
    relaytxes: boolean;
    lastsend: number;
    lastrecv: number;
    bytessent: number;
    bytesrecv: number;
    conntime: number;
    timeoffset: number;
    pingtime?: number;
    minping?: number;
    pingwait?: number;
    version: number;
    subver: string;
    inbound: boolean;
    startingheight: number;
    banscore: number;
    synced_headers: number;
    synced_blocks: number;
    inflight: number[];
    whitelisted: boolean;
    minfeefilter: number;
    bytesrecv_per_msg: Record<string, number>;
    bytesent_per_msg: Record<string, number>;
}

export interface CircuitBreakerState {
    is_open: boolean;
    failures: number;
    recovery_time: string | null;
}

export interface QueueMetrics {
    pending_jobs: number;
    failed_jobs_24h: number;
}

export interface DocumentMetrics {
    pending_1h: number;
    failed_24h: number;
}

export interface HealthStatus {
    status: 'healthy' | 'unhealthy';
    circuit_breaker: CircuitBreakerState;
    queue: QueueMetrics;
    documents: DocumentMetrics;
    checked_at: string;
}

export interface SearchResults {
  block?: object;
  transaction?: object;
  address?: object;
}

export interface BlockchainExplorerData {
  overview: BlockchainOverview | null;
  latestBlocks: BlockInfo[];
  streams: StreamInfo[];
  addresses: AddressInfo[];
  peers: PeerInfo[];
  health: HealthStatus | null;
  error?: string;
}
