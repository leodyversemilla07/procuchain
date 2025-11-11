/**
 * Dashboard Types
 * Contains interfaces for dashboard statistics, activities, and procurement data
 */

import type { Stage, Status } from './enums';

// ============================================================================
// DASHBOARD STATISTICS
// ============================================================================

export interface DashboardStats {
    ongoingProjects: number;
    pendingActions: number;
    completedBiddings: number;
    totalDocuments: number;
}

// ============================================================================
// RECENT ACTIVITIES
// ============================================================================

export interface RecentActivity {
    id: string | number;
    title?: string | null;
    action: string;
    date: string;
    user: string;
    stage?: string | null;
    userRole?: string | null;
}

// ============================================================================
// RECENT PROCUREMENTS
// ============================================================================

export interface RecentProcurement {
    id: string;
    title: string;
    stage: Stage;
    status: Status;
}

// ============================================================================
// PRIORITY ACTIONS
// ============================================================================

export interface PriorityAction {
    id: string;
    action: string;
    route: string;
}

// ============================================================================
// STATISTICS GRID
// ============================================================================

export interface StatsGridItem {
    id?: string;
    label: string;
    value: React.ReactNode;
    icon: React.ComponentType<{ className?: string }>;
    iconClassName?: string;
    roles?: string[];
}

// ============================================================================
// PROCUREMENT DISTRIBUTION
// ============================================================================

export interface ProcurementDistributionItem {
    name: string;
    value: number;
    fill?: string;
}

export type DistributionKey = 'stage' | 'status';

export interface StageDistributionItem {
    stage: string;
    count: number;
    fill?: string;
}
