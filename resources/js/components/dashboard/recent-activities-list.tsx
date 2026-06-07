import type { ErrorStateProps } from '@/components/error-state';
import { ErrorState } from '@/components/error-state';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { cn, formatRelativeDate } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ActivityIcon, ArrowRight, CheckCircle, CheckIcon, ExternalLinkIcon, FileTextIcon, FileUpIcon, PlusIcon } from 'lucide-react';
import { Fragment } from 'react';

const DEFAULT_ACTION_ICON_MAP: Record<string, LucideIcon> = {
    upload: FileUpIcon,
    document: FileUpIcon,
    stage: ArrowRight,
    transition: ArrowRight,
    'pre-procurement': FileTextIcon,
    decision: CheckCircle,
    publish: ExternalLinkIcon,
    complete: CheckIcon,
    submit: PlusIcon,
    add: PlusIcon,
    review: FileTextIcon,
    evaluate: FileTextIcon,
};

export interface RecentActivityItem {
    id: string | number;
    title?: string | null;
    action: string;
    date: string;
    user: string;
    stage?: string | null;
    userRole?: string | null;
}

export interface RecentActivitiesListProps {
    title: string;
    icon: LucideIcon;
    activities: RecentActivityItem[];
    getActivityHref: (activity: RecentActivityItem) => string;
    viewAllHref?: string;
    viewAllLabel?: string;
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    showUserRole?: boolean;
    className?: string;
    actionIconMap?: Record<string, LucideIcon>;
    errorState?: ErrorStateProps;
}

const DEFAULT_EMPTY_TITLE = 'No recent activities';
const DEFAULT_EMPTY_DESCRIPTION = 'Activities will appear once procurement actions are taken.';

const resolveActionIcon = (action: string, iconMap: Record<string, LucideIcon>) => {
    const entry = Object.entries(iconMap).find(([key]) => action.toLowerCase().includes(key));

    return entry ? entry[1] : ActivityIcon;
};

export const RecentActivitiesList = ({
    title,
    icon: Icon,
    activities,
    getActivityHref,
    viewAllHref,
    viewAllLabel = 'View all',
    emptyStateIcon = ActivityIcon,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    showUserRole = false,
    className,
    actionIconMap = DEFAULT_ACTION_ICON_MAP,
    errorState,
}: RecentActivitiesListProps) => {
    // Ensure activities is an array
    const safeActivities = Array.isArray(activities) ? activities : [];
    const hasActivities = safeActivities.length > 0;

    return (
        <Card className={cn('shadow-sm transition-shadow duration-300 hover:shadow-md', className)}>
            <CardHeader className="flex flex-row items-center justify-between gap-0 pb-2">
                <CardTitle className="flex items-center text-base font-semibold md:text-lg">
                    <Icon className="text-primary mr-2 transition-transform duration-200 group-hover:scale-110" />
                    {title}
                    {hasActivities ? <Fragment> ({safeActivities.length})</Fragment> : null}
                </CardTitle>
                {hasActivities && viewAllHref ? (
                    <Link
                        href={viewAllHref}
                        className="text-primary ml-2 flex shrink-0 items-center text-xs transition-all duration-200 hover:translate-x-1 hover:underline md:text-sm"
                    >
                        {viewAllLabel} <ArrowRight className="ml-1" />
                    </Link>
                ) : null}
            </CardHeader>
            <CardContent>
                {errorState ? (
                    <ErrorState {...errorState} />
                ) : !hasActivities ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {(() => {
                                    const Icon = emptyStateIcon;
                                    return <Icon />;
                                })()}
                            </EmptyMedia>
                        </EmptyHeader>
                        <EmptyTitle>{emptyStateTitle}</EmptyTitle>
                        <EmptyDescription>{emptyStateDescription}</EmptyDescription>
                    </Empty>
                ) : (
                    <div className="flex flex-col gap-3">
                        {safeActivities.map((activity, index) => {
                            const ActionIcon = resolveActionIcon(activity.action, actionIconMap);
                            const isLast = index === safeActivities.length - 1;

                            return (
                                <div
                                    key={`${activity.id}-${index}`}
                                    className={cn('group transition-all duration-200 hover:translate-x-1', !isLast && 'border-b pb-3')}
                                >
                                    <div className="flex items-start justify-between gap-2 sm:items-center">
                                        <Link
                                            href={getActivityHref(activity)}
                                            className="text-primary group-hover:text-primary/80 min-w-0 flex-1 truncate text-sm font-medium transition-colors duration-200 hover:underline"
                                        >
                                            {activity.title || `Procurement #${activity.id}`}
                                        </Link>
                                        <span className="text-muted-foreground shrink-0 text-xs">{formatRelativeDate(activity.date)}</span>
                                    </div>
                                    <div className="mt-1.5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center">
                                            <Badge
                                                variant="secondary"
                                                className="mr-2 flex items-center gap-1 text-xs transition-all duration-200 group-hover:shadow-sm"
                                            >
                                                <ActionIcon />
                                                <span>{activity.action}</span>
                                            </Badge>
                                            {activity.stage ? (
                                                <span className="text-muted-foreground ml-1 text-xs">in {activity.stage} stage</span>
                                            ) : null}
                                        </div>
                                        <span className="text-muted-foreground text-xs">
                                            by {activity.user}
                                            {showUserRole && activity.userRole ? (
                                                <span className="text-muted-foreground/70 ml-1">({activity.userRole})</span>
                                            ) : null}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
};
