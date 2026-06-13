import type { Stage, Status } from '@/types';
import { router } from '@inertiajs/react';

export const formatStageName = (stage: string | undefined): string => {
    if (!stage) return '';
    return stage
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

export const formatUserName = (user: string | undefined): string => {
    if (!user || user === 'Unknown' || user === 'System' || user.trim() === '') return 'System Process';
    return user;
};

export interface DashboardStats {
    ongoingProjects: number;
    pendingActions?: number;
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

export function buildErrorState(error: string | undefined, title: string) {
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
}

export function deduplicateProcurements(
    procurements: Array<{ id?: string; title: string; stage: Stage; status: Status } | null | undefined>,
): RecentProcurement[] {
    const seen = new Set<string>();
    return procurements
        .filter((p) => p && p.id && !seen.has(p.id) && (seen.add(p.id), true))
        .map((p) => ({ id: p!.id!, title: p!.title, stage: p!.stage, status: p!.status }));
}
