import { markAllAsRead, markAsRead } from '@/actions/App/Http/Controllers/NotificationController';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid, type StatsGridItem } from '@/components/stats-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Item, ItemActions, ItemContent, ItemDescription, ItemMedia, ItemTitle } from '@/components/ui/item';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { User } from '@/types';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { Head, router, usePage, usePoll, WhenVisible } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, Bell, Check, CheckCheck, Clock, Filter, Loader2, RotateCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// Import Wayfinder route helpers for each role
import { show as adminShow } from '@/routes/admin/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';

interface Notification {
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: {
        title: string;
        message: string;
        pr_number: string;
        procurement_title: string;
        stage_identifier: string;
        current_status: string;
        timestamp: string;
        action_type: string;
        next_stage?: string;
        transition_message?: string;
    };
    read_at: string | null;
    created_at: string;
    updated_at: string;
}

type FilterType = 'all' | 'read' | 'unread';

// Helper function to get the correct Wayfinder route based on user role
const getProcurementShowUrl = (role: string, id: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return bacSecretariatShow.url(id);
        case 'bac_chairman':
            return bacChairmanShow.url(id);
        case 'hope':
            return hopeShow.url(id);
        case 'admin':
            return adminShow.url(id);
        default:
            return `/procurements-list/${id}`;
    }
};

interface NotificationPageProps {
    auth: { user: User };
    notifications: Notification[];
    next_cursor: string | null;
    has_more: boolean;
    unread_count: number;
    [key: string]: unknown;
}

