import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart3, Calendar, FileText, TrendingUp } from 'lucide-react';

interface ReportSummaryCardsProps {
    totalCount: number;
    totalAbcAmount: number;
    stageCount: number;
    modeCount: number;
}

export function ReportSummaryCards({ totalCount, totalAbcAmount, stageCount, modeCount }: ReportSummaryCardsProps) {
    const stats = [
        { title: 'Total Procurements', icon: FileText, value: totalCount.toLocaleString() },
        { title: 'Total ABC Amount', icon: TrendingUp, value: `₱${totalAbcAmount.toLocaleString()}` },
        { title: 'Unique Stages', icon: Calendar, value: stageCount.toString() },
        { title: 'Procurement Modes', icon: BarChart3, value: modeCount.toString() },
    ];

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {stats.map((stat) => (
                <Card key={stat.title}>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                        <stat.icon />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stat.value}</div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
