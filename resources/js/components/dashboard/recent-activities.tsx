import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Clock } from "lucide-react";
import { Link } from "@inertiajs/react";
import { getActionIcon, getActionBadgeStyle } from "@/lib/action-utils";
import type { RecentActivity } from "@/types/dashboard";

interface RecentActivitiesProps {
    activities: RecentActivity[];
}

export function RecentActivities({ activities }: RecentActivitiesProps) {
    const formatRelativeDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} day ago`;

        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    };

    if (activities.length === 0) {
        return (
            <Card className="shadow-sm">
                <CardContent className="p-4">
                    <div className="text-center py-8">
                        <Clock className="mx-auto h-8 w-8 text-muted-foreground opacity-20 mb-2" />
                        <p>No recent activities found</p>
                        <p className="text-xs text-muted-foreground mt-2">
                            Activities will appear here when procurement actions are taken.<br />
                            Try refreshing if you've recently performed actions.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-4"
                            onClick={() => window.location.reload()}
                        >
                            Refresh Data
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="shadow-sm">
            <CardContent className="p-4">
                <div className="space-y-3">
                    {activities.map((activity, index) => {
                        const ActionIcon = getActionIcon(activity.action);
                        return (
                            <div key={index} className={`${index < activities.length - 1 ? "border-b pb-3" : ""}`}>
                                <div className="flex items-center justify-between">
                                    <Link 
                                        href={`/bac-secretariat/procurements-list/${activity.id}`}
                                        className="font-medium text-primary hover:underline text-sm max-w-[70%] truncate"
                                    >
                                        {activity.title || `Procurement #${activity.id}`}
                                    </Link>
                                    <span className="text-xs text-muted-foreground">
                                        {formatRelativeDate(activity.date)}
                                    </span>
                                </div>
                                <div className="mt-1.5 flex items-center justify-between">
                                    <div className="flex items-center">
                                        <Badge 
                                            variant="outline"
                                            className={`${getActionBadgeStyle(activity.action)} text-xs mr-2 flex items-center gap-1`}
                                        >
                                            <ActionIcon className="h-3.5 w-3.5" />
                                            <span>{activity.action}</span>
                                        </Badge>
                                        {activity.stage && (
                                            <span className="text-xs text-muted-foreground ml-1">
                                                in {activity.stage} stage
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-xs text-muted-foreground">by {activity.user}</span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}