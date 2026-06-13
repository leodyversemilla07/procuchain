import { ModeDistributionCard, type ModeStatistics } from '@/components/dashboard/mode-distribution-card';
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
import { UserRole } from '@/types';
import { getDashboardBreadcrumb } from '@/utils/breadcrumbs';
import {
    buildErrorState,
    deduplicateProcurements,
    formatStageName,
    formatUserName,
    type DashboardStats,
    type RecentActivity,
    type RecentProcurement,
} from '@/utils/dashboard';
import { Deferred, Head, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    modeStatistics?: ModeStatistics;
    error?: string;
}

export default function HOPEDashboard() {
    const {
        recentProcurements = [],
        procurementDistribution = [],
        recentActivities = [],
        stats,
        modeStatistics,
        error,
    } = usePage<DashboardProps>().props;

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

    const recentProcurementItems = useMemo(() => deduplicateProcurements(recentProcurements), [recentProcurements]);

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
                            errorState={buildErrorState(error, 'Unable to load procurement distribution')}
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
                            errorState={buildErrorState(error, 'Unable to load stage distribution')}
                        />
                    </Deferred>
                </div>

                {/* Mode Distribution Section - NGPA Compliance */}
                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
                    <Deferred
                        data="modeStatistics"
                        fallback={
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <ModeDistributionCard
                            modeStatistics={modeStatistics}
                            title="Procurement Mode Distribution"
                            description="NGPA-compliant mode usage statistics"
                            variant="chart"
                            errorState={buildErrorState(error, 'Unable to load mode distribution')}
                        />
                    </Deferred>
                    <Deferred
                        data="modeStatistics"
                        fallback={
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <ModeDistributionCard
                            modeStatistics={modeStatistics}
                            title="Competitive vs Alternative"
                            description="Mode type breakdown per NGPA IRR"
                            variant="breakdown"
                            errorState={buildErrorState(error, 'Unable to load mode breakdown')}
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
                            errorState={buildErrorState(error, 'Unable to load recent activities')}
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
                            errorState={buildErrorState(error, 'Unable to load recent procurements')}
                        />
                    </Deferred>
                </div>
            </div>
        </AppLayout>
    );
}
