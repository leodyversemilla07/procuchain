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

export function HealthTab({
  health,
  isHealthy,
  isCircuitOpen,
  isResetDialogOpen,
  setIsResetDialogOpen,
  handleResetCircuitBreaker,
}: HealthTabProps) {
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
                Health monitoring data is currently not available. Please check your blockchain connection and try
                refreshing the page.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Overall Health Status */}
      <Card
        className={
          isHealthy
            ? 'border-green-200 bg-green-50/50 dark:border-green-900 dark:bg-green-950/20'
            : 'border-red-200 bg-red-50/50 dark:border-red-900 dark:bg-red-950/20'
        }
      >
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
                <Activity className="h-5 w-5" />
                <CardTitle>Circuit Breaker</CardTitle>
              </div>
              <Badge variant={isCircuitOpen ? 'destructive' : 'secondary'}>
                {isCircuitOpen ? 'OPEN' : 'CLOSED'}
              </Badge>
            </div>
            <CardDescription>Protects system from cascading failures</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 p-4 sm:p-6">
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
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 sm:p-4 dark:border-amber-900 dark:bg-amber-950/20">
                <div className="flex gap-2">
                  <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                  <div className="flex-1 space-y-2">
                    <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                      Circuit breaker is open
                    </p>
                    <p className="text-sm text-amber-800 dark:text-amber-200">
                      All blockchain requests are currently blocked due to repeated failures. The system
                      will automatically retry after the recovery time.
                    </p>
                    <AlertDialog open={isResetDialogOpen} onOpenChange={setIsResetDialogOpen}>
                      <AlertDialogTrigger
                        render={<Button variant="outline" size="sm" className="mt-2" />}
                      >
                        Reset Circuit Breaker
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Reset Circuit Breaker?</AlertDialogTitle>
                          <AlertDialogDescription>
                            This will allow blockchain requests to resume immediately. Are you
                            sure you want to proceed?
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Cancel</AlertDialogCancel>
                          <AlertDialogAction onClick={handleResetCircuitBreaker}>
                            Reset
                          </AlertDialogAction>
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
              <Activity className="h-5 w-5" />
              <CardTitle>Queue Status</CardTitle>
            </div>
            <CardDescription>Background job processing metrics</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 p-4 sm:p-6">
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
              <div className="rounded-lg border border-red-200 bg-red-50 p-3 wrap-break-word dark:border-red-900 dark:bg-red-950/20">
                <p className="text-sm text-red-900 dark:text-red-100">
                  {health.queue.failed_jobs_24h} job{health.queue.failed_jobs_24h !== 1 ? 's' : ''} failed
                  in the last 24 hours. Check the failed jobs queue for details.
                </p>
              </div>
            )}
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
          <CardContent className="p-4 sm:p-6">
            <ul className="list-inside list-disc space-y-2 text-sm wrap-break-word">
              {isCircuitOpen && (
                <li>Circuit breaker is open - check blockchain node connectivity at 159.65.12.99:6487</li>
              )}
              {health.queue.failed_jobs_24h > 0 && (
                <li>
                  Review failed jobs:{' '}
                  <code className="rounded bg-black/10 px-1 py-0.5 text-xs dark:bg-white/10">
                    php artisan queue:failed
                  </code>
                </li>
              )}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
