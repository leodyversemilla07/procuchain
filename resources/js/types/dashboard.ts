import { Stage, Status } from './blockchain';
import { PageProps } from '@inertiajs/core';

export interface DashboardStats {
    ongoingProjects: number;
    pendingActions: number;
    completedBiddings: number;
    totalDocuments: number;
}

export interface PriorityAction {
    id: string;
    title: string;
    action: string;
    route: string;
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

export interface DashboardProps extends PageProps {
    recentProcurements: RecentProcurement[];
    recentActivities: RecentActivity[];
    priorityActions: PriorityAction[];
    stats: DashboardStats;
    error?: string;
}