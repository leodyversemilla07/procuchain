import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import admin from '@/routes/admin';
import { Activity, AlertTriangle, CheckCircle2, Clock, Globe, History, MapPin, Monitor, Smartphone, Tablet, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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

interface UserLoginHistorySheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    userId: number | null;
    userName?: string;
}

export default function UserLoginHistorySheet({ open, onOpenChange, userId, userName }: UserLoginHistorySheetProps) {
    const [loginLogs, setLoginLogs] = useState<LoginLog[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < 768); // md breakpoint for table vs cards
        };

        checkMobile();
        window.addEventListener('resize', checkMobile);
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

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

    // Statistics component
    const StatsSection = () => (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div className="bg-muted/50 rounded-lg p-3">
                <div className="flex items-center gap-2">
                    <Activity />
                    <span className="text-muted-foreground text-xs">Total Logins</span>
                </div>
                <p className="mt-1 text-lg font-bold sm:text-xl md:text-2xl">{stats.total}</p>
            </div>
            <div className="bg-muted/50 rounded-lg p-3">
                <div className="flex items-center gap-2">
                    <CheckCircle2 />
                    <span className="text-muted-foreground text-xs">Successful</span>
                </div>
                <p className="mt-1 text-lg font-bold text-primary sm:text-xl md:text-2xl dark:text-primary">{stats.successful}</p>
            </div>
            <div className="bg-muted/50 rounded-lg p-3">
                <div className="flex items-center gap-2">
                    <XCircle />
                    <span className="text-muted-foreground text-xs">Failed</span>
                </div>
                <p className="text-destructive mt-1 text-lg font-bold sm:text-xl md:text-2xl">{stats.failed}</p>
            </div>
            <div className="bg-muted/50 rounded-lg p-3">
                <div className="flex items-center gap-2">
                    <AlertTriangle />
                    <span className="text-muted-foreground text-xs">Success Rate</span>
                </div>
                <p className="mt-1 text-lg font-bold sm:text-xl md:text-2xl">{stats.successRate}%</p>
            </div>
        </div>
    );

    // Mobile card view for login logs
    const MobileLoginCards = () => (
        <div className="flex flex-col gap-3 pt-2">
            {loginLogs.map((log) => {
                const DeviceIcon = getDeviceIcon(log.device_type);
                return (
                    <Card key={log.id}>
                        <CardContent className="p-4">
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center justify-between">
                                    <Badge variant={log.successful ? 'default' : 'destructive'}>{log.successful ? 'Success' : 'Failed'}</Badge>
                                    <Badge variant={log.logout_at ? 'secondary' : 'default'}>{getSessionDuration(log.login_at, log.logout_at)}</Badge>
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-2">
                                    <div className="flex items-start gap-2">
                                        <Clock />
                                        <div className="min-w-0 flex-1">
                                            <div className="text-muted-foreground text-xs font-medium">Date & Time</div>
                                            <div className="text-sm">{formatDateTime(log.login_at)}</div>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-2">
                                        <DeviceIcon className="text-muted-foreground mt-0.5 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <div className="text-muted-foreground text-xs font-medium">Device</div>
                                            <div className="text-sm capitalize">{log.device_type || 'Unknown'}</div>
                                            {log.browser && <div className="text-muted-foreground text-xs">{log.browser}</div>}
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-2">
                                        <Globe className="text-muted-foreground mt-0.5 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <div className="text-muted-foreground text-xs font-medium">Location</div>
                                            <div className="font-mono text-sm break-all">{log.ip_address}</div>
                                            {log.location && (
                                                <div className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                                                    <MapPin className="shrink-0" />
                                                    <span>{log.location}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );

    // Desktop table view
    const DesktopLoginTable = () => (
        <div className="mt-2 overflow-x-auto rounded-md border">
            <Table className="w-full">
                <TableHeader>
                    <TableRow>
                        <TableHead className="whitespace-nowrap">Status</TableHead>
                        <TableHead className="whitespace-nowrap">Date & Time</TableHead>
                        <TableHead className="hidden whitespace-nowrap md:table-cell">Device</TableHead>
                        <TableHead className="whitespace-nowrap">Location</TableHead>
                        <TableHead className="hidden whitespace-nowrap lg:table-cell">Session</TableHead>
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
                                    <div className="flex items-center gap-2">
                                        <Clock />
                                        <span className="text-sm whitespace-nowrap">{formatDateTime(log.login_at)}</span>
                                    </div>
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    <div className="flex items-center gap-2">
                                        <DeviceIcon />
                                        <div className="min-w-0 flex flex-col gap-1">
                                            <div className="text-sm whitespace-nowrap capitalize">{log.device_type || 'Unknown'}</div>
                                            {log.browser && <div className="text-muted-foreground text-xs">{log.browser}</div>}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-1">
                                        <div className="flex items-center gap-2">
                                            <Globe />
                                            <span className="font-mono text-sm whitespace-nowrap">{log.ip_address}</span>
                                        </div>
                                        {log.location && (
                                            <div className="flex items-center gap-1">
                                                <MapPin className="text-muted-foreground h-3 w-3 shrink-0" />
                                                <span className="text-muted-foreground text-xs">{log.location}</span>
                                            </div>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="hidden lg:table-cell">
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
    );

    // Content section
    const ContentSection = () => {
        if (isLoading) {
            return (
                <div className="flex flex-col gap-2 pt-2">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-4">
                            <Skeleton className="h-12 w-12 rounded" />
                            <div className="flex-1 flex flex-col gap-2">
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-3 w-3/4" />
                            </div>
                        </div>
                    ))}
                </div>
            );
        }

        if (loginLogs.length === 0) {
            return (
                <div className="flex flex-col items-center justify-center py-12 text-center">
                    <History className="text-muted-foreground mb-4 h-12 w-12" />
                    <h3 className="mb-2 text-lg font-semibold">No Login History</h3>
                    <p className="text-muted-foreground text-sm">This user has no recorded login activity yet.</p>
                </div>
            );
        }

        return isMobile ? <MobileLoginCards /> : <DesktopLoginTable />;
    };

    // Render Sheet consistently across all devices
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl md:max-w-3xl lg:max-w-4xl">
                <SheetHeader className="shrink-0 border-b p-4 sm:px-6 sm:py-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 dark:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <History />
                        </div>
                        <div>
                            <SheetTitle className="text-foreground text-xl font-semibold">Login History</SheetTitle>
                            <SheetDescription className="text-muted-foreground mt-1 text-sm">
                                {userName ? `Login activity for ${userName}` : 'User login activity'}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="shrink-0 p-4 sm:px-6 sm:py-4">
                    <StatsSection />
                </div>

                <div className="flex-1 overflow-auto px-4 pb-4 sm:px-6">
                    <ContentSection />
                </div>

                <SheetFooter className="shrink-0 border-t p-4 sm:px-6 sm:py-3">
                    <SheetClose render={<Button variant="outline" />}>Close</SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
