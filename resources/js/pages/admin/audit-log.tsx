import { DiffView } from '@/components/diff-view';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import adminAuditLog from '@/routes/admin/audit-log';
import { Head, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ArrowDownUp, CalendarIcon, FileSearch, FilterX, ScrollText, Search } from 'lucide-react';
import { useState } from 'react';
import { type DateRange } from 'react-day-picker';

interface Actor {
    id: number;
    name: string;
    email: string;
}

interface AuditLogEntry {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    actor: Actor | null;
}

interface PaginatedLogs {
    data: AuditLogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface PageProps {
    [key: string]: unknown;
    logs: PaginatedLogs;
    distinctActions: string[];
    filters: {
        action?: string;
        user_id?: string;
        date_from?: string;
        date_to?: string;
    };
    error?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Audit Log', href: adminAuditLog.index.url() },
];

const ACTION_LABELS: Record<string, string> = {
    'user.created': 'User Created',
    'user.updated': 'User Updated',
    'user.deleted': 'User Deleted',
    'user.bulk_deleted': 'Bulk User Deletion',
    'user.password_reset_sent': 'Password Reset Sent',
    'account.locked': 'Account Locked',
    'account.unlocked': 'Account Unlocked',
    'account.attempts_reset': 'Login Attempts Reset',
    'account.bulk_unlocked': 'Bulk Accounts Unlocked',
    'account.bulk_attempts_reset': 'Bulk Attempts Reset',
};

const ACTION_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    'user.created': 'default',
    'user.updated': 'secondary',
    'user.deleted': 'destructive',
    'user.bulk_deleted': 'destructive',
    'user.password_reset_sent': 'secondary',
    'account.locked': 'destructive',
    'account.unlocked': 'default',
    'account.attempts_reset': 'secondary',
    'account.bulk_unlocked': 'default',
    'account.bulk_attempts_reset': 'secondary',
};

export default function AuditLog() {
    const { logs, distinctActions, filters, error } = usePage<PageProps>().props;
    const safeLogs = logs ?? {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
        links: [],
    };

    const [action, setAction] = useState(filters.action ?? '');
    const [userId, setUserId] = useState(filters.user_id ?? '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: filters.date_from ? parseISO(filters.date_from) : undefined,
        to: filters.date_to ? parseISO(filters.date_to) : undefined,
    });

    const applyFilters = () => {
        router.get(
            '/admin/audit-log',
            {
                ...(action && action !== 'all' ? { action } : {}),
                ...(userId ? { user_id: userId } : {}),
                ...(dateRange?.from ? { date_from: format(dateRange.from, 'yyyy-MM-dd') } : {}),
                ...(dateRange?.to ? { date_to: format(dateRange.to, 'yyyy-MM-dd') } : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setAction('');
        setUserId('');
        setDateRange(undefined);
        router.get('/admin/audit-log', {}, { preserveState: false, replace: true });
    };

    const hasActiveFilters = !!(filters.action || filters.user_id || filters.date_from || filters.date_to);
    const selectedActionLabel = action && action !== 'all' ? (ACTION_LABELS[action] ?? action) : 'All actions';

    const goToPage = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard icon={ScrollText} title="Audit Log" description="Track all admin actions performed on user accounts." />

                {error && <div className="bg-destructive/10 text-destructive rounded-md px-4 py-3 text-sm">{error}</div>}

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Search className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Select value={action || 'all'} onValueChange={(value) => value && setAction(value)}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All actions">{() => selectedActionLabel}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">All actions</SelectItem>
                                        {distinctActions.map((a) => (
                                            <SelectItem key={a} value={a}>
                                                {ACTION_LABELS[a] ?? a}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <Input type="number" placeholder="User ID" value={userId} onChange={(e) => setUserId(e.target.value)} />

                            <Popover>
                                <PopoverTrigger
                                    render={
                                        <Button
                                            variant="outline"
                                            className={cn('w-full justify-start text-left font-normal', !dateRange?.from && 'text-muted-foreground')}
                                        />
                                    }
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {dateRange?.from ? (
                                        dateRange.to ? (
                                            <>
                                                {format(dateRange.from, 'MMM d, yyyy')} - {format(dateRange.to, 'MMM d, yyyy')}
                                            </>
                                        ) : (
                                            format(dateRange.from, 'MMM d, yyyy')
                                        )
                                    ) : (
                                        <span>Date range</span>
                                    )}
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        initialFocus
                                        mode="range"
                                        defaultMonth={dateRange?.from}
                                        selected={dateRange}
                                        onSelect={setDateRange}
                                        numberOfMonths={2}
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </CardContent>
                    <CardFooter className="flex gap-2">
                        <Button onClick={applyFilters}>
                            <Search className="mr-2 h-4 w-4" />
                            Apply Filters
                        </Button>
                        {hasActiveFilters && (
                            <Button variant="outline" onClick={clearFilters}>
                                <FilterX className="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </CardFooter>
                </Card>

                {/* Log Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span>
                                {safeLogs.total.toLocaleString()} entr{safeLogs.total === 1 ? 'y' : 'ies'}
                            </span>
                            {hasActiveFilters && <Badge variant="secondary">Filtered</Badge>}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {safeLogs.data.length === 0 ? (
                            <Empty className="py-16">
                                <EmptyMedia>
                                    <FileSearch className="h-12 w-12" />
                                </EmptyMedia>
                                <EmptyHeader>
                                    <EmptyTitle>No audit log entries</EmptyTitle>
                                    <EmptyDescription>
                                        {hasActiveFilters
                                            ? 'No entries match the current filters.'
                                            : 'Admin actions will appear here once they occur.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Timestamp</TableHead>
                                        <TableHead>Actor</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>Details</TableHead>
                                        <TableHead>IP Address</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {safeLogs.data.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="text-muted-foreground text-xs whitespace-nowrap">
                                                {format(new Date(entry.created_at), 'MMM d, yyyy HH:mm:ss')}
                                            </TableCell>
                                            <TableCell>
                                                {entry.actor ? (
                                                    <div className="text-sm">
                                                        <div className="font-medium">{entry.actor.name}</div>
                                                        <div className="text-muted-foreground text-xs">{entry.actor.email}</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs italic">System</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={ACTION_VARIANTS[entry.action] ?? 'outline'}>
                                                    {ACTION_LABELS[entry.action] ?? entry.action}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {entry.subject_type && <span className="capitalize">{entry.subject_type}</span>}
                                                {entry.subject_id && <span className="ml-1 font-mono">#{entry.subject_id}</span>}
                                                {!entry.subject_type && !entry.subject_id && '—'}
                                            </TableCell>
                                            <TableCell className="max-w-xs">
                                                {entry.new_values && entry.old_values ? (
                                                    <DiffView oldValues={entry.old_values} newValues={entry.new_values} />
                                                ) : entry.new_values ? (
                                                    <pre className="bg-muted/50 overflow-x-auto rounded p-1 text-xs">
                                                        {JSON.stringify(entry.new_values, null, 1)}
                                                    </pre>
                                                ) : entry.old_values ? (
                                                    <pre className="overflow-x-auto rounded bg-red-50 p-1 text-xs dark:bg-red-950/20">
                                                        {JSON.stringify(entry.old_values, null, 1)}
                                                    </pre>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground font-mono text-xs">{entry.ip_address ?? '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                    {safeLogs.last_page > 1 && (
                        <CardFooter className="flex items-center justify-between pt-4">
                            <span className="text-muted-foreground text-sm">
                                Page {logs.current_page} of {logs.last_page}
                            </span>
                            <div className="flex gap-2">
                                {safeLogs.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => goToPage(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </CardFooter>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
