import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ReportDistributionCardProps {
    title: string;
    data: Record<string, number>;
}

export function ReportDistributionCard({ title, data }: ReportDistributionCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-2">
                    {Object.entries(data).map(([key, count]) => (
                        <div key={key} className="flex items-center justify-between">
                            <span className="text-sm capitalize">{key.replace(/_/g, ' ')}</span>
                            <Badge variant="secondary">{count}</Badge>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
