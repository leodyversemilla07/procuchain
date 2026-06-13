import { ModeDistributionCard, type ModeStatistics } from '@/components/dashboard/mode-distribution-card';
import { PriorityActionsStack } from '@/components/dashboard/priority-actions-stack';
import { ProcurementDistributionCard } from '@/components/dashboard/procurement-distribution-card';
import { RecentActivitiesList } from '@/components/dashboard/recent-activities-list';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { StageDistributionCard } from '@/components/dashboard/stage-distribution-card';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { SharedData, User } from '@/types';
import { UserRole } from '@/types/enums';
import { getDashboardBreadcrumb } from '@/utils/breadcrumbs';
import {
    buildErrorState,
    deduplicateProcurements,
    formatStageName,
    formatUserName,
    type DashboardStats,
    type RecentProcurement,
} from '@/utils/dashboard';
import { Deferred, Head, usePage } from '@inertiajs/react';
import { ActivityIcon, Bell, CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';

interface PriorityAction {
    id: string;
    title: string;
    action: string;
    route: string;
}

interface RecentActivity {
    id: string;
    title: string;
    action: string;
    date: string;
    user: string;
    user_role?: string;
    stage?: string;
}

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    procurementDistribution: RecentProcurement[];
    recentActivities: RecentActivity[];
    priorityActions: PriorityAction[];
    stats: DashboardStats;
    modeStatistics?: ModeStatistics;
    error?: string;
}

const breadcrumbs = [getDashboardBreadcrumb(UserRole.BAC_SECRETARIAT)];

export default function BACSecretariatDashboard() {
    const pageProps = usePage<DashboardProps>().props;
    const {
        recentProcurements = [],
        procurementDistribution = [],
        recentActivities = [],
        priorityActions = [],
        stats,
        modeStatistics,
        error,
    } = pageProps as DashboardProps;
    const { auth } = pageProps as unknown as { auth: { user: User } };
    const userRole = auth.user?.role;

    const statsItems = [
        {
            id: 'ongoing-projects',
            label: 'Ongoing Projects',
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            iconClassName: 'text-primary bg-primary/10',
        },
        {
            id: 'pending-actions',
            label: 'Pending Actions',
            value: stats?.pendingActions || 0,
            icon: Bell,
            iconClassName: 'text-secondary bg-secondary/10',
            roles: ['bac_secretariat'],
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
    const stageDistribution = useMemo(() => {
        return procurementDistribution.reduce<Record<string, number>>((distribution, procurement) => {
            const stage = procurement.stage;
            distribution[stage] = (distribution[stage] || 0) + 1;

            return distribution;
        }, {});
    }, [procurementDistribution]);

    const priorityActionItems = useMemo(() => priorityActions.map(({ id, action, route }) => ({ id, action, route })), [priorityActions]);

    const recentActivityItems = useMemo(
        () =>
            recentActivities.map((activity) => ({
                id: activity.id,
                title: activity.title,
                action: activity.action,
                date: activity.date,
                user: formatUserName(activity.user),
                stage: activity.stage ? formatStageName(activity.stage) : undefined,
                userRole: activity.user_role,
            })),
        [recentActivities],
    );

    const recentProcurementItems = useMemo(() => deduplicateProcurements(recentProcurements), [recentProcurements]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bids and Awards Committee Secretariat Dashboard" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard icon={ActivityIcon} title="BAC Secretariat Dashboard" description="Overview of procurement activities and tasks" />

                <StatsGrid items={statsItems} userRole={userRole} />

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

                <div className="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Deferred
                        data="priorityActions"
                        fallback={
                            <Card className="shadow-sm">
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="flex items-center text-sm font-semibold sm:text-base md:text-lg">
                                        <Bell className="text-primary mr-2 h-3 w-3 sm:h-4 sm:w-4 md:h-5 md:w-5" />
                                        Priority Actions
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex h-[150px] items-center justify-center sm:h-[200px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <Card className="shadow-sm">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="flex items-center text-sm font-semibold sm:text-base md:text-lg">
                                    <Bell className="text-primary mr-2 h-3 w-3 sm:h-4 sm:w-4 md:h-5 md:w-5" />
                                    Priority Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-3 sm:p-4 md:p-6">
                                <PriorityActionsStack
                                    actions={priorityActionItems}
                                    errorState={buildErrorState(error, 'Unable to load priority actions')}
                                />
                            </CardContent>
                        </Card>
                    </Deferred>

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
                            viewAllHref={recentActivityItems.length > 0 ? '/bac-secretariat/procurements-list' : undefined}
                            showUserRole
                            errorState={buildErrorState(error, 'Unable to load recent activities')}
                        />
                    </Deferred>

                    <Deferred
                        data="recentProcurements"
                        fallback={
                            <Card className="shadow-sm lg:col-span-3 xl:col-span-2">
                                <CardContent className="flex h-[200px] items-center justify-center sm:h-[250px] md:h-[300px]">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <RecentProcurementsTable
                            className="lg:col-span-3 xl:col-span-2"
                            procurements={recentProcurementItems}
                            getViewProcurementHref={(procurement) => {
                                if (!procurement?.id) return '#';
                                return `/bac-secretariat/procurements-list/${procurement.id}`;
                            }}
                            viewAllHref={recentProcurementItems.length > 0 ? '/bac-secretariat/procurements-list' : undefined}
                            errorState={buildErrorState(error, 'Unable to load recent procurements')}
                        />
                    </Deferred>
                </div>
            </div>
        </AppLayout>
    );
}
