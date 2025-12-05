import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { ViewStats } from '@/types';
import { formatRole, getRoleBadgeColor } from '@/utils/pdf-viewer/helpers';
import { BarChart3 } from 'lucide-react';

interface Props {
    viewStats: ViewStats;
}

export default function ViewsByRoleCard({ viewStats }: Props) {
    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
                    <BarChart3 className="h-4 w-4 sm:h-5 sm:w-5" />
                    Views by Role
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-2">
                    {Object.entries(viewStats.views_by_role).map(([role, count]) => (
                        <div key={role} className="flex items-center justify-between">
                            <Badge variant="secondary" className={cn('text-xs', getRoleBadgeColor(role))}>
                                {formatRole(role)}
                            </Badge>
                            <span className="text-xs font-medium sm:text-sm">{count}</span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
