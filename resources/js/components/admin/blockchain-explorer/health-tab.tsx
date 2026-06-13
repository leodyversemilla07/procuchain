import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import type { HealthStatus } from '@/types';
import { Activity, AlertCircle, CheckCircle, Shield, XCircle } from 'lucide-react';

interface HealthTabProps {
    health: HealthStatus | null;
    isHealthy: boolean;
    isCircuitOpen: boolean;
    isResetDialogOpen: boolean;
    setIsResetDialogOpen: (open: boolean) => void;
    handleResetCircuitBreaker: () => void;
}

export function HealthTab({ health, isHealthy, isCircuitOpen, isResetDialogOpen, setIsResetDialogOpen, handleResetCircuitBreaker }: HealthTabProps) {
    if (!health) {
        return (
            <Card>
                <CardContent>
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Shield />
                            </EmptyMedia>
                            <EmptyTitle>Health Data Unavailable</EmptyTitle>
                            <EmptyDescription>
                                Health monitoring data is currently not available. Please check your blockchain connection and try refreshing the
                                page.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {/* Overall Health Status */}
            <Card className={isHealthy ? 'border-primary/30 bg-primary/5' : 'border-destructive/30 bg-destructive/5'}>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {isHealthy ? <CheckCircle className="text-primary" /> : <XCircle className="text-destructive" />}
                            <div>
                                <CardTitle className="text-2xl">{isHealthy ? 'System Healthy' : 'System Unhealthy'}</CardTitle>
                                <CardDescription>Last checked: {new Date(health.checked_at).toLocaleString()}</CardDescription>
                            </div>
                        </div>
                        <Badge variant={isHealthy ? 'default' : 'destructive'} className="px-4 py-2 text-lg">
                            {health.status.toUpperCase()}
                        </Badge>
                    </div>
                </CardHeader>
            </Card>

            <div className="grid gap-6 md:grid-cols-2">
                {/* Circuit Breaker Status */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Activity />
                                <CardTitle>Circuit Breaker</CardTitle>
                            </div>
                            <Badge variant={isCircuitOpen ? 'destructive' : 'secondary'}>{isCircuitOpen ? 'OPEN' : 'CLOSED'}</Badge>
                        </div>
                        <CardDescription>Protects system from cascading failures</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 p-4 sm:p-6">
                        <div className="flex flex-col gap-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Status</span>
                                <span className="font-medium">{isCircuitOpen ? 'Blocking Requests' : 'Allowing Requests'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Consecutive Failures</span>
                                <span className="font-medium">{health.circuit_breaker.failures}</span>
                            </div>
                            {health.circuit_breaker.recovery_time && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Recovery Time</span>
                                    <span className="font-medium">{new Date(health.circuit_breaker.recovery_time).toLocaleString()}</span>
                                </div>
                            )}
                        </div>

                        {isCircuitOpen && (
                            <div className="bg-muted/50 dark:bg-muted/50/20 rounded-lg border border-amber-200 p-3 sm:p-4 dark:border-amber-900">
                                <div className="flex gap-2">
                                    <AlertCircle />
                                    <div className="flex flex-1 flex-col gap-2">
                                        <p className="text-muted-foreground dark:text-muted-foreground text-sm font-medium">
                                            Circuit breaker is open
                                        </p>
                                        <p className="text-muted-foreground dark:text-muted-foreground text-sm">
                                            All blockchain requests are currently blocked due to repeated failures. The system will automatically
                                            retry after the recovery time.
                                        </p>
                                        <AlertDialog open={isResetDialogOpen} onOpenChange={setIsResetDialogOpen}>
                                            <AlertDialogTrigger render={<Button variant="outline" size="sm" className="mt-2" />}>
                                                Reset Circuit Breaker
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>Reset Circuit Breaker?</AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        This will allow blockchain requests to resume immediately. Are you sure you want to proceed?
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                    <AlertDialogAction onClick={handleResetCircuitBreaker}>Reset</AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Queue Metrics */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Activity />
                            <CardTitle>Queue Status</CardTitle>
                        </div>
                        <CardDescription>Background job processing metrics</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 p-4 sm:p-6">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Pending Jobs</span>
                            <Badge variant="secondary">{health.queue.pending_jobs}</Badge>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Failed Jobs (24h)</span>
                            <Badge variant={health.queue.failed_jobs_24h > 0 ? 'destructive' : 'secondary'}>{health.queue.failed_jobs_24h}</Badge>
                        </div>

                        {health.queue.failed_jobs_24h > 0 && (
                            <div className="bg-destructive/10 dark:bg-destructive/10/20 rounded-lg border border-red-200 p-3 wrap-break-word dark:border-red-900">
                                <p className="text-destructive dark:text-destructive text-sm">
                                    {health.queue.failed_jobs_24h} job{health.queue.failed_jobs_24h !== 1 ? 's' : ''} failed in the last 24 hours.
                                    Check the failed jobs queue for details.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Recommendations */}
            {(!isHealthy || isCircuitOpen || health.queue.failed_jobs_24h > 0) && (
                <Card className="bg-muted/50/50 dark:bg-muted/50/20 border-amber-200 dark:border-amber-900">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <AlertCircle />
                            <CardTitle>Recommended Actions</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="p-4 sm:p-6">
                        <ul className="flex list-inside list-disc flex-col gap-2 text-sm wrap-break-word">
                            {isCircuitOpen && <li>Circuit breaker is open - check blockchain node connectivity at 159.65.12.99:6487</li>}
                            {health.queue.failed_jobs_24h > 0 && (
                                <li>
                                    Review failed jobs:{' '}
                                    <code className="dark:bg-muted/10 rounded bg-black/10 px-1 py-0.5 text-xs">php artisan queue:failed</code>
                                </li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
