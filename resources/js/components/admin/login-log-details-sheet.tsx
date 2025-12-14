import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
    AlertTriangle,
    Calendar,
    CheckCircle2,
    Clock,
    Globe,
    Lock,
    LockOpen,
    MapPin,
    Monitor,
    ShieldCheck,
    Smartphone,
    Tablet,
    User,
    XCircle,
} from 'lucide-react';

interface LoginLog {
    id: number;
    user_id?: number;
    user?: {
        id: number;
        name: string;
        email: string;
        primary_role: string;
        two_factor_enabled?: boolean;
        two_factor_confirmed_at?: string;
    };
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

interface LoginLogDetailsSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    log: LoginLog | null;
    category?: 'recent' | 'suspicious';
}

export default function LoginLogDetailsSheet({ open, onOpenChange, log, category }: LoginLogDetailsSheetProps) {
    if (!log) return null;

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

    const DeviceIcon = getDeviceIcon(log.device_type);

    const formatDateTime = (dateString: string) => {
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return 'Invalid Date';
            }
            return date.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        } catch {
            return 'Invalid Date';
        }
    };

    const getSessionDuration = (loginAt: string, logoutAt?: string) => {
        if (!logoutAt) return 'Active Session';

        try {
            const login = new Date(loginAt);
            const logout = new Date(logoutAt);
            const duration = logout.getTime() - login.getTime();
            const hours = Math.floor(duration / (1000 * 60 * 60));
            const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((duration % (1000 * 60)) / 1000);

            if (hours > 0) {
                return `${hours}h ${minutes}m ${seconds}s`;
            }
            if (minutes > 0) {
                return `${minutes}m ${seconds}s`;
            }
            return `${seconds}s`;
        } catch {
            return 'Unknown';
        }
    };

    const getRoleDisplayName = (role?: string) => {
        if (!role) return 'Unknown';
        return role.replace(/_/g, ' ').toUpperCase();
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col sm:max-w-[700px]" side="right">
                <SheetHeader>
                    <div className="flex items-start justify-between">
                        <div className="space-y-1">
                            <SheetTitle>Login Details</SheetTitle>
                            <SheetDescription>
                                Login ID: #{log.id} • {formatDateTime(log.login_at)}
                            </SheetDescription>
                        </div>
                        {category === 'suspicious' && (
                            <Badge variant="destructive" className="flex items-center gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                Suspicious
                            </Badge>
                        )}
                    </div>
                </SheetHeader>

                <div className="flex-1 space-y-6 overflow-y-auto py-6">
                    {/* Status Overview */}
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex items-start gap-3">
                                {log.successful ? (
                                    <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-green-600 dark:text-green-400" />
                                ) : (
                                    <XCircle className="text-destructive mt-0.5 h-5 w-5 shrink-0" />
                                )}
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">Status</p>
                                    <Badge variant={log.successful ? 'default' : 'destructive'}>
                                        {log.successful ? 'Successful Login' : 'Failed Login'}
                                    </Badge>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Clock className="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">Session Duration</p>
                                    <p className="text-muted-foreground text-sm">{getSessionDuration(log.login_at, log.logout_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* User Information */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <User className="text-muted-foreground h-4 w-4" />
                            <h3 className="text-sm font-semibold">User Information</h3>
                        </div>
                        <div className="space-y-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-xs">Name</p>
                                    <p className="mt-1 font-medium">{log.user?.name || 'Unknown User'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Email</p>
                                    <p className="mt-1 font-medium">{log.user?.email || 'Unknown Email'}</p>
                                </div>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-xs">Role</p>
                                    <Badge variant="secondary" className="mt-1">
                                        {getRoleDisplayName(log.user?.primary_role)}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Two-Factor Authentication</p>
                                    <div className="mt-1 flex items-center gap-2">
                                        {log.user?.two_factor_enabled ? (
                                            <>
                                                <ShieldCheck className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                <Badge variant="default" className="text-xs">
                                                    Enabled
                                                </Badge>
                                            </>
                                        ) : (
                                            <>
                                                <LockOpen className="text-muted-foreground h-4 w-4" />
                                                <Badge variant="secondary" className="text-xs">
                                                    Disabled
                                                </Badge>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Network & Location */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <Globe className="text-muted-foreground h-4 w-4" />
                            <h3 className="text-sm font-semibold">Network & Location</h3>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-muted-foreground text-xs">IP Address</p>
                                <div className="mt-1 flex items-center gap-2">
                                    <Globe className="text-muted-foreground h-4 w-4" />
                                    <code className="bg-muted rounded px-2 py-1 font-mono text-sm">{log.ip_address}</code>
                                </div>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs">Location</p>
                                <div className="mt-1 flex items-center gap-2">
                                    <MapPin className="text-muted-foreground h-4 w-4" />
                                    <p className="text-sm">{log.location || 'Unknown'}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Device & Browser Information */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <DeviceIcon className="text-muted-foreground h-4 w-4" />
                            <h3 className="text-sm font-semibold">Device & Browser</h3>
                        </div>
                        <div className="space-y-3">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <p className="text-muted-foreground text-xs">Device Type</p>
                                    <div className="mt-1 flex items-center gap-2">
                                        <DeviceIcon className="text-muted-foreground h-4 w-4" />
                                        <p className="text-sm capitalize">{log.device_type || 'Unknown'}</p>
                                    </div>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Browser</p>
                                    <p className="mt-1 text-sm">{log.browser || 'Unknown'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Platform</p>
                                    <p className="mt-1 text-sm">{log.platform || 'Unknown'}</p>
                                </div>
                            </div>
                            {log.user_agent && (
                                <div>
                                    <p className="text-muted-foreground text-xs">User Agent</p>
                                    <p className="text-muted-foreground mt-1 font-mono text-xs break-all">{log.user_agent}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    <Separator />

                    {/* Timeline */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <Calendar className="text-muted-foreground h-4 w-4" />
                            <h3 className="text-sm font-semibold">Session Timeline</h3>
                        </div>
                        <div className="space-y-3">
                            <div className="flex items-start gap-3">
                                <div className="bg-primary/10 dark:bg-primary/20 mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                    <Lock className="text-primary h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">Login Time</p>
                                    <p className="text-muted-foreground text-sm">{formatDateTime(log.login_at)}</p>
                                </div>
                            </div>
                            {log.logout_at && (
                                <>
                                    <div className="border-muted ml-4 h-4 border-l-2" />
                                    <div className="flex items-start gap-3">
                                        <div className="bg-muted mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                            <LockOpen className="text-muted-foreground h-4 w-4" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">Logout Time</p>
                                            <p className="text-muted-foreground text-sm">{formatDateTime(log.logout_at)}</p>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Security Information */}
                    {category === 'suspicious' && (
                        <>
                            <Separator />
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="text-destructive h-4 w-4" />
                                    <h3 className="text-sm font-semibold">Security Alert</h3>
                                </div>
                                <div className="bg-destructive/10 rounded-lg p-4">
                                    <div className="flex items-start gap-3">
                                        <AlertTriangle className="text-destructive mt-0.5 h-5 w-5 shrink-0" />
                                        <div>
                                            <p className="text-destructive font-medium">Suspicious Activity Detected</p>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                This login attempt has been flagged as suspicious. Please review the details carefully and take
                                                appropriate action if necessary.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
