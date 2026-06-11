import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type CombinedLog, formatDateTime, getSessionDuration, highlightSearchTerm } from '@/hooks/use-login-log-filters';
import { AlertTriangle, Clock, Eye, Globe, MapPin, Monitor, MoreVertical, QrCode, Shield, ShieldBan, Smartphone, Tablet } from 'lucide-react';
import { toast } from 'sonner';

interface LoginLogTableProps {
    logs: CombinedLog[];
    isLoading: boolean;
    isRefreshing: boolean;
    selectedLogs: Set<number>;
    onToggleSelectAll: () => void;
    onToggleLogSelection: (id: number) => void;
    debouncedSearchTerm: string;
    hasActiveFilters: boolean;
    onViewDetails: (log: CombinedLog, category: 'recent' | 'suspicious') => void;
    onBlockIpClick: (ip: string) => void;
    // Pagination
    pageIndex: number;
    pageSize: number;
    pageCount: number;
    totalItems: number;
    onPageChange: (i: number) => void;
    onPageSizeChange: (size: number) => void;
}

const getDeviceIcon = (deviceType?: string) => {
    switch (deviceType?.toLowerCase()) {
        case 'mobile':
            return <Smartphone />;
        case 'tablet':
            return <Tablet />;
        default:
            return <Monitor />;
    }
};

const getRoleBadge = (role?: string) => {
    if (!role) return <span className="text-muted-foreground text-xs">-</span>;
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        admin: 'destructive',
        super_admin: 'destructive',
        bac_secretariat: 'default',
        bac_chairperson: 'default',
        bac_member: 'secondary',
        bac_technical_working_group: 'secondary',
        procurement_officer: 'outline',
        end_user: 'outline',
        supplier: 'outline',
    };
    return (
        <Badge variant={variants[role.toLowerCase()] || 'secondary'} className="text-xs font-medium">
            {role.replace('_', ' ').toUpperCase()}
        </Badge>
    );
};

const getStatusBadge = (successful: boolean) => <Badge variant={successful ? 'default' : 'destructive'}>{successful ? 'Success' : 'Failed'}</Badge>;

