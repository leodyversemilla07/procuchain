import { Card, CardContent } from '@/components/ui/card';
import { ViewStats } from '@/types';
import { Activity, Calendar, Eye, Users } from 'lucide-react';

interface Props {
    viewStats: ViewStats;
}

export default function StatisticsCards({ viewStats }: Props) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:gap-4">
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs sm:text-sm">Total Views</p>
                            <p className="text-xl font-bold sm:text-2xl">{viewStats.total_views}</p>
                        </div>
                        <Eye className="text-muted-foreground h-6 w-6 sm:h-8 sm:w-8" />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs sm:text-sm">Unique Viewers</p>
                            <p className="text-xl font-bold sm:text-2xl">{viewStats.unique_viewers}</p>
                        </div>
                        <Users className="text-muted-foreground h-6 w-6 sm:h-8 sm:w-8" />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs sm:text-sm">Today</p>
                            <p className="text-xl font-bold sm:text-2xl">{viewStats.today_views}</p>
                        </div>
                        <Calendar className="text-muted-foreground h-6 w-6 sm:h-8 sm:w-8" />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs sm:text-sm">This Week</p>
                            <p className="text-xl font-bold sm:text-2xl">{viewStats.week_views}</p>
                        </div>
                        <Activity className="text-muted-foreground h-6 w-6 sm:h-8 sm:w-8" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
