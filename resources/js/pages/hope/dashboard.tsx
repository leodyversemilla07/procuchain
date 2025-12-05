import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index as procurementsListIndex, show as procurementsShow } from '@/routes/hope/procurements';
import type { SharedData } from '@/types';
import { Stage, Status, UserRole } from '@/types';
import { getDashboardBreadcrumb } from '@/utils/breadcrumbs';
import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';

const formatStageName = (stage: string | undefined): string => {
    if (!stage) return '';
    return stage
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

const formatUserName = (user: string | undefined): string => {
    if (!user || user === 'Unknown' || user === 'System') return 'System Process';
    return user;
};

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

export default function HOPEDashboard() {
    const { recentProcurements = [], procurementDistribution = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

    const breadcrumbs = [getDashboardBreadcrumb(UserRole.HOPE)];

    // Calculate distribution from procurementDistribution data (separate from recent procurements)
    const stageDistribution = useMemo(() => {
        const distribution: Record<string, number> = {};
        procurementDistribution.forEach((procurement) => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;
        });
        return distribution;
    }, [procurementDistribution]);

    // Define all possible cards
    const allCards = [
        {
            label: 'Ongoing Projects',
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: 'text-primary bg-primary/10',
        },
        {
            label: 'Completed Biddings',
            value: stats?.completedBiddings || 0,
            icon: CheckCircle,
            colors: 'text-primary bg-primary/10',
        },
        {
            label: 'Total Documents',
            value: stats?.totalDocuments || 0,
            icon: FileIcon,
            colors: 'text-muted-foreground bg-muted/10',
        },
    ];

    // Determine grid columns based on the number of cards to show
    const gridColsClass = allCards.length === 4 ? 'md:grid-cols-4' : 'md:grid-cols-3';

    const statsItems = allCards.map((card, index) => ({
        id: `${card.label}-${index}`,
        label: card.label,
        value: card.value,
        icon: card.icon,
        iconClassName: card.colors,
    }));

    const recentActivityItems = useMemo(
        () =>
            recentActivities.map((activity) => ({
                id: activity.id,
                title: activity.title,
                action: activity.action,
                date: activity.date,
                user: formatUserName(activity.user),
                stage: formatStageName(activity.stage),
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
                // Filter out null/undefined items
                if (!procurement) {
                    return false;
                }
                // Filter out procurements without IDs first
                if (!procurement.id) {
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
            .filter(Boolean); // Remove any falsy values
    }, [recentProcurements]);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Head of Procuring Entity Dashboard" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard icon={FileText} title="HOPE Dashboard" description="High-level overview of procurement status and activities" />

                <StatsGrid items={statsItems} gridClassName={gridColsClass} />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 xl:grid-cols-5">
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm xl:col-span-3">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <ProcurementDistributionCard
                            className="xl:col-span-3"
                            data={procurementDistribution}
                            title="Procurement Distribution"
                            description="Distribution of procurements across stages and statuses"
                            errorState={buildErrorState('Unable to load procurement distribution')}
                        />
                    </Deferred>
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm xl:col-span-2">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <StageDistributionCard
                            className="xl:col-span-2"
                            stageDistribution={stageDistribution}
                            errorState={buildErrorState('Unable to load stage distribution')}
                        />
                    </Deferred>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2 xl:grid-cols-1">
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
                            title="Recent Activities"
                            icon={Clock}
                            activities={recentActivityItems}
                            getActivityHref={(activity) => `/bac-secretariat/procurements-list/${activity.id}`}
                            viewAllHref={recentActivityItems.length > 0 ? procurementsListIndex.url() : undefined}
                            errorState={buildErrorState('Unable to load recent activities')}
                        />
                    </Deferred>

                    <Deferred
                        data="recentProcurements"
                        fallback={
                            <Card className="shadow-sm lg:col-span-2 xl:col-span-1">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <RecentProcurementsTable
                            className="lg:col-span-2 xl:col-span-1"
                            procurements={recentProcurementItems}
                            getViewProcurementHref={(procurement) => {
                                if (!procurement?.id) return '#';
                                return procurementsShow.url({ pr_number: procurement.id });
                            }}
                            viewAllHref={recentProcurementItems.length > 0 ? procurementsListIndex.url() : undefined}
                            errorState={buildErrorState('Unable to load recent procurements')}
                        />
                    </Deferred>
                </div>
            </div>
        </AppLayout>
    );
}
