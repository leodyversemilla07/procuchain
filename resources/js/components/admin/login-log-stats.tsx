import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatsGrid } from '@/components/stats-grid';
import { Activity, Calendar as CalendarIcon, Shield, TrendingUp, User } from 'lucide-react';
import type { LoginStatistics } from '@/hooks/use-login-log-filters';

interface LoginLogStatsProps {
  statistics: LoginStatistics;
}

export default function LoginLogStats({ statistics }: LoginLogStatsProps) {
  const successRate = statistics.total_logins > 0
    ? Math.round((statistics.successful_logins / statistics.total_logins) * 100)
    : 0;

  return (
    <>
      {/* Summary Statistics */}
      <StatsGrid
        items={[
          {
            label: 'Total Logins',
            value: statistics.total_logins?.toLocaleString() || '0',
            icon: Activity,
            iconClassName: 'text-blue-600 dark:text-blue-400',
          },
          {
            label: 'Success Rate',
            value: statistics.total_logins > 0 ? `${successRate}%` : '0%',
            icon: Shield,
            iconClassName: 'text-green-600 dark:text-green-400',
          },
          {
            label: "Today's Logins",
            value: statistics.today_logins?.toString() || '0',
            icon: CalendarIcon,
            iconClassName: 'text-purple-600 dark:text-purple-400',
          },
          {
            label: 'Unique Users',
            value: statistics.unique_users?.toString() || '0',
            icon: User,
            iconClassName: 'text-orange-600 dark:text-orange-400',
          },
        ]}
      />

      {/* Login Activity Trend */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <TrendingUp className="h-5 w-5" />
            Login Activity Trend
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <p className="text-muted-foreground text-xs sm:text-sm">This Week</p>
              <div className="flex items-baseline gap-2">
                <p className="text-xl font-bold sm:text-2xl">{statistics.this_week_logins?.toLocaleString() || '0'}</p>
                <Badge variant="secondary" className="text-xs">Logins</Badge>
              </div>
            </div>
            <div className="space-y-2">
              <p className="text-muted-foreground text-xs sm:text-sm">This Month</p>
              <div className="flex items-baseline gap-2">
                <p className="text-xl font-bold sm:text-2xl">{statistics.this_month_logins?.toLocaleString() || '0'}</p>
                <Badge variant="secondary" className="text-xs">Logins</Badge>
              </div>
            </div>
            <div className="space-y-2">
              <p className="text-muted-foreground text-xs sm:text-sm">Success Rate</p>
              <div className="flex items-baseline gap-2">
                <p className="text-xl font-bold sm:text-2xl">{successRate}%</p>
                <Badge
                  variant={successRate >= 90 ? 'default' : 'destructive'}
                  className="text-xs"
                >
                  {successRate >= 90 ? 'Healthy' : 'Review'}
                </Badge>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </>
  );
}
