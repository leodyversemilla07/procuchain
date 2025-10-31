import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Activity, AlertTriangle, CheckCircle2, Clock, Globe, History, MapPin, Monitor, Smartphone, Tablet, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import admin from '@/routes/admin';

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
            const response = await fetch(admin.loginLogs.recent.url({ query: { limit: 100 } }));
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
            <DialogContent className="flex max-h-[90vh] max-w-[95vw] flex-col gap-0 overflow-hidden p-0 md:max-w-6xl lg:max-w-7xl">
                <DialogHeader className="shrink-0 border-b px-6 py-4">
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

                <div className="shrink-0 px-6 py-4">
                    {/* Statistics */}
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <Activity className="text-primary h-4 w-4" />
                                <span className="text-muted-foreground text-xs">Total Logins</span>
                            </div>
                            <p className="mt-1 text-xl font-bold md:text-2xl">{stats.total}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                <span className="text-muted-foreground text-xs">Successful</span>
                            </div>
                            <p className="mt-1 text-xl font-bold text-green-600 md:text-2xl dark:text-green-400">{stats.successful}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <XCircle className="text-destructive h-4 w-4" />
                                <span className="text-muted-foreground text-xs">Failed</span>
                            </div>
                            <p className="text-destructive mt-1 text-xl font-bold md:text-2xl">{stats.failed}</p>
                        </div>
                        <div className="bg-muted/50 rounded-lg p-3">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                <span className="text-muted-foreground text-xs">Success Rate</span>
                            </div>
                            <p className="mt-1 text-xl font-bold md:text-2xl">{stats.successRate}%</p>
                        </div>
                    </div>
                </div>

                <div className="flex-1 overflow-hidden px-6">
                    <div className="h-full overflow-auto pb-4">
                        {isLoading ? (
                            <div className="space-y-2 pt-2">
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
                            <div className="mt-2 overflow-x-auto rounded-md border">
                                <Table className="w-full">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="whitespace-nowrap">Status</TableHead>
                                            <TableHead className="whitespace-nowrap">Date & Time</TableHead>
                                            <TableHead className="whitespace-nowrap">Device</TableHead>
                                            <TableHead className="whitespace-nowrap">Location</TableHead>
                                            <TableHead className="whitespace-nowrap">Session</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {loginLogs.map((log) => {
                                            const DeviceIcon = getDeviceIcon(log.device_type);
                                            return (
                                                <TableRow key={log.id}>
                                                    <TableCell>
                                                        <Badge variant={log.successful ? 'default' : 'destructive'} className="whitespace-nowrap">
                                                            {log.successful ? 'Success' : 'Failed'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center space-x-2">
                                                            <Clock className="text-muted-foreground h-4 w-4 shrink-0" />
                                                            <span className="text-sm whitespace-nowrap">{formatDateTime(log.login_at)}</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center space-x-2">
                                                            <DeviceIcon className="text-muted-foreground h-4 w-4 shrink-0" />
                                                            <div className="min-w-0 space-y-1">
                                                                <div className="text-sm whitespace-nowrap capitalize">
                                                                    {log.device_type || 'Unknown'}
                                                                </div>
                                                                {log.browser && <div className="text-muted-foreground text-xs">{log.browser}</div>}
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="space-y-1">
                                                            <div className="flex items-center space-x-2">
                                                                <Globe className="text-muted-foreground h-4 w-4 shrink-0" />
                                                                <span className="font-mono text-sm whitespace-nowrap">{log.ip_address}</span>
                                                            </div>
                                                            {log.location && (
                                                                <div className="flex items-center space-x-1">
                                                                    <MapPin className="text-muted-foreground h-3 w-3 shrink-0" />
                                                                    <span className="text-muted-foreground text-xs">{log.location}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={log.logout_at ? 'secondary' : 'default'} className="whitespace-nowrap">
                                                            {getSessionDuration(log.login_at, log.logout_at)}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 justify-end border-t px-6 py-3">
                    <Button onClick={() => onOpenChange(false)} variant="outline">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
