import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index as procurementsListIndex, show as procurementsShow } from '@/routes/bac-chairman/procurements';
import type { BreadcrumbItem, SharedData } from '@/types';
import { UserRole } from '@/types/enums';
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
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [getDashboardBreadcrumb(UserRole.BAC_CHAIRMAN)];

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

    const heroActions = (
        <div className="flex items-center gap-4">
            <Link
                href={procurementsListIndex.url()}
                className="bg-secondary text-secondary-foreground hover:bg-secondary/90 inline-flex items-center rounded-md px-3 py-2 text-sm font-medium"
            >
                View Procurements
            </Link>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bids and Awards Committee Chairman Dashboard" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={FileText}
                    title="BAC Chairman Dashboard"
                    description="Overview of procurement activities and committee tasks"
                    actions={heroActions}
                />

                <StatsGrid items={statsItems} />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-5">
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm lg:col-span-3">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <ProcurementDistributionCard
                            className="lg:col-span-3"
                            data={procurementDistribution}
                            title="Procurement Distribution"
                            description="Distribution of procurements across stages and statuses"
                            errorState={buildErrorState(error, 'Unable to load procurement distribution')}
                        />
                    </Deferred>
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="shadow-sm lg:col-span-2">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <StageDistributionCard
                            className="lg:col-span-2"
                            stageDistribution={stageDistribution}
                            errorState={buildErrorState(error, 'Unable to load stage distribution')}
                        />
                    </Deferred>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
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
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <RecentProcurementsTable
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