export default function LoginLogTable({
    logs,
    isLoading,
    isRefreshing,
    selectedLogs,
    onToggleSelectAll,
    onToggleLogSelection,
    debouncedSearchTerm,
    hasActiveFilters,
    onViewDetails,
    onBlockIpClick,
    pageIndex,
    pageSize,
    pageCount,
    totalItems,
    onPageChange,
    onPageSizeChange,
}: LoginLogTableProps) {
    const loading = isLoading || isRefreshing;

    return (
        <Card>
            {/* Mobile Card View */}
            <div className="md:hidden">
                <CardContent className="flex flex-col gap-4 p-4">
                    {loading ? (
                        Array.from({ length: 3 }).map((_, i) => (
                            <Card key={`mobile-skeleton-${i}`}>
                                <CardContent className="flex flex-col gap-3 p-4">
                                    <div className="flex items-center justify-between">
                                        <Skeleton className="h-6 w-24" />
                                        <Skeleton className="h-6 w-16" />
                                    </div>
                                    <Skeleton className="h-4 w-full" />
                                    <Skeleton className="h-4 w-3/4" />
                                    <div className="flex gap-2">
                                        <Skeleton className="h-6 w-20" />
                                        <Skeleton className="h-6 w-20" />
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    ) : logs.length > 0 ? (
                        logs.map((log) => (
                            <Card
                                key={`mobile-${log.category}-${log.id}`}
                                className={log.category === 'suspicious' ? 'border-destructive/50' : undefined}
                            >
                                <CardContent className="flex flex-col gap-3 p-4">
                                    <div className="flex items-center justify-between">
                                        {log.category === 'suspicious' ? (
                                            <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                                <AlertTriangle />
                                                Suspicious
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" className="text-xs">
                                                Recent
                                            </Badge>
                                        )}
                                        {getStatusBadge(log.successful)}
                                    </div>
                                    <div>
                                        <p className="font-medium">{log.user?.name || 'Unknown User'}</p>
                                        <p className="text-muted-foreground text-sm">{log.user?.email || 'Unknown Email'}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-muted-foreground text-xs">Role:</span>
                                        {getRoleBadge(log.user?.primary_role)}
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <p className="text-muted-foreground text-xs">IP Address</p>
                                            <p className="font-mono">{log.ip_address}</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">Device</p>
                                            <div className="flex items-center gap-1">
                                                {getDeviceIcon(log.device_type)}
                                                <span className="text-xs sm:text-sm">{log.device_type || 'Unknown'}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-xs">{formatDateTime(log.login_at)}</span>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger render={<Button variant="ghost" size="icon" />}>
                                                <MoreVertical />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuGroup>
                                                    <DropdownMenuItem onClick={() => onViewDetails(log, log.category)}>
                                                        <Eye className="h-4 w-4" />
                                                        View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => {
                                                            navigator.clipboard.writeText(log.ip_address);
                                                            toast.success('IP copied');
                                                        }}
                                                    >
                                                        <Globe className="h-4 w-4" />
                                                        Copy IP
                                                    </DropdownMenuItem>
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Shield className="h-6 w-6" />
                                </EmptyMedia>
                                <EmptyTitle>No login activities found</EmptyTitle>
                                <EmptyDescription>
                                    {hasActiveFilters ? 'No activities match your current filters.' : 'No login activities have been recorded yet.'}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    )}
                </CardContent>
            </div>

            {/* Desktop Table View */}
            <CardContent className="hidden p-0 md:block">
                <div className="overflow-x-auto">
                    <Table className="min-w-[1000px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12 pl-6">
                                    <Checkbox
                                        checked={selectedLogs.size === logs.length && logs.length > 0}
                                        onCheckedChange={onToggleSelectAll}
                                        aria-label="Select all"
                                    />
                                </TableHead>
                                <TableHead className="w-24">Category</TableHead>
                                <TableHead className="min-w-[180px]">User/Email</TableHead>
                                <TableHead className="w-28">Role</TableHead>
                                <TableHead className="w-20">2FA</TableHead>
                                <TableHead className="w-20">Status</TableHead>
                                <TableHead className="min-w-[140px]">IP Address</TableHead>
                                <TableHead className="w-24">Device</TableHead>
                                <TableHead className="w-24">Browser</TableHead>
                                <TableHead className="min-w-[150px]">Time</TableHead>
                                <TableHead className="w-20">Session</TableHead>
                                <TableHead className="w-12 pr-6">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loading ? (
                                Array.from({ length: 5 }).map((_, i) => (
                                    <TableRow key={`skeleton-${i}`}>
                                        {Array.from({ length: 12 }).map((_, j) => (
                                            <TableCell key={j} className={j === 0 ? 'pl-6' : j === 11 ? 'pr-6' : undefined}>
                                                <Skeleton className="h-4 w-20" />
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : logs.length > 0 ? (
                                logs.map((log) => (
                                    <TableRow
                                        key={`${log.category}-${log.id}`}
                                        className={log.category === 'suspicious' ? 'bg-destructive/5' : undefined}
                                    >
                                        <TableCell className="pl-6">
                                            <Checkbox
                                                checked={selectedLogs.has(log.id)}
                                                onCheckedChange={() => onToggleLogSelection(log.id)}
                                                aria-label={`Select log ${log.id}`}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {log.category === 'suspicious' ? (
                                                <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                                    <AlertTriangle />
                                                    Suspicious
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary" className="text-xs">
                                                    Recent
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="max-w-[180px] flex flex-col gap-1">
                                                <div className="truncate font-medium" title={log.user?.name || 'Unknown User'}>
                                                    {highlightSearchTerm(log.user?.name || 'Unknown User', debouncedSearchTerm)}
                                                </div>
                                                <div className="text-muted-foreground truncate text-sm" title={log.user?.email || 'Unknown Email'}>
                                                    {highlightSearchTerm(log.user?.email || 'Unknown Email', debouncedSearchTerm)}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>{getRoleBadge(log.user?.primary_role)}</TableCell>
                                        <TableCell>
                                            {log.user?.two_factor_enabled ? (
                                                <Badge className="border border-green-200 bg-primary/20 px-2 py-1 text-xs text-primary dark:border-green-800/30 dark:bg-primary/20/20 dark:text-primary">
                                                    <QrCode className="mr-1 h-3 w-3" />
                                                    Enabled
                                                </Badge>
                                            ) : (
                                                <Badge className="border border-border bg-muted px-2 py-1 text-xs text-foreground dark:border-border dark:bg-muted/50 dark:text-muted-foreground">
                                                    Disabled
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(log.successful)}</TableCell>
                                        <TableCell>
                                            <div className="max-w-[140px]">
                                                <div className="flex items-center gap-2">
                                                    <Globe />
                                                    <span className="truncate font-mono text-sm" title={log.ip_address}>
                                                        {highlightSearchTerm(log.ip_address, debouncedSearchTerm)}
                                                    </span>
                                                </div>
                                                {log.location && (
                                                    <div className="mt-1 flex items-center gap-1">
                                                        <MapPin className="text-muted-foreground h-3 w-3 shrink-0" />
                                                        <span className="text-muted-foreground truncate text-xs" title={log.location}>
                                                            {highlightSearchTerm(log.location, debouncedSearchTerm)}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                {getDeviceIcon(log.device_type)}
                                                <div className="max-w-20 flex flex-col gap-1">
                                                    <div className="truncate text-sm">{log.device_type || 'Unknown'}</div>
                                                    {log.platform && (
                                                        <div className="text-muted-foreground truncate text-xs" title={log.platform}>
                                                            {highlightSearchTerm(log.platform, debouncedSearchTerm)}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="max-w-[100px] truncate text-sm" title={log.browser || 'Unknown'}>
                                                {highlightSearchTerm(log.browser || 'Unknown', debouncedSearchTerm)}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Clock />
                                                <span className="text-sm text-nowrap">{formatDateTime(log.login_at)}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {log.category === 'recent' ? (
                                                <Badge variant={log.logout_at ? 'secondary' : 'default'}>
                                                    {getSessionDuration(log.login_at, log.logout_at)}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-sm">-</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="pr-6">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger render={<Button variant="ghost" size="icon" />}>
                                                    <MoreVertical />
                                                    <span className="sr-only">Open menu</span>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuItem onClick={() => onViewDetails(log, log.category)}>
                                                            <Eye className="h-4 w-4" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                    </DropdownMenuGroup>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                navigator.clipboard.writeText(log.ip_address);
                                                                toast.success('IP Address copied', { description: log.ip_address });
                                                            }}
                                                        >
                                                            <Globe className="h-4 w-4" />
                                                            Copy IP Address
                                                        </DropdownMenuItem>
                                                    </DropdownMenuGroup>
                                                    {log.category === 'suspicious' && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuGroup>
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onClick={() => onBlockIpClick(log.ip_address)}
                                                                >
                                                                    <ShieldBan />
                                                                    Block IP Address
                                                                </DropdownMenuItem>
                                                            </DropdownMenuGroup>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={12} className="h-96">
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <Shield className="h-6 w-6" />
                                                </EmptyMedia>
                                                <EmptyTitle>No login activities found</EmptyTitle>
                                                <EmptyDescription>
                                                    {hasActiveFilters
                                                        ? 'No activities match your current filters. Try adjusting your search criteria.'
                                                        : 'No login activities have been recorded yet.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>

            {logs.length > 0 && (
                <CardFooter className="justify-end">
                    <Pagination
                        pageIndex={pageIndex}
                        pageSize={pageSize}
                        pageCount={pageCount}
                        totalItems={totalItems}
                        onPageChange={onPageChange}
                        onPageSizeChange={onPageSizeChange}
                    />
                </CardFooter>
            )}
        </Card>
    );
}
