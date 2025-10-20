import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Globe,
    History,
    MapPin,
    Monitor,
    Smartphone,
    Tablet,
    XCircle,
} from 'lucide-react';
import React, { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

interface LoginLog {
    id: number;
    user_id?: number;
    ip_address: string;
    user_agent?: string;
    device_type?: string;
    browser?: string;
    platform?: string;
    location?: string;
    successful: boolean;
    login_at: string;
    logout_at?: string;
}

interface UserLoginHistoryDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    userId: number | null;
    userName?: string;
}

export default function UserLoginHistoryDialog({ open, onOpenChange, userId, userName }: UserLoginHistoryDialogProps) {
    const [loginLogs, setLoginLogs] = useState<LoginLog[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    const fetchLoginHistory = useCallback(async () => {
        if (!userId) return;

        setIsLoading(true);
        try {
            // Fetch recent logins from the existing endpoint
            const response = await fetch(route('admin.login-logs.recent', { limit: 100 }));
            const data = await response.json();

            if (data.success) {
                // Filter logs for this specific user
                const userLogs = data.data.filter((log: LoginLog) => log.user_id === userId);
                setLoginLogs(userLogs);
            } else {
                toast.error('Failed to load login history');
            }
        } catch (error) {
            console.error('Error fetching login history:', error);
            toast.error('Failed to load login history');
        } finally {
            setIsLoading(false);
        }
    }, [userId]);

    useEffect(() => {
        if (open && userId) {
            fetchLoginHistory();
        }
    }, [open, userId, fetchLoginHistory]);

    const getDeviceIcon = (deviceType?: string) => {
        switch (deviceType?.toLowerCase()) {
            case 'mobile':
                return Smartphone;
            case 'tablet':
                return Tablet;
            case 'desktop':
            default:
                return Monitor;
        }
    };

    const formatDateTime = (dateString: string) => {
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid Date';
            return date.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return 'Invalid Date';
        }
    };

    const getSessionDuration = (loginAt: string, logoutAt?: string) => {
        if (!logoutAt) return 'Active';

        try {
            const login = new Date(loginAt);
            const logout = new Date(logoutAt);
            const duration = logout.getTime() - login.getTime();
            const hours = Math.floor(duration / (1000 * 60 * 60));
            const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));

            if (hours > 0) return `${hours}h ${minutes}m`;
            return `${minutes}m`;
        } catch {
            return 'Unknown';
        }
    };

    const stats = {
        total: loginLogs.length,
        successful: loginLogs.filter((l) => l.successful).length,
        failed: loginLogs.filter((l) => !l.successful).length,
        successRate: loginLogs.length > 0 ? Math.round((loginLogs.filter((l) => l.successful).length / loginLogs.length) * 100) : 0,
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-5xl gap-0 p-0">
                <DialogHeader className="border-b px-6 py-6 pb-4">
                    <div className="flex items-center space-x-3">
                        <div className="bg-primary/10 dark:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <History className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-foreground text-xl font-semibold">Login History</DialogTitle>
                            <DialogDescription className="text-muted-foreground mt-1 text-sm">
                                {userName ? `Login activity for ${userName}` : 'User login activity'}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="px-6 py-4">
                    {/* Statistics */}
                    <div className="mb-4 grid grid-cols-4 gap-4">
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <Activity className="text-primary h-4 w-4" />
                                <span className="text-muted-foreground text-xs">Total Logins</span>
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                <span className="text-muted-foreground text-xs">Successful</span>
                            </div>
                            <p className="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{stats.successful}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <XCircle className="h-4 w-4 text-destructive" />
                                <span className="text-muted-foreground text-xs">Failed</span>
                            </div>
                            <p className="mt-1 text-2xl font-bold text-destructive">{stats.failed}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                <span className="text-muted-foreground text-xs">Success Rate</span>
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.successRate}%</p>
                        </div>
                    </div>
                </div>

                <ScrollArea className="max-h-[500px]">
                    <div className="px-6 pb-6">
                        {isLoading ? (
                            <div className="space-y-2">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="flex items-center space-x-4">
                                        <Skeleton className="h-12 w-12 rounded" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-4 w-full" />
                                            <Skeleton className="h-3 w-3/4" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : loginLogs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <History className="text-muted-foreground mb-4 h-12 w-12" />
                                <h3 className="mb-2 text-lg font-semibold">No Login History</h3>
                                <p className="text-muted-foreground text-sm">This user has no recorded login activity yet.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date & Time</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>Location</TableHead>
                                        <TableHead>Session</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loginLogs.map((log) => {
                                        const DeviceIcon = getDeviceIcon(log.device_type);
                                        return (
                                            <TableRow key={log.id}>
                                                <TableCell>
                                                    <Badge variant={log.successful ? 'default' : 'destructive'}>
                                                        {log.successful ? 'Success' : 'Failed'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        <Clock className="text-muted-foreground h-4 w-4" />
                                                        <span className="text-sm">{formatDateTime(log.login_at)}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center space-x-2">
                                                        <DeviceIcon className="text-muted-foreground h-4 w-4" />
                                                        <div className="space-y-1">
                                                            <div className="text-sm capitalize">{log.device_type || 'Unknown'}</div>
                                                            {log.browser && (
                                                                <div className="text-muted-foreground text-xs">{log.browser}</div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="flex items-center space-x-2">
                                                            <Globe className="text-muted-foreground h-4 w-4" />
                                                            <span className="font-mono text-sm">{log.ip_address}</span>
                                                        </div>
                                                        {log.location && (
                                                            <div className="flex items-center space-x-1">
                                                                <MapPin className="text-muted-foreground h-3 w-3" />
                                                                <span className="text-muted-foreground text-xs">{log.location}</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={log.logout_at ? 'secondary' : 'default'}>
                                                        {getSessionDuration(log.login_at, log.logout_at)}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </ScrollArea>

                <div className="flex justify-end border-t px-6 py-4">
                    <Button onClick={() => onOpenChange(false)} variant="outline">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
