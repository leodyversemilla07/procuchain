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
import { Stage, Status } from '@/types';
import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { ActivityIcon, Bell, CheckCircle, Clock, FileIcon, FileText } from 'lucide-react';
import { useMemo } from 'react';
import { getDashboardBreadcrumb } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

/**
 * Format stage name from snake_case to Title Case
 */
const formatStageName = (stage: string): string => {
    if (!stage) return stage;
    
    return stage
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

/**
 * Format user name, handling unknown users
 */
const formatUserName = (user: string): string => {
    if (!user || user === 'Unknown' || user === 'System' || user.trim() === '') {
        return 'System Process';
    }
    return user;
};

interface DashboardStats {
    ongoingProjects: number;
    pendingActions: number;
    completedBiddings: number;
    totalDocuments: number;
}

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
    priorityActions: PriorityAction[];
    stats: DashboardStats;
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
        error,
    } = pageProps as DashboardProps;
    const { auth } = pageProps as unknown as { auth: { user: User } };
    const userRole = auth.user?.role;

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bids and Awards Committee Secretariat Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-4 p-3 sm:space-y-6 sm:p-4 md:p-6 lg:p-8">
                <HeroCard
                    icon={ActivityIcon}
                    title="BAC Secretariat Dashboard"
                    description="Overview of procurement activities and tasks"
                />

                <StatsGrid items={statsItems} userRole={userRole} />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 xl:grid-cols-5">
                    <Deferred
                        data="procurementDistribution"
                        fallback={
                            <Card className="xl:col-span-3 shadow-sm">
                                <CardContent className="flex h-[200px] sm:h-[250px] md:h-[300px] items-center justify-center">
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
                            <Card className="xl:col-span-2 shadow-sm">
                                <CardContent className="flex h-[200px] sm:h-[250px] md:h-[300px] items-center justify-center">
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

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-2">
                    <Deferred
                        data="priorityActions"
                        fallback={
                            <Card className="shadow-sm">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="flex items-center text-sm sm:text-base font-semibold md:text-lg">
                                        <Bell className="text-primary mr-2 h-3 w-3 sm:h-4 sm:w-4 md:h-5 md:w-5" />
                                        Priority Actions
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex h-[150px] sm:h-[200px] items-center justify-center">
                                    <Spinner className="h-6 w-6 sm:h-8 sm:w-8" />
                                </CardContent>
                            </Card>
                        }
                    >
                        <Card className="shadow-sm">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="flex items-center text-sm sm:text-base font-semibold md:text-lg">
                                    <Bell className="text-primary mr-2 h-3 w-3 sm:h-4 sm:w-4 md:h-5 md:w-5" />
                                    Priority Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-3 sm:p-4 md:p-6">
                                <PriorityActionsStack actions={priorityActionItems} errorState={buildErrorState('Unable to load priority actions')} />
                            </CardContent>
                        </Card>
                    </Deferred>

                    <Deferred
                        data="recentActivities"
                        fallback={
                            <Card className="shadow-sm">
                                <CardContent className="flex h-[200px] sm:h-[250px] items-center justify-center">
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
                            errorState={buildErrorState('Unable to load recent activities')}
                        />
                    </Deferred>

                    <Deferred
                        data="recentProcurements"
                        fallback={
                            <Card className="lg:col-span-3 xl:col-span-2 shadow-sm">
                                <CardContent className="flex h-[200px] sm:h-[250px] md:h-[300px] items-center justify-center">
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
                            errorState={buildErrorState('Unable to load recent procurements')}
                        />
                    </Deferred>
                </div>
            </div>
        </AppLayout>
    );
}
