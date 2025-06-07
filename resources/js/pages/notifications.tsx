import { useState, useEffect, useCallback, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { formatDistanceToNow, format } from 'date-fns';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Bell, CheckCheck, Clock, AlertCircle, RotateCw, Check, Filter, ExternalLink, X } from 'lucide-react';
import { toast } from "sonner";
import axios from 'axios';
import { cn } from '@/lib/utils';
import { BreadcrumbItem } from '@/types';
import { User } from '@/types';

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

interface NotificationResponse {
    notifications: Notification[];
    pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
    unread_count: number;
}

type FilterType = 'all' | 'read' | 'unread';

const ITEMS_PER_PAGE = 10;

const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_chairman':
            return [
                { title: 'BAC Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Notifications', href: '/notifications' },
            ];
        case 'hope':
            return [
                { title: 'HOPE Dashboard', href: '/hope/dashboard' },
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

export default function Notifications() {
    const { auth } = usePage().props as unknown as { auth: { user: User } };
    const userRole = auth.user?.role;
    const breadcrumbs = getBreadcrumbs(userRole);

    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [allNotifications, setAllNotifications] = useState<Notification[]>([]);
    const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [pagination, setPagination] = useState({
        currentPage: 1,
        lastPage: 1,
        total: 0,
        perPage: ITEMS_PER_PAGE,
    });
    const [loading, setLoading] = useState(true);
    const [unreadCount, setUnreadCount] = useState(0);
    const [filter, setFilter] = useState<FilterType>('all');
    const [refreshing, setRefreshing] = useState(false);

    const filterNotifications = useCallback((notifications: Notification[], filterType: FilterType) => {
        if (filterType === 'read') return notifications.filter(n => n.read_at !== null);
        if (filterType === 'unread') return notifications.filter(n => n.read_at === null);
        return notifications;
    }, []);

    const updatePaginatedData = useCallback((filteredData: Notification[], page: number) => {
        const totalPages = Math.max(1, Math.ceil(filteredData.length / ITEMS_PER_PAGE));
        const validPage = Math.min(Math.max(1, page), totalPages);
        const startIndex = (validPage - 1) * ITEMS_PER_PAGE;
        const endIndex = startIndex + ITEMS_PER_PAGE;

        setNotifications(filteredData.slice(startIndex, endIndex));
        setPagination(prev => ({
            ...prev,
            currentPage: validPage,
            lastPage: totalPages,
            total: filteredData.length,
        }));
    }, []);

    const fetchNotifications = useCallback(async (page = 1) => {
        try {
            setLoading(true);
            const response = await axios.get<NotificationResponse>(`/notifications/list`);

            setAllNotifications(response.data.notifications);
            const filteredData = filterNotifications(response.data.notifications, filter);
            updatePaginatedData(filteredData, page);

            setUnreadCount(response.data.notifications.filter(n => !n.read_at).length);
        } catch {
            toast.error('Failed to fetch notifications');
            setNotifications([]);
            setAllNotifications([]);
            setPagination(prev => ({ ...prev, currentPage: 1, lastPage: 1, total: 0 }));
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [filter, filterNotifications, updatePaginatedData]);

    const handleFilterChange = useCallback((newFilter: FilterType) => {
        setFilter(newFilter);
        const filteredData = filterNotifications(allNotifications, newFilter);
        updatePaginatedData(filteredData, 1);
    }, [allNotifications, filterNotifications, updatePaginatedData]);

    const handleMarkAsRead = useCallback(async (id: string) => {
        try {
            await axios.post(`/notifications/${id}/mark-as-read`);
            const currentTime = new Date().toISOString();

            const updateNotificationList = (list: Notification[]) =>
                list.map(n => n.id === id ? { ...n, read_at: currentTime } : n);

            const updatedAllNotifications = updateNotificationList(allNotifications);
            setAllNotifications(updatedAllNotifications);

            const filteredData = filterNotifications(updatedAllNotifications, filter);
            updatePaginatedData(filteredData, pagination.currentPage);

            setUnreadCount(prev => Math.max(0, prev - 1));
            toast.success('Notification marked as read');
        } catch {
            toast.error('Failed to mark notification as read');
        }
    }, [allNotifications, filter, filterNotifications, pagination.currentPage, updatePaginatedData]);

    const handleMarkAllAsRead = useCallback(async () => {
        try {
            await axios.post('/notifications/mark-all-as-read');
            const currentTime = new Date().toISOString();

            const updatedAllNotifications = allNotifications.map(n => ({ ...n, read_at: currentTime }));
            setAllNotifications(updatedAllNotifications);

            const filteredData = filterNotifications(updatedAllNotifications, filter);
            updatePaginatedData(filteredData, 1);

            setUnreadCount(0);
            toast.success('All notifications marked as read');
        } catch {
            toast.error('Failed to mark all notifications as read');
        }
    }, [allNotifications, filter, filterNotifications, updatePaginatedData]);

    const handleRefresh = useCallback(() => {
        setRefreshing(true);
        fetchNotifications(pagination.currentPage);
    }, [fetchNotifications, pagination.currentPage]);

    useEffect(() => {
        fetchNotifications(1);
    }, [fetchNotifications]);

    const pageNumbers = useMemo(() =>
        Array.from({ length: pagination.lastPage }, (_, i) => i + 1),
        [pagination.lastPage]
    );

    const getNotificationIcon = (type: string) => {
        switch (type) {
            case 'success':
                return <CheckCheck className="h-5 w-5 text-primary" />;
            case 'warning':
                return <AlertCircle className="h-5 w-5 text-destructive" />;
            case 'info':
                return <Clock className="h-5 w-5 text-secondary-foreground" />;
            default:
                return <Bell className="h-5 w-5 text-muted-foreground" />;
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
        <div className="flex items-center justify-center min-h-[400px] w-full">
            <div className="text-center">
                <div className="rounded-full bg-muted/10 p-3 mb-4 mx-auto w-fit">
                    <Bell className="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 className="text-lg font-medium text-foreground mb-1">No notifications</h3>
                <p className="text-sm text-muted-foreground/70">You're all caught up!</p>
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
            <div className="flex flex-col h-full">
                {/* Header Section */}
                <div className="border-b bg-card">
                    <div className="px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="space-y-1">
                                <h1 className="text-xl sm:text-2xl font-bold tracking-tight">Notifications</h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Stay updated with your procurement activities and updates
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                                <Select value={filter} onValueChange={handleFilterChange}>
                                    <SelectTrigger className="w-[120px] sm:w-[140px]">
                                        <Filter className="w-4 h-4 mr-2 text-muted-foreground" />
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
                                    className={cn(
                                        "text-muted-foreground hover:text-foreground transition-all",
                                        refreshing && "animate-spin"
                                    )}
                                >
                                    <RotateCw className="h-4 w-4" />
                                </Button>
                                {notifications.some(n => !n.read_at) && (
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
                <div className="flex-1 px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                    <div className="flex flex-col gap-4 sm:gap-6">
                        {/* Stats Section */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="rounded-full bg-primary/10 p-2 sm:p-3">
                                            <Bell className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
                                        </div>
                                        <div>
                                            <p className="text-xs sm:text-sm font-medium text-muted-foreground">Total Notifications</p>
                                            <p className="text-xl sm:text-2xl font-bold">{pagination.total}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="rounded-full bg-yellow-500/10 p-2 sm:p-3">
                                            <AlertCircle className="h-5 w-5 sm:h-6 sm:w-6 text-yellow-500" />
                                        </div>
                                        <div>
                                            <p className="text-xs sm:text-sm font-medium text-muted-foreground">Unread</p>
                                            <p className="text-xl sm:text-2xl font-bold">{unreadCount}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="rounded-full bg-green-500/10 p-2 sm:p-3">
                                            <CheckCheck className="h-5 w-5 sm:h-6 sm:w-6 text-green-500" />
                                        </div>
                                        <div>
                                            <p className="text-xs sm:text-sm font-medium text-muted-foreground">Read</p>
                                            <p className="text-xl sm:text-2xl font-bold">{pagination.total - unreadCount}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Notifications List */}
                        <Card className="overflow-hidden">
                            <CardHeader className="bg-card border-b px-4 sm:px-6 py-3 sm:py-4">
                                <CardTitle className="text-base sm:text-lg">All Notifications</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                {loading ? (
                                    <div className="divide-y divide-border">
                                        <div className="space-y-4 p-4">
                                            {[...Array(3)].map((_, i) => (
                                                <div key={i} className="flex items-start gap-4">
                                                    <Skeleton className="h-8 w-8 sm:h-10 sm:w-10 rounded-full" />
                                                    <div className="space-y-2 flex-1">
                                                        <Skeleton className="h-4 w-[60%]" />
                                                        <Skeleton className="h-3 w-[80%]" />
                                                        <Skeleton className="h-3 w-[40%]" />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : notifications.length === 0 ? (
                                    <EmptyState />
                                ) : (
                                    <div className="divide-y divide-border">
                                        {notifications.map((notification) => (
                                            <div
                                                key={notification.id}
                                                className={cn(
                                                    "p-4 sm:p-6 flex items-start gap-3 sm:gap-4 transition-all relative group cursor-pointer",
                                                    !notification.read_at && "bg-primary/5 hover:bg-primary/10",
                                                    notification.read_at && "hover:bg-muted/5"
                                                )}
                                                onClick={() => handleNotificationClick(notification)}
                                            >
                                                <div className="flex-shrink-0">
                                                    {getNotificationIcon(notification.type)}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4">
                                                        <div className="flex-1">
                                                            <div className="flex flex-wrap items-center gap-2 mb-2">
                                                                <h3 className="font-medium text-foreground text-sm sm:text-base">
                                                                    {notification.data.procurement_title}
                                                                </h3>
                                                                {getStatusBadge(getNotificationStatus(notification))}
                                                            </div>
                                                            <div className="space-y-1">
                                                                <p className="text-xs sm:text-sm text-muted-foreground">
                                                                    Stage: {notification.data.stage_identifier} - {notification.data.action_type}
                                                                </p>
                                                                {notification.data.transition_message && (
                                                                    <p className="text-xs sm:text-sm text-muted-foreground/80 line-clamp-2">
                                                                        {notification.data.transition_message}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex flex-col items-start sm:items-end gap-2">
                                                            <HoverCard>
                                                                <HoverCardTrigger asChild>
                                                                    <time className="text-xs text-muted-foreground/70 whitespace-nowrap cursor-help">
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
                                                                    className="opacity-0 group-hover:opacity-100 transition-opacity"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        handleMarkAsRead(notification.id);
                                                                    }}
                                                                >
                                                                    <Check className="w-4 h-4 mr-1" />
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
                                {pagination.lastPage > 1 && (
                                    <div className="flex items-center justify-center gap-2 py-3 sm:py-4 border-t border-border bg-card/50">
                                        <div className="flex flex-wrap gap-1">
                                            {pageNumbers.map((pageNum) => (
                                                <Button
                                                    key={pageNum}
                                                    variant={pageNum === pagination.currentPage ? "default" : "outline"}
                                                    size="sm"
                                                    className={cn(
                                                        "w-7 h-7 sm:w-8 sm:h-8 p-0",
                                                        pageNum === pagination.currentPage && "bg-primary text-primary-foreground hover:bg-primary/90",
                                                        pageNum !== pagination.currentPage && "text-muted-foreground hover:text-foreground"
                                                    )}
                                                    onClick={() => fetchNotifications(pageNum)}
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
                <SheetContent className="w-full sm:max-w-lg overflow-y-auto p-0">
                    <div className="sticky top-0 z-10 bg-background border-b">
                        <div className="flex items-center justify-between p-6">
                            <div className="space-y-1">
                                <SheetTitle className="text-xl font-semibold">
                                    {selectedNotification?.data.title}
                                </SheetTitle>
                                <SheetDescription className="text-sm text-muted-foreground">
                                    {formatDate(selectedNotification?.created_at)}
                                </SheetDescription>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8"
                                onClick={() => setIsDialogOpen(false)}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="p-6 space-y-8">
                        {/* Status Badge */}
                        <div className="flex items-center gap-2">
                            {getNotificationIcon(selectedNotification?.type || '')}
                            {getStatusBadge(getNotificationStatus(selectedNotification || {} as Notification))}
                        </div>

                        {/* Procurement Details */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <h4 className="font-medium text-sm">Procurement Details</h4>
                                <div className="h-px flex-1 bg-border" />
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
                                <h4 className="font-medium text-sm">Timeline</h4>
                                <div className="h-px flex-1 bg-border" />
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-start gap-3">
                                    <div className="mt-1">
                                        <div className="h-2 w-2 rounded-full bg-primary" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="text-sm font-medium">Created</div>
                                        <div className="text-sm text-muted-foreground">
                                            {formatDate(selectedNotification?.created_at)}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="mt-1">
                                        <div className="h-2 w-2 rounded-full bg-muted-foreground" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="text-sm font-medium">Updated</div>
                                        <div className="text-sm text-muted-foreground">
                                            {formatDate(selectedNotification?.updated_at)}
                                        </div>
                                    </div>
                                </div>
                                {selectedNotification?.read_at && (
                                    <div className="flex items-start gap-3">
                                        <div className="mt-1">
                                            <div className="h-2 w-2 rounded-full bg-green-500" />
                                        </div>
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium">Read</div>
                                            <div className="text-sm text-muted-foreground">
                                                {formatDate(selectedNotification?.read_at)}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Message */}
                        {selectedNotification?.data.transition_message && (
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <h4 className="font-medium text-sm">Message</h4>
                                    <div className="h-px flex-1 bg-border" />
                                </div>
                                <div className="rounded-lg border bg-card p-4">
                                    <p className="text-sm text-muted-foreground">
                                        {selectedNotification.data.transition_message}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="sticky bottom-0 z-10 bg-background border-t p-6">
                        <div className="flex gap-3">
                            <Button
                                variant="outline"
                                className="flex-1"
                                onClick={() => setIsDialogOpen(false)}
                            >
                                Close
                            </Button>
                            <Button
                                variant="default"
                                className="flex-1"
                                onClick={() => {
                                    if (selectedNotification?.data.procurement_id) {
                                        window.location.href = `/${userRole?.replace('_', '-')}/procurements-list/${selectedNotification.data.procurement_id}`;
                                    }
                                }}
                            >
                                <ExternalLink className="w-4 h-4 mr-2" />
                                View Procurement
                            </Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}