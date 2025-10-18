import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Activity, AlertCircle, CheckCircle, RefreshCw, XCircle } from 'lucide-react';

interface CircuitBreakerState {
    is_open: boolean;
    failures: number;
    recovery_time: string | null;
}

interface QueueMetrics {
    pending_jobs: number;
    failed_jobs_24h: number;
}

interface DocumentMetrics {
    pending_1h: number;
    failed_24h: number;
}

interface HealthStatus {
    status: 'healthy' | 'unhealthy';
    circuit_breaker: CircuitBreakerState;
    queue: QueueMetrics;
    documents: DocumentMetrics;
    checked_at: string;
}

interface Props {
    health: HealthStatus;
}

export default function BlockchainHealth({ health }: Props) {
    const isHealthy = health.status === 'healthy';
    const isCircuitOpen = health.circuit_breaker.is_open;

    const handleReset = () => {
        if (confirm('Are you sure you want to reset the circuit breaker? This will allow blockchain requests to resume immediately.')) {
            router.post(route('admin.blockchain.health.reset'), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['health'] });
                },
            });
        }
    };

    const handleRefresh = () => {
        router.reload({ only: ['health'] });
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Admin', href: route('admin.dashboard') },
            { title: 'Blockchain Health', href: '#' },
        ]}>
            <Head title="Blockchain Health" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Blockchain Health</h1>
                        <p className="text-muted-foreground">
                            Monitor blockchain connectivity and system status
                        </p>
                    </div>
                    <Button onClick={handleRefresh} variant="outline">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                {/* Overall Status Card */}
                <Card className={isHealthy ? 'border-green-200 bg-green-50/50 dark:border-green-900 dark:bg-green-950/20' : 'border-red-200 bg-red-50/50 dark:border-red-900 dark:bg-red-950/20'}>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                {isHealthy ? (
                                    <CheckCircle className="h-8 w-8 text-green-600 dark:text-green-400" />
                                ) : (
                                    <XCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                                )}
                                <div>
                                    <CardTitle className="text-2xl">
                                        {isHealthy ? 'System Healthy' : 'System Unhealthy'}
                                    </CardTitle>
                                    <CardDescription>
                                        Last checked: {new Date(health.checked_at).toLocaleString()}
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge variant={isHealthy ? 'default' : 'destructive'} className="text-lg px-4 py-2">
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
                                    <Activity className="h-5 w-5" />
                                    <CardTitle>Circuit Breaker</CardTitle>
                                </div>
                                <Badge variant={isCircuitOpen ? 'destructive' : 'secondary'}>
                                    {isCircuitOpen ? 'OPEN' : 'CLOSED'}
                                </Badge>
                            </div>
                            <CardDescription>
                                Protects system from cascading failures
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Status</span>
                                    <span className="font-medium">
                                        {isCircuitOpen ? 'Blocking Requests' : 'Allowing Requests'}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Consecutive Failures</span>
                                    <span className="font-medium">{health.circuit_breaker.failures}</span>
                                </div>
                                {health.circuit_breaker.recovery_time && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Recovery Time</span>
                                        <span className="font-medium">
                                            {new Date(health.circuit_breaker.recovery_time).toLocaleString()}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {isCircuitOpen && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                                    <div className="flex gap-2">
                                        <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                        <div className="space-y-2 flex-1">
                                            <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                                Circuit breaker is open
                                            </p>
                                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                                All blockchain requests are currently blocked due to repeated failures. 
                                                The system will automatically retry after the recovery time.
                                            </p>
                                            <Button 
                                                onClick={handleReset} 
                                                variant="outline" 
                                                size="sm"
                                                className="mt-2"
                                            >
                                                Reset Circuit Breaker
                                            </Button>
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
                                <Activity className="h-5 w-5" />
                                <CardTitle>Queue Status</CardTitle>
                            </div>
                            <CardDescription>
                                Background job processing metrics
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Pending Jobs</span>
                                <Badge variant="secondary">{health.queue.pending_jobs}</Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Failed Jobs (24h)</span>
                                <Badge variant={health.queue.failed_jobs_24h > 0 ? 'destructive' : 'secondary'}>
                                    {health.queue.failed_jobs_24h}
                                </Badge>
                            </div>

                            {health.queue.failed_jobs_24h > 0 && (
                                <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/20">
                                    <p className="text-sm text-red-900 dark:text-red-100">
                                        {health.queue.failed_jobs_24h} job{health.queue.failed_jobs_24h !== 1 ? 's' : ''} failed in the last 24 hours.
                                        Check the failed jobs queue for details.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Document Metrics */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5" />
                                <CardTitle>Document Blockchain Status</CardTitle>
                            </div>
                            <CardDescription>
                                Blockchain publication status for procurement documents
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Pending (Last Hour)</span>
                                        <Badge variant={health.documents.pending_1h > 10 ? 'destructive' : 'secondary'}>
                                            {health.documents.pending_1h}
                                        </Badge>
                                    </div>
                                    {health.documents.pending_1h > 10 && (
                                        <p className="text-xs text-muted-foreground">
                                            Consider running the reconciliation command to check for stuck records
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Failed (Last 24h)</span>
                                        <Badge variant={health.documents.failed_24h > 0 ? 'destructive' : 'secondary'}>
                                            {health.documents.failed_24h}
                                        </Badge>
                                    </div>
                                    {health.documents.failed_24h > 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Review failed documents and retry if blockchain is now available
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recommendations */}
                {(!isHealthy || isCircuitOpen || health.queue.failed_jobs_24h > 0) && (
                    <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                <CardTitle>Recommended Actions</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ul className="list-disc list-inside space-y-2 text-sm">
                                {isCircuitOpen && (
                                    <li>Circuit breaker is open - check blockchain node connectivity at 159.65.12.99:6487</li>
                                )}
                                {health.queue.failed_jobs_24h > 0 && (
                                    <li>Review failed jobs: <code className="text-xs bg-black/10 dark:bg-white/10 px-1 py-0.5 rounded">php artisan queue:failed</code></li>
                                )}
                                {health.documents.pending_1h > 10 && (
                                    <li>Run reconciliation: <code className="text-xs bg-black/10 dark:bg-white/10 px-1 py-0.5 rounded">php artisan blockchain:reconcile</code></li>
                                )}
                                {health.documents.failed_24h > 0 && (
                                    <li>Investigate blockchain publication failures in application logs</li>
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
