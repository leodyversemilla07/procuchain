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
    const hasActivities = activities.length > 0;

    return (
        <Card className={cn(className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="flex items-center text-base font-semibold md:text-lg">
                    <Icon className="text-primary mr-2 h-4 w-4 md:h-5 md:w-5" />
                    {title}
                    {hasActivities ? <Fragment> ({activities.length})</Fragment> : null}
                </CardTitle>
                {hasActivities && viewAllHref ? (
                    <Link href={viewAllHref} className="text-primary ml-2 flex shrink-0 items-center text-xs hover:underline md:text-sm">
                        {viewAllLabel} <ArrowRight className="ml-1 h-3 w-3 md:h-4 md:w-4" />
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
                                    return <Icon className="h-8 w-8" />;
                                })()}
                            </EmptyMedia>
                        </EmptyHeader>
                        <EmptyTitle>{emptyStateTitle}</EmptyTitle>
                        <EmptyDescription>{emptyStateDescription}</EmptyDescription>
                    </Empty>
                ) : (
                    <div className="space-y-3">
                        {activities.map((activity, index) => {
                            const ActionIcon = resolveActionIcon(activity.action, actionIconMap);
                            const isLast = index === activities.length - 1;

                            return (
                                <div key={activity.id} className={!isLast ? 'border-b pb-3' : undefined}>
                                    <div className="flex items-center justify-between">
                                        <Link
                                            href={getActivityHref(activity)}
                                            className="text-primary max-w-[70%] truncate text-sm font-medium hover:underline"
                                        >
                                            {activity.title || `Procurement #${activity.id}`}
                                        </Link>
                                        <span className="text-muted-foreground text-xs">{formatRelativeDate(activity.date)}</span>
                                    </div>
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <div className="flex items-center">
                                            <Badge variant="secondary" className="mr-2 flex items-center gap-1 text-xs">
                                                <ActionIcon className="h-3.5 w-3.5" />
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
