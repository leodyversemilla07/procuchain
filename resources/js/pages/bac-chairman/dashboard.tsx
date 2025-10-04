import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Stage, Status } from '@/types/blockchain';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';

interface DashboardStats {
    ongoingProjects: number;
    completedBiddings: number;
    totalDocuments: number;
}

interface RecentActivity {
    id: string;
    title: string;
    action: string;
    date: string;
    user: string;
    stage?: string;
}

interface RecentProcurement {
    id: string;
    title: string;
    stage: Stage;
    status: Status;
}

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bids and Awards Committee Chairman Dashboard',
        href: route('bac-chairman.dashboard'),
    },
];

export default function BACChairmanDashboard() {
    const { recentProcurements = [], procurementDistribution = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

    // Calculate distribution from procurementDistribution data (separate from recent procurements)
    const stageDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach((procurement) => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    const statsItems = [
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
            iconClassName: 'text-muted-foreground bg-muted/10',
        },
    ];

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

    const heroActions = (
        <div className="flex items-center gap-4">
            <Link
                href={route('bac-chairman.procurements-list.index')}
                className="bg-secondary text-secondary-foreground hover:bg-secondary/90 inline-flex items-center rounded-md px-3 py-2 text-sm font-medium"
            >
                View Procurements
            </Link>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bids and Awards Committee Chairman Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <HeroCard
                    icon={FileText}
                    title="Bids and Awards Committee Chairman Dashboard"
                    description="Overview of procurement activities and committee tasks"
                    actions={heroActions}
                />

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
                        className="lg:col-span-2"
                        title="Recent Activities"
                        icon={Clock}
                        activities={recentActivityItems}
                        getActivityHref={(activity) => `/bac-secretariat/procurements-list/${activity.id}`}
                        viewAllHref={recentActivityItems.length > 0 ? route('bac-chairman.procurements-list.index') : undefined}
                        errorState={buildErrorState('Unable to load recent activities')}
                    />

                    <RecentProcurementsTable
                        procurements={recentProcurementItems}
                        getViewProcurementHref={(procurement) => route('bac-chairman.procurements.show', { id: procurement.id })}
                        viewAllHref={recentProcurementItems.length > 0 ? route('bac-chairman.procurements-list.index') : undefined}
                        errorState={buildErrorState('Unable to load recent procurements')}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