export default function Notifications() {
    const { auth, notifications: initialNotifications, next_cursor, has_more, unread_count } = usePage<NotificationPageProps>().props;
    const userRole = auth.user?.role;
    const breadcrumbs = buildBreadcrumbs(userRole, [{ title: 'Notifications', href: '/notifications' }]);

    // Use polling to keep notifications updated (only first page)
    usePoll(30000, {
        only: ['notifications', 'unread_count'],
    });

    const [filter, setFilter] = useState<FilterType>('all');
    const [refreshing, setRefreshing] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    // Optimistic state management
    const [optimisticNotifications, setOptimisticNotifications] = useState<Notification[]>(initialNotifications);
    const [optimisticUnreadCount, setOptimisticUnreadCount] = useState(unread_count);

    // Sync optimistic state with server data when it changes
    useEffect(() => {
        setOptimisticNotifications(initialNotifications);
        setOptimisticUnreadCount(unread_count);
    }, [initialNotifications, unread_count]);

    const filterNotifications = useCallback((notifications: Notification[], filterType: FilterType) => {
        if (filterType === 'read') return notifications.filter((n) => n.read_at !== null);
        if (filterType === 'unread') return notifications.filter((n) => n.read_at === null);
        return notifications;
    }, []);

    const filteredNotifications = useMemo(() => {
        return filterNotifications(optimisticNotifications, filter);
    }, [optimisticNotifications, filter, filterNotifications]);

    // Load more notifications when scrolling to bottom
    const loadMore = useCallback(() => {
        if (!has_more || loadingMore) return;

        setLoadingMore(true);
        router.get(
            window.location.pathname,
            { cursor: next_cursor },
            {
                only: ['notifications', 'next_cursor', 'has_more'],
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoadingMore(false),
            },
        );
    }, [next_cursor, has_more, loadingMore]);

    const handleFilterChange = useCallback((newFilter: FilterType) => {
        setFilter(newFilter);
    }, []);

    const handleMarkAsRead = useCallback(
        async (id: string) => {
            // Store previous state for rollback
            const previousNotifications = [...optimisticNotifications];
            const previousUnreadCount = optimisticUnreadCount;

            // Optimistically update UI immediately
            setOptimisticNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
            setOptimisticUnreadCount((prev) => Math.max(0, prev - 1));

            // Make the actual request
            router.post(
                markAsRead(id).url,
                {},
                {
                    preserveScroll: true,
                    // Reload notification data to sync across tabs/windows
                    only: ['notifications', 'unread_count'],
                    onSuccess: () => {
                        toast.success('Notification marked as read');
                    },
                    onError: () => {
                        // Rollback on error
                        setOptimisticNotifications(previousNotifications);
                        setOptimisticUnreadCount(previousUnreadCount);
                        toast.error('Failed to mark notification as read');
                    },
                },
            );
        },
        [optimisticNotifications, optimisticUnreadCount],
    );

    const handleMarkAllAsRead = useCallback(async () => {
        // Store previous state for rollback
        const previousNotifications = [...optimisticNotifications];
        const previousUnreadCount = optimisticUnreadCount;

        // Optimistically update all notifications immediately
        const timestamp = new Date().toISOString();
        setOptimisticNotifications((prev) => prev.map((n) => ({ ...n, read_at: n.read_at || timestamp })));
        setOptimisticUnreadCount(0);

        // Make the actual request
        router.post(
            markAllAsRead().url,
            {},
            {
                preserveScroll: true,
                // Reload notification data to sync across tabs/windows
                only: ['notifications', 'unread_count'],
                onSuccess: () => {
                    toast.success('All notifications marked as read');
                },
                onError: () => {
                    // Rollback on error
                    setOptimisticNotifications(previousNotifications);
                    setOptimisticUnreadCount(previousUnreadCount);
                    toast.error('Failed to mark all notifications as read');
                },
            },
        );
    }, [optimisticNotifications, optimisticUnreadCount]);

    const handleRefresh = useCallback(() => {
        setRefreshing(true);
        router.reload({
            only: ['notifications', 'unread_count', 'next_cursor', 'has_more'],
            onFinish: () => setRefreshing(false),
        });
    }, []);

    // Loading state will be handled by Inertia's processing state
    const loading = false;

    const getNotificationIcon = (type: string) => {
        switch (type) {
            case 'success':
                return <CheckCheck className="text-primary h-5 w-5" />;
            case 'warning':
                return <AlertCircle className="text-destructive h-5 w-5" />;
            case 'info':
                return <Clock className="text-secondary-foreground h-5 w-5" />;
            default:
                return <Bell className="text-muted-foreground h-5 w-5" />;
        }
    };

    const getNotificationStatus = (notification: Notification) => {
        if (!notification.read_at) return 'unread';
        return notification.type || 'info';
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'unread':
                return <Badge className="bg-primary/10 text-primary hover:bg-primary/20">Unread</Badge>;
            case 'success':
                return <Badge className="bg-green-500/10 text-green-500 hover:bg-green-500/20">Success</Badge>;
            case 'warning':
                return <Badge className="bg-yellow-500/10 text-yellow-500 hover:bg-yellow-500/20">Warning</Badge>;
            default:
                return <Badge className="bg-blue-500/10 text-blue-500 hover:bg-blue-500/20">Info</Badge>;
        }
    };

    const handleNotificationClick = useCallback(
        (notification: Notification) => {
            // Mark as read if not already read
            if (!notification.read_at) {
                handleMarkAsRead(notification.id);
            }
            // Navigate to procurement details using Wayfinder routes
            const procurementUrl = getProcurementShowUrl(userRole || 'guest', notification.data.pr_number);
            router.visit(procurementUrl);
        },
        [userRole, handleMarkAsRead],
    );

    const statsItems: StatsGridItem[] = [
        {
            id: 'total',
            label: 'Total Notifications',
            value: filteredNotifications.length,
            icon: Bell,
            iconClassName: 'bg-primary/10 text-primary',
        },
        {
            id: 'unread',
            label: 'Unread',
            value: optimisticUnreadCount,
            icon: AlertCircle,
            iconClassName: 'bg-yellow-500/10 text-yellow-500',
        },
        {
            id: 'read',
            label: 'Read',
            value: filteredNotifications.length - optimisticUnreadCount,
            icon: CheckCheck,
            iconClassName: 'bg-green-500/10 text-green-500',
        },
    ];

    const actions = (
        <div className="flex flex-wrap items-center gap-2 sm:gap-3">
            <Select value={filter} onValueChange={(value) => value && handleFilterChange(value)}>
                <SelectTrigger className="w-[120px] sm:w-[140px]">
                    <Filter className="text-muted-foreground mr-2 h-4 w-4" />
                    <SelectValue placeholder="Filter" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="unread">Unread</SelectItem>
                        <SelectItem value="read">Read</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Button
                variant="outline"
                size="icon"
                onClick={handleRefresh}
                className={cn('text-muted-foreground hover:text-foreground transition-all', refreshing && 'animate-spin')}
            >
                <RotateCw className="h-4 w-4" />
            </Button>
            {filteredNotifications.some((n: Notification) => !n.read_at) && (
                <Button onClick={handleMarkAllAsRead} variant="outline" size="sm" className="text-muted-foreground hover:text-foreground">
                    Mark all as read
                </Button>
            )}
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Bell}
                    title="Notifications"
                    description="Stay updated with your procurement activities and updates"
                    actions={actions}
                />

                {/* Stats Section */}
                <StatsGrid items={statsItems} userRole={userRole} />

                {/* Notifications List */}
                <Card className="overflow-hidden">
                    <CardContent className="p-0">
                        {loading ? (
                            <div className="divide-border divide-y">
                                <div className="space-y-4 p-4">
                                    {[...Array(3)].map((_, i) => (
                                        <div key={i} className="flex items-start gap-4">
                                            <Skeleton className="h-8 w-8 rounded-full sm:h-10 sm:w-10" />
                                            <div className="flex-1 space-y-2">
                                                <Skeleton className="h-4 w-[60%]" />
                                                <Skeleton className="h-3 w-[80%]" />
                                                <Skeleton className="h-3 w-[40%]" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : filteredNotifications.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Bell className="h-6 w-6" />
                                    </EmptyMedia>
                                    <EmptyTitle>No notifications</EmptyTitle>
                                    <EmptyDescription>You're all caught up!</EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-border divide-y">
                                {filteredNotifications.map((notification: Notification) => (
                                    <Item
                                        key={notification.id}
                                        className={cn(
                                            'group relative cursor-pointer border-0 p-4 transition-all sm:p-6',
                                            !notification.read_at && 'bg-primary/5 hover:bg-primary/10',
                                            notification.read_at && 'hover:bg-muted/5',
                                        )}
                                        onClick={() => handleNotificationClick(notification)}
                                    >
                                        <ItemMedia>{getNotificationIcon(notification.type)}</ItemMedia>
                                        <ItemContent>
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                                <div className="flex-1">
                                                    <div className="mb-2 flex flex-wrap items-center gap-2">
                                                        <ItemTitle className="text-sm font-medium sm:text-base">
                                                            {notification.data.procurement_title}
                                                        </ItemTitle>
                                                        {getStatusBadge(getNotificationStatus(notification))}
                                                    </div>
                                                    <div className="space-y-1">
                                                        <ItemDescription>
                                                            Stage: {notification.data.stage_identifier} - {notification.data.action_type}
                                                        </ItemDescription>
                                                        {notification.data.transition_message && (
                                                            <p className="text-muted-foreground/80 line-clamp-2 text-xs sm:text-sm">
                                                                {notification.data.transition_message}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex flex-col items-start gap-2 sm:items-end">
                                                    <time className="text-muted-foreground/70 text-xs whitespace-nowrap">
                                                        {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                                                    </time>
                                                </div>
                                            </div>
                                        </ItemContent>
                                        <ItemActions>
                                            {!notification.read_at && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="opacity-0 transition-opacity group-hover:opacity-100"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleMarkAsRead(notification.id);
                                                    }}
                                                >
                                                    <Check className="mr-1 h-4 w-4" />
                                                    Mark as read
                                                </Button>
                                            )}
                                        </ItemActions>
                                    </Item>
                                ))}

                                {/* Infinite Scroll Trigger */}
                                {has_more && (
                                    <WhenVisible
                                        fallback={
                                            <div className="flex items-center justify-center border-t py-6">
                                                <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
                                            </div>
                                        }
                                    >
                                        <div
                                            onClick={loadMore}
                                            className="text-muted-foreground hover:text-foreground cursor-pointer border-t py-4 text-center text-sm transition-colors"
                                        >
                                            {loadingMore ? (
                                                <div className="flex items-center justify-center gap-2">
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                    Loading more...
                                                </div>
                                            ) : (
                                                'Load more notifications'
                                            )}
                                        </div>
                                    </WhenVisible>
                                )}
                            </div>
                        )}

                        {/* End of List Indicator */}
                        {!loading && !has_more && filteredNotifications.length > 0 && (
                            <div className="text-muted-foreground border-t py-4 text-center text-sm">No more notifications</div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
