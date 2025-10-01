import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { BreadcrumbItem, User } from '@/types';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import { AlertCircle, Bell, Check, CheckCheck, Clock, ExternalLink, Filter, RotateCw, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Notification {
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: {
        title: string;
        message: string;
        procurement_id: string;
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

const ITEMS_PER_PAGE = 10;

const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_chairman':
            return [
                { title: 'Bids and Awards Committee Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Notifications', href: '/notifications' },
            ];
        case 'hope':
            return [
                { title: 'Head of Procuring Entity Dashboard', href: '/hope/dashboard' },
                { title: 'Notifications', href: '/notifications' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Notifications', href: '/notifications' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Notifications', href: '/notifications' },
            ];
    }
};

interface NotificationPageProps {
    auth: { user: User };
    notifications: Notification[];
    pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
    unread_count: number;
    [key: string]: unknown; // Index signature for PageProps compatibility
}

export default function Notifications() {
    const { auth, notifications: initialNotifications, pagination: initialPagination, unread_count } = usePage<NotificationPageProps>().props;
    const userRole = auth.user?.role;
    const breadcrumbs = getBreadcrumbs(userRole);

    // Use polling to keep notifications updated
    usePoll(30000); // Poll every 30 seconds

    const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [filter, setFilter] = useState<FilterType>('all');
    const [refreshing, setRefreshing] = useState(false);

    const filterNotifications = useCallback((notifications: Notification[], filterType: FilterType) => {
        if (filterType === 'read') return notifications.filter((n) => n.read_at !== null);
        if (filterType === 'unread') return notifications.filter((n) => n.read_at === null);
        return notifications;
    }, []);

    const filteredNotifications = useMemo(() => {
        return filterNotifications(initialNotifications, filter);
    }, [initialNotifications, filter, filterNotifications]);

    const currentPage = initialPagination.current_page;
    const totalPages = Math.max(1, Math.ceil(filteredNotifications.length / ITEMS_PER_PAGE));
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = startIndex + ITEMS_PER_PAGE;
    const paginatedNotifications = filteredNotifications.slice(startIndex, endIndex);

    const handleFilterChange = useCallback((newFilter: FilterType) => {
        setFilter(newFilter);
    }, []);

    const handleMarkAsRead = useCallback(async (id: string) => {
        router.post(
            `/notifications/${id}/mark-as-read`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Notification marked as read'),
                onError: () => toast.error('Failed to mark notification as read'),
            },
        );
    }, []);

    const handleMarkAllAsRead = useCallback(async () => {
        router.post(
            '/notifications/mark-all-as-read',
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('All notifications marked as read'),
                onError: () => toast.error('Failed to mark all notifications as read'),
            },
        );
    }, []);

    const handleRefresh = useCallback(() => {
        setRefreshing(true);
        router.reload({
            only: ['notifications', 'unread_count', 'pagination'],
            onFinish: () => setRefreshing(false),
        });
    }, []);

    const pageNumbers = useMemo(() => Array.from({ length: totalPages }, (_, i) => i + 1), [totalPages]);

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

    const EmptyState = () => (
        <div className="flex min-h-[400px] w-full items-center justify-center">
            <div className="text-center">
                <div className="bg-muted/10 mx-auto mb-4 w-fit rounded-full p-3">
                    <Bell className="text-muted-foreground h-6 w-6" />
                </div>
                <h3 className="text-foreground mb-1 text-lg font-medium">No notifications</h3>
                <p className="text-muted-foreground/70 text-sm">You're all caught up!</p>
            </div>
        </div>
    );

    const handleNotificationClick = (notification: Notification) => {
        setSelectedNotification(notification);
        setIsDialogOpen(true);
        if (!notification.read_at) {
            handleMarkAsRead(notification.id);
        }
    };

    const formatDate = (dateString: string | null | undefined) => {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid Date';
            return format(date, 'PPPp');
        } catch (error) {
            console.error('Error formatting date:', error);
            return 'Invalid Date';
        }
    };

    const formatDistance = (dateString: string | null | undefined) => {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid Date';
            return formatDistanceToNow(date, { addSuffix: true });
        } catch (error) {
            console.error('Error formatting date distance:', error);
            return 'Invalid Date';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="flex h-full flex-col">
                {/* Header Section */}
                <div className="bg-card border-b">
                    <div className="px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1">
                                <h1 className="text-xl font-bold tracking-tight sm:text-2xl">Notifications</h1>
                                <p className="text-muted-foreground text-xs sm:text-sm">Stay updated with your procurement activities and updates</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                                <Select value={filter} onValueChange={handleFilterChange}>
                                    <SelectTrigger className="w-[120px] sm:w-[140px]">
                                        <Filter className="text-muted-foreground mr-2 h-4 w-4" />
                                        <SelectValue placeholder="Filter">
                                            {filter === 'all' ? 'All' : filter === 'read' ? 'Read' : 'Unread'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="unread">Unread</SelectItem>
                                        <SelectItem value="read">Read</SelectItem>
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
                                {paginatedNotifications.some((n: Notification) => !n.read_at) && (
                                    <Button
                                        onClick={handleMarkAllAsRead}
                                        variant="outline"
                                        size="sm"
                                        className="text-muted-foreground hover:text-foreground"
                                    >
                                        Mark all as read
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Main Content */}
                <div className="flex-1 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                    <div className="flex flex-col gap-4 sm:gap-6">
                        {/* Stats Section */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="bg-primary/10 rounded-full p-2 sm:p-3">
                                            <Bell className="text-primary h-5 w-5 sm:h-6 sm:w-6" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium sm:text-sm">Total Notifications</p>
                                            <p className="text-xl font-bold sm:text-2xl">{filteredNotifications.length}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="rounded-full bg-yellow-500/10 p-2 sm:p-3">
                                            <AlertCircle className="h-5 w-5 text-yellow-500 sm:h-6 sm:w-6" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium sm:text-sm">Unread</p>
                                            <p className="text-xl font-bold sm:text-2xl">{unread_count}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="rounded-full bg-green-500/10 p-2 sm:p-3">
                                            <CheckCheck className="h-5 w-5 text-green-500 sm:h-6 sm:w-6" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium sm:text-sm">Read</p>
                                            <p className="text-xl font-bold sm:text-2xl">{filteredNotifications.length - unread_count}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Notifications List */}
                        <Card className="overflow-hidden">
                            <CardHeader className="bg-card border-b px-4 py-3 sm:px-6 sm:py-4">
                                <CardTitle className="text-base sm:text-lg">All Notifications</CardTitle>
                            </CardHeader>
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
                                ) : paginatedNotifications.length === 0 ? (
                                    <EmptyState />
                                ) : (
                                    <div className="divide-border divide-y">
                                        {paginatedNotifications.map((notification: Notification) => (
                                            <div
                                                key={notification.id}
                                                className={cn(
                                                    'group relative flex cursor-pointer items-start gap-3 p-4 transition-all sm:gap-4 sm:p-6',
                                                    !notification.read_at && 'bg-primary/5 hover:bg-primary/10',
                                                    notification.read_at && 'hover:bg-muted/5',
                                                )}
                                                onClick={() => handleNotificationClick(notification)}
                                            >
                                                <div className="flex-shrink-0">{getNotificationIcon(notification.type)}</div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                                        <div className="flex-1">
                                                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                                                <h3 className="text-foreground text-sm font-medium sm:text-base">
                                                                    {notification.data.procurement_title}
                                                                </h3>
                                                                {getStatusBadge(getNotificationStatus(notification))}
                                                            </div>
                                                            <div className="space-y-1">
                                                                <p className="text-muted-foreground text-xs sm:text-sm">
                                                                    Stage: {notification.data.stage_identifier} - {notification.data.action_type}
                                                                </p>
                                                                {notification.data.transition_message && (
                                                                    <p className="text-muted-foreground/80 line-clamp-2 text-xs sm:text-sm">
                                                                        {notification.data.transition_message}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex flex-col items-start gap-2 sm:items-end">
                                                            <HoverCard>
                                                                <HoverCardTrigger asChild>
                                                                    <time className="text-muted-foreground/70 cursor-help text-xs whitespace-nowrap">
                                                                        {formatDistance(notification.created_at)}
                                                                    </time>
                                                                </HoverCardTrigger>
                                                                <HoverCardContent className="w-fit">
                                                                    {formatDate(notification.created_at)}
                                                                </HoverCardContent>
                                                            </HoverCard>
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
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Pagination */}
                                {totalPages > 1 && (
                                    <div className="border-border bg-card/50 flex items-center justify-center gap-2 border-t py-3 sm:py-4">
                                        <div className="flex flex-wrap gap-1">
                                            {pageNumbers.map((pageNum) => (
                                                <Button
                                                    key={pageNum}
                                                    variant={pageNum === currentPage ? 'default' : 'outline'}
                                                    size="sm"
                                                    className={cn(
                                                        'h-7 w-7 p-0 sm:h-8 sm:w-8',
                                                        pageNum === currentPage && 'bg-primary text-primary-foreground hover:bg-primary/90',
                                                        pageNum !== currentPage && 'text-muted-foreground hover:text-foreground',
                                                    )}
                                                    onClick={() =>
                                                        router.get(
                                                            window.location.pathname,
                                                            { page: pageNum },
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {pageNum}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Notification Details Sheet */}
            <Sheet open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-lg">
                    <div className="bg-background sticky top-0 z-10 border-b">
                        <div className="flex items-center justify-between p-6">
                            <div className="space-y-1">
                                <SheetTitle className="text-xl font-semibold">{selectedNotification?.data.title}</SheetTitle>
                                <SheetDescription className="text-muted-foreground text-sm">
                                    {formatDate(selectedNotification?.created_at)}
                                </SheetDescription>
                            </div>
                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setIsDialogOpen(false)}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-8 p-6">
                        {/* Status Badge */}
                        <div className="flex items-center gap-2">
                            {getNotificationIcon(selectedNotification?.type || '')}
                            {getStatusBadge(getNotificationStatus(selectedNotification || ({} as Notification)))}
                        </div>

                        {/* Procurement Details */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <h4 className="text-sm font-medium">Procurement Details</h4>
                                <div className="bg-border h-px flex-1" />
                            </div>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">Procurement ID</div>
                                    <div className="font-medium">{selectedNotification?.data.procurement_id}</div>
                                </div>
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">Title</div>
                                    <div className="font-medium">{selectedNotification?.data.procurement_title}</div>
                                </div>
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">Stage</div>
                                    <div className="font-medium">{selectedNotification?.data.stage_identifier}</div>
                                </div>
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">Status</div>
                                    <div className="font-medium">{selectedNotification?.data.current_status}</div>
                                </div>
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">Action</div>
                                    <div className="font-medium">{selectedNotification?.data.action_type}</div>
                                </div>
                            </div>
                        </div>

                        {/* Timeline */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <h4 className="text-sm font-medium">Timeline</h4>
                                <div className="bg-border h-px flex-1" />
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-start gap-3">
                                    <div className="mt-1">
                                        <div className="bg-primary h-2 w-2 rounded-full" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="text-sm font-medium">Created</div>
                                        <div className="text-muted-foreground text-sm">{formatDate(selectedNotification?.created_at)}</div>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="mt-1">
                                        <div className="bg-muted-foreground h-2 w-2 rounded-full" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="text-sm font-medium">Updated</div>
                                        <div className="text-muted-foreground text-sm">{formatDate(selectedNotification?.updated_at)}</div>
                                    </div>
                                </div>
                                {selectedNotification?.read_at && (
                                    <div className="flex items-start gap-3">
                                        <div className="mt-1">
                                            <div className="h-2 w-2 rounded-full bg-green-500" />
                                        </div>
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium">Read</div>
                                            <div className="text-muted-foreground text-sm">{formatDate(selectedNotification?.read_at)}</div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Message */}
                        {selectedNotification?.data.transition_message && (
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-medium">Message</h4>
                                    <div className="bg-border h-px flex-1" />
                                </div>
                                <div className="bg-card rounded-lg border p-4">
                                    <p className="text-muted-foreground text-sm">{selectedNotification.data.transition_message}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="bg-background sticky bottom-0 z-10 border-t p-6">
                        <div className="flex gap-3">
                            <Button variant="outline" className="flex-1" onClick={() => setIsDialogOpen(false)}>
                                Close
                            </Button>
                            <Button
                                variant="default"
                                className="flex-1"
                                onClick={() => {
                                    if (selectedNotification?.data.procurement_id) {
                                        router.visit(`/${userRole?.replace('_', '-')}/procurements-list/${selectedNotification.data.procurement_id}`);
                                    }
                                }}
                            >
                                <ExternalLink className="mr-2 h-4 w-4" />
                                View Procurement
                            </Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
